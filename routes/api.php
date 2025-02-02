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

Route::get('/dashboard', [Controller::class, 'dashboard_index']);
Route::post('/dashboard', [Controller::class, 'dashboard']);

Route::get('/quilombos/mapa', [Controller::class, 'quilombo_mapa']);
Route::post('/quilombos/mapa', [Controller::class, 'quilombo_mapa']);

Route::get('/importar_processo', [Controller::class, 'importar_processo_index']);
Route::post('/importar_processo', [Controller::class, 'importar_processo']);

Route::get('/dados_processo/{processo}', [Controller::class, 'dados_processo']);
Route::post('/dados_processo', [Controller::class, 'dados_processo']);

Route::get('/dados_processos', [Controller::class, 'dados_processos']);
Route::post('/dados_processos', [Controller::class, 'dados_processos']);

Route::get('/quilombos_processos', [Controller::class, 'quilombos_processos']);
Route::get('/quilombo_processos/{quilombo}', [Controller::class, 'quilombo_processos']);
Route::post('/quilombos_processos', [Controller::class, 'quilombos_processos']);
Route::post('/quilombo_processos', [Controller::class, 'quilombo_processos']);

Route::get('/codigo_ibge', [Controller::class, 'codigo_ibge']);
Route::post('/codigo_ibge', [Controller::class, 'codigo_ibge']);

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
