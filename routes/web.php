<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCurveSyncKey;
use App\Http\Controllers\CurveSyncController;
use App\Http\Controllers\TestAppointmentSyncController;

// route return hello world arrow function
Route::get('/', fn () => 'Hello World')->name('home');

Route::prefix('curve/sync')->middleware(['throttle:10,1', VerifyCurveSyncKey::class])->group(function () {
    Route::post('all', [CurveSyncController::class, 'all']);
    Route::post('clinics', [CurveSyncController::class, 'clinics']);
    Route::post('operatories', [CurveSyncController::class, 'operatories']);
    Route::post('providers', [CurveSyncController::class, 'providers']);
    Route::post('appointment-tags', [CurveSyncController::class, 'appointmentTags']);
});

Route::prefix('test/appointment')->middleware([VerifyCurveSyncKey::class])->group(function () {
    Route::match(['get', 'post'], 'create', [TestAppointmentSyncController::class, 'testCreateAppointment']);
    Route::match(['get', 'post'], 'update/{id?}', [TestAppointmentSyncController::class, 'testUpdateAppointment']);
    Route::match(['get', 'post'], 'cancel/{id?}', [TestAppointmentSyncController::class, 'testCancelAppointment']);
});
