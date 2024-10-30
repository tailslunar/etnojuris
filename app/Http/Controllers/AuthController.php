<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Command;
use File;
use Smalot\PdfParser\Parser;
use Gufy\PdfToHtml\Pdf;
use DB;
use Carbon\Carbon;
use App\Models\Processo;
use App\Models\NomesColunasProcessos;
use App\Models\Movs;
use App\Models\NomesColunasMovs;
use App\Models\Anexos;
use App\Models\NomesColunasAnexos;
use App\Models\Audiencias;
use App\Models\NomesColunasAudiencias;
use App\Models\ProcessosRelacionados;
use App\Models\NomesColunasProcessosRelacionados;
use App\Models\Customs;
use App\Models\NomesColunasCustoms;
use App\Models\Classes;
use App\Models\NomesColunasClasses;
use App\Models\Acessos;
use App\Models\NomesColunasAcessos;
use App\Models\Partes;
use App\Models\NomesColunasPartes;
use App\Models\Tribunais;
use App\Models\TB_Advogado;
use App\Models\TB_Defensoria;
use App\Models\TB_Glossario;
use App\Models\TB_Localidade;
use App\Models\TB_Parte;
use App\Models\TB_Participante;
use App\Models\TB_Processo;
use App\Models\TB_Procurador;
use App\Models\TB_Quilombo;
use App\Models\TB_Repositorio;
use App\Models\TB_Usuario;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Session;
use App\Models\UserVerify;
use Illuminate\Support\Str;
use Mail;

class AuthController extends Controller
{
    private $nome_tabela = '';
    private $classname = '';

    public function __construct()
    {
        //...
    }

    public function register(Request $request)
    {
        if (!$request->post('name')) {
            $retorno = [
                'status' => 'error',
                'message' => 'O campo "name" é obrigatório!'
            ];
            return response()->json($retorno, 500);
        }
        if (!$request->post('email')) {
            $retorno = [
                'status' => 'error',
                'message' => 'O campo "email" é obrigatório!'
            ];
            return response()->json($retorno, 500);
        }
        if (!$request->post('password')) {
            $retorno = [
                'status' => 'error',
                'message' => 'O campo "password" é obrigatório!'
            ];
            return response()->json($retorno, 500);
        }
        $user_ = \App\Models\User::where('email',  $request->email)->first();
        if ($user_) {
            $retorno = [
                'status' => 'error',
                'message' => 'Já existe um usuário com este e-mail!'
            ];
            return response()->json($retorno, 500);
        }

        try {
            $user = \App\Models\User::create([
                'name' => $request->post('name'),
                'email' => $request->post('email'),
                'password' => Hash::make($request->post('password')),
                'admin' => '0',
                'ativo' => '1',
            ]);
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o registro não pôde ser criado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        try {
            $token = 'token=' . Str::random(64);
            $user->verification_token = $token;
            $user->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'O usuário foi criado, mas ocorreu uma exceção e o e-mail de verificação não pôde ser enviado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        try {
            UserVerify::create([
                'user_id' => $user->id,
                'token' => $token
            ]);
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'O usuário foi criado, mas ocorreu uma exceção e o e-mail de verificação não pôde ser enviado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        try {
            \Illuminate\Support\Facades\Mail::send('email.emailVerificationEmail', ['token' => $token], function($message) use($request, $user){
                $message->to(strtolower($user->email));
                $message->subject('Verifique seu E-Mail');
            });
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'O usuário foi criado, mas ocorreu uma exceção e o e-mail de verificação não pôde ser enviado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Registro criado com sucesso!',
            'data' => $user
        ];
        return response()->json($retorno);
    }

    public function login(Request $request)
    {
        $user = \App\Models\User::where('email',  $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nome do Usuário ou Senha estão incorretas!',
            ], 404);
        }

        if (!$user->is_email_verified) {
            return response()->json([
                'status' => 'error',
                'message' => 'Seu e-mail ainda não foi verificado: clique no link do seu e-mail de verificação ou solicite um novo e-mail de verificação',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->api_token = $token;
        $user->remember_token = null;

        try {
            $user->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o novo token não pôde ser salvo!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Usuário logado com sucesso!',
            'data' => $user
        ];
        return response()->json($retorno);
    }

    public function logout(Request $request)
    {
        $user = \App\Models\User::where('email',  $request->email)->first();
        if (!$user) {
            $user = \App\Models\User::where('id',  $request->id)->first();
        }

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário solicitado não existe.',
            ], 404);
        }

        try {
            $user->tokens()->delete();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o logout não foi realizado (token não foi apagado)!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        try {
            $user->api_token = null;
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o logout não foi realizado (token não foi apagado)!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        try {
            $user->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o logout não foi realizado (token não foi apagado)!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Logoff realizado com sucesso!',
            'data' => $user
        ];
        return response()->json($retorno);
    }
}
