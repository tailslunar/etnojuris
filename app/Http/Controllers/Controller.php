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
use App\Models\TB_Sentenca;
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
        'TB_Sentenca' => ['sentenca', 'sentencas'],
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
        'TB_Sentenca' => 'etno_mysql',
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
        'codigo_ibge' => 'codigo_ibge',
        'importar_processo' => 'importar_processo'
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
                'table' => $model,
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
                'table' => $item,
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
                'table' => $item,
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
                'table' => $item,
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
                'table' => $item,
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
        $dado['processo']['trf5'] = 0;
        $dado['processo']['trf6'] = 0;

        $dado['quilombo'] = [];
        $dado['quilombo']['trf1'] = 0;
        $dado['quilombo']['trf2'] = 0;
        $dado['quilombo']['trf3'] = 0;
        $dado['quilombo']['trf4'] = 0;
        $dado['quilombo']['trf5'] = 0;
        $dado['quilombo']['trf6'] = 0;

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

    public function importar_processo_index(Request $request)
    {
        $retorno = [
            'status' => 'error',
            'message' => 'Método não implementado.',
        ];
        return response()->json($retorno, 405);
    }

    public function importar_processo(Request $request)
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

        $todos_quilombos = TB_Quilombo::all();
        $todos_processos = TB_Processo::all();
        $todas_partes = TB_Parte::all();
        $todos_advogados = TB_Advogado::all();
        $todos_procuradores = TB_Procurador::all();
        $todos_defensores = TB_Defensoria::all();
        $todos_participantes = TB_Participante::all();

        $retorno = [];
        $obj = json_decode($request->post('obj'), true);

        if (in_array("objQuilombo", array_keys($obj))) {
            if (is_array(reset($obj['objQuilombo']))) {
                foreach ($obj['objQuilombo'] as $input_quilombo) {
                    //cria novo quilombo e salva, mesmo processo abaixo (podia ser uma função)
                    $salvar_quilombo = true;
                    if (isset($input_quilombo['id'])) {
                        if (!is_numeric(($input_quilombo['id']))) {
                            $input_quilombo['id'] = null;
                        }
                        if (!isset($id_quilombo)) {
                            $id_quilombo = $input_quilombo['id'];
                        }
                        if ($input_quilombo['id']) {
                            $quilombo = $todos_quilombos->where('id', $input_quilombo['id'])->first();
                        } else {
                            $quilombo = new TB_Quilombo();
                        }
                        if (!$quilombo) {
                            $quilombo = new TB_Quilombo();
                        } else {
                            $salvar_quilombo = false;
                        }
                    } else {
                        $quilombo = new TB_Quilombo();
                    }
                    foreach ($input_quilombo as $key_quilombo => $value_quilombo) {
                        $quilombo->{$key_quilombo} = $value_quilombo;
                    }
                    if ($salvar_quilombo) {
                        try {
                            $quilombo->save();
                        } catch (\Exception $e) {
                            $retorno = [
                                'status' => 'error',
                                'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                'table' => 'quilombo',
                                'exception' => $e
                            ];
                            return response()->json($retorno, 500);
                        }
                    }
                    if (!isset($id_quilombo)) {
                        $id_quilombo = $quilombo->id;
                    }
                    $retorno['objQuilombo'][] = $quilombo;
                }
            } else {
                $input_quilombo = $obj['objQuilombo'];

                //cria novo quilombo e salva, mesmo processo acima (podia ser uma função)
                $salvar_quilombo = true;
                if (isset($input_quilombo['id'])) {
                    if (!is_numeric(($input_quilombo['id']))) {
                        $input_quilombo['id'] = null;
                    }
                    if (!isset($id_quilombo)) {
                        $id_quilombo = $input_quilombo['id'];
                    }
                    if ($input_quilombo['id']) {
                        $quilombo = $todos_quilombos->where('id', $input_quilombo['id'])->first();
                    } else {
                        $quilombo = new TB_Quilombo();
                    }
                    if (!$quilombo) {
                        $quilombo = new TB_Quilombo();
                    } else {
                        $salvar_quilombo = false;
                    }
                } else {
                    $quilombo = new TB_Quilombo();
                }
                foreach ($input_quilombo as $key_quilombo => $value_quilombo) {
                    $quilombo->{$key_quilombo} = $value_quilombo;
                }
                if ($salvar_quilombo) {
                    try {
                        $quilombo->save();
                    } catch (\Exception $e) {
                        $retorno = [
                            'status' => 'error',
                            'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                            'table' => 'quilombo',
                            'exception' => $e
                        ];
                        return response()->json($retorno, 500);
                    }
                }
                if (!isset($id_quilombo)) {
                    $id_quilombo = $quilombo->id;
                }
                $retorno['objQuilombo'][] = $quilombo;
            }
        }

        if (in_array('objProcesso', array_keys($obj))) {
            if (is_array(reset($obj['objProcesso']))) {
                foreach ($obj['objProcesso'] as $input_processo) {
                    //cria novo processo e salva, mesmo processo abaixo (podia ser uma função)
                    $salvar_processo = true;
                    if (isset($input_processo['id'])) {
                        if (!is_numeric(($input_processo['id']))) {
                            $input_processo['id'] = null;
                        }
                        if (!isset($id_processo)) {
                            $id_processo = $input_processo['id'];
                        }
                        if ($input_processo['id']) {
                            $processo = $todos_processos->where('id', $input_processo['id'])->first();
                        } else {
                            $processo = new TB_Processo();
                        }
                        if (!$processo) {
                            $processo = new TB_Processo();
                        } else {
                            $salvar_processo = false;
                        }
                    } else {
                        $processo = new TB_Processo();
                    }
                    foreach ($input_processo as $key_processo => $value_processo) {
                        if ($key_processo == 'data_publicacao') {
                            $processo->{$key_processo} = Carbon::createFromFormat('d/m/Y', $value_processo)->toDateTimeString();
                        } else {
                            $processo->{$key_processo} = $value_processo;
                        }
                    }
                    $processo->quilombo_id = $id_quilombo;
                    if ($salvar_processo) {
                        try {
                            $processo->save();
                        } catch (\Exception $e) {
                            $retorno = [
                                'status' => 'error',
                                'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                'table' => 'processo',
                                'exception' => $e
                            ];
                            return response()->json($retorno, 500);
                        }
                    }
                    if (!isset($id_processo)) {
                        $id_processo = $processo->id;
                    }
                    $retorno['objProcesso'][] = $processo;
                }
            } else {
                $input_processo = $obj['objProcesso'];

                //cria novo processo e salva, mesmo processo acima (podia ser uma função)
                $salvar_processo = true;
                if (isset($input_processo['id'])) {
                    if (!is_numeric(($input_processo['id']))) {
                        $input_processo['id'] = null;
                    }
                    if (!isset($id_processo)) {
                        $id_processo = $input_processo['id'];
                    }
                    if ($input_processo['id']) {
                        $processo = $todos_processos->where('id', $input_processo['id'])->first();
                    } else {
                        $processo = new TB_Processo();
                    }
                    if (!$processo) {
                        $processo = new TB_Processo();
                    } else {
                        $salvar_processo = false;
                    }
                } else {
                    $processo = new TB_Processo();
                }
                foreach ($input_processo as $key_processo => $value_processo) {
                    if ($key_processo == 'data_publicacao') {
                        $processo->{$key_processo} = Carbon::createFromFormat('d/m/Y', $value_processo)->toDateTimeString();
                    } else {
                        $processo->{$key_processo} = $value_processo;
                    }
                }
                $processo->quilombo_id = $id_quilombo;
                if ($salvar_processo) {
                    try {
                        $processo->save();
                    } catch (\Exception $e) {
                        $retorno = [
                            'status' => 'error',
                            'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                            'table' => 'processo',
                            'exception' => $e
                        ];
                        return response()->json($retorno, 500);
                    }
                }
                if (!isset($id_processo)) {
                    $id_processo = $processo->id;
                }
                $retorno['objProcesso'][] = $processo;
            }
        }

        if (in_array('objParte', array_keys($obj))) {
            if (is_array(reset($obj['objParte']))) {
                foreach ($obj['objParte'] as $input_parte) {
                    //cria nova parte e salva, mesmo processo abaixo (podia ser uma função)
                    $salvar_parte = true;
                    if (isset($input_parte['id'])) {
                        if (!is_numeric(($input_parte['id']))) {
                            $input_parte['id'] = null;
                        }
                        if (!isset($id_parte)) {
                            $id_parte = $input_parte['id'];
                        }
                        if ($input_parte['id']) {
                            $parte = $todas_partes->where('id', $input_parte['id'])->first();
                        } else {
                            $parte = new TB_Parte();
                        }
                        if (!$parte) {
                            $parte = new TB_Parte();
                        } else {
                            $salvar_parte = false;
                        }
                    } else {
                        $parte = new TB_Parte();
                    }
                    $advogado = null;
                    $procurador = null;
                    $defensor = null;
                    foreach ($input_parte as $key_parte => $value_parte) {
                        $salvar_advogado = true;
                        if ($key_parte == 'advogado') {
                            $input_advogado = $input_parte['advogado'];
                            if (isset($input_advogado['id'])) {
                                if (!is_numeric(($input_advogado['id']))) {
                                    $input_advogado['id'] = null;
                                }
                                if (!isset($id_advogado)) {
                                    $id_advogado = $input_advogado['id'];
                                }
                                if ($input_advogado['id']) {
                                    $advogado = $todos_advogados->where('id', $input_advogado['id'])->first();
                                } else {
                                    $advogado = new TB_Advogado();
                                }
                                if (!$advogado) {
                                    $advogado = new TB_Advogado();
                                } else {
                                    $salvar_advogado = false;
                                }
                            } else {
                                $advogado = new TB_Advogado();
                            }
                            foreach ($input_advogado as $key_advogado => $value_advogado) {
                                $advogado->{$key_advogado} = $value_advogado;
                            }
                            if ($salvar_advogado) {
                                try {
                                    $advogado->save();
                                } catch (\Exception $e) {
                                    $retorno = [
                                        'status' => 'error',
                                        'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                        'table' => 'advogado',
                                        'exception' => $e
                                    ];
                                    return response()->json($retorno, 500);
                                }
                            }
                            if (!isset($id_advogado)) {
                                $id_advogado = $advogado->id;
                            }
                        }
                        else if ($key_parte == 'defensoria') {
                            $salvar_defensor = true;
                            $input_defensor = $input_parte['defensoria'];
                            //if (in_array('defensoria', array_keys($input_defensor))) {
                                if (in_array('instituicao', array_keys($input_defensor))) {
                                    if (isset($input_defensor['instituicao']['id'])) {
                                        $input_defensor['id'] = $input_defensor['instituicao']['id'];
                                    }
                                }
                            //}
                            if (isset($input_defensor['id'])) {
                                if (!is_numeric(($input_defensor['id']))) {
                                    $input_defensor['id'] = null;
                                }
                                if (!isset($id_defensor)) {
                                    $id_defensor = $input_defensor['id'];
                                }
                                if ($input_defensor['id']) {
                                    $defensor = $todos_defensores->where('id', $input_defensor['id'])->first();
                                } else {
                                    $defensor = new TB_Defensoria();
                                }
                                if (!$defensor) {
                                    $defensor = new TB_Defensoria();
                                } else {
                                    $salvar_defensor = false;
                                }
                            } else {
                                $defensor = new TB_Defensoria();
                            }
                            foreach ($input_defensor as $key_defensor => $value_defensor) {
                                $defensor->{$key_defensor} = $value_defensor;
                            }
                            if ($salvar_defensor) {
                                //por enquanto nao salvar defensor
                                try {
                                    //$defensor->save();
                                } catch (\Exception $e) {
                                    $retorno = [
                                        'status' => 'error',
                                        'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                        'table' => 'defensoria',
                                        'exception' => $e
                                    ];
                                    return response()->json($retorno, 500);
                                }
                            }
                            if (!isset($id_defensor)) {
                                $id_defensor = $defensor->id;
                            }
                        }
                        else if ($key_parte == 'procurador') {
                            $salvar_procurador = true;
                            $input_procurador = $input_parte['procurador'];
                            //if (in_array('procurador', array_keys($input_procurador))) {
                                if (in_array('instituicao', array_keys($input_procurador))) {
                                    if (isset($input_procurador['instituicao']['id'])) {
                                        $input_procurador['id'] = $input_procurador['instituicao']['id'];
                                    }
                                }
                            //}
                            if (isset($input_procurador['id'])) {
                                if (!is_numeric(($input_procurador['id']))) {
                                    $input_procurador['id'] = null;
                                }
                                if (!isset($id_procurador)) {
                                    $id_procurador = $input_procurador['id'];
                                }
                                if ($input_procurador['id']) {
                                    $procurador = $todos_procuradores->where('id', $input_procurador['id'])->first();
                                } else {
                                    $procurador = new TB_Procurador();
                                }
                                if (!$procurador) {
                                    $procurador = new TB_Procurador();
                                } else {
                                    $salvar_procurador = false;
                                }
                            } else {
                                $procurador = new TB_Procurador();
                            }
                            foreach ($input_procurador as $key_procurador => $value_procurador) {
                                $procurador->{$key_procurador} = $value_procurador;
                            }
                            if ($salvar_procurador) {
                                //por enquanto nao salvar procurador
                                try {
                                    //$procurador->save();
                                } catch (\Exception $e) {
                                    $retorno = [
                                        'status' => 'error',
                                        'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                        'table' => 'procurador',
                                        'exception' => $e
                                    ];
                                    return response()->json($retorno, 500);
                                }
                            }
                            if (!isset($id_procurador)) {
                                $id_procurador = $procurador->id;
                            }
                        } else {
                            $parte->{$key_parte} = $value_parte;
                        }
                    }
                    if (!isset($parte->id)) {
                        $salvar_parte = true;
                    } else {
                        if (!$parte->id) {
                            $salvar_parte = true;
                        }
                    }
                    if ($salvar_parte) {
                        try {
                            $parte->save();
                        } catch (\Exception $e) {
                            $retorno = [
                                'status' => 'error',
                                'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                'table' => 'parte',
                                'exception' => $e
                            ];
                            return response()->json($retorno, 500);
                        }
                    }
                    if (!isset($id_parte)) {
                        $id_parte = $parte->id;
                    }
                    $parte_ = [];
                    $parte_['parte'] = $parte;
                    $salvar_participante = false;
                    if ($advogado) {
                        $parte_['advogado'][] = $advogado;
                        $salvar_participante = true;
                    }
                    if ($procurador) {
                        $parte_['procurador'][] = $procurador;
                        $salvar_participante = true;
                    }
                    if ($defensor) {
                        $parte_['defensoria'][] = $defensor;
                        $salvar_participante = true;
                    }
                    $retorno['objParte'][] = $parte_;
                    if ($salvar_participante) {
                        $participante = new TB_Participante();
                        $participante->categoria = $parte->categoria;
                        $participante->papel = $parte->papel;
                        if ($advogado) {
                            $participante->advogado_id = $advogado->id;
                        }
                        if ($procurador) {
                            $participante->procurador_id = $procurador->id;
                        }
                        if ($defensor) {
                            $participante->defensoria_id = $defensor->id;
                        }
                        $participante->parte_id = $parte->id;
                        $participante->processo_id = $id_processo;
                        $participante_existe = $todos_participantes;
                        if (isset($participante->advogado_id)) {
                            $participante_existe = $participante_existe->where('advogado_id', $participante->advogado_id);
                        }
                        if (isset($participante->procurador_id)) {
                            $participante_existe = $participante_existe->where('procurador_id', $participante->procurador_id);
                        }
                        if (isset($participante->defensoria_id)) {
                            $participante_existe = $participante_existe->where('defensoria_id', $participante->defensoria_id);
                        }
                        if (isset($participante->parte_id)) {
                            $participante_existe = $participante_existe->where('parte_id', $participante->parte_id);
                        }
                        if (isset($participante->processo_id)) {
                            $participante_existe = $participante_existe->where('processo_id', $participante->processo_id);
                        }
                        $participante_existe = $participante_existe->first();
                        if (!$participante_existe) {
                            try {
                                $participante->save();
                            } catch (\Exception $e) {
                                $retorno = [
                                    'status' => 'error',
                                    'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                    'table' => 'participante',
                                    'exception' => $e
                                ];
                                return response()->json($retorno, 500);
                            }
                        }
                        $retorno['objParticipante'][] = $participante;
                    }
                }
            } else {
                $input_parte = $obj['objParte'];

                //cria nova parte e salva, mesmo processo acima (podia ser uma função)
                $salvar_parte = true;
                if (isset($input_parte['id'])) {
                    if (!is_numeric(($input_parte['id']))) {
                        $input_parte['id'] = null;
                    }
                    if (!isset($id_parte)) {
                        $id_parte = $input_parte['id'];
                    }
                    if ($input_parte['id']) {
                        $parte = $todas_partes->where('id', $input_parte['id'])->first();
                    }  else {
                        $parte = new TB_Parte();
                    }
                    if (!$parte) {
                        $parte = new TB_Parte();
                    } else {
                        $salvar_parte = false;
                    }
                } else {
                    $parte = new TB_Parte();
                }
                $advogado = null;
                $procurador = null;
                $defensor = null;
                foreach ($input_parte as $key_parte => $value_parte) {
                    $salvar_advogado = true;
                    if ($key_parte == 'advogado') {
                        $input_advogado = $input_parte['advogado'];
                        if (isset($input_advogado['id'])) {
                            if (!is_numeric(($input_advogado['id']))) {
                                $input_advogado['id'] = null;
                            }
                            if (!isset($id_advogado)) {
                                $id_advogado = $input_advogado['id'];
                            }
                            if ($input_advogado) {
                                $advogado = $todos_advogados->where('id', $input_advogado['id'])->first();
                            } else {
                                $advogado = new TB_Advogado();
                            }
                            if (!$advogado) {
                                $advogado = new TB_Advogado();
                            } else {
                                $salvar_advogado = false;
                            }
                        } else {
                            $advogado = new TB_Advogado();
                        }
                        foreach ($input_advogado as $key_advogado => $value_advogado) {
                            $advogado->{$key_advogado} = $value_advogado;
                        }
                        if ($salvar_advogado) {
                            try {
                                $advogado->save();
                            } catch (\Exception $e) {
                                $retorno = [
                                    'status' => 'error',
                                    'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                    'table' => 'advogado',
                                    'exception' => $e
                                ];
                                return response()->json($retorno, 500);
                            }
                        }
                        if (!isset($id_advogado)) {
                            $id_advogado = $advogado->id;
                        }
                    }
                    else if ($key_parte == 'defensoria') {
                        $salvar_defensor = true;
                        $input_defensor = $input_parte['defensoria'];
                        //if (in_array('defensoria', array_keys($input_defensor))) {
                            if (in_array('instituicao', array_keys($input_defensor))) {
                                if (isset($input_defensor['instituicao']['id'])) {
                                    $input_defensor['id'] = $input_defensor['instituicao']['id'];
                                }
                            }
                        //}
                        if (isset($input_defensor['id'])) {
                            if (!is_numeric(($input_defensor['id']))) {
                                $input_defensor['id'] = null;
                            }
                            if (!isset($id_defensor)) {
                                $id_defensor = $input_defensor['id'];
                            }
                            if ($input_defensor['id']) {
                                $defensor = $todos_defensores->where('id', $input_defensor['id'])->first();
                            } else {
                                $defensor = new TB_Defensoria();
                            }
                            if (!$defensor) {
                                $defensor = new TB_Defensoria();
                            } else {
                                $salvar_defensor = false;
                            }
                        } else {
                            $defensor = new TB_Defensoria();
                        }
                        foreach ($input_defensor as $key_defensor => $value_defensor) {
                            $defensor->{$key_defensor} = $value_defensor;
                        }
                        if ($salvar_defensor) {
                            //por enquanto nao salvar defensor
                            try {
                                //$defensor->save();
                            } catch (\Exception $e) {
                                $retorno = [
                                    'status' => 'error',
                                    'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                    'table' => 'defensoria',
                                    'exception' => $e
                                ];
                                return response()->json($retorno, 500);
                            }
                        }
                        if (!isset($id_defensor)) {
                            $id_defensor = $defensor->id;
                        }
                    }
                    else if ($key_parte == 'procurador') {
                        $salvar_procurador = true;
                        $input_procurador = $input_parte['procurador'];
                        //if (in_array('procurador', array_keys($input_procurador))) {
                            if (in_array('instituicao', array_keys($input_procurador))) {
                                if (isset($input_procurador['instituicao']['id'])) {
                                    $input_procurador['id'] = $input_procurador['instituicao']['id'];
                                }
                            }
                        //}
                        if (isset($input_procurador['id'])) {
                            if (!is_numeric(($input_procurador['id']))) {
                                $input_procurador['id'] = null;
                            }
                            if (!isset($id_procurador)) {
                                $id_procurador = $input_procurador['id'];
                            }
                            if ($input_procurador['id']) {
                                $procurador = $todos_procuradores->where('id', $input_procurador['id'])->first();
                            } else {
                                $procurador = new TB_Procurador();
                            }
                            if (!$procurador) {
                                $procurador = new TB_Procurador();
                            } else {
                                $salvar_procurador = false;
                            }
                        } else {
                            $procurador = new TB_Procurador();
                        }
                        foreach ($input_procurador as $key_procurador => $value_procurador) {
                            $procurador->{$key_procurador} = $value_procurador;
                        }
                        if ($salvar_procurador) {
                            //por enquanto nao salvar procurador
                            try {
                                //$procurador->save();
                            } catch (\Exception $e) {
                                $retorno = [
                                    'status' => 'error',
                                    'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                    'table' => 'procurador',
                                    'exception' => $e
                                ];
                                return response()->json($retorno, 500);
                            }
                        }
                        if (!isset($id_procurador)) {
                            $id_procurador = $procurador->id;
                        }
                    } else {
                        $parte->{$key_parte} = $value_parte;
                    }
                }
                if (!isset($parte->id)) {
                    $salvar_parte = true;
                } else {
                    if (!$parte->id) {
                        $salvar_parte = true;
                    }
                }
                if ($salvar_parte) {
                    try {
                        $parte->save();
                    } catch (\Exception $e) {
                        $retorno = [
                            'status' => 'error',
                            'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                            'table' => 'parte',
                            'exception' => $e
                        ];
                        return response()->json($retorno, 500);
                    }
                }
                if (!isset($id_parte)) {
                    $id_parte = $parte->id;
                }
                $parte_ = [];
                $parte_['parte'] = $parte;
                $salvar_participante = false;
                if ($advogado) {
                    $parte_['advogado'][] = $advogado;
                    $salvar_participante = true;
                }
                if ($procurador) {
                    $parte_['procurador'][] = $procurador;
                    $salvar_participante = true;
                }
                if ($defensor) {
                    $parte_['defensoria'][] = $defensor;
                    $salvar_participante = true;
                }
                $retorno['objParte'][] = $parte_;
                if ($salvar_participante) {
                    $participante = new TB_Participante();
                    $participante->categoria = $parte->categoria;
                    $participante->papel = $parte->papel;
                    if ($advogado) {
                        $participante->advogado_id = $advogado->id;
                    }
                    if ($procurador) {
                        $participante->procurador_id = $procurador->id;
                    }
                    if ($defensor) {
                        $participante->defensoria_id = $defensor->id;
                    }
                    $participante->parte_id = $parte->id;
                    $participante->processo_id = $id_processo;
                    $participante_existe = $todos_participantes;
                    if (isset($participante->advogado_id)) {
                        $participante_existe = $participante_existe->where('advogado_id', $participante->advogado_id);
                    }
                    if (isset($participante->procurador_id)) {
                        $participante_existe = $participante_existe->where('procurador_id', $participante->procurador_id);
                    }
                    if (isset($participante->defensoria_id)) {
                        $participante_existe = $participante_existe->where('defensoria_id', $participante->defensoria_id);
                    }
                    if (isset($participante->parte_id)) {
                        $participante_existe = $participante_existe->where('parte_id', $participante->parte_id);
                    }
                    if (isset($participante->processo_id)) {
                        $participante_existe = $participante_existe->where('processo_id', $participante->processo_id);
                    }
                    $participante_existe = $participante_existe->first();
                    if (!$participante_existe) {
                        try {
                            $participante->save();
                        } catch (\Exception $e) {
                            $retorno = [
                                'status' => 'error',
                                'message' => 'Ocorreu uma exceção e o registro não pôde ser importado!',
                                'table' => 'participante',
                                'exception' => $e
                            ];
                            return response()->json($retorno, 500);
                        }
                    }
                    $retorno['objParticipante'][] = $participante;
                }
            }
        }

        /* desativado, por enquanto
        $sentencas_processo = TB_Sentenca::where('id', $processo->sentenca_id)->get();
        $retorno['objSentenca'][] = $sentencas_processo;
        */

        $ret = [
            'status' => 'success',
            'message' => 'Registro salvo com sucesso!',
            'data' => $retorno,
            'input' => $obj
        ];
        return response()->json($ret);
    }

    public function dados_processo(Request $request)
    {
        $todos_processos = TB_Processo::all();

        $retorno = [];
        $id_processo = $request->post('processo');
        if (!$id_processo) {
            $id_processo = Route::current()->parameter('processo');
        }
        $processo = $todos_processos->where('id', $id_processo)->first();

        $quilombos_processo = TB_Quilombo::where('id', $processo->quilombo_id)->get();
        $participantes_processo = TB_Participante::where('processo_id', $processo->id)->get();
        $sentencas_processo = TB_Sentenca::where('id', $processo->sentenca_id)->get();
        $localidades_processo = TB_Localidade::where('id', $processo->localidade_id)->get();

        $advogados_processo = [];
        $procuradores_processo = [];
        $defensorias_processo = [];
        $partes_processo = [];
        foreach ($participantes_processo as $participante) {
            if ($participante->advogado_id) {
                $advogados_processo[] = TB_Advogado::where('id', $participante->advogado_id)->get();
            }
            if ($participante->procurador_id) {
                $procuradores_processo[] = TB_Procurador::where('id', $participante->procurador_id)->get();
            }
            if ($participante->defensoria_id) {
                $defensorias_processo[] = TB_Defensoria::where('id', $participante->defensoria_id)->get();
            }
            if ($participante->parte_id) {
                $partes_processo[] = TB_Parte::where('id', $participante->parte_id)->get();
            }
        }

        foreach($quilombos_processo as $quilombo) {
            $quilombo_localidade = TB_Localidade::where('id', $quilombo->localidade_id)->get();
            
            if ($quilombo_localidade->count() > 1) {
                $quilombo->localidade = $quilombo_localidade;
            } else if ($quilombo_localidade->count() > 0) {
                $quilombo->localidade = $quilombo_localidade[0];
            } else {
                $quilombo->localidade = $quilombo_localidade;
            }
        }

        if ($quilombos_processo->count() > 1) {
            $retorno['objQuilombo'] = $quilombos_processo;
        } else if ($quilombos_processo->count() > 0) {
            $retorno['objQuilombo'] = $quilombos_processo[0];
        } else {
            $retorno['objQuilombo'] = $quilombos_processo;
        }

        if ($processo->count() > 1) {
            $retorno['objProcesso'] = $processo;
        } else  if ($processo->count() > 0) {
            $retorno['objProcesso'] = $processo[0];
        } else {
            $retorno['objProcesso'] = $processo;
        }

        if (count($partes_processo) > 1) {
            $retorno['objParte'] = $partes_processo;    
        } else if (count($partes_processo) > 0) {
            $retorno['objParte'] = $partes_processo[0];
        } else {
            $retorno['objParte'] = $partes_processo;
        }

        if (count($advogados_processo) > 1) {
            $retorno['objAdvogado'] = $advogados_processo;
        } else if (count($advogados_processo) > 0) {
            $retorno['objAdvogado'] = $advogados_processo[0];
        } else {
            $retorno['objAdvogado'] = $advogados_processo;
        }

        if (count($procuradores_processo) > 1) {
            $retorno['objProcurador'] = $procuradores_processo;
        } else if (count($procuradores_processo) > 0) {
            $retorno['objProcurador'] = $procuradores_processo[0];
        } else {
            $retorno['objProcurador'] = $procuradores_processo;
        }

        if (count($defensorias_processo) > 1) {
            $retorno['objDefensoria'] = $defensorias_processo;
        } else if (count($defensorias_processo) > 0) {
            $retorno['objDefensoria'] = $defensorias_processo[0];
        } else {
            $retorno['objDefensoria'] = $defensorias_processo;
        }

        if ($participantes_processo->count() > 1) {
            $retorno['objParticipante'] = $participantes_processo;
        } else if ($participantes_processo->count() > 0) {
            $retorno['objParticipante'] = $participantes_processo[0];
        } else {
            $retorno['objParticipante'] = $participantes_processo;
        }

        if ($sentencas_processo->count() > 1) {
            $retorno['objSentenca'] = $sentencas_processo;
        } else if ($sentencas_processo->count() > 0) {
            $retorno['objSentenca'] = $sentencas_processo[0];
        } else {
            $retorno['objSentenca'] = $sentencas_processo;
        }

        if ($localidades_processo->count() > 1) {
            $retorno['objLocalidade'] = $localidades_processo;
        } else if ($localidades_processo->count() > 0) {
            $retorno['objLocalidade'] = $localidades_processo[0];
        } else {
            $retorno['objLocalidade'] = $localidades_processo;
        }

        return response()->json($retorno);
    }

    public function quilombo_processos(Request $request)
    {
        $retorno = [];
        $id_quilombo = $request->post('quilombo');
        if (!$id_quilombo) {
            $id_quilombo = Route::current()->parameter('quilombo');
        }
        $processos = TB_Processo::where('quilombo_id', $id_quilombo)->get();

        return response()->json($processos);
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
                'table' => 'user',
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
