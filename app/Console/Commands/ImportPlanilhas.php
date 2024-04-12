<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use File;
use Rap2hpoutre\FastExcel\FastExcel;
use Smalot\PdfParser\Parser;
//use Spatie\PdfToText\Pdf;
use Gufy\PdfToHtml\Pdf;

class ImportPlanilhas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import-planilhas {recriar}';

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
        $files = File::files(public_path() . "\input_planilhas");

        $dropar = $this->argument('recriar');
        if ($dropar == 'sim') {
            $this->info('Selecionou para que os dados sejam recriados.. Executando limpeza prévia dos dados..');
            $this->LimparTabelas();
        } else if ($dropar == 'nao') {
            $this->info('Selecionou para manter os dados gerados anteriormente.. Não será executado nenhuma limpeza prévia dos dados...');
        } else {
            $this->info('Sua seleção sobre manter os dados gerados anteriormente ou não é inválida e não foi reconhecida.. Não será executado nenhuma limpeza prévia dos dados...');
        }

        foreach ($files as $index => $file) {
            $fileName = explode(".", str_replace(public_path(), "", $file))[0];
            $fileExtension = explode(".", str_replace(public_path(), "", $file))[1];

            $input = [
                'index' => $index,
                'file' => $file,
                'fileName' => $fileName,
                'fileExtension' => $fileExtension
            ];

            if ($fileExtension == "csv") {
                $this->ImportarCSV($input);
            } else if ($fileExtension == "pdf") {
                $this->ImportarPDF($input);
            } else {
                $this->error("Formato do arquivo '". $fileName . $fileExtension ."' não reconhecido.");
            }
        }
    }

    private function ImportarCSV($input)
    {
        $this->info("Importando arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ."...");
        $file = fopen($input['file'], 'r');
        $header = fgetcsv($file, 0, ';');

        $i = 0;
        $j = 1;
        foreach ($header as $key => $column) {
            if ($column == '') {
                $k = $i + 1;
                $header[$i] = 'CAMPO_' . $k;
                $j++;
            }
            $i++;
        }

        $rows = [];
        while ($row = fgetcsv($file, 0, ';')) {
            $rows[] = array_combine($header, $row);
        }

        fclose($file);

        foreach ($rows as $key => $row) {
            $input['nomeTabela'] = $this->gerarNomeTabela($input['fileName']);
            $this->criarTabelaSeNaoExiste($input, $row, $header);
            $this->salvarNoBancoDeDados($input, $row);
        }
    }

    private function ImportarPDF($input)
    {
        $this->info("Importando arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ."...");

        $pdfParser = new Parser();
        $pdf = $pdfParser->parseFile($input['file']);
        $content = $pdf->getText();
        dd($content);
    }

    private function salvarNoBancoDeDados($input, $row) {
        $this->info("Salvando dado do arquivo '" . $input['fileName'] ."' de formato ". $input['fileExtension'] ." no Banco de Dados...");
        //...
    }

    private function gerarNomeTabela($filename)
    {
        $nome_tabela = str_replace('-', '_', str_replace("\input_planilhas\\", '', $filename));
        $nome_tabela_array_temp = explode('_', $nome_tabela);

        $nome_tabela_array = [];
        foreach ($nome_tabela_array_temp as $key => $part) {
            if (!is_numeric($part)) {
                $nome_tabela_array[] = $part;
            }
        }

        $nome_tabela = "";
        foreach ($nome_tabela_array as $key => $part) {
            $nome_tabela .= $part . "_";
        }
        $nome_tabela = substr($nome_tabela, 0, -1);

        return $nome_tabela;
    }

    private function criarTabelaSeNaoExiste($input, $row, $header)
    {
        dd(
            [
                'input' => var_dump($input),
                'row' => var_dump($row),
                'header' => var_dump($header),
            ]
        );
    }

    private function LimparTabelas()
    {
        //...
    }
}
