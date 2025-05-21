<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Crypt;
use Yajra\DataTables\DataTables;

class BookingController extends Controller
{
    // This method will show booking page for user 
    public function index() {
        return view('user.booking');
    }

    // This method will list booking
    public function list (Request $request) {
        $bookings = Booking::latest()->limit(100)->get();
        return view('user.booking_list', compact('bookings'));
    }

    // This method will show the detail single record
    public function show($id)
    {
        $booking = Booking::findOrFail($id);
        return view('user.booking_show', compact('booking'));
    }

    // This method will process the booking store in database
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'booking_date' => 'required|date',
            'booking_type' => 'required|in:full_day,half_day,custom',
            'booking_slot' => 'required_if:booking_type,half_day|nullable|in:first_half,second_half',
            'booking_time_from' => 'required_if:booking_type,custom|nullable|date_format:H:i',
            'booking_time_to' => 'required_if:booking_type,custom|nullable|date_format:H:i|after:booking_time_from',
        ]);

        if ($validator->fails()) {
            return redirect()->route('account.booking')->withInput()->withErrors($validator);
        }

        $bookingDate = $request->booking_date;
        $bookingType = $request->booking_type;

        // Fetch all bookings for the selected date
        $existingBookings = Booking::where('booking_date', $bookingDate)->get();

        // Check conflicts
        foreach ($existingBookings as $existing) {
            if ($existing->booking_type === 'full_day') {
                return redirect()->back()->withInput()->with('error', 'Full day is already booked for this date.');
            }

            if ($bookingType === 'full_day') {
                return redirect()->back()->withInput()->with('error', 'Cannot book full day. There is already a booking on this date.');
            }

            if ($bookingType === 'half_day') {
                if ($existing->booking_type === 'half_day' && $existing->booking_slot === $request->booking_slot) {
                    return redirect()->back()->withInput()->with('error', 'This half-day slot is already booked.');
                }

                // Check if custom booking overlaps with half day
                if ($existing->booking_type === 'custom') {
                    if ($request->booking_slot === 'first_half' && $this->isMorning($existing->from_time)) {
                        return redirect()->back()->withInput()->with('error', 'First half already booked by custom booking.');
                    }

                    if ($request->booking_slot === 'second_half' && $this->isAfternoon($existing->from_time)) {
                        return redirect()->back()->withInput()->with('error', 'Second half already booked by custom booking.');
                    }
                }
            }

            if ($bookingType === 'custom') {
                $newFrom = $request->booking_time_from;
                $newTo = $request->booking_time_to;

                // Block if full day
                if ($existing->booking_type === 'full_day') {
                    return redirect()->back()->withInput()->with('error', 'Full day is already booked on this date.');
                }

                // Block if half day and overlaps
                if ($existing->booking_type === 'half_day') {
                    if ($existing->booking_slot === 'first_half' && $this->isMorning($newFrom)) {
                        return redirect()->back()->withInput()->with('error', 'Cannot book custom morning time. First half is already booked.');
                    }

                    if ($existing->booking_slot === 'second_half' && $this->isAfternoon($newFrom)) {
                        return redirect()->back()->withInput()->with('error', 'Cannot book custom afternoon time. Second half is already booked.');
                    }
                }

                // Block if custom overlaps with existing custom
                if ($existing->booking_type === 'custom') {
                    if ($this->timeOverlaps($newFrom, $newTo, $existing->from_time, $existing->to_time)) {
                        return redirect()->back()->withInput()->with('error', 'Time slot overlaps with another custom booking.');
                    }
                }
            }
        }

        // Save booking
        $booking = new Booking();
        $booking->user_id = Auth::id();
        $booking->customer_name = $request->customer_name;
        $booking->customer_email = $request->customer_email;
        $booking->booking_date = $bookingDate;
        $booking->booking_type = $bookingType;
        $booking->booking_slot = $request->booking_slot;
        $booking->from_time = $request->booking_time_from;
        $booking->to_time = $request->booking_time_to;
        $booking->save();

        return redirect()->back()->with('success', 'Booking created successfully!');
    }


    // This method will fetch the data 
    public function edit($id)
    {
        $booking = Booking::findOrFail($id);
        return view('user.booking_edit', compact('booking'));
    }

    // This method will booking record update
    public function update(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
    
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'booking_date' => 'required|date',
            'booking_type' => 'required|in:full_day,half_day,custom',
            'booking_slot' => 'required_if:booking_type,half_day|nullable|in:first_half,second_half',
            'booking_time_from' => 'required_if:booking_type,custom|nullable|date_format:H:i',
            'booking_time_to' => 'required_if:booking_type,custom|nullable|date_format:H:i|after:booking_time_from',
        ]);
    
        $bookingDate = $request->booking_date;
        $bookingType = $request->booking_type;
    
        // Fetch all other bookings on same date, excluding current one
        $existingBookings = Booking::where('booking_date', $bookingDate)
            ->where('id', '!=', $id)
            ->get();
    
        foreach ($existingBookings as $existing) {
            if ($existing->booking_type === 'full_day') {
                return redirect()->back()->withInput()->with('error', 'Full day is already booked for this date.');
            }
    
            if ($bookingType === 'full_day') {
                return redirect()->back()->withInput()->with('error', 'Cannot book full day. There is already a booking on this date.');
            }
    
            if ($bookingType === 'half_day') {
                if ($existing->booking_type === 'half_day' && $existing->booking_slot === $request->booking_slot) {
                    return redirect()->back()->withInput()->with('error', 'This half-day slot is already booked.');
                }
    
                if ($existing->booking_type === 'custom') {
                    if ($request->booking_slot === 'first_half' && $this->isMorning($existing->from_time)) {
                        return redirect()->back()->withInput()->with('error', 'First half already booked by a custom booking.');
                    }
    
                    if ($request->booking_slot === 'second_half' && $this->isAfternoon($existing->from_time)) {
                        return redirect()->back()->withInput()->with('error', 'Second half already booked by a custom booking.');
                    }
                }
            }
    
            if ($bookingType === 'custom') {
                $newFrom = $request->booking_time_from;
                $newTo = $request->booking_time_to;
    
                if ($existing->booking_type === 'full_day') {
                    return redirect()->back()->withInput()->with('error', 'Full day is already booked on this date.');
                }
    
                if ($existing->booking_type === 'half_day') {
                    if ($existing->booking_slot === 'first_half' && $this->isMorning($newFrom)) {
                        return redirect()->back()->withInput()->with('error', 'Cannot book custom morning time. First half is already booked.');
                    }
    
                    if ($existing->booking_slot === 'second_half' && $this->isAfternoon($newFrom)) {
                        return redirect()->back()->withInput()->with('error', 'Cannot book custom afternoon time. Second half is already booked.');
                    }
                }
    
                if ($existing->booking_type === 'custom') {
                    if ($this->timeOverlaps($newFrom, $newTo, $existing->from_time, $existing->to_time)) {
                        return redirect()->back()->withInput()->with('error', 'Time slot overlaps with another custom booking.');
                    }
                }
            }
        }
    
        // If no conflicts, update the booking
        $booking->customer_name = $request->customer_name;
        $booking->customer_email = $request->customer_email;
        $booking->booking_date = $request->booking_date;
        $booking->booking_type = $request->booking_type;
        $booking->booking_slot = $request->booking_slot;
        $booking->from_time = $request->booking_time_from;
        $booking->to_time = $request->booking_time_to;
        $booking->save();
    
        return redirect()->route('account.booking_list')->with('success', 'Booking updated successfully.');
    }
    


    // This method will delete the booking record
    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()->back()->with('success', 'Booking deleted successfully.');
    }
}
