<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\RedirectsUsers;
use Illuminate\Foundation\Auth\VerifiesEmails;
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
use App\Models\UserVerify;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Auth\Events\Verified;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Session;
use Illuminate\Support\Str;
use Mail;

class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */

    use RedirectsUsers;
    use VerifiesEmails {
        verify as verifyTrait;
    }

    /**
     * Where to redirect users after verification.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
        //$this->middleware('signed')->only('verify');
        //$this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    /**
     * Show the email verification notice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function show(Request $request)
    {
        $user = null;
        if ($request->post('email')) {
            $user = \App\Models\User::where('email', $request->post('email'));
        }
        if (!$user) {
            if ($request->post('id')) {
                $user = \App\Models\User::find($request->post('id'));
            }
        }

        $user = $user ?: $request->user();

        if (!$user) {
            $retorno = [
                'status' => 'not found',
                'message' => 'Usuário não encontrado.',
            ];
            return response()->json($retorno, 404);
        }

        if ($user->hasVerifiedEmail()) {
            $retorno = [
                'status' => 'success',
                'message' => 'Este e-mail já foi verificado.',
                'data' => $user
            ];
            return response()->json($retorno);
        }
        else {
            $retorno = [
                'status' => 'warning',
                'message' => 'Antes de prosseguir, você precisa verificar seu e-mail!',
                'data' => $user
            ];
            return response()->json($retorno, 401);
        }
    }

    public function post_verify(Request $request)
    {
        $token = $request->post('token');

        $verification = $this->verify($request, $token);
        if ($verification['verified']) {
            return response()->json($verification);
        }
        else {
            return response()->json($verification, $verification['code']);
        }
    }

    public function get_verify(Request $request)
    {
        $token = $request->query('token');

        $verification = $this->verify($request, $token);
        if ($verification['verified']) {
            return redirect('www.etnojuris.ufam.edu.br');
        }
        else {
            return response()->json($verification, $verification['code']);
        }
    }

    public function verify(Request $request, string $token)
    {
        $prefixo_token = substr($token, 0, 6);
        if ($prefixo_token != 'token=') {
            $token = 'token=' . $token;
        }

        $user_token = UserVerify::where('token', $token)->first();
        if (!$user_token) {
            $retorno = [
                'code' => 404,
                'status' => 'not found',
                'message' => 'Token inválido ou explirado.',
                'verified' => false,
            ];
            return $retorno;
        }

        $user = \App\Models\User::find($user_token->user_id);

        if (!$user) {
            if ($request->post('email')) {
                $user = \App\Models\User::where('email', $request->post('email'));
            }
        }
        if (!$user) {
            if ($request->post('id')) {
                $user = \App\Models\User::find($request->post('id'));
            }
        }

        if (!$user) {
            $retorno = [
                'code' => 404,
                'status' => 'not found',
                'message' => 'Usuário não encontrado.',
                'verified' => false,
            ];
            return $retorno;
        }

        if (!$user->is_email_verified) {
            try {
                $user->is_email_verified = 1;
            } catch (\Exception $e) {
                $retorno = [
                    'code' => 500,
                    'status' => 'error',
                    'message' => 'Ocorreu uma exceção e o e-mail não pôde ser verificado!',
                    'exception' => $e,
                    'data' => $user,
                    'verified' => false,
                ];
                return $retorno;
            }

            try {
                $user->save();
            } catch (\Exception $e) {
                $retorno = [
                    'code' => 500,
                    'status' => 'error',
                    'message' => 'Ocorreu uma exceção e o e-mail não pôde ser verificado!',
                    'exception' => $e,
                    'data' => $user,
                    'verified' => false,
                ];
                return $retorno;
            }

            $retorno = [
                'code' => 200,
                'status' => 'success',
                'message' => 'E-mail verificado com sucesso!',
                'data' => $user,
                'verified' => true,
            ];
            return $retorno;
        }
        else {
            $retorno = [
                'code' => 200,
                'status' => 'warning',
                'message' => 'Seu e-mail já foi verificado!',
                'data' => $user,
                'verified' => false,
            ];
            return $retorno;
        }
    }

    public function resend(Request $request)
    {
        $user = null;
        if ($request->post('email')) {
            $user = \App\Models\User::where('email', $request->post('email'));
        }
        if (!$user) {
            if ($request->post('id')) {
                $user = \App\Models\User::find($request->post('id'));
            }
        }

        $user = $user ?: $request->user();

        if (!$user) {
            $retorno = [
                'status' => 'not found',
                'message' => 'Usuário não encontrado.',
            ];
            return response()->json($retorno, 404);
        }

        if ($user->is_email_verified) {
            $retorno = [
                'status' => 'success',
                'message' => 'Este e-mail já foi verificado.',
                'data' => $user
            ];
            return response()->json($retorno);
        }

        try {
            $token = 'token=' . Str::random(64);
            $user->verification_token = $token;
            $user->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o e-mail de verificação não pôde ser enviado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        try {
            $user_verify = UserVerify::where('user_id', $user->id)->first();
            if (!$user_verify) {
                UserVerify::create([
                    'user_id' => $user->id,
                    'token' => $token
                ]);
            } else {
                $user_verify->token = $token;
                $user_verify->save();
            }
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o e-mail de verificação não pôde ser reenviado!',
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
                'message' => 'Ocorreu uma exceção e o e-mail de verificação não pôde ser enviado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'E-mail de verificação reenviado!',
            'data' => $user
        ];
        return response()->json($retorno);
    }

    public function forgot_password(Request $request)
    {
        $user = null;
        if ($request->post('email')) {
            $user = \App\Models\User::where('email', $request->post('email'))->first();
        }
        if (!$user) {
            if ($request->post('id')) {
                $user = \App\Models\User::find($request->post('id'));
            }
        }

        $user = $user ?: $request->user();

        if (!$user) {
            $retorno = [
                'status' => 'not found',
                'message' => 'Usuário não encontrado.',
            ];
            return response()->json($retorno, 404);
        }

        try {
            $token = random_int(100000, 999999);
            $token_ = 'token=' . $token;
            $user->remember_token = $token;
            $user->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o envio do e-mail de nova senha não pôde ser enviado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        try {
            \Illuminate\Support\Facades\Mail::send('email.emailRememberPasswordEmail', ['token' => $token, 'token_' => $token_], function($message) use($request, $user){
                $message->to(strtolower($user->email));
                $message->subject('Seu Código de Recuperação de Senha');
            });
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o envio do e-mail de nova senha não pôde ser enviado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'E-mail para recuperação de senha enviado!',
            'data' => $user
        ];
        return response()->json($retorno);
    }

    public function post_change_password(Request $request)
    {
        $token = $request->post('token');

        $verification = $this->change_password($request, $token);
        if ($verification['password_changed']) {
            return response()->json($verification);
        }
        else {
            return response()->json($verification, $verification['code']);
        }
    }

    public function get_change_password(Request $request)
    {
        $retorno = [
            'code' => 405,
            'method' => 'GET',
            'status' => 'error',
            'message' => 'Método não implementado.',
            'password_changed' => false,
        ];
        return response()->json($retorno, $retorno['code']);
    }

    public function change_password(Request $request, string $token)
    {
        if (!$request->post('password')) {
            $retorno = [
                'code' => 404,
                'status' => 'error',
                'message' => 'Você não enviou uma nova senha!',
                'password_changed' => false,
            ];
            return $retorno;
        }

        $prefixo_token = substr($token, 0, 6);
        if ($prefixo_token != 'token=') {
            $token_ = 'token=' . $token;
        }

        $user = null;
        if ($request->post('email')) {
            $user = \App\Models\User::where('email', $request->post('email'))->first();
        }
        if (!$user) {
            if ($request->post('id')) {
                $user = \App\Models\User::find($request->post('id'));
            }
        }

        $user = $user ?: $request->user();

        if (!$user) {
            $retorno = [
                'code' => 404,
                'status' => 'error',
                'message' => 'Usuário não encontrado!',
                'password_changed' => false,
            ];
            return $retorno;
        }

        if ($user->remember_token == $token) {
            $user->remember_token = null;
            $user->password = Hash::make($request->post('password'));

            try {
                $user->save();
            } catch (\Exception $e) {
                $retorno = [
                    'code' => 500,
                    'status' => 'error',
                    'message' => 'Ocorreu uma exceção e a senha não pôde ser atualizada!',
                    'exception' => $e,
                    'data' => $user,
                    'password_changed' => false,
                ];
                return $retorno;
            }
        } else {
            $retorno = [
                'code' => 403,
                'status' => 'error',
                'message' => 'Código inválido ou expirado!',
                'password_changed' => false,
            ];
            return $retorno;
        }

        $retorno = [
            'code' => 200,
            'status' => 'success',
            'message' => 'Sua senha foi atualizada!',
            'data' => $user,
            'password_changed' => true,
        ];
        return $retorno;
    }
}
