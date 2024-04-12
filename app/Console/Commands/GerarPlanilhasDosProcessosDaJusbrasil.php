<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

class GerarPlanilhasDosProcessosDaJusbrasil extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gerarplanilhas {modo} {recriar} {backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $tabelas = [
        'movs',
        'audiencias',
        'anexos',
        'processosRelacionados',
        'customs',
        'classes',
        'acessos',
        'partes'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modo = $this->argument('modo');
        $dropar = $this->argument('recriar');
        $backup = $this->argument('backup');

        $this->criarTabelasDeSistema();

        if ($dropar == 'sim') {
            $this->droparTabelas();
            $this->executar();
        } else if ($dropar != 'nao') {
            $this->opcaoDeDroparNaoExiste();
        } else {
            $this->executar();
        }
    }

    private function executar()
    {
        $modo = $this->argument('modo');
        $dropar = $this->argument('recriar');
        $backup = $this->argument('backup');

        $this->inicializarTabelas();

        if ($modo == 'completo') {
            $this->modoCompleto();
        }
        else if ($modo == 'planilhas') {
            $this->geradorDePlanilhas();
        }
        else {
            $this->modoNaoExiste();
        }
    }

    private function criarTabelasDeSistema() {
        //todo: tabela variaveis com coluna ciclo
        //todo: tabela atualizacao_processos com coluna ciclo
    }

    private function atualizarTabelasDeSistemaAposRodarComSucesso() {
        //...
    }

    private function atualizarTabelasDeSistemaAposRodarComFalha() {
        //...
    }

    private function modoNaoExiste() {
        $this->info('Modo não reconhecido. Os modos que o programa pode rodar são: "completo" ou "planilhas"');
    }

    private function opcaoDeDroparNaoExiste() {
        $this->info('Opção de recriar tabelas não reconhecida. As opções de recriação de tabelas disponíveis são: "sim" ou "nao"');
    }

    private function opcaoDeBackupNaoExiste() {
        $this->info('Opção de backup das planilhas não reconhecida. As opções de backup das planilhas disponíveis são: "sim" ou "nao"');
    }

    private function ehPlanilhaImportante($sheet) {
        $planilhas_nao_importantes = [
            'Resumo',
            'Glossario'
        ];

        if (in_array($sheet, $planilhas_nao_importantes)) {
            return false;
        }
        return true;
    }

    private function arrayPraObject($array) {
        return json_decode(json_encode($array));
    }

    private function pausaPraNaoSobrecarregarApi() {
        //todo: descobrir os limites da api?
        sleep(1);
    }

    private function quantosProcessos($sheets) {
        $total_processos = 0;
        foreach ($sheets as $sheet_index => $sheet) {
            if ($this->ehPlanilhaImportante($sheet_index)) {
                $total_processos += count($sheet);
            }
        }
        return $total_processos;
    }

    private function calcularPorcentagem($atual, $total) {
        return round(($atual / $total) * 100);
    }

    private function geradorDePlanilhas() {
        $inicio = Carbon::now();
        $this->info("Rodando programa no modo apenas gerar planilhas: vai rodar apenas a geração de planilhas...");
        $this->exportaBancoDeDadosPraPlanilha($inicio);
        $this->finalizarJob();
    }

    private function inicializarTabelas() {
        if (!Schema::hasTable('processos')) {
            Schema::create('processos', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->softdeletes();
            });
        }

        if (!Schema::hasTable('nomes_colunas_processos')) {
            Schema::create('nomes_colunas_processos', function (Blueprint $table) {
                $table->id();
                $table->text('nome_coluna')->nullable();
                $table->text('nome_na_tabela')->nullable();
                $table->text('nome_final_na_planilha')->nullable();
                $table->timestamps();
                $table->softdeletes();
            });
        }
    }

    private function salvaExcecao($e) {
        if (is_array($e)) {
            $e = json_encode($e);
        }

        $this->error('Deu erro! Excecao salva no arquivo de log!');
        $filename = public_path() . '/log_excecoes_gerador_planilhas.txt' ;
        file_put_contents($filename, $e);
    }

    private function salvaDebug($e) {
        if (is_array($e)) {
            $e = json_encode($e);
        }

        $this->error('Deu erro! Excecao salva no arquivo de log!');
        $filename = public_path() . '/log_debugs_gerador_planilhas.txt' ;
        file_put_contents($filename, $e);
    }

    private function calculaTempo($inicio, $fim) {
        $seconds = $fim->diffInSeconds($inicio);
        $output = sprintf('%02d:%02d:%02d', ($seconds/ 3600),($seconds/ 60 % 60), $seconds% 60);
        return $output;
    }

    private function modoCompleto() {
        $inicio = Carbon::now();
        $modo = $this->argument('modo');
        $dropar = $this->argument('recriar');
        $backup = $this->argument('backup');
        $e = null;
        $sheets = null;
        $sheet_index = null;
        $sheet = null;
        $processo_index = null;
        $processo_na_planilha = null;
        $processo_na_api = null;

        try{
            $filename = public_path() . "\procs_relatorio-quilombo_2023-04-12-2053_zJt.xlsx";
            $sheets = (new FastExcel)->withSheetsNames()->importSheets($filename);
            $processos_count = 1;
            $total_de_processos = $this->quantosProcessos($sheets);

            if ($dropar == 'sim') {
                $this->info("Você selecionou 'SIM' para a opção de limpar as tabelas, entao o programa executará no modo completo..");
            }
            $this->info("Rodando programa no modo completo: vai rodar todos os passos do programa...");

            foreach ($sheets as $sheet_index => $sheet) {
                if ($this->ehPlanilhaImportante($sheet_index)) {
                    foreach($sheet as $processo_index => $processo_na_planilha) {
                        $count_sheet = count($sheet);
                        $p_index = $processo_index + 1;
                        $porcentagem_total = $this->calcularPorcentagem($processos_count, $total_de_processos);
                        $porcentagem_parcial = $this->calcularPorcentagem($p_index, $count_sheet);
                        $this->info("------------------------------------------------------------------------");
                        $this->info("Processo {$processos_count} de {$total_de_processos} [{$porcentagem_total}%]:");
                        $this->info("Processo {$p_index} de {$count_sheet} na planilha {$sheet_index} [{$porcentagem_parcial}%]:");
                        $this->info("Tempo decorrido até agora: {$this->calculaTempo($inicio, Carbon::now())}");
                        $processo_na_api = $this->consultaProcessoNaJusbrasilComRetries($this->arrayPraObject($processo_na_planilha));
                        $this->salvaProcessoNoBanco($processo_na_planilha, $processo_na_api);
                        $processos_count++;

                        $infos = [$processo_na_planilha, $processo_na_api];
                        //ativar isso se quiser debugar e ver que entraram novos campos na planilha ou na api quando rodar o job futuramente
                        //$this->testarDados($infos);
                    }
                }
            }

            $this->info("------------------------------------------------------------------------");
            $this->info("Fim das consultas. Este processo levou {$this->calculaTempo($inicio, Carbon::now())}");

            $this->exportaBancoDeDadosPraPlanilha($inicio);
            $this->finalizarJob();
        } catch (\Exception $e) {
            $debug = [
                "excecao",
                $e,
                //"sheets",
                //$sheets,
                "sheets_index",
                $sheet_index,
                //"sheet",
                //$sheet,
                "processo_index",
                $processo_index,
                "processo_na_planilha",
                $processo_na_planilha,
                "processo_na_api",
                $processo_na_api,
            ];
            $this->salvaExcecao($e);
            $this->salvaDebug($debug);
            dd($debug);
        }
    }

    private function consultaProcessoNaJusbrasilComRetries($processo) {
        $retorno = null;
        $retries = 3;

        $this->info("Processo {$processo->Processo} será consultado na Jusbrasil...");
        for ($i = 0; $i <= $retries; $i++) {
            $this->pausaPraNaoSobrecarregarApi();
            if (!$retorno) {
                $this->info("Tentativa {$i} de {$retries}");
                $retorno = $this->consultaProcessoNaJusbrasil($processo);

                if (!$retorno) {
                    $this->error("Consulta retornou vazia.. uma nova tentativa será realizada..");
                } else {
                    $this->info("Consulta realizada com sucesso!");
                }
            }
        }

        if (!$retorno) {
            dd("Falha nas consultas!");
        }
        return $retorno;
    }

    private function consultaProcessoNaJusbrasil($processo) {
        $client = new \GuzzleHttp\Client();

        try {
            $url = "https://op.digesto.com.br/api/tribproc/{$processo->Processo}?tipo_numero=5";
            $token = "8628cdc8-6c2e-44c6-ac56-22bdd2f9414b";
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                //CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                //CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                CURLOPT_SSL_VERIFYPEER => FALSE,
                CURLOPT_CUSTOMREQUEST => "GET",
                //CURLOPT_VERBOSE => false,
                //CURLOPT_FAILONERROR => true,
                CURLOPT_HTTPHEADER => [
                    //"accept-language: en-US,en;q=0.9,pt-BR;q=0.8,pt;q=0.7",
                    //"User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36",
                    'Authorization: Bearer '. $token,
                    'Content-Type: application/json'
                ],
            ]);

            if(curl_exec($curl) === false) {
                echo 'Curl error: '. curl_error($curl);
            }

            if(curl_errno($curl)) {
                echo 'Curl error: '. curl_error($curl);
            }

            $response = curl_exec($curl);
            curl_close($curl);

            return json_decode($response);
        } catch (\Exception $e) {
            dd("deu errado", $e);
        }
    }

    private function pegarColunasDaTabela($table_name) {
        $item = Processo::first();
        return array_keys($item->getOriginal());
    }

    private function adicionarProcessoTeste() {
        $teste = new Processo();
        $teste->save();
    }

    private function adicionaNomesColunasProcessosPadrao() {
        $processos = Processo::all();
        $adicionado_processo_teste = false;
        if (!$processos->count()) {
            $this->adicionarProcessoTeste();
            $adicionado_processo_teste = true;
        }
        $colunas = $this->pegarColunasDaTabela('processos');
        if ($adicionado_processo_teste) {
            Processo::truncate();
        }
        NomesColunasProcessos::truncate();
        $nomes_colunas_processos = NomesColunasProcessos::all();

        foreach($colunas as $index => $coluna) {
            $nome_coluna_processos = new NomesColunasProcessos();
            $nome_coluna_processos->nome_coluna = $coluna;
            $nome_coluna_processos->nome_na_tabela = $coluna;
            $nome_coluna_processos->save();
        }

        $nomes_colunas_processos = NomesColunasProcessos::all();
    }

    private function droparTabelas() {
        $this->info('Limpando a base dados conforme configuração de entrada...');

        Schema::dropIfExists('processos');
        Schema::dropIfExists('nomes_colunas_processos');

        Schema::dropIfExists('movs_processos');
        Schema::dropIfExists('nomes_colunas_movs');

        Schema::dropIfExists('anexos_processos');
        Schema::dropIfExists('nomes_colunas_anexos');

        Schema::dropIfExists('audiencias_processos');
        Schema::dropIfExists('nomes_colunas_audiencias');

        Schema::dropIfExists('processosRelacionados_processos');
        Schema::dropIfExists('nomes_colunas_processosRelacionados');

        Schema::dropIfExists('customs_processos');
        Schema::dropIfExists('nomes_colunas_customs');

        Schema::dropIfExists('classes_processos');
        Schema::dropIfExists('nomes_colunas_classes');

        Schema::dropIfExists('acessos_processos');
        Schema::dropIfExists('nomes_colunas_acessos');

        Schema::dropIfExists('partes_processos');
        Schema::dropIfExists('nomes_colunas_partes');
    }

    private function apenasCaracteres($dados) {
        $dados = normalizer_normalize($dados);
        $dados = preg_replace("#[^A-Za-z1-9]#","", $dados);
        return $dados;
    }

    private function testarDados($dados) {
        dd($dados);
    }

    private function primeiraLetraMaiuscula($input) {
        $input = strtolower($input);
        return ucfirst($input);
    }

    private function salvaProcessoNoBanco($da_planilha, $da_api) {
        //todo: se o processo já existir: salvar novamente? apagar e salvar? atualizar com update? truncar tabela de processos antes?

        $num_processo = $da_planilha['Processo'];
        $dados = new \stdClass;
        $infos = [$da_planilha, $da_api];
        $index = 1;

        $processo = new Processo();
        $this->adicionaNomesColunasProcessosPadrao($processo);
        $this->info("Salvando processo no banco!");

        //ativar isso se quiser debugar e ver que entraram novos campos na planilha ou na api quando rodar o job futuramente
        //$this->testarDados($infos);

        foreach ($da_planilha as $key => $value)
        {
            $key_corrigida = 'planilha_' . $this->apenasCaracteres($key);
            if (!is_array($da_planilha[$key])) {
                if (!Schema::hasColumn('processos', $key_corrigida)) {
                    Schema::table('processos', function (Blueprint $table) use($key_corrigida) {
                        $table->text($key_corrigida)->nullable();
                    });
                }

                $processo->{$key_corrigida} = $value;
                $nome_coluna_processos = new NomesColunasProcessos();
                $nome_coluna_processos->nome_coluna = $key;
                $nome_coluna_processos->nome_na_tabela = $key_corrigida;
                $nome_coluna_processos->save();
            }
            else {
                dd($key);

                if (!Schema::hasTable($key.'_processos')) {
                    Schema::create($key.'_processos', function (Blueprint $table) {
                        $table->id();
                        $table->timestamps();
                        $table->softdeletes();
                    });
                }
                //e se for array? tratar caso a caso e ver se precisa criar uma nova tabela relacionada
                //se sim: vai ser um processo igual ao de cima. nao esquecer de colocar o processo atual como fk

                //pra cada key, ver se existe como coluna na tabela de processos:
                //se nao existe, altera a tabela pra adicionar a coluna
                //salva o dado atual dando update no dado atual
            }
            $index++;
        }

        foreach ($da_api as $key => $value)
        {
            $key_corrigida = 'api_' . $this->apenasCaracteres($key);
            if (!is_array($da_api->$key)) {
                if (!Schema::hasColumn('processos', $key_corrigida)) {
                    Schema::table('processos', function (Blueprint $table) use($key_corrigida) {
                        $table->text($key_corrigida)->nullable();
                    });
                }

                $processo->{$key_corrigida} = $value;
                $nome_coluna_processos = new NomesColunasProcessos();
                $nome_coluna_processos->nome_coluna = $key;
                $nome_coluna_processos->nome_na_tabela = $key_corrigida;
                $nome_coluna_processos->save();
            }
            else {
                $classes_conhecidas = $this->tabelas;

                if (!in_array($key, $classes_conhecidas)) {
                    dd('alerta: classe ainda nao conhecida', $key, $value);
                }

                if (!Schema::hasTable($key.'_processos')) {
                    Schema::create($key.'_processos', function (Blueprint $table) {
                        $table->id();
                        $table->text('processo')->nullable();
                        $table->timestamps();
                        $table->softdeletes();
                    });
                }

                if (!Schema::hasTable('nomes_colunas_'.$key)) {
                    Schema::create('nomes_colunas_'.$key, function (Blueprint $table) {
                        $table->id();
                        $table->text('nome_coluna')->nullable();
                        $table->text('nome_na_tabela')->nullable();
                        $table->text('nome_final_na_planilha')->nullable();
                        $table->timestamps();
                        $table->softdeletes();
                    });
                }

                if (!Schema::hasColumn('processos', 'numero_de_'.$key)) {
                    Schema::table('processos', function (Blueprint $table) use($key) {
                        $table->text('numero_de_'.$key)->nullable();
                    });
                }

                $numero_de_itens = 'numero_de_' . $key;
                $processo->{$numero_de_itens} = count($da_api->{$key});

                foreach ($value as $key_ => $value_) {
                    $className = 'App\\Models\\' . $this->primeiraLetraMaiuscula($key);
                    $controller =  new $className;

                    //anexos
                    if ($key == 'anexos') {
                        if (is_array($key_)) {
                            $array_keys = array_keys($key_);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'array_keys',
                                $array_keys
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 1');
                            dd($debug);
                        } else if (is_object($key_)) {
                            $array = get_object_vars($key_);
                            $properties = array_keys($array);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'properties',
                                $properties
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 2');
                            dd($debug);
                        } else {
                            $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($key);
                            $nome_coluna_controller =  new $nome_coluna_classname;

                            //$key_corrigida_ = 'api_' . $this->apenasCaracteres($key) . '_' . $this->apenasCaracteres($key_);
                            $key_corrigida_ = 'api_' . $this->apenasCaracteres($key);

                            $index_maior_conteudo = 0;
                            $tamanho_maior_conteudo = 0;
                            foreach($value_ as $key__ => $value__) {
                                if (strlen($value__) > $tamanho_maior_conteudo) {
                                    $index_maior_conteudo = $key__;
                                    $tamanho_maior_conteudo = strlen($value__);
                                }
                            }

                            foreach($value_ as $key__ => $value__) {
                                if (!Schema::hasColumn($key.'_processos', $key_corrigida_."_".$key__)) {
                                    Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_, $key__) {
                                        $table->text($key_corrigida_."_".$key__)->nullable();
                                    });

                                    $nome_coluna_controller = new $nome_coluna_classname;
                                    $nome_coluna_controller->nome_coluna = $key_;
                                    $nome_coluna_controller->nome_na_tabela = $key_corrigida_."_".$key__;
                                    $nome_coluna_controller->save();
                                }

                                if (!Schema::hasColumn($key.'_processos', 'local_anexo')) {
                                    Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_, $key__) {
                                        $table->text('local_anexo')->nullable();
                                    });

                                    $nome_coluna_controller = new $nome_coluna_classname;
                                    $nome_coluna_controller->nome_coluna = 'local_anexo';
                                    $nome_coluna_controller->nome_na_tabela = 'local_anexo';
                                    $nome_coluna_controller->save();
                                }

                                $tamanho_text_field = 1000;
                                $controller->{$key_corrigida_."_".$key__} = mb_substr($value__, 0, $tamanho_text_field);
                                $debug = [
                                    'key',
                                    $key,
                                    'value',
                                    $value,
                                    'key_',
                                    $key_,
                                    'value_',
                                    $value_,
                                    'key__',
                                    $key__,
                                    'value__',
                                    $value__,
                                    'da_planilha',
                                    $da_planilha,
                                    'da_api',
                                    $da_api
                                ];
                            }
                            $controller->local_anexo = $this->salvarAnexo($num_processo, $key_, $value_[$index_maior_conteudo], $debug);
                        }
                    //movs
                    } else if ($key == 'movs') {
                        if (is_array($key_)) {
                            $array_keys = array_keys($key_);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'array_keys',
                                $array_keys
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 1');
                            dd($debug);
                        } else if (is_object($key_)) {
                            $array = get_object_vars($key_);
                            $properties = array_keys($array);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'properties',
                                $properties
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 2');
                            dd($debug);
                        } else {
                            $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($key);
                            $nome_coluna_controller =  new $nome_coluna_classname;

                            //$key_corrigida_ = 'api_' . $this->apenasCaracteres($key) . '_' . $this->apenasCaracteres($key_);
                            $key_corrigida_ = 'api_' . $this->apenasCaracteres($key);

                            $index_maior_conteudo = 0;
                            $tamanho_maior_conteudo = 0;

                            foreach($value_ as $key__ => $value__) {
                                if (!Schema::hasColumn($key.'_processos', $key_corrigida_."_".$key__)) {
                                    Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_, $key__) {
                                        $table->text($key_corrigida_."_".$key__)->nullable();
                                    });

                                    $nome_coluna_controller = new $nome_coluna_classname;
                                    $nome_coluna_controller->nome_coluna = $key_;
                                    $nome_coluna_controller->nome_na_tabela = $key_corrigida_."_".$key__;
                                    $nome_coluna_controller->save();
                                }

                                $tamanho_text_field = 1000;
                                if (!is_array($value__)) {
                                    $controller->{$key_corrigida_."_".$key__} = $value__;
                                } else {
                                    $controller->{$key_corrigida_."_".$key__} = json_encode($value__);;
                                }
                                $debug = [
                                    'key',
                                    $key,
                                    'value',
                                    $value,
                                    'key_',
                                    $key_,
                                    'value_',
                                    $value_,
                                    'key__',
                                    $key__,
                                    'value__',
                                    $value__,
                                    'da_planilha',
                                    $da_planilha,
                                    'da_api',
                                    $da_api
                                ];
                            }
                        }
                    //acessos
                    } else if ($key == 'acessos') {
                        if (is_array($key_)) {
                            $array_keys = array_keys($key_);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'array_keys',
                                $array_keys
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 1');
                            dd($debug);
                        } else if (is_object($key_)) {
                            $array = get_object_vars($key_);
                            $properties = array_keys($array);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'properties',
                                $properties
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 2');
                            dd($debug);
                        } else {
                            $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($key);
                            $nome_coluna_controller =  new $nome_coluna_classname;

                            //$key_corrigida_ = 'api_' . $this->apenasCaracteres($key) . '_' . $this->apenasCaracteres($key_);
                            $key_corrigida_ = 'api_' . $this->apenasCaracteres($key);

                            $index_maior_conteudo = 0;
                            $tamanho_maior_conteudo = 0;

                            if (!is_array($value_)) {
                                if (!Schema::hasColumn($key.'_processos', $key_corrigida_)) {
                                    Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_) {
                                        $table->text($key_corrigida_)->nullable();
                                    });

                                    $nome_coluna_controller = new $nome_coluna_classname;
                                    $nome_coluna_controller->nome_coluna = $key_;
                                    $nome_coluna_controller->nome_na_tabela = $key_corrigida_;
                                    $nome_coluna_controller->save();
                                }

                                $tamanho_text_field = 1000;
                                $controller->{$key_corrigida_} = $value_;
                                $debug = [
                                    'key',
                                    $key,
                                    'value',
                                    $value,
                                    'key_',
                                    $key_,
                                    'value_',
                                    $value_,
                                    'da_planilha',
                                    $da_planilha,
                                    'da_api',
                                    $da_api
                                ];
                            } else {
                                foreach($value_ as $key__ => $value__) {
                                    if (!Schema::hasColumn($key.'_processos', $key_corrigida_."_".$key__)) {
                                        Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_, $key__) {
                                            $table->text($key_corrigida_."_".$key__)->nullable();
                                        });

                                        $nome_coluna_controller = new $nome_coluna_classname;
                                        $nome_coluna_controller->nome_coluna = $key_;
                                        $nome_coluna_controller->nome_na_tabela = $key_corrigida_."_".$key__;
                                        $nome_coluna_controller->save();
                                    }

                                    if (!Schema::hasColumn($key.'_processos', 'local_anexo')) {
                                        Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_, $key__) {
                                            $table->text('local_anexo')->nullable();
                                        });

                                        $nome_coluna_controller = new $nome_coluna_classname;
                                        $nome_coluna_controller->nome_coluna = 'local_anexo';
                                        $nome_coluna_controller->nome_na_tabela = 'local_anexo';
                                        $nome_coluna_controller->save();
                                    }

                                    $tamanho_text_field = 1000;
                                    if (!is_array($value__)) {
                                        $controller->{$key_corrigida_."_".$key__} = $value__;
                                    } else {
                                        $controller->{$key_corrigida_."_".$key__} = json_encode($value__);;
                                    }
                                    $debug = [
                                        'key',
                                        $key,
                                        'value',
                                        $value,
                                        'key_',
                                        $key_,
                                        'value_',
                                        $value_,
                                        'key__',
                                        $key__,
                                        'value__',
                                        $value__,
                                        'da_planilha',
                                        $da_planilha,
                                        'da_api',
                                        $da_api
                                    ];
                                }
                            }
                        }
                    //classes
                    } else if ($key == 'classes') {
                        if (is_array($key_)) {
                            $array_keys = array_keys($key_);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'array_keys',
                                $array_keys
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 1');
                            dd($debug);
                        } else if (is_object($key_)) {
                            $array = get_object_vars($key_);
                            $properties = array_keys($array);
                            $debug = [
                                'key',
                                $key,
                                'value',
                                $value,
                                'key_',
                                $key_,
                                'value_',
                                $value_,
                                'properties',
                                $properties
                            ];
                            $this->salvaExcecao($debug);
                            dd('erro 2');
                            dd($debug);
                        } else {
                            $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($key);
                            $nome_coluna_controller =  new $nome_coluna_classname;

                            //$key_corrigida_ = 'api_' . $this->apenasCaracteres($key) . '_' . $this->apenasCaracteres($key_);
                            $key_corrigida_ = 'api_' . $this->apenasCaracteres($key);

                            $index_maior_conteudo = 0;
                            $tamanho_maior_conteudo = 0;

                            if (!is_array($value_)) {
                                if (!Schema::hasColumn($key.'_processos', $key_corrigida_)) {
                                    Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_) {
                                        $table->text($key_corrigida_)->nullable();
                                    });

                                    $nome_coluna_controller = new $nome_coluna_classname;
                                    $nome_coluna_controller->nome_coluna = $key_;
                                    $nome_coluna_controller->nome_na_tabela = $key_corrigida_;
                                    $nome_coluna_controller->save();
                                }

                                $tamanho_text_field = 1000;
                                $controller->{$key_corrigida_} = $value_;
                                $debug = [
                                    'key',
                                    $key,
                                    'value',
                                    $value,
                                    'key_',
                                    $key_,
                                    'value_',
                                    $value_,
                                    'da_planilha',
                                    $da_planilha,
                                    'da_api',
                                    $da_api
                                ];
                            } else {
                                foreach($value_ as $key__ => $value__) {
                                    if (!Schema::hasColumn($key.'_processos', $key_corrigida_."_".$key__)) {
                                        Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_, $key__) {
                                            $table->text($key_corrigida_."_".$key__)->nullable();
                                        });

                                        $nome_coluna_controller = new $nome_coluna_classname;
                                        $nome_coluna_controller->nome_coluna = $key_;
                                        $nome_coluna_controller->nome_na_tabela = $key_corrigida_."_".$key__;
                                        $nome_coluna_controller->save();
                                    }

                                    if (!Schema::hasColumn($key.'_processos', 'local_anexo')) {
                                        Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_, $key__) {
                                            $table->text('local_anexo')->nullable();
                                        });

                                        $nome_coluna_controller = new $nome_coluna_classname;
                                        $nome_coluna_controller->nome_coluna = 'local_anexo';
                                        $nome_coluna_controller->nome_na_tabela = 'local_anexo';
                                        $nome_coluna_controller->save();
                                    }

                                    $tamanho_text_field = 1000;
                                    if (!is_array($value__)) {
                                        $controller->{$key_corrigida_."_".$key__} = $value__;
                                    } else {
                                        $controller->{$key_corrigida_."_".$key__} = json_encode($value__);;
                                    }
                                    $debug = [
                                        'key',
                                        $key,
                                        'value',
                                        $value,
                                        'key_',
                                        $key_,
                                        'value_',
                                        $value_,
                                        'key__',
                                        $key__,
                                        'value__',
                                        $value__,
                                        'da_planilha',
                                        $da_planilha,
                                        'da_api',
                                        $da_api
                                    ];
                                }
                            }
                        }
                    }
                    else {
                        if (!is_string($value_)) {
                            foreach ($value_ as $key__ => $value__) {
                                $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($key);
                                $nome_coluna_controller =  new $nome_coluna_classname;

                                $key_corrigida_ = 'api_' . $this->apenasCaracteres($key) . '_' . $this->apenasCaracteres($key__);

                                if (is_array($da_api->$key[$key_])) {
                                    if (!is_array($da_api->$key[$key_][$key__])) {
                                        if (!Schema::hasColumn($key.'_processos', $key_corrigida_)) {
                                            Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_) {
                                                $table->text($key_corrigida_)->nullable();
                                            });

                                            $nome_coluna_controller = new $nome_coluna_classname;
                                            $nome_coluna_controller->nome_coluna = $key__;
                                            $nome_coluna_controller->nome_na_tabela = $key_corrigida_;
                                            $nome_coluna_controller->save();
                                        }
                                        $controller->{$key_corrigida_} = $value__;
                                    }
                                    else {
                                        $classes_internas_conhecidas = $this->tabelas;

                                        if (!in_array($key, $classes_internas_conhecidas)) {
                                            dd('alerta: array dentro do array ainda nao conhecido', $key, $value);
                                        }

                                        if (!Schema::hasColumn($key.'_processos', $key_corrigida_)) {
                                            Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_) {
                                                $table->text($key_corrigida_)->nullable();
                                            });

                                            $nome_coluna_controller = new $nome_coluna_classname;
                                            $nome_coluna_controller->nome_coluna = $key__;
                                            $nome_coluna_controller->nome_na_tabela = $key_corrigida_;
                                            $nome_coluna_controller->save();
                                        }
                                        $controller->{$key_corrigida_} = json_encode($value__);
                                    }
                                } else {
                                    if (!is_array($da_api->$key[$key_]->$key__)) {
                                        if (!Schema::hasColumn($key.'_processos', $key_corrigida_)) {
                                            Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_) {
                                                $table->text($key_corrigida_)->nullable();
                                            });

                                            $nome_coluna_controller = new $nome_coluna_classname;
                                            $nome_coluna_controller->nome_coluna = $key__;
                                            $nome_coluna_controller->nome_na_tabela = $key_corrigida_;
                                            $nome_coluna_controller->save();
                                        }
                                        $controller->{$key_corrigida_} = $value__;
                                    }
                                    else {
                                        $classes_internas_conhecidas = $this->tabelas;

                                        if (!in_array($key, $classes_internas_conhecidas)) {
                                            dd('alerta: array dentro do array ainda nao conhecido', $key, $value);
                                        }

                                        if (!Schema::hasColumn($key.'_processos', $key_corrigida_)) {
                                            Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_) {
                                                $table->text($key_corrigida_)->nullable();
                                            });

                                            $nome_coluna_controller = new $nome_coluna_classname;
                                            $nome_coluna_controller->nome_coluna = $key__;
                                            $nome_coluna_controller->nome_na_tabela = $key_corrigida_;
                                            $nome_coluna_controller->save();
                                        }
                                        $controller->{$key_corrigida_} = json_encode($value__);
                                    }
                                }
                            }
                        } else {
                            $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($key);
                            $nome_coluna_controller =  new $nome_coluna_classname;

                            $key_corrigida_ = 'api_' . $this->apenasCaracteres($key) . '_' . $this->apenasCaracteres($key_);

                            if (!Schema::hasColumn($key.'_processos', $key_corrigida_)) {
                                Schema::table($key.'_processos', function (Blueprint $table) use($key_corrigida_) {
                                    $table->text($key_corrigida_)->nullable();
                                });

                                $nome_coluna_controller = new $nome_coluna_classname;
                                $nome_coluna_controller->nome_coluna = $key_;
                                $nome_coluna_controller->nome_na_tabela = $key_corrigida_;
                                $nome_coluna_controller->save();
                            }
                            $controller->{$key_corrigida_} = $value_;
                        }
                    }

                    //partes
                    if ($key == 'partes') {
                        $partes = json_decode($controller->api_partes_9);
                        $pt = 9;
                        if (count($partes) > 0) {
                            foreach ($partes as $index_parte => $parte) {
                                foreach($parte as $key_parte => $value_parte) {
                                    $key_parte_ =
                                        'api_' .
                                        $this->apenasCaracteres($key) .
                                        '_' .
                                        $pt .
                                        '_parte_' .
                                        $this->apenasCaracteres($index_parte) .
                                        '_informacao_' .
                                        $this->apenasCaracteres($key_parte);

                                    if (!Schema::hasColumn($key.'_processos', $key_parte_)) {
                                        Schema::table($key.'_processos', function (Blueprint $table) use($key_parte_) {
                                            $table->text($key_parte_)->nullable();
                                        });

                                        $nome_coluna_controller = new $nome_coluna_classname;
                                        $nome_coluna_controller->nome_coluna = $key_;
                                        $nome_coluna_controller->nome_na_tabela = $key_parte_;
                                        $nome_coluna_controller->save();
                                    }
                                    $controller->{$key_parte_} = $value_parte;
                                }
                            }
                        }
                    }
                    $controller->processo = $num_processo;
                    $controller->save();
                }
            }
            $index++;
        }
        $processo->save();
    }

    private function salvarAnexo($num_processo, $index_anexo, $texto_anexo, $debug) {
        //todo: data referencia na hora de salvar anexo?
        $filename = '/anexos' . '/' . $num_processo . '/anexo_' .  $index_anexo . '.txt' ;
        $filename_full = public_path() . $filename;

        if (!file_exists(public_path() .'/anexos' .  '/' . $num_processo)) {
            mkdir(public_path() . '/anexos' . '/' . $num_processo, 0777, true);
        }

        $this->info('Salvando Anexo: ' . $filename_full);
        file_put_contents($filename_full, $texto_anexo);

        try {
            return $filename;
        } catch (\Exception $e) {
            $debug_ = [
                'num_processo',
                $num_processo,
                'index_anexo',
                $index_anexo,
                'texto_anexo',
                $texto_anexo,
                'filename',
                $filename,
                'filename_full',
                $filename_full,
                'debug',
                $debug
            ];
            $this->salvaDebug($debug_);
            $this->salvaExcecao($e);
            dd($debug_);
        }
    }

    private function exportaBancoDeDadosPraPlanilha($inicio_programa) {
        $modo = $this->argument('modo');
        $dropar = $this->argument('recriar');
        $backup = $this->argument('backup');

        if ($backup == 'sim') {
            $this->backupDasPlanilhas();
            $this->executaExportacaoBancoDeDadosPraPlanilha($inicio_programa);
        } else if ($dropar != 'nao') {
            $this->opcaoDeBackupNaoExiste();
        } else {
            $this->apagarPlanilhas();
            $this->executaExportacaoBancoDeDadosPraPlanilha($inicio_programa);
        }
    }

    private function backupDasPlanilhas() {
        //selecionar todas as planilhas
        //descobrir a data referencia (de onde: ?)
        //criar pasta de backup com a data referencia
        //mover backup das planilhas pra pasta
    }

    private function apagarPlanilhas () {
        //selecionar todas as planilhas
        //apagar as planilhas
    }

    private function executaExportacaoBancoDeDadosPraPlanilha($inicio_programa) {
        $inicio = Carbon::now();
        $modo = $this->argument('modo');
        $dropar = $this->argument('recriar');
        $backup = $this->argument('backup');

        $tabelas = ['processos'];

        foreach ($this->tabelas as $index_tabela => $tabela)
        {
            $tabelas[] = $tabela;
        }

        $processos = Processo::all();
        $nomes_colunas_processos = NomesColunasProcessos::all();
        $movs = Movs::all();
        $nomes_colunas_movs = NomesColunasMovs::all();
        $anexos = Anexos::all();
        $nomes_colunas_anexos = NomesColunasAnexos::all();
        $audiencias = Audiencias::all();
        $nomes_colunas_audiencias = NomesColunasAudiencias::all();
        $processosRelacionados = ProcessosRelacionados::all();
        $nomes_colunas_processosRelacionados = NomesColunasProcessosRelacionados::all();
        $customs = Customs::all();
        $nomes_colunas_customs = NomesColunasCustoms::all();
        $classes = Classes::all();
        $nomes_colunas_classes = NomesColunasClasses::all();
        $acessos = Acessos::all();
        $nomes_colunas_acessos = NomesColunasAcessos::all();
        $partes = Partes::all();
        $nomes_colunas_partes = NomesColunasPartes::all();

        $planilhas = [
            'processos' => $processos,
            'movs' => $movs,
            'anexos' => $anexos,
            'audiencias' => $audiencias,
            'processosRelacionados' => $processosRelacionados,
            'customs' => $customs,
            'classes' => $classes,
            'acessos' => $acessos,
            'partes' => $partes
        ];

        $this->info("Exportando dados para planilha...");
        $this->nomearColunasNaPlanilhaFinal();
        $this->ajustarColunasNaTabelaDeNomesColunas($planilhas);

        $index = 1;
        $total = count($planilhas);
        $index_total = 0;
        $total_de_registros_em_todas_planilhas = 0;

        foreach ($planilhas as $index_planilha => $planilha) {
            $total_de_registros_em_todas_planilhas += $planilha->count();
        }

        foreach ($planilhas as $index_planilha => $planilha) {
            $nome_planilha = ucfirst($index_planilha);
            $porcentagem = $this->calcularPorcentagem($index, $total);

            if (count($planilha) > 0) {
                $index_total += $this->gerarPlanilha($index_planilha, $planilha, $nome_planilha, $index, $total, $porcentagem, $inicio, $inicio_programa, $index_total, $total_de_registros_em_todas_planilhas);
            }

            $index++;
        }
    }

    private function ehColunaImportante($coluna) {
        $nao_importantes = [
            'created_at',
            'deleted_at',
            'updated_at'
        ];

        if (in_array($coluna, $nao_importantes)) {
            return false;
        }
        return true;
    }

    private function ajustarNomesDasColunas($index_planilha, $planilha, $columns, $item, $nome_planilha, $_index, $_total, $_porcentagem) {
        $colunas[] = [];
        $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($index_planilha);

        foreach ($columns as $key => $value) {
            $nome_coluna_controller_ = $nome_coluna_classname::all();
            $nome_coluna_controller =  $nome_coluna_controller_->where('nome_na_tabela', $value)->last();

            try {
                $value = $nome_coluna_controller->nome_coluna;
            } catch (\Exception $e) {
                $debug = [
                    'index_planilha',
                    $index_planilha,
                    'planilha',
                    $planilha,
                    'colunas',
                    $colunas,
                    'columns',
                    $columns,
                    'e',
                    $e,
                    'key',
                    $key,
                    'value',
                    $value,
                    'nome_coluna_controller',
                    $nome_coluna_controller,
                    'nome_coluna_controller_',
                    $nome_coluna_controller_,
                    'nome_coluna_classname',
                    $nome_coluna_classname,
                    'item',
                    $item,
                    'nome_planilha',
                    $nome_planilha,
                    '_index',
                    $_index,
                    '_total',
                    $_total,
                    '_porcentagem',
                    $_porcentagem
                ];
                $this->salvaExcecao($e);
                $this->salvaDebug($debug);
                dd($debug);
            }
            $value = str_replace("api_", "", $value);
            $value = str_replace("planilha_", "", $value);
            if ($this->ehColunaImportante($value)) {
                $colunas[] = $value;
            }
        }

        return $colunas;
    }

    private function ajustarColunasNaTabelaDeNomesColunas($planilhas) {
        $colunas_para_adicionar = [
            'id',
            'processo',
            'created_at',
            'deleted_at',
            'updated_at'
        ];

        foreach ($planilhas as $index_planilha => $planilha) {
            foreach ($colunas_para_adicionar as $index_coluna => $coluna) {
                $nome_coluna_classname = 'App\\Models\\NomesColunas' . $this->primeiraLetraMaiuscula($index_planilha);
                $nome_tabela = 'nomes_colunas_' . $index_planilha;

                if (!Schema::hasColumn($nome_tabela, $coluna)) {
                    Schema::table($nome_tabela, function (Blueprint $table) use($coluna) {
                        $table->text($coluna)->nullable();
                    });
                }

                $nome_coluna_controller_ = $nome_coluna_classname::all();
                $nome_coluna_controller =  $nome_coluna_controller_->where('nome_na_tabela', $coluna)->last();

                if (!$nome_coluna_controller) {
                    $nome_coluna_controller = new $nome_coluna_classname;

                    $nome_coluna_controller->nome_coluna = $coluna;
                    $nome_coluna_controller->nome_na_tabela = $coluna;
                    $nome_coluna_controller->save();
                }
            }
        }
    }

    private function gerarPlanilha($index_planilha, $planilha, $nome_planilha, $_index, $_total, $_porcentagem, $inicio, $inicio_programa, $index_total, $total_maximo) {
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=file.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $item = $planilha->first();
        $columns = array_keys($item->getOriginal());
        $columns = $this->ajustarNomesDasColunas($index_planilha, $planilha, $columns, $item, $nome_planilha, $_index, $_total, $_porcentagem);

        $filename = '/planilhas' . '/' .  $index_planilha . '.csv';
        $filename_full = public_path() . $filename;

        if (!file_exists(public_path() . '/planilhas')) {
            mkdir(public_path() . '/planilhas', 0777, true);
        }

        $file = fopen($filename_full, 'w');
        unset($columns[0]);
        unset($columns['created_at']);
        unset($columns['deleted_at']);
        unset($columns['updated_at']);
        fputcsv($file, $columns, ';');

        $index = 1;
        $total = $planilha->count();
        foreach($planilha as $item_) {
            $colunas[] = [];
            foreach ($item_->getOriginal() as $key => $value) {
                $colunas[$key] = $value;
            }
            unset($colunas[0]);
            unset($colunas['created_at']);
            unset($colunas['deleted_at']);
            unset($colunas['updated_at']);
            $colunas = array_values($colunas);
            $colunas = $this->arrayPraObject($colunas);
            $item__ = (array) $item_->getOriginal();
            unset($item__[0]);
            unset($item__['created_at']);
            unset($item__['deleted_at']);
            unset($item__['updated_at']);
            $item__ = array_values($item__);
            $item__ = $this->arrayPraObject($item__);

            fputcsv($file, $item__, ';');

            $index_agora = $index_total + $index;
            $porcentagem = $this->calcularPorcentagem($index, $total);
            $porcentagem_ = $this->calcularPorcentagem($index_agora, $total_maximo);
            $this->info("------------------------------------------------------------------------");
            $this->info("Gerando dados da planilha de {$nome_planilha} [{$_index} de {$_total}] [{$_porcentagem}%]...");
            $this->info("Exportando registro {$index} de {$total} [{$porcentagem}%] da planilha de {$nome_planilha}");
            $this->info("Exportando registro {$index_agora} de {$total_maximo} [{$porcentagem_}%] de todos os registros");
            $this->info("Tempo decorrido até agora da geracao das planilhas: {$this->calculaTempo($inicio, Carbon::now())}");
            $this->info("Tempo decorrido até agora da execucao do programa: {$this->calculaTempo($inicio_programa, Carbon::now())}");
            $index++;
        }
        fclose($file);
        return $index;
    }

    private function nomearColunasNaPlanilhaFinal() {
        //todo: preencher os campos nome_final_na_planilha de todas as tabelas
    }

    private function finalizarJob() {
        $this->atualizarTabelasDeSistemaAposRodarComSucesso();
        $this->info("Planilhas geradas com sucesso!");

        //todo: modo de backup ou de apagar planilhas anteriores?
        //todo: modo de salvar as tabelas antigas em backup no banco?
        //todo: remover TODOs:?
        //todo: remover dds > acabou?
    }
}
