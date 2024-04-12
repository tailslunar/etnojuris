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
use App\Models\Tribunais;

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

use App\Models\TB_Advogados;
use App\Models\TB_Defensoria;
use App\Models\TB_Glossarios;
use App\Models\TB_Localidade;
use App\Models\TB_Parte;
use App\Models\TB_Participante;
use App\Models\TB_Processo;
use App\Models\TB_Procurador;
use App\Models\TB_Quilombo;
use App\Models\TB_Repositorio;
use App\Models\TB_Usuarios;

class DadosTJs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dados-tjs {recriar} {importar}';

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

    private $estados_arquivos = [
        "0" => ["Acre", "acre_numeracao_unica_codigos_tjac.pdf", "AC"],
        "1" => ["Alagoas", [
            "alagoas_1.pdf",
            "alagoas_2.pdf",
            "alagoas_3.pdf",
        ], "AL"],
        "2" => ["Amapá", "amapa_cdigos_tjap.csv", "AP"],
        "3" => ["Amazonas", [
            "am_1.pdf",
            "am_2.pdf",
            "am_3.pdf",
            "am_4.pdf",
        ], "AM"],
        "4" => ["Bahia", "bahia_tabela_de_cdigos_de_origem_tjba.csv", "BA"],
        "5" => ["Ceará", [
            "ceara_Numeracao_unica_codigos_TJCE_1.pdf",
            "ceara_Numeracao_unica_codigos_TJCE_2.pdf",
            "ceara_Numeracao_unica_codigos_TJCE_3.pdf",
            "ceara_Numeracao_unica_codigos_TJCE_4.pdf",
            "ceara_Numeracao_unica_codigos_TJCE_5.pdf",
        ], "CE"],
        "6" => ["Distrito Federal", "df_tjdft_cdigos.pdf", "DF"],
        "7" => ["Espírito Santo", "es_tjes.pdf", "ES"],
        "8" => ["Goiás", "goias_tjgo_codigosunidadesjudiciarias.txt", "GO"],
        "9" => ["Maranhão", [
            "maranhao_1.pdf",
            "maranhao_2.pdf",
            "maranhao_3.pdf",
        ], "MA"],
        "10" => ["Mato Grosso", [
            "mt_1.pdf",
            "mt_2.pdf",
        ], "MT"],
        "11" => ["Mato Grosso do Sul", [
            "ms_1.pdf",
            "ms_2.pdf",
            "ms_3.pdf",
            "ms_4.pdf",
        ], "MS"],
        "12" => ["Minas Gerais", [
            "mg_1.pdf",
            "mg_2.pdf",
            "mg_3.pdf",
        ], "MG"],
        "13" => ["Pará", [
            "para_1.pdf",
            "para_2.pdf",
            "para_3.pdf",
            "para_4.pdf",
            "para_5.pdf",
            "para_6.pdf",
            "para_7.pdf",
            "para_8.pdf",
        ], "PA"],
        "14" => ["Paraíba", "", "PB"],
        "15" => ["Paraná", "", "PR"],
        "16" => ["Pernambuco", "", "PE"],
        "17" => ["Piauí", "", "PI"],
        "18" => ["Rio de Janeiro", "", "RJ"],
        "19" => ["Rio Grande do Norte", "", "RN"],
        "20" => ["Rio Grande do Sul", "", "RS"],
        "21" => ["Rondônia", "", "RO"],
        "22" => ["Roraima", "", "RR"],
        "23" => ["Santa Catarina", "", "SC"],
        "24" => ["São Paulo", "", "SP"],
        "25" => ["Sergipe", "", "SE"],
        "26" => ["Tocantins", "", "TO"],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $importar_ = $this->argument('importar');

        if ($importar_ == "importar") {
            $this->handleImportarMySQLParaPostGreSQL();
        } else if ($importar_ != "nao_importar") {
            dd("ARGUMENTO INCORRETO: ARGUMENTOS ACEITOS SÃO AS OPÇÔES 'importar OU 'nao_importar' PARA IMPORTAR OU NAO A BASE DE DADOS DO MYSQL PARA O POSTGRES APÓS A EXECUÇÃO DO JOB!");
        }

        $this->handleDadosTJs();
    }

    private function handleDadosTJs()
    {
        ini_set('memory_limit', '20384M');
        $recriar = $this->argument('recriar');
        $importar_ = $this->argument('importar');
        $files = File::files(public_path() . "\\input_planilhas\\tjs\\");

        if ($recriar == "recriar") {
            $this->DroparTabelas();
        } else if ($recriar != "nao_recriar") {
            dd("ARGUMENTO INCORRETO: ARGUMENTOS ACEITOS SÃO AS OPÇÔES 'recriar OU 'nao_recriar' PARA RECRIAR OU NAO A BASE DE DADOS DURANTE A EXECUÇÃO DO JOB!");
        }

        foreach ($this->estados_arquivos as $index => $dados_estado) {
            $arquivos = [];
            if (is_array($dados_estado[1])) {
                foreach ($dados_estado[1] as $index_arquivo => $nome_arquivo) {
                    $arquivos[] = $nome_arquivo;
                }
            } else {
                $arquivos[] = $dados_estado[1];
            }

            foreach ($arquivos as $index_arquivo => $arquivo) {
                $estado = strtolower($dados_estado[0]);
                $this->info("Carregando dados do estado: " . $estado);
                $estado = strtolower($estado);
                $estado_sigla = strtoupper($dados_estado[2]);
                $file = public_path() . "\\input_planilhas\\tjs\\" . strtolower($arquivo);

                $fileName = explode(".", str_replace(public_path(), "", $file))[0] ?? "";
                $fileShortName = $arquivo;
                $fileExtension = explode(".", str_replace(public_path(), "", $file))[1] ?? "";

                $input = [
                    'index' => $index,
                    'index_arquivo' => $index_arquivo,
                    'estado' => $estado,
                    'estado_sigla' => $estado_sigla,
                    'file' => $file,
                    'fileName' => $fileName,
                    'fileShortName' => $fileShortName,
                    'fileExtension' => $fileExtension
                ];

                if ($fileExtension == "csv") {
                    $this->ImportarCSV($input);
                } else if ($fileExtension == "xlsx") {
                    $this->ImportarXLS($input);
                } else if ($fileExtension == "pdf") {
                    $this->ImportarPDF($input);
                } else if ($fileExtension == "doc") {
                    $this->ImportarDOC($input);
                } else if ($fileExtension == "txt") {
                    $this->ImportarTXT($input);
                } else {
                    $this->error("Formato do arquivo '". $fileName . $fileExtension ."' não reconhecido.");
                }
            }
        }

        $this->AjustesFinais();

        $this->info("Execução concluída com sucesso!");
        $this->info("Obrigado por utilizar! Criado por Rodrigo Barbosa Sousa Orrego: rodrigobsorrego@gmail.com");
    }

    private function DroparTabelas()
    {
        $this->info('Foi selecionado para recriar a base de dados de tribunais...');
        $this->info('Limpando a base dados de tribunais conforme configuração de entrada...');
        Schema::dropIfExists('tribunais');
    }

    private function ReplaceEspacoPorUnderline($string)
    {
        return str_replace(" ", "_", $string);
    }

    private function ImportarDOC($input)
    {
        $this->info("Importando arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ."...");

        $source = $input['file'];
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($source, 'MsDoc');

        dd($phpWord);
    }

    private function ImportarCSV($input)
    {
        $pos_util = 1;

        $this->info("Importando arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ."...");
        $file = fopen($input['file'], 'r');
        $header = fgetcsv($file, 0, ';');

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
        while ($row = fgetcsv($file, 0, ';')) {
            $rows[] = array_combine($header, $row);
        }

        $rows = array_slice($rows, $pos_util);
        fclose($file);

        $this->ImportarPorEstado($input, $rows);
    }

    private function ImportarXLS($input)
    {
        $pos_util = 1;

        $this->info("Importando arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ."...");
        $file = fopen($input['file'], 'r');
        $header = fgetcsv($file, 0, ';');

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
        while ($row = fgetcsv($file, 0, ';')) {
            $rows[] = array_combine($header, $row);
        }

        $rows = array_slice($rows, $pos_util);
        fclose($file);

        dd($rows);
        $this->ImportarPorEstado($input, $rows);
    }

    private function ImportarPDF($input)
    {
        $this->info("Importando arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ."...");

        $pdfParser = new Parser();
        $pdf = $pdfParser->parseFile($input['file']);
        $content = $pdf->getText();

        $this->ImportarPorEstado($input, $content);
    }

    private function ImportarTXT($input)
    {
        $this->info("Importando arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ."...");

        $content = file_get_contents($input['file']);
        $this->ImportarPorEstado($input, $content);
    }

    private function ImportarPorEstado($input, $content)
    {
        $estado = $input['estado'];

        if ($estado == 'acre') {
            $this->ImportarAcre($input, $content); //pdf
        }
        if ($estado == 'alagoas') {
            $this->ImportarAlagoas($input, $content);
        }
        if ($estado == 'amapá') {
            $this->ImportarAmapa($input, $content); //csv
        }
        if ($estado == 'amazonas') {
            $this->ImportarAmazonas($input, $content); //pdf
        }
        if ($estado == 'bahia') {
            $this->ImportarBahia($input, $content); //csv
        }
        if ($estado == 'ceará') {
            if ($input['index_arquivo'] == 0) {
                $this->ImportarCearaModelo2($input, $content); //pdf
            } else {
                $this->ImportarCeara($input, $content); //pdf
            }
        }
        if ($estado == 'distrito federal') {
            $this->ImportarDistritoFederal($input, $content); //pdf
        }
        if ($estado == 'espírito santo') {
            $this->ImportarEspiritoSanto($input, $content); //pdf
        }
        if ($estado == 'goiás') {
            $this->ImportarGoias($input, $content); //txt
        }
        if ($estado == 'maranhão') {
            $this->ImportarMaranhao($input, $content); //pdf
        }
        if ($estado == 'mato grosso') {
            $this->ImportarMatoGrosso($input, $content); //pdf
        }
        if ($estado == 'mato grosso do sul') {
            $this->ImportarMatoGrossoDoSul($input, $content); //pdf
        }
        if ($estado == 'minas gerais') {
            $this->ImportarMinasGerais($input, $content);
        }
        if ($estado == 'pará') {
            $this->ImportarPara($input, $content);
        }
        if ($estado == 'paraiba') {
            $this->ImportarParaiba($input, $content);
        }
    }

    private function FormatarConteudoCampo($conteudo, $tipo_conteudo)
    {
        if ($tipo_conteudo == 'codigo_estado' || $tipo_conteudo == 1) {
            return str_pad($conteudo, 2, "0", STR_PAD_LEFT);
        }
        if ($tipo_conteudo == 'codigo_unidade' || $tipo_conteudo == 2) {
            return str_pad($conteudo, 4, "0", STR_PAD_LEFT);
        }
        if ($tipo_conteudo == 'codigo_estruturado') {
            if (!is_array($conteudo)) {
                if (substr_count($conteudo, '.') == 2) {
                    $conteudo_explode = explode('.', $conteudo);
                    $ret = $conteudo_explode[0] . '.';
                    $ret .= str_pad($conteudo_explode[1], 2, "0", STR_PAD_LEFT) . '.';
                    $ret .= str_pad($conteudo_explode[2], 4, "0", STR_PAD_LEFT);
                    return $ret;
                }
            }
        }
        return $conteudo;
    }

    private function ImportarAcre($input, $content)
    {
        $pos_util = 89;

        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Codigo Comarca",
            "Codigo Vara",
            "Nome Unidade",
            "Nome Comarca",
            "Nome Vara",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content = array_slice($content, $pos_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        foreach ($content as $key => $value) {
            if ($value != "") {
                $comarca[] = $value;
            } else {
                $comarcas[] = $comarca;
                $comarca = [];
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo)));
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($dados_comarca[$ultimo]);

            $linhas[] = $linha;
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas, $colunas_formatado);
    }

    private function ImportarAlagoas($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Array de Arquivos
        $pos_util = 0;
        $pos_util_fim = 0;
        $length_util = ($pos_util_fim - $pos_util) + 1;
        if ($input['index_arquivo'] == 0) {
            $pos_util = 51;
            $pos_util_fim = 282;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 0;
            $pos_util_fim = 358;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 0;
            $pos_util_fim = 34;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 3) {
            dd('existe?');
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];
        $comarcas__ = [];
        $comarca__ = [];

        if ($input['index_arquivo'] < 4) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        if ($input['index_arquivo'] < 4) {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content__[] = $value;
                }
            }

            foreach ($content__ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca__[] = $value;
                } else {
                    if (count($comarca__) > 0) {
                        $comarcas__[] = $comarca__;
                    }
                    $comarca__ = [];
                    $comarca__[] = $value;
                }
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] == 3) {
            dd(
                '$comarcas',
                $comarcas,
                '$comarcas__',
                $comarcas__
            );
        }

        if ($input['index_arquivo'] == 1) {
            $comarcas = $comarcas__;
        }

        //apenas para debug
        if ($input['index_arquivo'] == 0) {
            unset($comarcas[0][6]);
            unset($comarcas[0][7]);
            unset($comarcas[0][8]);
            $comarcas[0] = array_merge($comarcas[0], ["do Estado de Alagoas", "Tribunal", "8.02.0000"]);

            $comarcas[3][1] = "02";
            unset($comarcas[5][1]);
            $comarcas[6][0] = "8";
            $comarcas[6][1] = "02";
            unset($comarcas[6][6]);

            $comarcas[7][0] = "8";
            $comarcas[7][1] = "02";
            $comarcas[7][2] = "0005";
            unset($comarcas[7][6]);
            unset($comarcas[7][7]);
            unset($comarcas[7][8]);
            $comarcas[7] = array_merge($comarcas[7], ["Campos", "Comarca", "8.02.0005"]);

            unset($comarcas[1]);
            unset($comarcas[2]);
            unset($comarcas[8]);

            $comarcas[13][4] = "do Ipanema";
            unset($comarcas[14]);

            $comarcas[15][2] = "0011";
            $comarcas[16][6] = "Comarca";
            $comarcas[17][2] = "0013";
            $comarcas[18][1] = "02";
            $comarcas[21][2] = "0017";

            $comarcas[22][2] = "0018";
            $comarcas[22][5] = "do Quitunde";
            unset($comarcas[22][7]);

            $comarcas[24][2] = "0019";
            unset($comarcas[23]);
            unset($comarcas[24][7]);

            $comarcas[29][1] = "02";
            unset($comarcas[25][2]);
            unset($comarcas[30][6]);

            //apenas para debug
            //dd($comarcas);

            $comarcas = array_values($comarcas);

            $i = 0;
            foreach($comarcas as $comarca) {
                $comarcas[$i] = array_values($comarca);
                $i++;
            }

            //apenas para debug
            //dd($comarcas);
        }

        if ($input['index_arquivo'] == 1) {
            unset($comarcas[0]);
            unset($comarcas[1]);
            $comarcas[2][4] = "Flores";
            unset($comarcas[2][7]);
            unset($comarcas[2][8]);
            unset($comarcas[2][9]);
            $comarcas[2] = array_merge(["8", "02"], $comarcas[2]);

            unset($comarcas[3]);
            $comarcas[4][0] = "0027";
            $comarcas[4][4] = "Norte";
            $comarcas[4][5] = "Comarca";
            $comarcas[4][6] = "8.02.0027";
            $comarcas[4] = array_merge(["8", "02"], $comarcas[4]);

            unset($comarcas[5]);
            unset($comarcas[6]);
            $comarcas[7][3] = "Leopoldina";
            $comarcas[7][4] = "Comarca";
            $comarcas[7][5] = "8.02.0028";
            $comarcas[7] = array_merge(["8", "02"], $comarcas[7]);

            unset($comarcas[8]);
            unset($comarcas[9]);
            unset($comarcas[11]);
            unset($comarcas[12]);
            unset($comarcas[14]);
            unset($comarcas[15]);
            $comarcas[10] = array_merge(["8", "02"], $comarcas[10]);
            $comarcas[13] = array_merge(["8", "02"], $comarcas[13]);

            unset($comarcas[16][0]);
            unset($comarcas[16][1]);
            unset($comarcas[16][5]);
            unset($comarcas[17]);
            unset($comarcas[18]);
            $comarcas[16] = array_merge(["8", "02", "0031", "São "], $comarcas[16]);

            unset($comarcas[19][0]);
            unset($comarcas[19][6]);
            unset($comarcas[20]);
            unset($comarcas[21]);
            unset($comarcas[23]);
            unset($comarcas[24]);
            $comarcas[19] = array_merge(["8", "02", "0032"], $comarcas[19]);
            $comarcas[22] = array_merge(["8", "02"], $comarcas[22]);
            $comarcas[19][5] = "do Colégio";
            unset($comarcas[19][8]);

            $comarcas[25][0] = "0034";
            $comarcas[27][0] = "0035";
            $comarcas[31][1] = "0037";
            $comarcas[31][5] = "8.02.0037";
            $comarcas[25] = array_merge(["8", "02"], $comarcas[25]);
            $comarcas[27] = array_merge(["8", "02"], $comarcas[27]);
            $comarcas[30] = array_merge(["8", "02"], $comarcas[30]);
            $comarcas[31] = array_merge(["8"], $comarcas[31]);
            unset($comarcas[26]);
            unset($comarcas[28]);
            unset($comarcas[29]);
            unset($comarcas[32]);
            unset($comarcas[34]);
            unset($comarcas[35]);
            unset($comarcas[37]);
            unset($comarcas[38]);
            unset($comarcas[40]);
            unset($comarcas[30][6]);
            unset($comarcas[31][5]);

            $comarcas[33][0] = "0038";
            $comarcas[39][0] = "0040";
            $comarcas[33] = array_merge(["8", "02"], $comarcas[33]);
            $comarcas[36] = array_merge(["8", "02"], $comarcas[36]);
            $comarcas[39] = array_merge(["8", "02"], $comarcas[39]);
            $comarcas[41] = array_merge(["8", "02"], $comarcas[41]);
            unset($comarcas[41][6]);

            $comarcas[42][1] = "0042";
            $comarcas[42] = array_merge(["8"], $comarcas[42]);
            $comarcas[45] = array_merge(["8", "02"], $comarcas[45]);
            $comarcas[48] = array_merge(["8", "02"], $comarcas[48]);
            $comarcas[51] = array_merge(["8", "02"], $comarcas[51]);
            $comarcas[54] = array_merge(["8", "02"], $comarcas[54]);
            $comarcas[57] = array_merge(["8", "02"], $comarcas[57]);
            unset($comarcas[51][6]);
            unset($comarcas[43]);
            unset($comarcas[44]);
            unset($comarcas[46]);
            unset($comarcas[47]);
            unset($comarcas[49]);
            unset($comarcas[50]);
            unset($comarcas[52]);
            unset($comarcas[53]);
            unset($comarcas[55]);
            unset($comarcas[56]);
            unset($comarcas[58]);
            unset($comarcas[60]);
            unset($comarcas[61]);
            unset($comarcas[63]);
            unset($comarcas[65]);
            unset($comarcas[66]);
            unset($comarcas[68]);
            unset($comarcas[69]);
            unset($comarcas[71]);
            unset($comarcas[72]);

            unset($comarcas[59][0]);
            unset($comarcas[59][1]);
            unset($comarcas[59][6]);
            $comarcas[59] = array_merge(["8", "02", "0048"], $comarcas[59]);
            $comarcas[59][5] = "Camaragibe";
            $comarcas[59][6] = "Comarca";
            $comarcas[59][7] = "8.02.0048";

            unset($comarcas[62][5]);
            $comarcas[62] = array_merge(["8", "02"], $comarcas[62]);
            $comarcas[64] = array_merge(["8", "02"], $comarcas[64]);
            $comarcas[67] = array_merge(["8", "02"], $comarcas[67]);
            $comarcas[70] = array_merge(["8", "02"], $comarcas[70]);
            unset($comarcas[74]);
            unset($comarcas[75]);
            unset($comarcas[77]);
            unset($comarcas[78]);
            unset($comarcas[80]);
            unset($comarcas[81]);
            unset($comarcas[83]);

            $comarcas[73][0] = "0053";
            $comarcas[79][0] = "0055";
            unset($comarcas[76][4]);
            $comarcas[73] = array_merge(["8", "02"], $comarcas[73]);
            $comarcas[76] = array_merge(["8", "02"], $comarcas[76]);
            $comarcas[79] = array_merge(["8", "02"], $comarcas[79]);
            $comarcas[82] = array_merge(["8", "02"], $comarcas[82]);

            //apenas para debug
            //dd($comarcas);

            $comarcas = array_values($comarcas);

            $i = 0;
            foreach($comarcas as $comarca) {
                $comarcas[$i] = array_values($comarca);
                $i++;
            }

            //apenas para debug
            //dd($comarcas);
        }

        if ($input['index_arquivo'] == 2) {
            //apenas para debug
            //dd($comarcas);

            unset($comarcas[0]);
            unset($comarcas[4]);
            unset($comarcas[1][0]);
            unset($comarcas[2][0]);
            unset($comarcas[2][1]);
            unset($comarcas[3][0]);
            unset($comarcas[3][1]);
            unset($comarcas[3][2]);
            unset($comarcas[5][0]);
            unset($comarcas[5][1]);
            $comarcas[1] = array_merge(["8", "02", "0058"], $comarcas[1]);
            $comarcas[2] = array_merge(["8", "02", "0059"], $comarcas[2]);
            $comarcas[3] = array_merge(["8", "02", "0060"], $comarcas[3]);
            $comarcas[5] = array_merge(["8", "02", "0061"], $comarcas[5]);
            $comarcas[3][4] = "de Camaragibe";

            //apenas para debug
            //dd($comarcas);

            $comarcas = array_values($comarcas);

            $i = 0;
            foreach($comarcas as $comarca) {
                $comarcas[$i] = array_values($comarca);
                $i++;
            }

            //apenas para debug
            //dd($comarcas);
        }

        $comarcas_sem_parte_final = [];
        if ($input['index_arquivo'] < 0) {
            $parte_final_comarcas = [];
            $comarcas_ = $comarcas;
            $comarcas = [];
            foreach($comarcas_ as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    $parte_final_comarcas[] = $dados_comarca;
                } else {
                    $comarcas[] = $dados_comarca;
                }
            }

            $comarcas_ = $comarcas;
            $comarcas = [];
            foreach ($comarcas_ as $index_comarca => $dados_comarca) {
                $tamanho_dados = count($dados_comarca);
                $penultimo = $tamanho_dados - 2;
                if (trim($dados_comarca[$penultimo]) == "-") {
                    $comarcas_sem_parte_final[] = $dados_comarca;
                } else {
                    $comarcas[] = $dados_comarca;
                }
            }
        }

        if ($input['index_arquivo'] < 0) {
            $comarcas = array_merge([["0000", "TRIBUNAL DE JUSTIÇA"]], $comarcas);
            $comarcas = array_merge([["0001", "COMARCA DE FORTALEZA - FÓRUM CLÓVIS BEVILÁQUA"]], $comarcas);
            $comarcas = array_merge([["0002", "COMARCA DE FORTALEZA - 10A. UNIDADE DO JECC - BAIRRO DE FÁTIMA"]], $comarcas);
            $comarcas = array_merge([["0003", "COMARCA DE FORTALEZA - 11A. UNIDADE DO JECC - TANCREDO NEVES"]], $comarcas);
            $comarcas = array_merge([["0004", "COMARCA DE FORTALEZA - 12A. UNIDADE DO JECC - ANEXO FÓRUM CLÓVIS BEVILÁQUA"]], $comarcas);
            $comarcas = array_merge([["0005", "COMARCA DE FORTALEZA - 12A. PRAIA DE IRACEMA"]], $comarcas);
        }

        if ($input['index_arquivo'] < 0) {
            unset($parte_final_comarcas[0]);
            unset($parte_final_comarcas[1]);
            unset($parte_final_comarcas[2]);
            unset($parte_final_comarcas[3]);
            $parte_final_comarcas = array_values($parte_final_comarcas);
        }

        $i = 0;
        $comarcas_corrigidas = [];
        foreach ($comarcas_sem_parte_final as $index_comarca => $dados_comarca) {
            $comarcas_corrigidas[] = array_merge($comarcas_sem_parte_final[$i], $parte_final_comarcas[$i]);
            $i++;
        }

        if ($input['index_arquivo'] < 0) {
            $comarcas = array_merge($comarcas_corrigidas, $comarcas);
        }

        //para minas gerais precisa disso: ligar codigos com comarcas
        if ($input['index_arquivo'] < 0) {
            if ($input['index_arquivo'] < 0) {
                unset($comarcas[50]);

                unset($comarcas[95][0]);
                $comarcas[95][0] = $comarcas[95][1];
                unset($comarcas[95][1]);
            }

            $comarcas_sem_codigo = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0]) && $dados_comarca[0][0] != "0") {
                    $comarcas_sem_codigo[] = $comarcas[$index_comarca];
                }
            }

            $codigos_sem_comarca = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if ((count($dados_comarca) == 1) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    $codigos_sem_comarca[] = $dados_comarca[0];
                }
                if ((count($dados_comarca) == 2) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                    }
                }
                if ((count($dados_comarca) == 3) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                        $codigos_sem_comarca[] = $dados_comarca[2];
                    }
                }
            }

            if ($input['index_arquivo'] < 0) {
                $codigos_sem_comarca[] = "0249 ";
            }

            $separar_comarcas_array = [
                "canápolis",
                "capinópolis",
                "curvelo",
                "espinosa",
                "guanhães",
                "guaxupé",
                "ibirité",
                "iguatama",
                "ipatinga",
                "itabira",
                "itambacuri",
                "itamonte",
                "itapecerica",
                "leopoldina",
                "luz",
                "manga",
                "manhuaçu",
                "mercês",
                "mesquita",
                "miraí",
                "mutum",
                "palma",
                "paracatu"
            ];

            $loops_necessarios = 2;
            for ($i = 1; $i <= $loops_necessarios; $i++) {
                $comarcas_sem_codigo_ = $comarcas_sem_codigo;
                $comarcas_sem_codigo = [];
                foreach ($comarcas_sem_codigo_ as $index_comarca => $dados_comarca) {
                    $adicionado = false;
                    foreach ($separar_comarcas_array as $index_array => $nome_array) {
                        if (trim(mb_strtolower($dados_comarca[0])) == trim(mb_strtolower($nome_array))) {
                            if (count($dados_comarca) > 1) {
                                $adicionado = true;
                                $comarcas_sem_codigo[] = [$dados_comarca[0]];
                                if (isset($dados_comarca[3])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2], $dados_comarca[3]];
                                } else if (isset($dados_comarca[2])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2]];
                                } else {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1]];
                                }
                            }
                        }
                    }
                    if (!$adicionado) {
                        $comarcas_sem_codigo[] = $dados_comarca;
                    }
                }
            }

            $comarcas_corretas = [];
            if ($input['index_arquivo'] < 0) {
                $comarcas_corretas[] = ["0000", "TJMG"];
            }

            if ($input['index_arquivo'] < 0) {
                foreach ($comarcas as $index_comarca => $dados_comarca) {
                    if (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0") {
                        if (count($dados_comarca) > 1) {
                            $comarcas_corretas[] = $comarcas[$index_comarca];
                        }
                    }
                }
            }

            if ($input['index_arquivo'] < 0) {
                unset($comarcas_corretas[55]);
                unset($comarcas_corretas[54]);
                unset($comarcas_corretas[53]);
                unset($comarcas_corretas[52]);
                unset($comarcas_corretas[51]);
                unset($comarcas_corretas[50]);
            }

            $codigos_sem_comarca_ = $codigos_sem_comarca;
            $codigos_sem_comarca = [];
            $comarcas_sem_codigo_ = $comarcas_sem_codigo;
            $comarcas_sem_codigo = [];
            if ($input['index_arquivo'] < 0) {
                foreach ($codigos_sem_comarca_ as $index_array => $codigo) {
                    $codigos_sem_comarca[] = $codigos_sem_comarca_[$index_array];
                    if (trim($codigo) == '0384') {
                        $codigos_sem_comarca[] = "0386";
                    }
                }
                $codigos_sem_comarca[] = "0477";

                foreach ($comarcas_sem_codigo_ as $index_array => $dados_comarca) {
                    $comarcas_sem_codigo[] = $comarcas_sem_codigo_[$index_array];
                    if ($index_array == 51) {
                        $comarcas_sem_codigo[] = ["EXTREMA"];
                    }
                }

                $comarcas_sem_codigo = array_merge([['FORMIGA']], $comarcas_sem_codigo);
            }

            //apenas para debug
            if ($input['index_arquivo'] == 2) {
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }

            $i = 0;
            $comarcas_ = $comarcas;

            if ($input['index_arquivo'] < 0) {
            $comarcas = [];
                foreach ($codigos_sem_comarca as $index_codigo => $codigo) {
                    $comarcas[] = array_merge([$codigos_sem_comarca[$i]], $comarcas_sem_codigo[$i]);
                    $i++;
                }
                $comarcas = array_merge($comarcas_corretas, $comarcas);
            }

            //apenas para debug
            if ($input['index_arquivo'] == 2) {
                //dd($comarcas, count($comarcas));
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }
        }

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 2) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 3) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca_) {
            $dados_comarca = array_values($dados_comarca_);

            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            //$dados_comarca = array_merge(["8", "02"], $dados_comarca); //alagoas nao precisa disso?
            $nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            //$codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca)); //alagoas nao precisa disso?
            //$dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]); //alagoas nao precisa disso?

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            //$linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_estado'] = "02"; //alagoas é 02
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            //$linha['codigo_estruturado'] = trim($this->FormatarConteudoCampo($codigo_estruturado, 'codigo_estruturadp')); //alagoas nao precisa disso?
            $linha['codigo_estruturado'] = trim($this->FormatarConteudoCampo(trim($dados_comarca[$ultimo]), 'codigo_estruturadp'));

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] == 3) {
            dd($linhas_formatado);
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarAmazonas($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Array de Arquivos
        if ($input['index_arquivo'] == 0) {
            $pos_util = 15;
            $pos_util_fim = 384;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 2;
            $pos_util_fim = 247;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 15;
            $pos_util_fim = 289;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 3) {
            $pos_util = 0;
            $pos_util_fim = 237;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];

        //ultimo arquivo para ceará tem tratamento diferente
        foreach ($content as $key => $value) {
            if ($value != "") {
                $content_[] = $value;
            }
        }

        foreach ($content_ as $key => $value) {
            if (!is_numeric($value)) {
                $comarca[] = $value;
            } else {
                if (count($comarca) > 0) {
                    $comarcas[] = $comarca;
                }
                $comarca = [];
                $comarca[] = $value;
            }
        }

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 2) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "4"], $dados_comarca); //todo: amazonas é 4?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($codigo_estruturado);

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarAmapa($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        $rows = $content;
        $content = [];
        $row = [];
        foreach ($rows as $num_linha => $linha_) {
            $nomes_colunas = array_keys($linha_);
            $linha = array_values($linha_);
            $row["estado"] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $row["estado_sigla"] = trim(strtoupper($input['estado_sigla']));
            $row["tipo_justica"] = trim($linha[0]);
            $row["codigo_estado"] = trim($linha[1]);
            $row["codigo_unidade"] = trim($linha[2]);
            $row["nome_unidade"] = trim($this->RemoverCaracteresInapropriados($linha[3]));
            $row["tipo_unidade"] = trim($linha[4]);
            $row["codigo_estruturado"] = trim($linha[5]);
            $content[] = $row;
        }

        foreach ($content as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $content[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $content, $colunas_formatado);
    }

    private function ImportarBahia($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        $rows = $content;
        $content = [];
        $row = [];
        foreach ($rows as $num_linha => $linha_) {
            $nomes_colunas = explode(',', array_keys($linha_)[0]);
            $linha = explode(',', array_values($linha_)[0]);

            $row["estado"] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $row["estado_sigla"] = trim(strtoupper($input['estado_sigla']));
            $row["tipo_justica"] = trim($linha[0]);
            $row["codigo_estado"] = trim($linha[1]);
            $row["codigo_unidade"] = trim($linha[2]);
            $row["nome_unidade"] = trim($this->RemoverCaracteresInapropriados($linha[3]));
            $row["tipo_unidade"] = trim($this->Sinonimos($linha[4]));
            $row["codigo_estruturado"] = trim($this->MontarCodigoEstruturado($row));

            $content[] = $row;
        }

        foreach ($content as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $content[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $content, $colunas_formatado);
    }

    private function ImportarCeara($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Ceará é um array de arquivos
        if ($input['index_arquivo'] == 0) {
            /* todo: não sabemos qual é ainda
            $pos_util = 18;
            $pos_util_fim = 222;
            $length_util = ($pos_util_fim - $pos_util) + 1;
            */
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 18;
            $pos_util_fim = 222;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 17;
            $pos_util_fim = 230;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 3) {
            $pos_util = 17;
            $pos_util_fim = 248;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 4) {
            $pos_util = 18;
            $pos_util_fim = 211;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];
        if ($input['index_arquivo'] != 4) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "6"], $dados_comarca); //todo: ceara é 6?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($codigo_estruturado);

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarDistritoFederal($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        $pos_util = 57;
        $pos_util_fim = 152;
        $length_util = ($pos_util_fim - $pos_util) + 1;

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];

        foreach ($content as $key => $value) {
            if ($value != "") {
                $content_[] = $value;
            }
        }

        foreach ($content_ as $key => $value) {
            if (!is_numeric($value)) {
                $comarca[] = $value;
            } else {
                if ($value[0] == "0") {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                }
                $comarca[] = $value;
            }
        }

        //para brasilia precisa dropar essa coluna dos registros
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            unset($comarcas[$index_comarca][1]);
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "7"], $dados_comarca); //todo: df é 7?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($codigo_estruturado);

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarEspiritoSanto($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        $pos_util = 0;
        $pos_util_fim = 0;
        $length_util = ($pos_util_fim - $pos_util) + 1;

        //limpar linhas em branco no ES
        unset($content[0]);
        $content_ = [];
        foreach ($content as $index_content => $dados_content) {
            if ($dados_content != "") {
                $content_[] = $dados_content;
            }
        }
        $content = $content_;

        //$content = array_slice($content, $pos_util, $length_util); //ES nao precisa
        //$content[] = ""; //ES nao precisa

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];

        //para ES é diferente
        foreach ($content as $index_content => $dados_content) {
            $content_explode = explode(" ", $dados_content);

            foreach ($content_explode as $index_ce => $dados_ce) {
                if (is_numeric($dados_ce) && $dados_ce[0] == "0" && mb_strlen($dados_ce) == 4)
                {
                    $codigo_unidade = $dados_ce;
                    $linha_explode = explode($codigo_unidade, $dados_content);

                    $comarca['codigo_unidade'] = $codigo_unidade;
                    $comarca['nome_unidade'] = trim($linha_explode[0]);
                    $comarca['descricao_unidade'] = trim($linha_explode[1]);
                    $comarcas[] = $comarca;
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca_) {
            $dados_comarca = array_values($dados_comarca_);

            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "8"], $dados_comarca); //todo: es é 8?
            $nome_unidade = $dados_comarca_['nome_unidade'];
            $descricao_unidade = $dados_comarca_['descricao_unidade'];
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['descricao_unidade'] = trim($descricao_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($codigo_estruturado);

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarGoias($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\r", $content);
        $content_temp = $content;
        $pos_util = 4;
        $pos_util_fim = 531;
        $length_util = ($pos_util_fim - $pos_util) + 1;
        $content = array_slice($content, $pos_util, $length_util);

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];

        foreach ($content as $key => $value) {
            if ($value != "") {
                $content_[] = $value;
            }
        }

        foreach ($content_ as $key => $value) {
            if (!is_numeric($value)) {
                $comarca[] = $value;
            } else {
                if ($value[0] == "0") {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                }
                $comarca[] = $value;
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca_) {
            $dados_comarca = array_values($dados_comarca_);

            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "9"], $dados_comarca); //todo: goias é 9?
            $nome_unidade = $dados_comarca_[1];
            $descricao_unidade = $dados_comarca_[2];
            $tipo_unidade = trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['descricao_unidade'] = trim($descricao_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($codigo_estruturado);

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarMaranhao($input, $content)
    {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Ceará é um array de arquivos
        if ($input['index_arquivo'] == 0) {
            $pos_util = 0;
            $pos_util_fim = 496;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 7;
            $pos_util_fim = 383;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 14;
            $pos_util_fim = 89;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 1) {
            unset($content[372]);
        }

        if ($input['index_arquivo'] != 4) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    unset($comarcas[$index_comarca]);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 2) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "10"], $dados_comarca); //todo: maranhão é 10?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($codigo_estruturado);

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        if ($input['index_arquivo'] == 0) {
            $linhas_formatado[] = [
                "estado" => "Maranhão",
                "estado_sigla" => "MA",
                "tipo_justica" => "8",
                "codigo_estado" => "10",
                "codigo_unidade" => "0056",
                "nome_unidade" => "Forum da Comarca Santa Inês",
                "tipo_unidade" => "Fórum",
                "codigo_estruturado" => "8.10.0056"
            ];
        }
        if ($input['index_arquivo'] == 1) {
            $linhas_formatado[] = [
                "estado" => "Maranhão",
                "estado_sigla" => "MA",
                "tipo_justica" => "8",
                "codigo_estado" => "10",
                "codigo_unidade" => "0063",
                "nome_unidade" => "Forum da Comarca Zé Doca",
                "tipo_unidade" => "Fórum",
                "codigo_estruturado" => "8.10.0063"
            ];
        }
        if ($input['index_arquivo'] == 2) {
            $linhas_formatado[] = [
                "estado" => "Maranhão",
                "estado_sigla" => "MA",
                "tipo_justica" => "8",
                "codigo_estado" => "10",
                "codigo_unidade" => "0129",
                "nome_unidade" => "Forum da Comarca São Raimundo das Mangabeiras",
                "tipo_unidade" => "Fórum",
                "codigo_estruturado" => "8.10.0129"
            ];
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarMatoGrosso($input, $content) {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Array de Arquivos
        $pos_util = 0;
        $pos_util_fim = 0;
        $length_util = ($pos_util_fim - $pos_util) + 1;
        if ($input['index_arquivo'] == 0) {
            $pos_util = 7;
            $pos_util_fim = 232;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 0;
            $pos_util_fim = 165;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];

        if ($input['index_arquivo'] != 4) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        if ($input['index_arquivo'] == 1) {
            $content__ = [];
            $comarca_ = [];
            $comarcas_ = [];

            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content__[] = $value;
                }
            }

            foreach ($content__ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca_[] = $value;
                } else {
                    if (count($comarca_) > 0) {
                        $comarcas_[] = $comarca_;
                    }
                    $comarca_ = [];
                    $comarca_[] = $value;
                }
            }

            $lista_de_nomes_de_comarcas = $comarcas;
            unset($lista_de_nomes_de_comarcas[0]);
            unset($lista_de_nomes_de_comarcas[1]);
            unset($lista_de_nomes_de_comarcas[2]);
            unset($lista_de_nomes_de_comarcas[3]);
            unset($lista_de_nomes_de_comarcas[4]);
            unset($lista_de_nomes_de_comarcas[5]);
            unset($lista_de_nomes_de_comarcas[6]);
            unset($lista_de_nomes_de_comarcas[7]);
            unset($lista_de_nomes_de_comarcas[8]);
            unset($lista_de_nomes_de_comarcas[9]);
            unset($lista_de_nomes_de_comarcas[10]);
            $lista_de_nomes_de_comarcas = array_values($lista_de_nomes_de_comarcas);

            $lista_de_numeros_de_comarcas = $comarcas_;
            $lista_de_numeros_de_comarcas[] = ["111"];
            $numeros_que_nao_estao_na_lista = [
                ["60"],
                ["62"],
                ["63"],
                ["64"],
                ["70"],
                ["71"],
                ["72"],
                ["77"],
                ["78"],
                ["79"],
                ["80"],
                ["81"],
                ["82"],
                ["83"],
                ["84"],
                ["85"],
                ["86"],
                ["87"],
                ["88"],
                ["89"],
                ["90"],
                ["91"],
                ["92"],
                ["93"],
                ["94"],
                ["95"],
                ["96"],
                ["97"],
                ["98"],
                ["99"],
            ];
            $lista_de_numeros_de_comarcas = array_merge($numeros_que_nao_estao_na_lista, $lista_de_numeros_de_comarcas);

            $separar_comarcas_array = [
                "sapezal",
                "torixoréu",
                "confresa"
            ];

            $loops_necessarios = 2;
            for ($i = 1; $i <= $loops_necessarios; $i++) {
                $comarcas_sem_codigo_ = $lista_de_nomes_de_comarcas;
                $comarcas_sem_codigo = [];
                foreach ($comarcas_sem_codigo_ as $index_comarca => $dados_comarca) {
                    $adicionado = false;
                    foreach ($separar_comarcas_array as $index_array => $nome_array) {
                        if (trim(mb_strtolower($dados_comarca[0])) == trim(mb_strtolower($nome_array))) {
                            if (count($dados_comarca) > 1) {
                                $adicionado = true;
                                $comarcas_sem_codigo[] = [$dados_comarca[0]];
                                if (isset($dados_comarca[3])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2], $dados_comarca[3]];
                                } else if (isset($dados_comarca[2])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2]];
                                } else {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1]];
                                }
                            }
                        }
                    }
                    if (!$adicionado) {
                        $comarcas_sem_codigo[] = $dados_comarca;
                    }
                }
                $lista_de_nomes_de_comarcas = $comarcas_sem_codigo;
            }
            $lista_de_nomes_de_comarcas = $comarcas_sem_codigo;

            $comarcas = [];
            foreach ($lista_de_nomes_de_comarcas as $index_comarca => $nomes_comarcas) {
                $comarcas[] = array_merge($lista_de_numeros_de_comarcas[$index_comarca], $lista_de_nomes_de_comarcas[$index_comarca]);
            }

            /*
            dd(
                '$comarcas',
                $comarcas,
                '$lista_de_nomes_de_comarcas',
                $lista_de_nomes_de_comarcas,
                '$lista_de_numeros_de_comarcas',
                $lista_de_numeros_de_comarcas,
                'count: $comarcas',
                count($comarcas),
                'count: $lista_de_nomes_de_comarcas',
                count($lista_de_nomes_de_comarcas),
                'count: $lista_de_numeros_de_comarcas',
                count($lista_de_numeros_de_comarcas)
            );
            */
        }

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        //apemas para debug
        if ($input['index_arquivo'] == 2) {
            dd($comarcas);
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "11"], $dados_comarca); //todo: mt é 11?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($this->FormatarConteudoCampo($codigo_estruturado, 'codigo_estruturadp'));

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //apemas para debug
        if ($input['index_arquivo'] == 2) {
            dd($linhas_formatado);
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarMatoGrossoDoSul($input, $content) {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Array de Arquivos
        $pos_util = 0;
        $pos_util_fim = 0;
        $length_util = ($pos_util_fim - $pos_util) + 1;
        if ($input['index_arquivo'] == 0) {
            $pos_util = 84;
            $pos_util_fim = 135;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 25;
            $pos_util_fim = 135;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 28;
            $pos_util_fim = 172;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 3) {
            $pos_util = 6;
            $pos_util_fim = 107;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 4) {
            dd('existe?');
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        //ajustes pontuais nos arquivos
        if ($input['index_arquivo'] == 0) {
            $content[21] = '5';
        }

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];

        if ($input['index_arquivo'] != 4) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] == 4) {
            dd($comarcas);
        }

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 2) {
            unset($comarcas[8]);
            unset($comarcas[27]);

            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 3) {
            unset($comarcas[8]);

            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "12"], $dados_comarca); //todo: ms é 12?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($this->FormatarConteudoCampo($codigo_estruturado, 'codigo_estruturadp'));

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] == 4) {
            dd($linhas_formatado);
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarMinasGerais($input, $content) {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Array de Arquivos
        $pos_util = 0;
        $pos_util_fim = 0;
        $length_util = ($pos_util_fim - $pos_util) + 1;
        if ($input['index_arquivo'] == 0) {
            $pos_util = 29;
            $pos_util_fim = 436;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 22;
            $pos_util_fim = 442;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 23;
            $pos_util_fim = 330;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 3) {
            dd('existe?');
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];
        $comarcas__ = [];
        $comarca__ = [];

        if ($input['index_arquivo'] != 4) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        if ($input['index_arquivo'] == 2) {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content__[] = $value;
                }
            }

            foreach ($content__ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca__[] = $value;
                } else {
                    if (count($comarca__) > 0) {
                        $comarcas__[] = $comarca__;
                    }
                    $comarca__ = [];
                    $comarca__[] = $value;
                }
            }
        }

        if ($input['index_arquivo'] == 2) {
            $comarcas__[] = ["0624", "SÃO JOÃO DA PONTE"];
            //$comarcas__[] = ["0720", "VISCONDE DO RIO BRANCO"];
        }

        if ($input['index_arquivo'] == 2) {
            $comarcas = $comarcas__;
        }

        //para minas gerais precisa disso: ligar codigos com comarcas
        if ($input['index_arquivo'] <= 2) {
            if ($input['index_arquivo'] == 0) {
                unset($comarcas[50]);

                unset($comarcas[95][0]);
                $comarcas[95][0] = $comarcas[95][1];
                unset($comarcas[95][1]);
            }

            $comarcas_sem_codigo = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0]) && $dados_comarca[0][0] != "0") {
                    $comarcas_sem_codigo[] = $comarcas[$index_comarca];
                }
            }

            $codigos_sem_comarca = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if ((count($dados_comarca) == 1) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    $codigos_sem_comarca[] = $dados_comarca[0];
                }
                if ((count($dados_comarca) == 2) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                    }
                }
                if ((count($dados_comarca) == 3) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                        $codigos_sem_comarca[] = $dados_comarca[2];
                    }
                }
            }

            if ($input['index_arquivo'] == 0) {
                $codigos_sem_comarca[] = "0249 ";
            }

            $separar_comarcas_array = [
                "canápolis",
                "capinópolis",
                "curvelo",
                "espinosa",
                "guanhães",
                "guaxupé",
                "ibirité",
                "iguatama",
                "ipatinga",
                "itabira",
                "itambacuri",
                "itamonte",
                "itapecerica",
                "leopoldina",
                "luz",
                "manga",
                "manhuaçu",
                "mercês",
                "mesquita",
                "miraí",
                "mutum",
                "palma",
                "paracatu"
            ];

            $loops_necessarios = 2;
            for ($i = 1; $i <= $loops_necessarios; $i++) {
                $comarcas_sem_codigo_ = $comarcas_sem_codigo;
                $comarcas_sem_codigo = [];
                foreach ($comarcas_sem_codigo_ as $index_comarca => $dados_comarca) {
                    $adicionado = false;
                    foreach ($separar_comarcas_array as $index_array => $nome_array) {
                        if (trim(mb_strtolower($dados_comarca[0])) == trim(mb_strtolower($nome_array))) {
                            if (count($dados_comarca) > 1) {
                                $adicionado = true;
                                $comarcas_sem_codigo[] = [$dados_comarca[0]];
                                if (isset($dados_comarca[3])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2], $dados_comarca[3]];
                                } else if (isset($dados_comarca[2])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2]];
                                } else {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1]];
                                }
                            }
                        }
                    }
                    if (!$adicionado) {
                        $comarcas_sem_codigo[] = $dados_comarca;
                    }
                }
            }

            $comarcas_corretas = [];
            if ($input['index_arquivo'] == 0) {
                $comarcas_corretas[] = ["0000", "TJMG"];
            }

            if ($input['index_arquivo'] == 0) {
                foreach ($comarcas as $index_comarca => $dados_comarca) {
                    if (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0") {
                        if (count($dados_comarca) > 1) {
                            $comarcas_corretas[] = $comarcas[$index_comarca];
                        }
                    }
                }
            }

            if ($input['index_arquivo'] == 0) {
                unset($comarcas_corretas[55]);
                unset($comarcas_corretas[54]);
                unset($comarcas_corretas[53]);
                unset($comarcas_corretas[52]);
                unset($comarcas_corretas[51]);
                unset($comarcas_corretas[50]);
            }

            $codigos_sem_comarca_ = $codigos_sem_comarca;
            $codigos_sem_comarca = [];
            $comarcas_sem_codigo_ = $comarcas_sem_codigo;
            $comarcas_sem_codigo = [];
            if ($input['index_arquivo'] == 1) {
                foreach ($codigos_sem_comarca_ as $index_array => $codigo) {
                    $codigos_sem_comarca[] = $codigos_sem_comarca_[$index_array];
                    if (trim($codigo) == '0384') {
                        $codigos_sem_comarca[] = "0386";
                    }
                }
                $codigos_sem_comarca[] = "0477";

                foreach ($comarcas_sem_codigo_ as $index_array => $dados_comarca) {
                    $comarcas_sem_codigo[] = $comarcas_sem_codigo_[$index_array];
                    if ($index_array == 51) {
                        $comarcas_sem_codigo[] = ["EXTREMA"];
                    }
                }

                $comarcas_sem_codigo = array_merge([['FORMIGA']], $comarcas_sem_codigo);
            }

            //apenas para debug
            if ($input['index_arquivo'] == 3) {
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }

            $i = 0;
            $comarcas_ = $comarcas;

            if ($input['index_arquivo'] < 2) {
            $comarcas = [];
                foreach ($codigos_sem_comarca as $index_codigo => $codigo) {
                    $comarcas[] = array_merge([$codigos_sem_comarca[$i]], $comarcas_sem_codigo[$i]);
                    $i++;
                }
                $comarcas = array_merge($comarcas_corretas, $comarcas);
            }

            //apenas para debug
            if ($input['index_arquivo'] == 3) {
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }
        }

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 2) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 3) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "13"], $dados_comarca); //todo: mg é 13?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($this->FormatarConteudoCampo($codigo_estruturado, 'codigo_estruturadp'));

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] == 3) {
            dd($linhas_formatado);
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarPara($input, $content) {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Codigo Comarca",
            "Codigo Vara",
            "Nome Unidade",
            "Nome Comarca",
            "Nome Vara",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Array de Arquivos
        $pos_util = 0;
        $pos_util_fim = 0;
        $length_util = ($pos_util_fim - $pos_util) + 1;
        if ($input['index_arquivo'] == 0) {
            $pos_util = 35;
            $pos_util_fim = 366;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 34;
            $pos_util_fim = 332;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 23;
            $pos_util_fim = 330;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 3) {
            dd('existe?');
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];
        $comarcas__ = [];
        $comarca__ = [];

        if ($input['index_arquivo'] >= 0) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        if ($input['index_arquivo'] >= 0) {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content__[] = $value;
                }
            }

            foreach ($content__ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca__[] = $value;
                } else {
                    if (count($comarca__) > 0) {
                        $comarcas__[] = $comarca__;
                    }
                    $comarca__ = [];
                    $comarca__[] = $value;
                }
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] >= 2) {
            dd('$comarcas', $comarcas, '$comarcas__', $comarcas__);
        }

        if ($input['index_arquivo'] >= 1) {
            $comarcas = $comarcas__;
        }

        //para minas gerais precisa disso: ligar codigos com comarcas
        if ($input['index_arquivo'] >= 2) {
            $comarcas_sem_codigo = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0]) && $dados_comarca[0][0] != "0") {
                    $comarcas_sem_codigo[] = $comarcas[$index_comarca];
                }
            }

            $codigos_sem_comarca = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if ((count($dados_comarca) == 1) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    $codigos_sem_comarca[] = $dados_comarca[0];
                }
                if ((count($dados_comarca) == 2) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                    }
                }
                if ((count($dados_comarca) == 3) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                        $codigos_sem_comarca[] = $dados_comarca[2];
                    }
                }
            }

            $separar_comarcas_array = [
                "",
            ];

            $loops_necessarios = 2;
            for ($i = 1; $i <= $loops_necessarios; $i++) {
                $comarcas_sem_codigo_ = $comarcas_sem_codigo;
                $comarcas_sem_codigo = [];
                foreach ($comarcas_sem_codigo_ as $index_comarca => $dados_comarca) {
                    $adicionado = false;
                    foreach ($separar_comarcas_array as $index_array => $nome_array) {
                        if (trim(mb_strtolower($dados_comarca[0])) == trim(mb_strtolower($nome_array))) {
                            if (count($dados_comarca) > 1) {
                                $adicionado = true;
                                $comarcas_sem_codigo[] = [$dados_comarca[0]];
                                if (isset($dados_comarca[3])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2], $dados_comarca[3]];
                                } else if (isset($dados_comarca[2])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2]];
                                } else {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1]];
                                }
                            }
                        }
                    }
                    if (!$adicionado) {
                        $comarcas_sem_codigo[] = $dados_comarca;
                    }
                }
            }

            $comarcas_corretas = [];
            if ($input['index_arquivo'] >= 2) {
                foreach ($comarcas as $index_comarca => $dados_comarca) {
                    if (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0") {
                        if (count($dados_comarca) > 1) {
                            $comarcas_corretas[] = $comarcas[$index_comarca];
                        }
                    }
                }
            }

            //apenas para debug
            if ($input['index_arquivo'] >= 2) {
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }

            $i = 0;
            $comarcas_ = $comarcas;
            if ($input['index_arquivo'] <= 2) {
            $comarcas = [];
                foreach ($codigos_sem_comarca as $index_codigo => $codigo) {
                    $comarcas[] = array_merge([$codigos_sem_comarca[$i]], $comarcas_sem_codigo[$i]);
                    $i++;
                }
                $comarcas = array_merge($comarcas_corretas, $comarcas);
            }

            //apenas para debug
            if ($input['index_arquivo'] <= 2) {
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] >= 2) {
            dd($comarcas);
        }

        //formatação especifica para o estado do pará
        if ($input['index_arquivo'] == 0) {
            unset($comarcas[0]);
            unset($comarcas[1][0]);
            $comarcas[1] = array_merge(["70", "ABAETETUBA", "70001", "1ª "], $comarcas[1]);

            unset($comarcas[2]);
            unset($comarcas[3][0]);
            $comarcas[3] = array_merge(["70", "ABAETETUBA", "70002", "2ª "], $comarcas[3]);

            unset($comarcas[4]);
            unset($comarcas[5][0]);
            $comarcas[5] = array_merge(["70", "ABAETETUBA", "70003", "3ª "], $comarcas[5]);

            unset($comarcas[6]);
            unset($comarcas[7][0]);
            unset($comarcas[7][1]);
            $comarcas[7] = array_merge(["70", "ACARA", "76001"], $comarcas[7]);

            unset($comarcas[8]);
            unset($comarcas[9][0]);
            $comarcas[9] = array_merge(["2", "AFUA", "210"], $comarcas[9]);

            unset($comarcas[10]);
            unset($comarcas[11][1]);
            $comarcas[11] = array_merge(["3", "ALENQUER"], $comarcas[11]);

            unset($comarcas[12]);
            unset($comarcas[13][0]);
            unset($comarcas[13][1]);
            $comarcas[13] = array_merge(["4", "ALMEIRIM", "4001", "VARA "], $comarcas[13]);

            unset($comarcas[14]);
            unset($comarcas[18]);
            unset($comarcas[20]);
            unset($comarcas[21]);
            unset($comarcas[22]);
            unset($comarcas[15][0]);
            unset($comarcas[15][1]);
            $comarcas[15] = array_merge(["5", "ALTAMIRA", "5004"], $comarcas[15]);
            $comarcas[16] = array_merge($comarcas[16], ["5", "ALTAMIRA", "5001", "1ª VARA CIVEL"]);
            $comarcas[17] = array_merge($comarcas[17], ["5", "ALTAMIRA", "5002", "2ª VARA CIVEL"]);
            $comarcas[19] = array_merge($comarcas[19], ["5", "ALTAMIRA", "5003", "3ª VARA PENAL"]);
            $comarcas[23] = array_merge(["5", "ALTAMIRA", "5005", "4ª "], $comarcas[23]);
            unset($comarcas[15][5]);
            unset($comarcas[15][6]);
            unset($comarcas[15][7]);
            unset($comarcas[16][0]);
            unset($comarcas[17][0]);
            unset($comarcas[17][1]);
            unset($comarcas[17][2]);
            unset($comarcas[19][0]);
            unset($comarcas[19][1]);
            unset($comarcas[19][2]);
            unset($comarcas[19][3]);
            unset($comarcas[23][4]);

            unset($comarcas[24]);
            unset($comarcas[25][0]);
            unset($comarcas[25][1]);
            $comarcas[25] = array_merge(["77", "ANAJAS", "77001"], $comarcas[25]);

            unset($comarcas[26]);
            unset($comarcas[27][1]);
            $comarcas[27][8] = "VIII";
            $comarcas[27] = array_merge(["6", "ANANINDEUA"], $comarcas[27]);

            unset($comarcas[28]);
            unset($comarcas[29][1]);
            $comarcas[29] = array_merge(["6", "ANANINDEUA"], $comarcas[29]);

            unset($comarcas[30]);
            unset($comarcas[31][0]);
            $comarcas[31] = array_merge(["6", "ANANINDEUA", "11106", "1ª "], $comarcas[31]);

            unset($comarcas[32]);
            unset($comarcas[33][0]);
            $comarcas[33] = array_merge(["6", "ANANINDEUA", "11108", "2ª "], $comarcas[33]);

            unset($comarcas[34]);
            unset($comarcas[35][0]);
            $comarcas[35] = array_merge(["6", "ANANINDEUA", "11110", "3ª "], $comarcas[35]);

            unset($comarcas[36]);
            unset($comarcas[37][0]);
            $comarcas[37] = array_merge(["6", "ANANINDEUA", "11112", "4ª "], $comarcas[37]);

            unset($comarcas[38]);
            unset($comarcas[39][0]);
            $comarcas[39] = array_merge(["6", "ANANINDEUA", "11114", "5ª "], $comarcas[39]);

            unset($comarcas[40]);
            unset($comarcas[41][0]);
            unset($comarcas[41][5]);
            unset($comarcas[41][6]);
            unset($comarcas[41][7]);
            unset($comarcas[41][8]);
            unset($comarcas[41][9]);
            $comarcas[41] = array_merge(["6", "ANANINDEUA", "11116", "6ª "], $comarcas[41]);
            $comarcas[42] = array_merge($comarcas[42], ["6", "ANANINDEUA", "11118", "7ª VARA CIVEL DE ANANINDEUA"]);
            unset($comarcas[42][0]);
            unset($comarcas[43][0]);
            $comarcas[43] = array_merge(["6", "ANANINDEUA", "11120", "8ª "], $comarcas[43]);
            unset($comarcas[44]);
            unset($comarcas[45]);
            unset($comarcas[46]);

            unset($comarcas[47]);
            unset($comarcas[48][0]);
            $comarcas[48] = array_merge(["6", "ANANINDEUA", "11122", "9ª "], $comarcas[48]);

            unset($comarcas[49]);
            unset($comarcas[50][0]);
            unset($comarcas[50][1]);
            $comarcas[50] = array_merge(["68", "AUGUSTO CORREA", "68001"], $comarcas[50]);

            unset($comarcas[51]);
            unset($comarcas[52][0]);
            unset($comarcas[52][1]);
            $comarcas[52] = array_merge(["100", "AURORA DO PARA", "100001"], $comarcas[52]);

            unset($comarcas[53]);
            unset($comarcas[54][0]);
            unset($comarcas[54][1]);
            $comarcas[54] = array_merge(["7", "BAIAO", "7001"], $comarcas[54]);

            unset($comarcas[55]);
            unset($comarcas[56][0]);
            unset($comarcas[56][1]);
            $comarcas[56] = array_merge(["8", "BARBACENA", "8011", "1ª "], $comarcas[56]);

            unset($comarcas[57]);
            unset($comarcas[58][0]);
            $comarcas[58] = array_merge(["8", "BARBACENA", "8012"], $comarcas[58]);

            unset($comarcas[59]);
            unset($comarcas[60][0]);
            $comarcas[60] = array_merge(["8", "BARBACENA", "8015", "2ª "], $comarcas[60]);

            unset($comarcas[61]);
            unset($comarcas[62][0]);
            $comarcas[62] = array_merge(["8", "BARBACENA", "8014", "3ª "], $comarcas[62]);

            unset($comarcas[63]);
            unset($comarcas[64][0]);
            $comarcas[64] = array_merge(["1", "BELEM", "19007"], $comarcas[64]);

            unset($comarcas[65]);
            unset($comarcas[66][0]);
            unset($comarcas[66][1]);
            unset($comarcas[66][7]);
            unset($comarcas[66][8]);
            unset($comarcas[66][9]);
            unset($comarcas[66][10]);
            unset($comarcas[66][11]);
            unset($comarcas[68][0]);
            $comarcas[66] = array_merge(["1", "BELEM", "19004"], $comarcas[66]);
            $comarcas[68] = array_merge(["1", "BELEM", "91012"], $comarcas[68]);
            $comarcas[67] = array_merge($comarcas[67], ["1", "BELEM", "50001", "JUIZADO ESPECIAL DO JURUNAS"]);
            unset($comarcas[67][0]);

            unset($comarcas[69]);
            unset($comarcas[70][0]);
            unset($comarcas[70][1]);
            $comarcas[70] = array_merge(["1", "BELEM", "19001"], $comarcas[70]);

            unset($comarcas[71]);
            unset($comarcas[72][0]);
            unset($comarcas[72][1]);
            $comarcas[72] = array_merge(["1", "BELEM", "14041", "VARA "], $comarcas[72]);

            unset($comarcas[73]);
            unset($comarcas[74]);
            unset($comarcas[75]);
            unset($comarcas[76]);
            unset($comarcas[77]);
            unset($comarcas[78][0]);
            $comarcas[78] = array_merge(["1", "BELEM", "14040", "VARA "], $comarcas[78]);

            //apenas para debug
            //dd($comarcas);

            $comarcas = array_values($comarcas);

            $i = 0;
            foreach($comarcas as $comarca) {
                $comarcas[$i] = array_values($comarca);
                $i++;
            }

            //apenas para debug
            //dd($comarcas);
        }
        if ($input['index_arquivo'] == 1) {
            //apenas para debug
            $comarcas = [];

            $comarcas = [
                ["1", "BELÉM", "19003", "VARA DE CRIMES CONTRA CRIANCAS/ADOLESCENT."],
                ["1", "BELÉM", "11127", ""],
                ["1", "BELÉM", "19002", ""],
                ["1", "BELÉM", "11082", ""],
                ["1", "BELÉM", "1096", ""],
                ["1", "BELÉM", "19008", ""],
                ["1", "BELÉM", "50002", ""],
                ["1", "BELÉM", "92011", ""],
                ["1", "BELÉM", "15018", ""],
                ["1", "BELÉM", "9011", ""],
                ["1", "BELÉM", "1018", ""],
                ["1", "BELÉM", "11115", ""],
                ["1", "BELÉM", "7015", ""],
                ["1", "BELÉM", "1034", ""],
                ["1", "BELÉM", "10019", ""],
                ["1", "BELÉM", "19005", ""],
                ["1", "BELÉM", "11017", ""],
                ["1", "BELÉM", "1095", ""],
                ["1", "BELÉM", "1075", ""],
                ["1", "BELÉM", "11126", ""],
                ["1", "BELÉM", "8041", ""],
                ["1", "BELÉM", "14029", ""],
                ["1", "BELÉM", "1083", ""],
                ["1", "BELÉM", "11125", ""],
                ["1", "BELÉM", "8043", ""],
                ["1", "BELÉM", "9035", ""],
                ["1", "BELÉM", "9029", ""],
                ["1", "BELÉM", "1026", ""],
                ["1", "BELÉM", "11033", ""],
                ["1", "BELÉM", "7016", ""],
                ["1", "BELÉM", "1042", ""],
                ["1", "BELÉM", "10027", ""],
                ["1", "BELÉM", "7017", ""],
                ["1", "BELÉM", "16016", ""],
                ["1", "BELÉM", "11025", ""],
                ["1", "BELÉM", "1091", ""],
                ["1", "BELÉM", "11041", ""],
                ["1", "BELÉM", "1059", ""],
            ];

            dd($comarcas, count($comarcas));

            $comarcas = array_values($comarcas);

            $i = 0;
            foreach($comarcas as $comarca) {
                $comarcas[$i] = array_values($comarca);
                $i++;
            }

            //apenas para debug
            dd($comarcas);
        }
        if ($input['index_arquivo'] == 2) {
            //apenas para debug
            dd($comarcas);

            $comarcas = array_values($comarcas);

            $i = 0;
            foreach($comarcas as $comarca) {
                $comarcas[$i] = array_values($comarca);
                $i++;
            }

            //apenas para debug
            dd($comarcas);
        }


        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 2) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 3) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] >= 1) {
            dd($comarcas);
        }

        //formatação especifica para o estado do pará
        $linhas_pre_formatadas = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            $linha_pre_formatada = [];
            $tamanho_dados = count($dados_comarca);

            $pos_codigo_da_vara = 0;
            $achou = false;
            foreach ($dados_comarca as $index_dado => $dado) {
                if (is_numeric($dado) && $index_dado > 0 && !$achou) {
                    $pos_codigo_da_vara = $index_dado;
                    $achou = true;
                }
            }

            $linha_pre_formatada['codigo_da_comarca'] = $dados_comarca[0];
            $linha_pre_formatada['nome_da_comarca'] = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 1, $pos_codigo_da_vara - 1)));
            $linha_pre_formatada['codigo_da_vara'] = $dados_comarca[$pos_codigo_da_vara];
            $linha_pre_formatada['nome_da_vara'] = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, $pos_codigo_da_vara + 1, $tamanho_dados)));

            $linhas_pre_formatadas[] = $linha_pre_formatada;
        }

        //apenas para debug
        if ($input['index_arquivo'] >= 1) {
            dd($comarcas, $linhas_pre_formatadas);
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "14"], $dados_comarca); //todo: pa é 14?

            $dados_fake_comarca['tipo_justica'] = trim($dados_comarca[0]);
            $dados_fake_comarca['codigo_estado'] = trim($dados_comarca[1]);
            $dados_fake_comarca['codigo_unidade'] = trim($linhas_pre_formatadas[$index_comarca]['codigo_da_comarca']);

            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            //$nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1)));
            $nome_unidade = trim($linhas_pre_formatadas[$index_comarca]['nome_da_comarca']) . " - " . trim($linhas_pre_formatadas[$index_comarca]['nome_da_vara']);
            $nome_comarca = trim($linhas_pre_formatadas[$index_comarca]['nome_da_comarca']);
            $nome_vara = trim($linhas_pre_formatadas[$index_comarca]['nome_da_vara']);
            $codigo_unidade = trim($linhas_pre_formatadas[$index_comarca]['codigo_da_comarca']);
            $codigo_comarca = trim($linhas_pre_formatadas[$index_comarca]['codigo_da_comarca']);
            $codigo_vara = trim($linhas_pre_formatadas[$index_comarca]['codigo_da_vara']);
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_fake_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($codigo_unidade);
            $linha['codigo_comarca'] = trim($codigo_comarca);
            $linha['codigo_vara'] = trim($codigo_vara);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['nome_comarca'] = trim($nome_comarca);
            $linha['nome_vara'] = trim($nome_vara);
            $linha['tipo_unidade'] = "Comarca";
            $linha['codigo_estruturado'] = trim($this->FormatarConteudoCampo($codigo_estruturado, 'codigo_estruturado'));

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] >= 1) {
            dd($linhas_formatado);
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function ImportarParaiba($input, $content) {
        dd("Paraíba", $input, $content);
    }

    private function ImportarParana($input, $content) {
        dd("PR", $input, $content);
    }

    private function ImportarRioDeJaneiro($input, $content) {
        dd("RJ", $input, $content);
    }

    private function ImportarRioGrandeDoNorte($input, $content) {
        dd("RN", $input, $content);
    }

    private function ImportarRioGrandeDoSul($input, $content) {
        dd("RS", $input, $content);
    }

    private function ImportarRondonia($input, $content) {
        dd("RO", $input, $content);
    }

    private function ImportarRoraima($input, $content) {
        dd("RR", $input, $content);
    }

    private function ImportarSantaCatarina($input, $content) {
        dd("SC", $input, $content);
    }

    private function ImportarSaoPaulo($input, $content) {
        dd("SP", $input, $content);
    }

    private function ImportarTocantins($input, $content) {
        dd("TO", $input, $content);
    }

    private function ImportarCearaModelo2($input, $content) {
        $colunas = [
            "Estado",
            "Estado Sigla",
            "Tipo Justiça",
            "Codigo Estado",
            "Codigo Unidade",
            "Nome Unidade",
            "Descriçao Unidade",
            "Tipo Unidade",
            "Codigo Estruturado"
        ];

        $colunas_formatado = [];
        foreach ($colunas as $coluna) {
            $colunas_formatado[] = $this->SubstituirCaracteresInapropriados($this->ReplaceEspacoPorUnderline(strtolower($coluna)));
        }

        //Processamento dos Dados do Arquivo
        $content = explode("\n", $content);
        $content_temp = $content;

        //Array de Arquivos
        $pos_util = 0;
        $pos_util_fim = 0;
        $length_util = ($pos_util_fim - $pos_util) + 1;
        if ($input['index_arquivo'] == 0) {
            $pos_util = 78;
            $pos_util_fim = 435;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 1) {
            $pos_util = 22;
            $pos_util_fim = 442;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 2) {
            $pos_util = 23;
            $pos_util_fim = 330;
            $length_util = ($pos_util_fim - $pos_util) + 1;
        }
        if ($input['index_arquivo'] == 3) {
            dd('existe?');
        }

        $content = array_slice($content, $pos_util, $length_util);
        $content[] = "";

        $comarcas = [];
        $comarca = [];
        $content_ = [];
        $comarcas_ = [];
        $comarca_ = [];
        $comarcas__ = [];
        $comarca__ = [];

        if ($input['index_arquivo'] != 4) {
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $comarca[] = $value;
                } else {
                    $comarcas[] = $comarca;
                    $comarca = [];
                }
            }
        } else {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content_[] = $value;
                }
            }

            foreach ($content_ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca[] = $value;
                } else {
                    if (count($comarca) > 0) {
                        $comarcas[] = $comarca;
                    }
                    $comarca = [];
                    $comarca[] = $value;
                }
            }
        }

        if ($input['index_arquivo'] == 0) {
            //ultimo arquivo para ceará tem tratamento diferente
            foreach ($content as $key => $value) {
                if ($value != "") {
                    $content__[] = $value;
                }
            }

            foreach ($content__ as $key => $value) {
                if (!is_numeric($value)) {
                    $comarca__[] = $value;
                } else {
                    if (count($comarca__) > 0) {
                        $comarcas__[] = $comarca__;
                    }
                    $comarca__ = [];
                    $comarca__[] = $value;
                }
            }
        }

        if ($input['index_arquivo'] == 2) {
            $comarcas__[] = ["0624", "SÃO JOÃO DA PONTE"];
            //$comarcas__[] = ["0720", "VISCONDE DO RIO BRANCO"];
        }

        if ($input['index_arquivo'] == 2) {
            $comarcas = $comarcas__;
        }

        //ajustes especificos pro arquivo 1 do ceara
        $comarcas_antes = $comarcas;
        $comarcas[42][] = "A MULHER";
        unset($comarcas[43]);

        $parte_final_comarcas = [];
        $comarcas_ = $comarcas;
        $comarcas = [];
        foreach($comarcas_ as $index_comarca => $dados_comarca) {
            if (!is_numeric($dados_comarca[0])) {
                $parte_final_comarcas[] = $dados_comarca;
            } else {
                $comarcas[] = $dados_comarca;
            }
        }

        $comarcas_sem_parte_final = [];
        $comarcas_ = $comarcas;
        $comarcas = [];
        foreach ($comarcas_ as $index_comarca => $dados_comarca) {
            $tamanho_dados = count($dados_comarca);
            $penultimo = $tamanho_dados - 2;
            if (trim($dados_comarca[$penultimo]) == "-") {
                $comarcas_sem_parte_final[] = $dados_comarca;
            } else {
                $comarcas[] = $dados_comarca;
            }
        }

        $comarcas = array_merge([["0000", "TRIBUNAL DE JUSTIÇA"]], $comarcas);
        $comarcas = array_merge([["0001", "COMARCA DE FORTALEZA - FÓRUM CLÓVIS BEVILÁQUA"]], $comarcas);
        $comarcas = array_merge([["0002", "COMARCA DE FORTALEZA - 10A. UNIDADE DO JECC - BAIRRO DE FÁTIMA"]], $comarcas);
        $comarcas = array_merge([["0003", "COMARCA DE FORTALEZA - 11A. UNIDADE DO JECC - TANCREDO NEVES"]], $comarcas);
        $comarcas = array_merge([["0004", "COMARCA DE FORTALEZA - 12A. UNIDADE DO JECC - ANEXO FÓRUM CLÓVIS BEVILÁQUA"]], $comarcas);
        $comarcas = array_merge([["0005", "COMARCA DE FORTALEZA - 12A. PRAIA DE IRACEMA"]], $comarcas);

        unset($parte_final_comarcas[0]);
        unset($parte_final_comarcas[1]);
        unset($parte_final_comarcas[2]);
        unset($parte_final_comarcas[3]);
        $parte_final_comarcas = array_values($parte_final_comarcas);

        $i = 0;
        $comarcas_corrigidas = [];
        foreach ($comarcas_sem_parte_final as $index_comarca => $dados_comarca) {
            $comarcas_corrigidas[] = array_merge($comarcas_sem_parte_final[$i], $parte_final_comarcas[$i]);
            $i++;
        }
        $comarcas = array_merge($comarcas_corrigidas, $comarcas);

        //para minas gerais precisa disso: ligar codigos com comarcas
        if ($input['index_arquivo'] <= 2) {
            if ($input['index_arquivo'] != 0) {
                unset($comarcas[50]);

                unset($comarcas[95][0]);
                $comarcas[95][0] = $comarcas[95][1];
                unset($comarcas[95][1]);
            }

            $comarcas_sem_codigo = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0]) && $dados_comarca[0][0] != "0") {
                    $comarcas_sem_codigo[] = $comarcas[$index_comarca];
                }
            }

            $codigos_sem_comarca = [];
            foreach ($comarcas as $index_comarca => $dados_comarca) {
                if ((count($dados_comarca) == 1) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    $codigos_sem_comarca[] = $dados_comarca[0];
                }
                if ((count($dados_comarca) == 2) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                    }
                }
                if ((count($dados_comarca) == 3) && (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0")) {
                    if ((is_numeric($dados_comarca[1]) && $dados_comarca[1][0] == "0")) {
                        $codigos_sem_comarca[] = $dados_comarca[0];
                        $codigos_sem_comarca[] = $dados_comarca[1];
                        $codigos_sem_comarca[] = $dados_comarca[2];
                    }
                }
            }

            if ($input['index_arquivo'] != 0) {
                $codigos_sem_comarca[] = "0249 ";
            }

            $separar_comarcas_array = [
                "canápolis",
                "capinópolis",
                "curvelo",
                "espinosa",
                "guanhães",
                "guaxupé",
                "ibirité",
                "iguatama",
                "ipatinga",
                "itabira",
                "itambacuri",
                "itamonte",
                "itapecerica",
                "leopoldina",
                "luz",
                "manga",
                "manhuaçu",
                "mercês",
                "mesquita",
                "miraí",
                "mutum",
                "palma",
                "paracatu"
            ];

            $loops_necessarios = 2;
            for ($i = 1; $i <= $loops_necessarios; $i++) {
                $comarcas_sem_codigo_ = $comarcas_sem_codigo;
                $comarcas_sem_codigo = [];
                foreach ($comarcas_sem_codigo_ as $index_comarca => $dados_comarca) {
                    $adicionado = false;
                    foreach ($separar_comarcas_array as $index_array => $nome_array) {
                        if (trim(mb_strtolower($dados_comarca[0])) == trim(mb_strtolower($nome_array))) {
                            if (count($dados_comarca) > 1) {
                                $adicionado = true;
                                $comarcas_sem_codigo[] = [$dados_comarca[0]];
                                if (isset($dados_comarca[3])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2], $dados_comarca[3]];
                                } else if (isset($dados_comarca[2])) {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1], $dados_comarca[2]];
                                } else {
                                    $comarcas_sem_codigo[] = [$dados_comarca[1]];
                                }
                            }
                        }
                    }
                    if (!$adicionado) {
                        $comarcas_sem_codigo[] = $dados_comarca;
                    }
                }
            }

            $comarcas_corretas = [];
            if ($input['index_arquivo'] != 0) {
                $comarcas_corretas[] = ["0000", "TJMG"];
            }

            if ($input['index_arquivo'] == 0) {
                foreach ($comarcas as $index_comarca => $dados_comarca) {
                    if (is_numeric($dados_comarca[0]) && $dados_comarca[0][0] == "0") {
                        if (count($dados_comarca) > 1) {
                            $comarcas_corretas[] = $comarcas[$index_comarca];
                        }
                    }
                }
            }

            if ($input['index_arquivo'] != 0) {
                unset($comarcas_corretas[55]);
                unset($comarcas_corretas[54]);
                unset($comarcas_corretas[53]);
                unset($comarcas_corretas[52]);
                unset($comarcas_corretas[51]);
                unset($comarcas_corretas[50]);
            }

            $codigos_sem_comarca_ = $codigos_sem_comarca;
            $codigos_sem_comarca = [];
            $comarcas_sem_codigo_ = $comarcas_sem_codigo;
            $comarcas_sem_codigo = [];
            if ($input['index_arquivo'] == 1) {
                foreach ($codigos_sem_comarca_ as $index_array => $codigo) {
                    $codigos_sem_comarca[] = $codigos_sem_comarca_[$index_array];
                    if (trim($codigo) == '0384') {
                        $codigos_sem_comarca[] = "0386";
                    }
                }
                $codigos_sem_comarca[] = "0477";

                foreach ($comarcas_sem_codigo_ as $index_array => $dados_comarca) {
                    $comarcas_sem_codigo[] = $comarcas_sem_codigo_[$index_array];
                    if ($index_array == 51) {
                        $comarcas_sem_codigo[] = ["EXTREMA"];
                    }
                }

                $comarcas_sem_codigo = array_merge([['FORMIGA']], $comarcas_sem_codigo);
            }

            //apenas para debug
            if ($input['index_arquivo'] == 1) {
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }

            $i = 0;
            $comarcas_ = $comarcas;

            if ($input['index_arquivo'] > 0) {
            $comarcas = [];
                foreach ($codigos_sem_comarca as $index_codigo => $codigo) {
                    $comarcas[] = array_merge([$codigos_sem_comarca[$i]], $comarcas_sem_codigo[$i]);
                    $i++;
                }
                $comarcas = array_merge($comarcas_corretas, $comarcas);
            }

            //apenas para debug
            if ($input['index_arquivo'] == 1) {
                //dd($comarcas, count($comarcas));
                dd(
                    '$comarcas',
                    $comarcas,
                    '$codigos_sem_comarca',
                    $codigos_sem_comarca,
                    '$comarcas_sem_codigo',
                    $comarcas_sem_codigo,
                    '$comarcas_corretas',
                    $comarcas_corretas,
                    'count($codigos_sem_comarca)',
                    count($codigos_sem_comarca),
                    'count($comarcas_sem_codigo)',
                    count($comarcas_sem_codigo),
                    '$this->array_has_dupes($codigos_sem_comarca)',
                    $this->array_has_dupes($codigos_sem_comarca)
                );
            }
        }

        //ajustes pontuais em cada arquivo
        if ($input['index_arquivo'] == 0) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 1) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 2) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }
        if ($input['index_arquivo'] == 3) {
            foreach($comarcas as $index_comarca => $dados_comarca) {
                if (!is_numeric($dados_comarca[0])) {
                    dd($index_comarca, $dados_comarca[0], $dados_comarca);
                }
            }
        }

        $linhas = [];
        $linha = [];
        foreach ($comarcas as $index_comarca => $dados_comarca) {
            //antes da manipulação
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $dados_comarca = array_merge(["8", "6"], $dados_comarca); //todo: ce é 6?
            //$nome_unidade = $this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $antepenultimo));
            $nome_unidade = trim($this->RemoverCaracteresInapropriados($this->MesclarColunas($dados_comarca, 3, $tamanho_dados_comarca + 1))); //essa linha no ceará é diferente
            $tipo_unidade =  trim($this->InferirTipoUnidade($nome_unidade));
            $codigo_estruturado = trim($this->MontarCodigoEstruturado($dados_comarca));
            $dados_comarca = array_merge($dados_comarca, [$tipo_unidade, $codigo_estruturado]);

            //depois da manipulação os valores mudam
            $tamanho_dados_comarca = count($dados_comarca);
            $ultimo = $tamanho_dados_comarca - 1;
            $penultimo = $ultimo - 1;
            $antepenultimo = $penultimo - 1;

            $linha['estado'] = trim(ucfirst($input['estado'])); //todo: funciona para estados com duas palavras?
            $linha['estado_sigla'] = trim(strtoupper($input['estado_sigla']));
            $linha['tipo_justica'] = trim($dados_comarca[0]);
            $linha['codigo_estado'] = trim($dados_comarca[1]);
            $linha['codigo_unidade'] = trim($dados_comarca[2]);
            $linha['nome_unidade'] = trim($nome_unidade);
            $linha['tipo_unidade'] = trim($dados_comarca[$penultimo]);
            $linha['codigo_estruturado'] = trim($this->FormatarConteudoCampo($codigo_estruturado, 'codigo_estruturadp'));

            $linhas[] = $linha;
        }

        $linhas_formatado = [];
        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $coluna => $dado) {
                $linhas_formatado[$num_linha][$coluna] = $this->FormatarConteudoCampo($dado, $coluna);
            }
        }

        //apenas para debug
        if ($input['index_arquivo'] == 1) {
            dd($linhas_formatado);
        }

        //Adicionar na tabela
        $this->CarregaTabela($input, $linhas_formatado, $colunas_formatado);
    }

    private function InferirTipoUnidade($input)
    {
        $input_orig = $input;
        $input = preg_replace('/[0-9]+/', '', $input);
        $input = trim($input);
        $nome_unidade = explode(" ", $input);
        $primeiro_nome = strtolower($nome_unidade[0]);

        if ($primeiro_nome == 'comarca') {
            return "Comarca";
        }
        else if ($primeiro_nome == 'comarcas') {
            return "Comarca";
        }
        else if ($primeiro_nome == 'tribunal') {
            return 'Tribunal';
        }
        else if ($primeiro_nome == 'tribunais') {
            return 'Tribunal';
        }
        else if ($primeiro_nome == 'foro') {
            return 'Foro';
        }
        else if ($primeiro_nome == 'foros') {
            return 'Foro';
        }
        else if ($primeiro_nome == 'fórum') {
            return 'Fórum';
        }
        else if ($primeiro_nome == 'fóruns') {
            return 'Fórum';
        }
        else if ($primeiro_nome == 'forum') {
            return 'Fórum';
        }
        else if ($primeiro_nome == 'foruns') {
            return 'Fórum';
        }
        else if ($primeiro_nome == 'turma') {
            return 'Turma Recursal';
        }
        else if ($primeiro_nome == 'turmas') {
            return 'Turma Recursal';
        }
        else if ($primeiro_nome == 'vara') {
            return 'Vara';
        }
        else if ($primeiro_nome == 'varas') {
            return 'Vara';
        }

        return "Comarca"; //todo: o que não é encontrado é comarca?
    }
    private function MontarCodigoEstruturado($input)
    {
        $input_formatado = [];
        foreach ($input as $nome_campo => $dado_campo) {
            $input_formatado[$nome_campo] = $this->FormatarConteudoCampo($dado_campo, $nome_campo);
        }
        $primeiro = $input_formatado['tipo_justica'] ?? $input_formatado[0];
        $segundo = $input_formatado['codigo_estado'] ?? $input_formatado[1];
        $terceiro = $input_formatado['codigo_unidade'] ?? $input_formatado[2];
        $final = $primeiro . '.' . $segundo . '.' . $terceiro;
        return $final;
    }

    private function Sinonimos($string)
    {
        $lista_de_caracteres = [
            'Foro de comarca' => 'Comarca'
        ];

        foreach ($lista_de_caracteres as $original => $novo) {
            $string = str_replace($original, $novo, $string);
        }

        return $string;
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

    private function MesclarColunas($array, $pos_inicial, $pos_final)
    {
        $result = "";

        $index = 0;
        foreach ($array as $key => $value) {
            if (($index >= $pos_inicial) && ($index <= $pos_final)) {
                $result .= $value;
            }
            $index++;
        }

        return $result;
    }

    private function ChecaSeTemDadosEmBranco($input, $linhas)
    {
        foreach($linhas as $index => $linha) {
            foreach ($linha as $coluna => $dado) {
                if ($dado == "" || $dado == null || $dado == " ") {
                    if (!$this->PodeSerEmBranco($coluna, $dado, $input))
                    {
                        dd("ACHEI DADO EM BRANCO!", $coluna, $dado, $input, $index, $linha);
                    }
                }
            }
        }
    }

    private function PodeSerEmBranco($coluna, $dado, $input)
    {
        $estado = $input['estado'];

        if ($estado == 'bahia') {
            if ($coluna == 'codigo_estruturado') {
                return true;
            }
        }
        return false;
    }

    private function RevisarTabela($colunas)
    {
        if (!Schema::hasTable('tribunais')) {
            Schema::create('tribunais', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->softdeletes();
            });
        }
    }

    private function RevisarColunas($colunas)
    {
        foreach ($colunas as $index_coluna => $coluna) {
            if (!Schema::hasColumn('tribunais', $coluna)) {
                Schema::table('tribunais', function (Blueprint $table) use($coluna) {
                    $table->text($coluna)->nullable();
                });
            }
        }
    }

    private function arrayParaObject($array) {
        return json_decode(json_encode($array));
    }

    private function TitleCase($string) {
        //todo: artigos e preposições?

        $string_array = explode(' ', $string);

        foreach ($string_array as $num_parte => $parte) {
            $string_array[$num_parte] = ucfirst(mb_strtolower($parte));
        }

        $result = "";
        foreach ($string_array as $parte) {
            $result .= $parte . ' ';
        }

        $result = trim($result);
        return $result;
    }
    private function CarregaTabela($input, $linhas, $colunas_formatado)
    {
        $this->ChecaSeTemDadosEmBranco($input, $linhas);
        $this->RevisarTabela($colunas_formatado);
        $this->RevisarColunas($colunas_formatado);

        foreach($linhas as $index_linha => $conteudo_linha) {
            if (!isset($conteudo_linha['descricao_unidade'])) {
                $conteudo_linha['descricao_unidade'] = "";
            }

            $dados = $this->ArrayParaObject($conteudo_linha);
            $tribunal = new Tribunais();

            //ultimas correções
            $nome_unidade_corrigido = $this->TitleCase($dados->nome_unidade);
            $descricao_unidade_corrigido = $this->TitleCase($dados->descricao_unidade);
            $estado = $this->TitleCase($dados->estado);

            $tribunal->estado = $estado;
            $tribunal->estado_sigla = $dados->estado_sigla;
            $tribunal->tipo_justica = $dados->tipo_justica;
            $tribunal->codigo_estado = $dados->codigo_estado;
            $tribunal->codigo_unidade = $dados->codigo_unidade;
            $tribunal->nome_unidade = $nome_unidade_corrigido;
            $tribunal->descricao_unidade = $descricao_unidade_corrigido;
            $tribunal->tipo_unidade = $dados->tipo_unidade;
            $tribunal->codigo_estruturado = $dados->codigo_estruturado;

            $tribunal->save();
        }
    }

    private function AjustesFinais()
    {
        $this->info("Aplicando ajustes finais nos dados...");
        $this->AjusteTurmasRecursais();
        $this->ForunsSaoComarcas();
    }

    private function AjusteTurmasRecursais()
    {
        $tribunais = Tribunais::all();

        foreach ($tribunais as $key_tribunal => $tribunal) {
            if (strtolower($tribunal->nome_unidade) == "turma recursal" || strtolower($tribunal->nome_unidade) == "turmas recursais") {
                $tribunal->tipo_unidade = "Turma Recursal";
                $tribunal->save();
            }
        }
    }

    private function ForunsSaoComarcas()
    {
        $tribunais = Tribunais::all();

        foreach ($tribunais as $key_tribunal => $tribunal) {
            if (strtolower($tribunal->tipo_unidade) == "fórum") {
                $tribunal->tipo_unidade = "Comarca";
                $tribunal->save();
            }
        }
    }

    private function handleImportarMySQLParaPostGreSQL()
    {
        $this->info("Importar dados do banco MySQL para o banco PostGreSQL...");

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

        $tb_advogado = "";
        $tb_defensoria = "";
        $tb_glossario = "";
        $tb_localidade = "";
        $tb_parte = "";
        $tb_participante = "";
        $tb_processo = "";
        $tb_procurador = "";
        $tb_quilombo = "";
        $tb_repositorio = "";
        $tb_usuario = "";

        $planilhas = [
            'processos' => $processos,
            'movs' => $movs,
            'anexos' => $anexos,
            'audiencias' => $audiencias,
            'processosRelacionados' => $processosRelacionados,
            'customs' => $customs,
            'classes' => $classes,
            'acessos' => $acessos,
            'partes' => $partes,
            'tb_advogado' => $tb_advogado,
            'tb_defensoria' => $tb_defensoria,
            'tb_glossario' => $tb_glossario,
            'tb_localidade' => $tb_localidade,
            'tb_parte' => $tb_parte,
            'tb_participante' => $tb_participante,
            'tb_processo' => $tb_processo,
            'tb_procurador' => $tb_procurador,
            'tb_quilombo' => $tb_quilombo,
            'tb_repositorio' => $tb_repositorio,
            'tb_usuario' => $tb_usuario
        ];

        foreach ($processos as $index_processo => $dados_processo) {
            dd($index_processo, $dados_processo);
        }
    }

    private function array_has_dupes($array) {
        return count($array) !== count(array_unique($array));
    }
}
