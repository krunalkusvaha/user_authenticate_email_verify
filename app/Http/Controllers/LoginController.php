<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Monolog\Registry;
use App\Notifications\CustomVerifyEmail;

class LoginController extends Controller
{
    public function verify(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);
    
        // Check if the hash in the URL matches the user's email hash
        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->route('account.login')->with('error', 'Invalid verification link.');
        }
    
        // Check if the user has already verified their email
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account.login')->with('error', 'Email already verified.');
        }
    
        // Mark the email as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    
        return redirect()->route('account.login')->with('success', 'Your email has been verified!');
    }
    
     
  
    public function check(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:5|max:12'
        ]);
    
        $userInfo = User::where('email', $request->email)->first();
    
        if (!$userInfo) {
            return back()->withInput()->withErrors(['email' => 'Email not found']);
        }
    
        // Check if the user's email is verified
        if (!$userInfo->hasVerifiedEmail()) {
            return back()->withInput()->withErrors(['email' => 'Please verify your email to log in.'])
                         ->with('resend_email', true); // This will indicate that the Resend Email button should be shown
        }
     
    
        // Check if the password is correct
        if (!Hash::check($request->password, $userInfo->password)) {
            return back()->withInput()->withErrors(['password' => 'Incorrect password']);
        }
    
        // Set session data
        session([
            'LoggedUserInfo' => $userInfo->id,
            'LoggedUserName' => $userInfo->name,  
        ]);
    
        return redirect()->route('account.dashboard');
    }


    // This method will show login page for user 
    public function index() {
        return view('user.login');
    }

    // This method will authenticate user
    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->route('account.login')->withInput()->withErrors($validator);
        }

        // First, check if user exists with that email
        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user) {
            if ($user->email_verified_at === null) {
                return redirect()->route('account.login')->with('error', 'Please verify your email address before logging in.');
            }

            // Email is verified, try login
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                return redirect()->route('account.dashboard');
            } else {
                return redirect()->route('account.login')->with('error', 'Either email or password is incorrect.');
            }
        } else {
            return redirect()->route('account.login')->with('error', 'No account found with this email.');
        }
    }

    // This method will show register page 
    public function register() {
        return view('user.register');
    }

    // This method will register form store databse
    public function processRegister(Request $request) {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed',
        ]);

        if ($validator->passes()) {

            $user = new User();
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();

            // Send custom email verification
            $user->notify(new CustomVerifyEmail());

            return redirect()->route('account.login')->with('success', 'You have register successfully We have sent an email to verify your account.');
        } else {
            return redirect()->route('account.register')->withInput()->withErrors($validator);
        }

    }


    // This method will logout user 
    public function logout() {
        Auth::logout();
        return redirect()->route('account.login');
    }
}
