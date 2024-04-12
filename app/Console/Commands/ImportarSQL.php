<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use File;
use Rap2hpoutre\FastExcel\FastExcel;
use Smalot\PdfParser\Parser;
//use Spatie\PdfToText\Pdf;
use Gufy\PdfToHtml\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Database\Migrations\Migration;
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
use App\Models\User;
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

class ImportarSQL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importarsql {recriar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $recriar = $this->argument('recriar');

        if ($recriar == "recriar") {
            $this->DroparTabelas();
        } else if ($recriar != "nao_recriar") {
            dd("ARGUMENTO INCORRETO: ARGUMENTOS ACEITOS SÃO AS OPÇÔES 'recriar OU 'nao_recriar' PARA RECRIAR OU NAO A BASE DE DADOS DURANTE A EXECUÇÃO DO JOB!");
        }

        $this->info("Importar dados do banco MySQL para o banco PostGreSQL...");

        $todos_processos = Processo::all();
        $todos_movs = Movs::all();
        $todos_anexos = Anexos::all();
        $todos_audiencias = Audiencias::all();
        $todos_processosRelacionados = ProcessosRelacionados::all();
        $todos_customs = Customs::all();
        $todos_classes = Classes::all();
        $todos_acessos = Acessos::all();
        $todos_partes = Partes::all();
        $todos_tribunais = Tribunais::all();
        $todos_users = User::all();

        $todos_tb_advogado = TB_Advogado::all();
        $todos_tb_defensoria = TB_Defensoria::all();
        $todos_tb_glossario = TB_Glossario::all();        
        $todos_tb_localidade = TB_Localidade::all();
        $todos_tb_parte = TB_Parte::all();
        $todos_tb_participante = TB_Participante::all();
        $todos_tb_processo = TB_Processo::all();
        $todos_tb_procurador = TB_Procurador::all();
        $todos_tb_quilombo = TB_Quilombo::all();
        $todos_tb_repositorio = TB_Repositorio::all();
        $todos_tb_usuario = TB_Usuario::all();

        $planilhas_pgsql = [
            'tb_advogado' => $todos_tb_advogado,
            'tb_defensoria' => $todos_tb_defensoria,
            'tb_glossario' => $todos_tb_glossario,
            'tb_localidade' => $todos_tb_localidade,
            'tb_parte' => $todos_tb_parte,
            'tb_participante' => $todos_tb_participante,
            'tb_processo' => $todos_tb_processo,
            'tb_procurador' => $todos_tb_procurador,
            'tb_quilombo' => $todos_tb_quilombo,
            'tb_repositorio' => $todos_tb_repositorio,
            'tb_usuario' => $todos_tb_usuario,
            'count_tb_advogado' => count($todos_tb_advogado),
            'count_tb_defensoria' => count($todos_tb_defensoria),
            'count_tb_glossario' => count($todos_tb_glossario),
            'count_tb_localidade' => count($todos_tb_localidade),
            'count_tb_parte' => count($todos_tb_parte),
            'count_tb_participante' => count($todos_tb_participante),
            'count_tb_processo' => count($todos_tb_processo),
            'count_tb_procurador' => count($todos_tb_procurador),
            'count_tb_quilombo' => count($todos_tb_quilombo),
            'count_tb_repositorio' => count($todos_tb_repositorio),
            'count_tb_usuario' => count($todos_tb_usuario)
        ];

        $planilhas_mysql = [
            'processos' => $todos_processos,
            'movs' => $todos_movs,
            'anexos' => $todos_anexos,
            'audiencias' => $todos_audiencias,
            'processosRelacionados' => $todos_processosRelacionados,
            'customs' => $todos_customs,
            'classes' => $todos_classes,
            'acessos' => $todos_acessos,
            'partes' => $todos_partes,
            'tribunais' => $todos_tribunais,
            'users' => $todos_users,
            'count_processos' => count($todos_processos),
            'count_movs' => count($todos_movs),
            'count_anexos' => count($todos_anexos),
            'count_audiencias' => count($todos_audiencias),
            'count_processosRelacionados' => count($todos_processosRelacionados),
            'count_customs' => count($todos_customs),
            'count_classes' => count($todos_classes),
            'count_acessos' => count($todos_acessos),
            'count_partes' => count($todos_partes),
            'count_tribunais' => count($todos_tribunais),
            'count_users' => count($todos_users)
        ];

        $planilhas = [
            'mysql' => $planilhas_mysql,
            'pgsql' => $planilhas_pgsql
        ];

        $processos_filtrados = $this->carregarProcessosFiltrados();

        $quant_processos = $todos_processos->count();
        $i = 1;
        foreach ($todos_processos as $index_processo => $processo) {
            $num_processo = $processo->planilha_Processo;
            $num_processo_array = explode('.', $processo->planilha_Processo);
            $codigo_estruturado = $num_processo_array[2];
            $codigo_estruturado .= '.';
            $codigo_estruturado .= $num_processo_array[3];
            $codigo_estruturado .= '.';
            $codigo_estruturado .= $num_processo_array[4];

            $movs_do_processo = Movs::where('processo', $processo->planilha_Processo)->get();
            $anexos_do_processo = Anexos::where('processo', $processo->planilha_Processo)->get();
            $audiencias_do_processo = Audiencias::where('processo', $processo->planilha_Processo)->get();
            $processosRelacionados_do_processo = ProcessosRelacionados::where('processo', $processo->planilha_Processo)->get();
            $customs_do_processo = Customs::where('processo', $processo->planilha_Processo)->get();
            $classes_do_processo = Classes::where('processo', $processo->planilha_Processo)->get();
            $acessos_do_processo = Acessos::where('processo', $processo->planilha_Processo)->get();
            $partes_do_processo = Partes::where('processo', $processo->planilha_Processo)->get();
            $tribunais_do_processo = Tribunais::where('codigo_estruturado', $codigo_estruturado)->get();

            $dados_processo = [
                'movs_do_processo' => $movs_do_processo,
                'anexos_do_processo' => $anexos_do_processo,
                'audiencias_do_processo' => $audiencias_do_processo,
                'processosRelacionados_do_processo' => $processosRelacionados_do_processo,
                'customs_do_processo' => $customs_do_processo,
                'classes_do_processo' => $classes_do_processo,
                'acessos_do_processo' => $acessos_do_processo,
                'partes_do_processo' => $partes_do_processo,
                'tribunais_do_processo' => $tribunais_do_processo,
                'count_movs_do_processo' => count($movs_do_processo),
                'count_anexos_do_processo' => count($anexos_do_processo),
                'count_audiencias_do_processo' => count($audiencias_do_processo),
                'count_processosRelacionados_do_processo' => count($processosRelacionados_do_processo),
                'count_customs_do_processo' => count($customs_do_processo),
                'count_classes_do_processo' => count($classes_do_processo),
                'count_acessos_do_processo' => count($acessos_do_processo),
                'count_partes_do_processo' => count($partes_do_processo),
                'count_tribunais_do_processo' => count($tribunais_do_processo)
            ];

            $this->info($i . " de " . $quant_processos . " processos importados...");

            if (in_array($num_processo, $processos_filtrados)) {
                $this->importarProcessoParaPGSQL($processo, $dados_processo, $num_processo_array);
            }
            $i++;
        }
    }

    private function carregarProcessosFiltrados()
    {
        $filtrados = [];
        $importados = $this->ImportarCSV();
        
        foreach ($importados as $importado) {
            $filtrados[] = $importado['Processo'];
        }

        return $filtrados;
    }

    private function DroparTabelas()
    {
        $this->info('Foi selecionado para recriar a base de dados de tribunais...');
        $this->info('Limpando a base dados de tribunais conforme configuração de entrada...');
        sleep(5);

        TB_Advogado::truncate();
        TB_Defensoria::truncate();
        TB_Glossario::truncate();  
        TB_Localidade::truncate();
        TB_Parte::truncate();
        TB_Participante::truncate();
        TB_Processo::truncate();
        TB_Procurador::truncate();
        TB_Quilombo::truncate();
        TB_Repositorio::truncate();
        TB_Usuario::truncate();
    }

    private function ImportarCSV()
    {
        $pos_util = 1;
        $input_file = public_path() . "\\input_planilhas\\processos_filtrados.csv";

        $file = fopen($input_file, 'r');
        $header = fgetcsv($file, 0, ',');

        $i = 0;
        $j = 1;
        foreach ($header as $key => $column) {
            if ($column == '') {
                $k = $i + 1;
                $header[$i] = $key;
                $j++;
            }
            $i++;
        }

        $rows = [];
        while ($row = fgetcsv($file, 0, ',')) {
            $rows[] = array_combine($header, $row);
        }

        $rows = array_slice($rows, $pos_util);
        fclose($file);

        return $rows;
    }

    private function importarProcessoParaPGSQL($input_processo, $dados_processo, $num_processo_array)
    {
        //adicionar partes        
        foreach ($dados_processo['partes_do_processo'] as $parte) {
            $tipo_parte = $parte->api_partes_8;
            
            //PARTICIPANTE:
            //advogado?
            //procurador?
            //defensor?
            //parte?

            //papel:
            //autor
            //reu
            //terceiro interessado

            //categoria:
            //advogado
            //procurador
            //defensor
        }

        //adicionar quilombo
        //se precisar
        $todos_quilombos = TB_Quilombo::all();
        $nome_quilombo = trim(explode('(', $input_processo->planilha_Nomepartemonitorado)[0]);
        $quilombo = $todos_quilombos->where('nome', '=', $nome_quilombo)->first();

        if (!$quilombo) {
            //quilombo nao existe
            $quilombo = new TB_Quilombo();

            $quilombo->nome = $nome_quilombo;
            $quilombo->associacao = $nome_quilombo;
            $quilombo->latitude = 0;
            $quilombo->longitude = 0;
            $quilombo->area = 0;

            $quilombo->save();    
        }

        //adicionar processo
        $processo = new TB_Processo();

        if ($num_processo_array[2] == '8') {
            $competencia = 'Estadual';
        } else if ($num_processo_array[2] == '4') {
            $competencia = 'Federal';
        } else {
            $competencia = 'Outros';
        }

        $processo->numero = $input_processo->planilha_Processo;
        $processo->competencia = $competencia;
        $processo->jurisdicao = $input_processo->planilha_Tribunal;
        $processo->comarca = $input_processo->planilha_ComarcaCNJ;
        $processo->foro = $input_processo->planilha_ForoCNJ;
        $processo->vara = $input_processo->planilha_OrgaoJulgador;

        $processo->quilombo = $input_processo->planilha_Nomepartemonitorado;

        $processo->localidade_id = 0;
        $processo->usuario_id = 1;
        $processo->quilombo_id = $quilombo->id;

        $processo->save();
    }
}
