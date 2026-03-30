<?php

use App\Http\Controllers\Api\V1\SportsEventController;
use App\Http\Controllers\Api\V1\SportsFacilityController;
use App\Http\Controllers\Api\V1\FacilityBookingController;

Route::prefix('sports')->middleware(['auth:sanctum'])->group(function () {
    // Sports Events
    Route::get('/events', [SportsEventController::class, 'index']);
    Route::post('/events', [SportsEventController::class, 'store']);
    Route::get('/events/{sportsEvent}', [SportsEventController::class, 'show']);
    Route::put('/events/{sportsEvent}', [SportsEventController::class, 'update']);
    Route::delete('/events/{sportsEvent}', [SportsEventController::class, 'destroy']);
    
    // Sports Event Enrollments
    Route::post('/events/{sportsEvent}/enrollments', [SportsEventController::class, 'enroll']);
    Route::get('/events/{sportsEvent}/enrollments/{enrollment}', [SportsEventController::class, 'showEnrollment']);
    Route::put('/events/{sportsEvent}/enrollments/{enrollment}', [SportsEventController::class, 'updateEnrollment']);
    Route::get('/events/{sportsEvent}/waitlist', [SportsEventController::class, 'waitlist']);
    
    // Sports Facilities
    Route::get('/facilities', [SportsFacilityController::class, 'index']);
    Route::post('/facilities', [SportsFacilityController::class, 'store']);
    Route::get('/facilities/{facility}', [SportsFacilityController::class, 'show']);
    Route::put('/facilities/{facility}', [SportsFacilityController::class, 'update']);
    Route::delete('/facilities/{facility}', [SportsFacilityController::class, 'destroy']);

    // Facility Availability
    Route::get('/facilities/{facility}/availability', [SportsFacilityController::class, 'availability']);
    Route::get('/facilities/{facility}/occupancy', [SportsFacilityController::class, 'occupancy']);
    Route::get('/facilities/{facility}/no-show-alerts', [SportsFacilityController::class, 'noShowAlerts']);

    // Facility Bookings
    Route::get('/facility-bookings', [FacilityBookingController::class, 'index']);
    Route::post('/facility-bookings', [FacilityBookingController::class, 'store']);
    Route::get('/facility-bookings/{booking}', [FacilityBookingController::class, 'show']);
    Route::put('/facility-bookings/{booking}', [FacilityBookingController::class, 'update']);
    Route::delete('/facility-bookings/{booking}', [FacilityBookingController::class, 'destroy']);

    // Facility Blockouts (Sports Manager only)
    Route::get('/facilities/{facility}/blockouts', [App\Http\Controllers\Api\V1\SportsBlockoutController::class, 'index']);
    Route::post('/facilities/{facility}/blockouts', [App\Http\Controllers\Api\V1\SportsBlockoutController::class, 'store']);
    Route::delete('/blockouts/{blockout}', [App\Http\Controllers\Api\V1\SportsBlockoutController::class, 'destroy']);
    Route::post('/facility-bookings/{booking}/cancel', [FacilityBookingController::class, 'cancel']);
    
    // Sports Manager - Court Management
    Route::prefix('courts')->middleware('role:Sports Manager')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'toggleStatus']);
    });
    
    // Sports Manager - Active Requests
    Route::get('/active-requests', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'activeRequests'])
        ->middleware('role:Sports Manager');

    // Sports Manager - Raise booking on behalf of student
    Route::post('/raise-booking', [App\Http\Controllers\Api\V1\Sports\CourtController::class, 'raiseBooking'])
        ->middleware('role:Sports Manager');
});
