<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
*/

Route::get('/verify_email', 'App\Http\Controllers\VerificationController@get_verify')->name('verification.verify');
Route::post('/verify_email_post', 'App\Http\Controllers\VerificationController@post_verify')->name('verification.verify_post');

Route::get('/password_recovery', 'App\Http\Controllers\VerificationController@get_change_password')->name('password.recovery');
Route::post('/password_recovery_post', 'App\Http\Controllers\VerificationController@post_change_password')->name('password.recovery_post');

require __DIR__.'/auth.php';
