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

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private $nome_tabela = '';
    private $classname = '';

    //para compatibilidade com legado
    protected $nomes_tabelas = [
        'TB_Advogado' => ['advogado', 'advogados'],
        'TB_Defensoria' => ['defensoria', 'defensorias'],
        'TB_Glossario' => ['glossario', 'glossarios'],
        'TB_Localidade' => ['localidade', 'localidades'],
        'TB_Parte' => ['parte', 'partes'],
        'TB_Participante' => ['participante', 'participantes'],
        'TB_Processo' => ['processo', 'processos'],
        'TB_Procurador' => ['procurador', 'procuradores'],
        'TB_Quilombo' => ['quilombo', 'quilombos'],
        'TB_Repositorio' => ['repositorio', 'repositorios'],
        'TB_Usuario' => ['usuario', 'usuarios'],
    ];

    public function __construct()
    {
        $request = Route::current()->parameter('request');
        $tabela = Route::current()->parameter('tabela');
        $id = Route::current()->parameter('id');
        $debug = Route::current()->parameter('debug');

        if ($debug == 'debug') {
            $tabela = 'debug';
        }

        if ($tabela != null) {
            $this->nome_tabela = $this->traduzirNomeTabela($tabela);
        } else {
            $this->nome_tabela = 'Tabela';
        }

        $this->classname = 'App\\Models\\' . $this->nome_tabela;
    }

    public function list(Request $request, string $tabela)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $model = new $this->classname;
        $retorno = [];
        $retorno['data'] = $model::all();

        if (!$retorno['data']->count()) {
            $retorno['status'] = 'success';
            return response()->json($retorno, 204);
        }
        $retoro['status'] = 'success';
        return response()->json($retorno);
    }

    public function get(Request $request, string $tabela, string $id)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $model = new $this->classname;
        $retorno = [];
        $retorno['data'] = $model::where('id', $id)->first();

        if (!$retorno['data']->count()) {
            $retorno['status'] = 'success';
            return response()->json($retorno, 204);
        }
        $retorno['status'] = 'success';
        return response()->json($retorno);
    }

    public function post(Request $request, string $tabela)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $colunas = DB::connection('etno_mysql')->getSchemaBuilder()->getColumnListing($this->nome_tabela);
        $model = new $this->classname;

        foreach ($colunas as $coluna) {
            $model->{$coluna} = $request->input($coluna);
        }

        try {
            $model->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o registro não foi salvo!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Adicionado com sucesso!',
            'data' => $model
        ];
        return response()->json($retorno);
    }

    public function update(Request $request, string $tabela, string $id)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $colunas = DB::connection('etno_mysql')->getSchemaBuilder()->getColumnListing($this->nome_tabela);
        $model = new $this->classname;
        $item = $model::where('id', $id)->first();
        $before = $item;

        if (!$item) {
            $retorno = [
                'status' => 'not found',
                'message' => 'Registro de ID '. $id .' não existe na tabela.'
            ];
            return response()->json($retorno, 404);
        }

        foreach ($colunas as $coluna) {
            $item->{$coluna} = $request->input($coluna);
        }
        $item->id = $id;

        try {
            $item->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o registro não pôde ser atualizado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Registro atualizado com sucesso!',
            'antes_do_update' => $before,
            'data' => $item
        ];
        return response()->json($retorno);
    }

    public function delete(Request $request, string $tabela, string $id)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $model = new $this->classname;
        $item = $model::where('id', $id)->first();

        if (!$item) {
            $retorno = [
                'status' => 'not found',
                'message' => 'Registro de ID '. $id .' não existe na tabela.'
            ];
            return response()->json($retorno, 404);
        }

        try {
            $item->delete();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o registro não pôde ser deletado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Registro deletado com sucesso!',
            'data' => $item
        ];
        return response()->json($retorno);
    }

    public function get_legado(Request $request, string $tabela)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $id = $request->input('id');
        if (!$id) {
            $retorno = [
                'status' => 'error',
                'message' => 'O ID não foi enviado na solicitação.'
            ];
            return response()->json($retorno, 500);
        }

        $model = new $this->classname;
        $retorno = [];
        $retorno['data'] = $model::where('id', $id)->first();

        if (!$retorno['data']->count()) {
            $retorno['status'] = 'success';
            return response()->json($retorno, 204);
        }
        $retorno['status'] = 'success';
        return response()->json($retorno);
    }

    public function update_legado(Request $request, string $tabela)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $id = $request->input('id');
        if (!$id) {
            $retorno = [
                'status' => 'error',
                'message' => 'O ID não foi enviado na solicitação.'
            ];
            return response()->json($retorno, 500);
        }

        $colunas = DB::connection('etno_mysql')->getSchemaBuilder()->getColumnListing($this->nome_tabela);
        $model = new $this->classname;
        $item = $model::where('id', $id)->first();
        $before = $item;

        if (!$item) {
            $retorno = [
                'status' => 'not found',
                'message' => 'Registro de ID '. $id .' não existe na tabela.'
            ];
            return response()->json($retorno, 404);
        }

        foreach ($colunas as $coluna) {
            $item->{$coluna} = $request->input($coluna);
        }

        try {
            $item->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o registro não pôde ser atualizado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Registro atualizado com sucesso!',
            'antes_do_update' => $before,
            'data' => $item
        ];
        return response()->json($retorno);
    }

    public function delete_legado(Request $request, string $tabela)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!class_exists($this->classname)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Tabela '. $this->nome_tabela .' solicitada não existe.'
            ];
            return response()->json($retorno, 404);
        }

        $id = $request->input('id');
        if (!$id) {
            $retorno = [
                'status' => 'error',
                'message' => 'O ID não foi enviado na solicitação.'
            ];
            return response()->json($retorno, 500);
        }

        $model = new $this->classname;
        $item = $model::where('id', $id)->first();

        if (!$item) {
            $retorno = [
                'status' => 'not found',
                'message' => 'Registro de ID '. $id .' não existe na tabela.'
            ];
            return response()->json($retorno, 404);
        }

        try {
            $item->delete();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o registro não pôde ser deletado!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Registro deletado com sucesso!',
            'data' => $item
        ];
        return response()->json($retorno);
    }

    public function patch(Request $request, string $tabela)
    {
        $retorno = [
            'status' => 'error',
            'message' => 'Método não implementado.',
        ];
        return response()->json($retorno, 405);
    }

    public function options(Request $request, string $tabela)
    {
        $retorno = [
            'status' => 'error',
            'message' => 'Método não implementado.',
        ];
        return response()->json($retorno, 405);
    }

    public function head(Request $request, string $tabela)
    {
        $retorno = [
            'status' => 'error',
            'message' => 'Método não implementado.',
        ];
        return response()->json($retorno, 405);
    }

    //essa função existe para compatibilidade com legado
    private function traduzirNomeTabela(string $tabela)
    {
        $nome_tabela = $tabela;

        foreach ($this->nomes_tabelas as $nome_real => $nomes_referencias) {
            if (strtolower($tabela) == strtolower($nome_real)) {
                $nome_tabela = $nome_real;
            }

            foreach ($nomes_referencias as $nome_referencia) {
                if (strtolower($tabela) == strtolower($nome_referencia)) {
                    $nome_tabela = $nome_real;
                }
            }
        }

        return $nome_tabela;
    }

    private function checar_token_bearer($request)
    {
        $bearer = $request->input('Bearer');
        if (!$bearer) {
            $bearer = $request->input('bearer');
        }

        $user_bearer = \App\Models\User::where('api_token', $bearer)->first();

        if (!$user_bearer) {
            return false;
        } else {
            return true;
        }
    }

    //apenas para debug: apagar esta função depois
    public function testUser(Request $request)
    {
        $user = new \App\Models\User();

        $user->name = "Bruno Rodrigues";
        $user->email = "rodrigobsorrego@gmail.com";
        $user->password = Hash::make("senha123");
        $user->admin = true;
        $user->ativo = true;

        try {
            $user->save();
        } catch (\Exception $e) {
            $retorno = [
                'status' => 'error',
                'message' => 'Ocorreu uma exceção e o registro não pôde ser salvo!',
                'exception' => $e
            ];
            return response()->json($retorno, 500);
        }

        $retorno = [
            'status' => 'success',
            'message' => 'Registro salvo com sucesso!',
            'data' => $user
        ];
        return response()->json($retorno);
    }
}
