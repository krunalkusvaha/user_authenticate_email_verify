<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BookingController;


Route::get('/', function () {
    return view('user.login');
});

// Verfications
Route::get('email/verify', [LoginController::class, 'show'])->name('verification.notice');
Route::get('email/verify/{id}/{hash}', [LoginController::class, 'verify'])->name('verification.verify')->middleware(['signed']);
Route::post('email/resend', [LoginController::class, 'resend'])->name('verification.resend');

Route::group(['prefix' => 'account'], function () {

    // Guest middleware
    Route::group(['middleware' => 'guest'], function () {
        Route::get('login', [LoginController::class,'index'])->name('account.login');
        Route::get('register', [LoginController::class,'register'])->name('account.register');
        Route::post('register', [LoginController::class,'processRegister'])->name('account.processRegister');
        Route::post('authenticate', [LoginController::class,'authenticate'])->name('account.authenticate');
    });

    // Authenticated middleware
    Route::group(['middleware' => ['auth', 'verified']], function () {
        Route::get('logout', [LoginController::class,'logout'])->name('account.logout');
        Route::get('dashboard', [DashboardController::class,'index'])->name('account.dashboard');

        Route::get('booking', [BookingController::class,'index'])->name('account.booking');
        Route::get('booking-list', [BookingController::class,'list'])->name('account.booking_list');
        Route::get('booking-show/{id}', [BookingController::class,'show'])->name('account.booking_show');
        Route::get('bookings/{id}/edit', [BookingController::class, 'edit'])->name('account.booking_edit');
        Route::put('bookings/{id}', [BookingController::class, 'update'])->name('account.update');

        Route::post('booking', [BookingController::class, 'store'])->name('booking.store');
        Route::delete('bookings/{id}', [BookingController::class, 'destroy'])->name('booking.destroy');

    });
});






