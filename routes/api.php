<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VerificationController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/forgot_password', [VerificationController::class, 'forgot_password']);
Route::post('/email/resend', 'App\Http\Controllers\VerificationController@resend')->name('verification.resend');

Route::get('/dashboard', [Controller::class, 'dashboard']);

Route::get('/{tabela}/list', [Controller::class, 'list']); // para manter compatibilidade com legado
Route::get('/{tabela}/view', [Controller::class, 'get_legado']);  // para manter compatibilidade com legado
Route::get('/{tabela}/{id}', [Controller::class, 'get']);

Route::post('/{tabela}/create', [Controller::class, 'post']); // para manter compatibilidade com legado

Route::put('/{tabela}/update', [Controller::class, 'update_legado']);  // para manter compatibilidade com legado
Route::put('/{tabela}/{id}', [Controller::class, 'update']);

Route::delete('/{tabela}/delete', [Controller::class, 'delete_legado']);  // para manter compatibilidade com legado
Route::delete('/{tabela}/{id}', [Controller::class, 'delete']);

Route::patch('/{tabela}', [Controller::class, 'patch']);
Route::options('/{tabela}', [Controller::class, 'options']);

Route::get('/{tabela}', [Controller::class, 'list']);
Route::post('/{tabela}', [Controller::class, 'post']);
