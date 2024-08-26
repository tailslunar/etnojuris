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
use App\Models\Estados;
use App\Models\Municipios;
use Illuminate\Support\Facades\Route;

use function Ramsey\Uuid\v1;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private $nome_tabela = '';
    private $nome_tabela_ = '';
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
        'Estados' => ['estados', 'estado'],
        'Municipios' => ['municipios', 'municipio']
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
        'etno_mysql' => 'etno_mysql',
        'Estados' => 'etno_mysql',
        'Municpios' => 'etno_mysql'
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
        'condigo_ibge' => 'codigo_ibge'
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
            $this->nome_tabela_ = strtolower($this->nome_tabela);
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

        $colunas = DB::connection($this->banco)->getSchemaBuilder()->getColumnListing($this->nome_tabela_);
        $model = new $this->classname;

        foreach ($colunas as $coluna) {
            $model->{$coluna} = $request->post($coluna);
        }

        if (in_array('usuario_id', $colunas)) {
            $model->usuario_id = $this->get_usuario_por_token($request)->id;
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

        $colunas = DB::connection($this->banco)->getSchemaBuilder()->getColumnListing($this->nome_tabela_);
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

        if (in_array('usuario_id', $colunas)) {
            $model->usuario_id = $this->get_usuario_por_token($request)->id;
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

        $colunas = DB::connection($this->banco)->getSchemaBuilder()->getColumnListing($this->nome_tabela_);
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

        if (in_array('usuario_id', $colunas)) {
            $model->usuario_id = $this->get_usuario_por_token($request)->id;
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

    public function codigo_ibge(Request $request)
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

        $todos_municipios = Municipios::all();
        $ibge = $request->post('ibge');

        if ($ibge) {
            $filtro = $todos_municipios->where('codigo_ibge', $ibge);
        } else {
            $filtro = $todos_municipios;
        }


        $retorno = [];
        if ($filtro) {
            foreach ($filtro as $index_cidade => $cidade) {
                $item = [];
                $item['municipio'] = $cidade;
                $item['estado'] = $this->pegarEstadoPorCodigo($cidade);
                $item['localidade'] = $this->pegarLocalidadePorNome($cidade);
                $retorno[$cidade->codigo_ibge] = $item;
            }
        } else {
            $retorno = [
                'status' => 'error',
                'message' => 'Localidade não encontrada.',
            ];
            return response()->json($retorno, 404);
        }

        return $retorno;
    }

    private function pegarEstadoPorCodigo($cidade)
    {
        $todos_estados = Estados::all();
        return $todos_estados->where('codigo_uf', $cidade->codigo_uf);
    }

    private function pegarLocalidadePorNome($cidade)
    {
        $todas_localidades = TB_Localidade::all();

        $retorno = null;
        foreach ($todas_localidades as $index_localidade => $localidade) {
            $debug = [
                'cidade_nome' => '',
                'localidade_cidade' => ''
            ];

            $cidade_nome_formatado = $this->apenasCaracteres($this->semAcentos(strtolower($cidade->nome)));
            $localidade_nome_formatado = $this->apenasCaracteres($this->semAcentos(strtolower($localidade->cidade)));

            if ($cidade_nome_formatado == $localidade_nome_formatado) {
                $retorno = $localidade;
            }
        }

        return $retorno;
    }

    private function apenasCaracteres($dados) {
        $dados = normalizer_normalize($dados);
        $dados = preg_replace("#[^A-Za-z1-9]#","", $dados);
        return $dados;
    }

    private function SubstituirCaracteresInapropriados($string)
    {
        $lista_de_caracteres = [
            'ç' => 'c'
        ];

        foreach ($lista_de_caracteres as $original => $novo) {
            $string = str_replace($original, $novo, $string);
        }

        return $string;
    }

    private function RemoverCaracteresInapropriados($string)
    {
        $lista_de_caracteres = [
            '|'
        ];

        foreach ($lista_de_caracteres as $caractere) {
            $string = str_replace($caractere, "", $string);
        }

        return $string;
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

    private function get_usuario_por_token($request)
    {
        $bearer = $request->post('Bearer');
        if (!$bearer) {
            $bearer = $request->post('bearer');
        }

        $user_bearer = \App\Models\User::where('api_token', $bearer)->first();

        if (!$user_bearer) {
            return null;
        } else {
            return $user_bearer;
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

    private function semAcentos($string) {
        if ( !preg_match('/[\x80-\xff]/', $string) )
            return $string;

        $chars = array(
        // Decompositions for Latin-1 Supplement
        chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
        chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
        chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
        chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
        chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
        chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
        chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
        chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
        chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
        chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
        chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
        chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
        chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
        chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
        chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
        chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
        chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
        chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
        chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
        chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
        chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
        chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
        chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
        chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
        chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
        chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
        chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
        chr(195).chr(191) => 'y',
        // Decompositions for Latin Extended-A
        chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
        chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
        chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
        chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
        chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
        chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
        chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
        chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
        chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
        chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
        chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
        chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
        chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
        chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
        chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
        chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
        chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
        chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
        chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
        chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
        chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
        chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
        chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
        chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
        chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
        chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
        chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
        chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
        chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
        chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
        chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
        chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
        chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
        chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
        chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
        chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
        chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
        chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
        chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
        chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
        chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
        chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
        chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
        chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
        chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
        chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
        chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
        chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
        chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
        chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
        chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
        chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
        chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
        chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
        chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
        chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
        chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
        chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
        chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
        chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
        chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
        chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
        chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
        chr(197).chr(190) => 'z', chr(197).chr(191) => 's'
        );

        $string = strtr($string, $chars);

        return $string;
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
