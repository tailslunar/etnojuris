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

use function Ramsey\Uuid\v1;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private $nome_tabela = '';
    private $classname = '';
    private $banco = 'etno_mysql';

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
        'TB_Usuario' => ['tb_usuario', 'tb_usuarios', 'tb_user', 'tb_users'],
        'User' => ['usuario', 'usuarios', 'user', 'users'],
        'Acessos' => ['acesso', 'acesso'],
        'Anexos' => ['anexos', 'anexo'],
        'Audiencias' => ['audiencias',  'audiencia'],
        'Classes' => ['classes',  'classe'],
        'Customs' => ['customs',  'custom'],
        'Movs' => ['movimentos',  'movimento', 'movs', 'mov'],
        'Partes' => ['partes_jubsrasil', 'parte_jusbrasil'],
        'Processo' => ['processos_jubsrasil', 'processos_jusbrasil'],
        'Tribunais' => ['tribunais', 'tribunal'],
    ];

    protected $tabelas_que_exigem_admin = [
        'TB_Usuario',
        'User',
    ];

    protected $tabelas_nao_acessiveis = [
        'failed_jobs',
        'migrations',
        'password_reset_tokens',
        'personal_access_tokens',
        'users_verify'
    ];

    protected $tabelas_banco = [
        'TB_Advogado' => 'etno_mysql',
        'TB_Defensoria' => 'etno_mysql',
        'TB_Glossario' => 'etno_mysql',
        'TB_Localidade' => 'etno_mysql',
        'TB_Parte' => 'etno_mysql',
        'TB_Participante' => 'etno_mysql',
        'TB_Processo' => 'etno_mysql',
        'TB_Procurador' => 'etno_mysql',
        'TB_Quilombo' => 'etno_mysql',
        'TB_Repositorio' => 'etno_mysql',
        'TB_Usuario' => 'etno_mysql',
        'User' => 'mysql',
        'Acessos' => 'mysql',
        'Anexos' => 'mysql',
        'Audiencias' => 'mysql',
        'Classes' => 'mysql',
        'Customs' => 'mysql',
        'Movs' => 'mysql',
        'Partes' => 'mysql',
        'Processo' => 'mysql',
        'Tribunais' => 'mysql',
        'failed_jobs' => 'mysql',
        'migrations' => 'mysql',
        'password_reset_tokens' => 'mysql',
        'personal_access_tokens' => 'mysql',
        'users_verify' => 'mysql',
        'mysql' => 'mysql',
        'etno_mysql' => 'etno_mysql'
    ];

    protected $estados = [
        "0" => [
            "nome" => "Acre",
            "sigla" => "AC"
        ],
        "1" => [
            "nome" => "Alagoas",
            "sigla" => "AL"
        ],
        "2" => [
            "nome" => "Amapá",
            "sigla" => "AP"
        ],
        "3" => [
            "nome" => "Amazonas",
            "sigla" => "AM"
        ],
        "4" => [
            "nome" => "Bahia",
            "sigla" => "BA"
        ],
        "5" => [
            "nome" => "Ceará",
            "sigla" => "CE"
        ],
        "6" => [
            "nome" => "Distrito Federal",
            "sigla" => "DF"
        ],
        "7" => [
            "nome" => "Espírito Santo",
            "sigla" => "ES"
        ],
        "8" => [
            "nome" => "Goiás",
            "sigla" => "GO"
        ],
        "9" => [
            "nome" => "Maranhão",
            "sigla" => "MA"
        ],
        "10" => [
            "nome" => "Mato Grosso",
            "sigla" => "MT"
        ],
        "11" => [
            "nome" => "Mato Grosso do Sul",
            "sigla" => "MS"
        ],
        "12" => [
            "nome" => "Minas Gerais",
            "sigla" => "MG"
        ],
        "13" => [
            "nome" => "Pará",
            "sigla" => "PA"
        ],
        "14" => [
            "nome" => "Paraíba",
            "sigla" => "PB"
        ],
        "15" => [
            "nome" => "Paraná",
            "sigla" => "PR"
        ],
        "16" => [
            "nome" => "Pernambuco",
            "sigla" => "PE"
        ],
        "17" => [
            "nome" => "Piauí",
            "sigla" => "PI"
        ],
        "18" => [
            "nome" => "Rio de Janeiro",
            "sigla" => "RJ"
        ],
        "19" => [
            "nome" => "Rio Grande do Norte",
            "sigla" => "RN"
        ],
        "20" => [
            "nome" => "Rio Grande do Sul",
            "sigla" => "RS"
        ],
        "21" => [
            "nome" => "Rondônia",
            "sigla" => "RO"
        ],
        "22" => [
            "nome" => "Roraima",
            "sigla" => "RR"
        ],
        "23" => [
            "nome" => "Santa Catarina",
            "sigla" => "SC"
        ],
        "24" => [
            "nome" => "São Paulo",
            "sigla" => "SP"
        ],
        "25" => [
            "nome" => "Sergipe",
            "sigla" => "SE"
        ],
        "26" => [
            "nome" => "Tocantins",
            "sigla" => "TO"
        ],
    ];

    protected $rotasAPI = [
        'debug' => 'debug',
        'dashboard' => 'dashboard',
    ];

    public function __construct()
    {
        $request = Route::current()->parameter('request');
        $tabela = Route::current()->parameter('tabela');
        $id = Route::current()->parameter('id');
        $debug = Route::current()->parameter('debug');

        $tabela = $this->verificarRotasDeAPI($tabela);

        if ($tabela != null) {
            $this->nome_tabela = $this->traduzirNomeTabela($tabela);
            $this->banco = $this->qualBanco($this->nome_tabela);
        } else {
            $this->nome_tabela = 'Tabela';
            $this->banco = 'etno_mysql'; //default?
        }

        $this->classname = 'App\\Models\\' . $this->nome_tabela;
    }

    private function verificarRotasDeAPI($tabela)
    {
        if (in_array($tabela, array_keys($this->rotasAPI))) {
            return null;
        }
        return $tabela;
    }

    public function list(Request $request, string $tabela)
    {
        /*
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }
        */

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        /*
        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }
        */

        $model = new $this->classname;
        $retorno = [];
        $retorno['data'] = $model::all();

        if (!$retorno['data']->count()) {
            $retorno['status'] = 'success';
            return response()->json($retorno, 200);
        }
        $retoro['status'] = 'success';
        return response()->json($retorno);
    }

    public function get(Request $request, string $tabela, string $id)
    {
        /*
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }
        */

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        /*
        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }
        */

        $model = new $this->classname;
        $retorno = [];
        $retorno['data'] = $model::where('id', $id)->first();

        if (!$retorno['data']->count()) {
            $retorno['status'] = 'success';
            return response()->json($retorno, 200);
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

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }

        $colunas = DB::connection($this->banco)->getSchemaBuilder()->getColumnListing($this->nome_tabela);
        $model = new $this->classname;

        foreach ($colunas as $coluna) {
            $model->{$coluna} = $request->post($coluna);
        }

        return response()->json([
            'colunas' => $colunas,
            'model' => $model
        ], 500);

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

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }

        $colunas = DB::connection($this->banco)->getSchemaBuilder()->getColumnListing($this->nome_tabela);
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
            $item->{$coluna} = $request->post($coluna);
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

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
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
        /*
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }
        */

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        /*
        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }
        */

        $id = $request->post('id');
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
            return response()->json($retorno, 200);
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

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }

        $id = $request->post('id');
        if (!$id) {
            $retorno = [
                'status' => 'error',
                'message' => 'O ID não foi enviado na solicitação.'
            ];
            return response()->json($retorno, 500);
        }

        $colunas = DB::connection($this->banco)->getSchemaBuilder()->getColumnListing($this->nome_tabela);
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
            $item->{$coluna} = $request->post($coluna);
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

        if (!$this->checar_acesso_tabela($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Apenas administradores tem acesso a esta tabela ou esta tabela é protegida.',
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

        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }

        $id = $request->post('id');
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

    public function dashboard_index(Request $request)
    {
        $retorno = [
            'status' => 'error',
            'message' => 'Método não implementado. Use o método POST.',
        ];
        return response()->json($retorno, 405);
    }

    public function dashboard(Request $request)
    {
        if (!$request || !$this->checar_token_bearer($request)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Token inválido ou não enviado.',
            ];
            return response()->json($retorno, 403);
        }

        if (!$this->checar_usuario_ativo($request, $this->nome_tabela)) {
            $retorno = [
                'status' => 'error',
                'message' => 'Usuário logado está inativo.',
            ];
            return response()->json($retorno, 403);
        }

        //todo: retornando um placeholder, por enquanto
        $dados = [];

        foreach ($this->estados as $estado) {
            $dado = [];
            $dado['estado'] = $estado['sigla'];

            $dado['total'] = [];
            $dado['total']['processo'] = 0;
            $dado['total']['quilombo'] = 0;
            $dado['total']['tempo_meio'] = 504;
            $dado['total']['unidade_tempo_medio'] = "dias";

            $dado['processo'] = [];
            $dado['processo']['trf1'] = 0;
            $dado['processo']['trf2'] = 0;
            $dado['processo']['trf3'] = 0;
            $dado['processo']['trf4'] = 0;

            $dado['quilombo'] = [];
            $dado['quilombo']['trf1'] = 0;
            $dado['quilombo']['trf2'] = 0;
            $dado['quilombo']['trf3'] = 0;
            $dado['quilombo']['trf4'] = 0;

            $dado['polo'] = [];
            $dado['polo']['passivo'] = 0;
            $dado['polo']['ativo'] = 0;

            $dado['sentenca'] = [];
            $dado['sentenca']['procedente'] = 0;
            $dado['sentenca']['acordo'] = 0;
            $dado['sentenca']['improcedente'] = 0;
            $dado['sentenca']['parcialmente_procedente'] = 0;
            $dado['sentenca']['embargos_acolhidos'] = 0;
            $dado['sentenca']['sem_merito'] = 0;
            $dado['sentenca']['sem_informacao'] = 0;

            $dados[] = $dado;
        }

        $dado = [];
        $dado['estado'] = "todos";

        $dado['total'] = [];
        $dado['total']['processo'] = 0;
        $dado['total']['quilombo'] = 0;
        $dado['total']['tempo_meio'] = 504;
        $dado['total']['unidade_tempo_medio'] = "dias";

        $dado['processo'] = [];
        $dado['processo']['trf1'] = 0;
        $dado['processo']['trf2'] = 0;
        $dado['processo']['trf3'] = 0;
        $dado['processo']['trf4'] = 0;

        $dado['quilombo'] = [];
        $dado['quilombo']['trf1'] = 0;
        $dado['quilombo']['trf2'] = 0;
        $dado['quilombo']['trf3'] = 0;
        $dado['quilombo']['trf4'] = 0;

        $dado['polo'] = [];
        $dado['polo']['passivo'] = 0;
        $dado['polo']['ativo'] = 0;

        $dado['sentenca'] = [];
        $dado['sentenca']['procedente'] = 0;
        $dado['sentenca']['acordo'] = 0;
        $dado['sentenca']['improcedente'] = 0;
        $dado['sentenca']['parcialmente_procedente'] = 0;
        $dado['sentenca']['embargos_acolhidos'] = 0;
        $dado['sentenca']['sem_merito'] = 0;
        $dado['sentenca']['sem_informacao'] = 0;

        $dados[] = $dado;

        return response()->json($dados);
    }

    private function checar_token_bearer($request)
    {
        $bearer = $request->post('Bearer');
        if (!$bearer) {
            $bearer = $request->post('bearer');
        }

        $user_bearer = \App\Models\User::where('api_token', $bearer)->first();

        if (!$user_bearer) {
            return false;
        } else {
            return true;
        }
    }

    private function checar_acesso_tabela($request, $tabela)
    {
        $bearer = $request->post('Bearer');
        if (!$bearer) {
            $bearer = $request->post('bearer');
        }

        $user_bearer = \App\Models\User::where('api_token', $bearer)->first();

        $tabela_eh_protegida = false;
        foreach ($this->tabelas_nao_acessiveis as $tabela_protegida) {
            if (strtolower($tabela) == strtolower($tabela_protegida)) {
                $tabela_eh_protegida = true;
            }
        }

        $tabela_exige_admin = false;
        foreach ($this->tabelas_que_exigem_admin as $tabela_protegida) {
            if (strtolower($tabela) == strtolower($tabela_protegida)) {
                $tabela_exige_admin = true;
            }
        }

        if ($tabela_eh_protegida) {
            return false;
        }

        if (!$tabela_exige_admin) {
            return true;
        }

        if ($tabela_exige_admin && $user_bearer->admin) {
            return true;
        }
        else {
            return false;
        }
    }

    private function checar_usuario_ativo($request, $tabela)
    {
        $bearer = $request->post('Bearer');
        if (!$bearer) {
            $bearer = $request->post('bearer');
        }

        $user_bearer = \App\Models\User::where('api_token', $bearer)->first();

        if ($user_bearer->ativo) {
            return true;
        }
        return false;
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

    //essa função existe para compatibilidade com legado
    private function qualBanco(string $nome_tabela)
    {
        return $this->tabelas_banco[$nome_tabela];
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
