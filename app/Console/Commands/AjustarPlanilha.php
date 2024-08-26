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
use Datetime;

class AjustarPlanilha extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ajustarplanilha';

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
        $inicio = Carbon::now();
        $this->info("Preparando geração da planilha...");
        ini_set('memory_limit', '20384M');

        $arquivo = public_path() . '\tabela.xlsx';
        $abas = (new FastExcel)->withSheetsNames()->importSheets($arquivo);
        $todos_processos = $this->carregarTodosProcessos($abas);

        $processos = $this->carregarTodosProcessos($abas);
        $audiencias['todas'] = $this->carregarTodasAudiencias($abas);
        $classes['todas'] = $this->carregarTodasClasses($abas);
        $partes['todas'] = $this->carregarTodasPartes($abas);

        // FILTROS
        $processos = $this->filtrarNomeParteProcessos($processos);
        $processos = $this->filtrarAreaProcessos($processos);
        $processos = $this->formatarValorProcessos($processos);
        $processos = $this->filtrarArea3Processos($processos);
        $processos = $this->filtrarAssuntoExtra($processos);
        $processos = $this->filtrarNatureza($processos);
        $processos = $this->filtrarNaturezaVazios($processos);

        $processos = $this->removerColunaPorNome($processos, 'Area');
        $processos = $this->renomearColuna($processos, 'Data distribuição', 'Distribuição');
        $processos = $this->formatarDataDistribuicao($processos);
        $processos = $this->formatarDataAudiencia($processos);
        $processos = $this->formatarDataArquivamento($processos);
        $processos = $this->formatarCitado($processos);
        $processos = $this->removerColunaPorNome($processos, 'Tipo distribuicao');
        $processos = $this->removerColunaPorNome($processos, 'Vara');
        $processos = $this->renomearColuna($processos, 'Comarca CNJ', 'Comarca');
        $processos = $this->renomearColuna($processos, 'Foro CNJ', 'Foro');
        $processos = $this->renomearColuna($processos, 'Valor', 'Valor da Ação (R$)');
        $processos = $this->removerColunaPorNome($processos, 'assuntoExtra');
        $processos = $this->removerColunaPorNome($processos, 'Penhorado');
        $processos = $this->removerColunaPorNome($processos, 'situacao');
        $processos = $this->removerColunaPorNome($processos, 'situacao');
        $processos = $this->removerColunaPorNome($processos, 'instancia');
        $processos = $this->removerColunaPorNome($processos, 'area3');
        $processos = $this->removerProcessoPorID($processos, '833');
        $processos = $this->removerProcessoPorID($processos, '622');
        $processos = $this->removerProcessoPorID($processos, '742');
        $processos = $this->removerProcessoPorID($processos, '765');
        $processos = $this->removerProcessoPorID($processos, '768');
        $processos = $this->removerProcessoPorID($processos, '141');
        $processos = $this->removerProcessoPorID($processos, '67');
        $processos = $this->removerProcessoPorID($processos, ''); //remover linhas em branco
        $processos = $this->formatarNumeroDeProcessosRelacionados($processos);
        $processos = $this->formatarFonteSistema($processos);

        $processos = $this->separarPorClasseNatureza($processos);
        $processos = $this->removerColunaPorNome($processos, 'classeNatureza');

        $processos['repetidos'] = $this->testarSeExistemProcessosRepetidos($processos);
        $audiencias['repetidos'] = $this->testarSeExistemAudicenciasRepetidasPorProcesso($audiencias['todas'], $processos);
        $classes['repetidos'] = $this->testarSeExistemClassesRepetidasPorProcesso($classes['todas'], $processos);
        $partes['repetidos'] = $this->testarSeExistemPartesRepetidasPorProcesso($partes['todas'], $processos);

        $this->gerarPlanilha($processos, $audiencias, $classes, $partes, $abas, $inicio);
    }

    private function carregarTodosProcessos($abas)
    {
        $processos = [];

        foreach ($abas as $aba_index => $aba) {
            foreach($aba as $linha_index => $linha) {
                if ($aba_index == 'Processos') {
                    $processos[] = $linha;
                }
            }
        }

        return $processos;
    }

    private function carregarTodasAudiencias($abas)
    {
        $processos = [];

        foreach ($abas as $aba_index => $aba) {
            foreach($aba as $linha_index => $linha) {
                if ($aba_index == 'Audiencias') {
                    $processos[] = $linha;
                }
            }
        }

        return $processos;
    }

    private function carregarTodasClasses($abas)
    {
        $processos = [];

        foreach ($abas as $aba_index => $aba) {
            foreach($aba as $linha_index => $linha) {
                if ($aba_index == 'Classes') {
                    $processos[] = $linha;
                }
            }
        }

        return $processos;
    }

    private function carregarTodasPartes($abas)
    {
        $processos = [];

        foreach ($abas as $aba_index => $aba) {
            foreach($aba as $linha_index => $linha) {
                if ($aba_index == 'Partes') {
                    $processos[] = $linha;
                }
            }
        }

        return $processos;
    }

    private function apenasUmProcessoPorLinha($processos_)
    {
        $processos = $processos_;

        foreach ($processos as $index_tipo => $processos_por_tipo) {
        if ($index_tipo != 'repetidos') {
                foreach ($processos_por_tipo as $index_processo => $processo) {
                    for ($i = 1; $i <= 10; $i++) {
                        unset($processos[$index_tipo][$index_processo][$i]);
                    }
                }
            }
        }

        return $processos;
    }

    private function filtrarNomeParteProcessos($processos)
    {
        foreach ($processos as $processo) {
            $parte = strtolower($processo['Relacao (parte monitorada)']);
            if (str_contains($parte, 'deprecante') || str_contains($parte, 'deprecado')) {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['resultado'][] = $processo;
            }
        }

        return $filtrados['resultado'];
    }

    private function filtrarAreaProcessos($processos)
    {
        foreach ($processos as $processo) {
            $parte = strtolower($processo['Area']);
            if (str_contains($parte, 'trabalhista')) {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['resultado'][] = $processo;
            }
        }

        return $filtrados['resultado'];
    }

    private function formatarValorProcessos($processos)
    {
        foreach ($processos as $index => $processo) {
            if (is_numeric($processo['Valor'])) {
                $processos[$index]['Valor'] = 'R$ ' . number_format($processos[$index]['Valor'], 2, ',', '');
            }
        }

        return $processos;
    }

    private function filtrarArea3Processos($processos)
    {
        $filtrados['excluidos'] = [];

        foreach ($processos as $processo) {
            $parte = strtolower($processo['area3']);
            if (str_contains($parte, 'trabalhista')) {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['resultado'][] = $processo;
            }
        }

        return $filtrados['resultado'];
    }

    private function filtrarNaturezaVazios($processos)
    {
        $filtrados['excluidos'] = [];

        foreach ($processos as $processo) {
            $parte = strtolower($processo['Natureza']);
            if ($parte == '') {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['resultado'][] = $processo;
            }
        }

        return $filtrados['resultado'];
    }

    private function moverColuna($processos, $coluna_para_mover, $coluna_onde_mover_depois)
    {
        //....
    }

    private function filtrarAssuntoExtra($processos)
    {
        $filtrados['excluidos'] = [];

        $itens_para_filtrar = [
            'Pagamento',
            'Cheque',
            'Conselho Regionais',
            'Taxa',
            'Dívida Ativa',
            'Dívida Ativa',
            'Dissolucao',
            'Titulo de credito',
            'Energia eletrica',
            'administrador provisorio',
            'honorarios',
            'mutuo',
            'ferias',
            'oficio',
            'tributo',
            'improbidade',
            'contrato',
            'apreensao',
            'conta',
            'saude',
            'esgoto',
            'educacao',
            'gestante',
            'duplicata',
            'titulo',
            'produto',
            'eleicao',
            'indigena',
            'mora',
            'lixo',
            'quitacao',
            'caluniosa',
            'iptu'
        ];

        foreach ($itens_para_filtrar as $item) {
            $itens[] = $this->semAcentos(strtolower($item));
        }

        foreach ($processos as $processo) {
            $parte = $this->semAcentos(strtolower($processo['Assunto extra']));
            $filtrar = false;
            foreach ($itens as $filtro) {
                if (str_contains($parte, $filtro)) {
                    $filtrar = true;
                }
            }
            if ($filtrar) {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['resultado'][] = $processo;
            }
        }

        return $filtrados['resultado'];
    }

    private function filtrarNatureza($processos)
    {
        $filtrados['excluidos'] = [];

        $itens_para_filtrar = [
            'titulo extrajudicial',
            'Execucao fiscal',
            'divida',
            'embargos',
            'monitoria',
            'habeas',
            'alienação',
            'fiscal',
        ];

        foreach ($itens_para_filtrar as $item) {
            $itens[] = $this->semAcentos(strtolower($item));
        }

        foreach ($processos as $processo) {
            $parte = $this->semAcentos(strtolower($processo['Natureza']));
            $filtrar = false;
            foreach ($itens as $filtro) {
                if (str_contains($parte, $filtro)) {
                    $filtrar = true;
                }
            }
            if ($filtrar) {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['resultado'][] = $processo;
            }
        }

        return $filtrados['resultado'];
    }


function semAcentos($string) {
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
    private function removerColunaPorNome($processos, $coluna_selecionada)
    {
        $processos_ = $processos;

        foreach ($processos_ as $index_processo => $processo) {
            unset($processos_[$index_processo][$coluna_selecionada]);
        }

        return $processos_;
    }

    private function removerColunaPorNomeDosDados($dados__, $nome_planilha, $coluna_selecionada)
    {
        $dados = $dados__;

        foreach($dados as $index_tipo_dado => $tipo_dado) {
            if ($index_tipo_dado == $nome_planilha) {
                foreach($tipo_dado as $nome_dado => $dados_) {
                    if ($nome_dado == 'colunas') {
                        $onde_esta_coluna_selecionada = 0;
                        foreach ($dados_ as $index_dado => $dado) {
                            if ($dado == $coluna_selecionada) {
                                $onde_esta_coluna_selecionada = $index_dado;
                            }
                        }
                        unset($dados[$index_tipo_dado][$nome_dado][$onde_esta_coluna_selecionada]);
                    }

                    if ($nome_dado == 'linhas') {
                        foreach ($dados_ as $index_dado => $dado) {
                            unset($dados[$index_tipo_dado][$nome_dado][$index_dado][$coluna_selecionada]);
                        }
                    }
                }
            }
        }

        return $dados[$nome_planilha];
    }

    private function removerProcessoPorID($processos, $id)
    {
        $filtrados['excluidos'] = [];

        foreach ($processos as $processo) {
            $parte = strtolower($processo['id']);
            if ($parte == $id) {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['resultado'][] = $processo;
            }
        }

        return $filtrados['resultado'];
    }

    private function renomearColuna($processos, $nome_antigo, $nome_novo)
    {
        $processos_ = $processos;
        $temp_processos = [];

        foreach ($processos_ as $index_processo => $processo) {
            $temp_processo = [];
            foreach ($processo as $nome_coluna => $conteudo_coluna) {
                if ($nome_coluna != $nome_antigo) {
                    $temp_processo[$nome_coluna] = $conteudo_coluna;
                } else {
                    $temp_processo[$nome_novo] = $conteudo_coluna;
                }
            }
            $temp_processos[$index_processo] = $temp_processo;
        }

        return $temp_processos;
    }

    private function formatarDataDistribuicao($processos)
    {
        foreach ($processos as $index => $processo) {
            $processos[$index]['Distribuição'] = substr($processos[$index]['Distribuição'], 0, 10);
        }

        return $processos;
    }

    private function formatarDataAudiencia($processos)
    {
        foreach ($processos as $index => $processo) {
            $processos[$index]['Data Audiência'] = substr($processos[$index]['Data Audiência'], 0, 10);
        }

        return $processos;
    }

    private function formatarDataArquivamento($processos)
    {
        foreach ($processos as $index => $processo) {
            $processos[$index]['Data arquivamento'] = substr($processos[$index]['Data arquivamento'], 0, 10);
        }

        return $processos;
    }

    private function formatarCitado($processos)
    {
        foreach ($processos as $index => $processo) {
            $processos[$index]['Citado'] = substr($processos[$index]['Citado'], 0, 10);
        }

        return $processos;
    }

    private function validateDate($date, $format = 'Y-m-d')
    {
        if (gettype($date) == 'object') {
            if (get_class($date) == 'DateTimeImmutable') {
                $date = $date->format('Y-m-d');
            }
        }
        try {
            $d = DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) == $date;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function validateDatetime($date, $format = 'Y-m-d H:i:s')
    {
        if (gettype($date) == 'object') {
            if (get_class($date) == 'DateTimeImmutable') {
                $date = $date->format('Y-m-d H:i:s');
            }
        }
        try {
            $d = DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) == $date;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatarNumeroDeProcessosRelacionados($processos)
    {
        foreach ($processos as $index => $processo) {
            if ($this->validateDate($processos[$index]['numero_de_processosRelacionados'])) {
                $processos[$index]['numero_de_processosRelacionados'] = null;
            }
        }

        return $processos;
    }

    private function formatarFonteSistema($processos)
    {
        foreach ($processos as $index => $processo) {
            $processos[$index]['fonte_sistema'] = explode(' ', $processos[$index]['fonte_sistema'])[0];
        }

        return $processos;
    }

    private function separarPorClasseNatureza($processos)
    {
        $filtrados['excluidos'] = [];

        foreach ($processos as $processo) {
            $parte = strtolower($processo['classeNatureza']);

            if (str_contains($parte, 'precat') || str_contains($parte, 'cartprec')) {
                $filtrados['precatorios'][] = $processo;
            } else if (str_contains($parte, 'execução fiscal') || str_contains($parte, 'exfis')) {
                $filtrados['excluidos'][] = $processo;
            } else {
                $filtrados['processos'][] = $processo;
            }
        }

        return $filtrados;
    }

    private function testarSeExistemProcessosRepetidos($processos)
    {
        $processos_processos = [];
        $processos_precatorios = [];

        foreach ($processos['processos'] as $processo) {
            $num_processo = $processo['Processo'];
            if (array_key_exists($num_processo, $processos_processos)) {
                $processos_processos[$num_processo] = $processos_processos[$num_processo] + 1;
            } else {
                $processos_processos[$num_processo] = 1;
            }
        }

        $temp_processos = $processos_processos;
        $processos_processos = [];
        foreach ($temp_processos as $num_processo => $qtd_processo) {
            if ($qtd_processo > 1) {
                $processos_processos[$num_processo] = $qtd_processo;
            }
        }

        foreach ($processos['precatorios'] as $processo) {
            $num_processo = $processo['Processo'];
            if (array_key_exists($num_processo, $processos_precatorios)) {
                $processos_precatorios[$num_processo] = $processos_precatorios[$num_processo] + 1;
            } else {
                $processos_precatorios[$num_processo] = 1;
            }
        }

        $temp_processos = $processos_precatorios;
        $processos_precatorios = [];
        foreach ($temp_processos as $num_processo => $qtd_processo) {
            if ($qtd_processo > 1) {
                $processos_precatorios[$num_processo] = $qtd_processo;
            }
        }

        $maior = 0;
        foreach ($processos_processos as $num_processo => $qtd_processo) {
            if ($qtd_processo > $maior) {
                $processos_processos['maior'] = [];
                $processos_processos['maior'][$num_processo] = $qtd_processo;
                $maior = $qtd_processo;
            }
        }

        $maior = 0;
        foreach ($processos_precatorios as $num_processo => $qtd_processo) {
            if ($qtd_processo > $maior) {
                $processos_precatorios['maior'] = [];
                $processos_precatorios['maior'][$num_processo] = $qtd_processo;
                $maior = $qtd_processo;
            }
        }

        $retorno['processos'] = $processos_processos;
        $retorno['precatorios'] = $processos_precatorios;
        return $retorno;
    }

    private function testarSeExistemAudicenciasRepetidasPorProcesso($audiencias, $processos)
    {
        $audiencias_processos = [];
        $lista_processos['processos'] = [];
        $lista_processos['precatorios'] = [];

        foreach ($processos['processos'] as $processo) {
            $num_processo = $processo['Processo'];
            if (!(in_array($num_processo, $lista_processos['processos']))) {
                $lista_processos['processos'][] = $num_processo;
            }
        }

        foreach ($processos['precatorios'] as $processo) {
            $num_processo = $processo['Processo'];
            if (!(in_array($num_processo, $lista_processos['precatorios']))) {
                $lista_processos['precatorios'][] = $num_processo;
            }
        }

        foreach ($audiencias as $audiencia) {
            $num_processo = $audiencia['processo'];
            if (array_key_exists($num_processo, $audiencias_processos)) {
                $audiencias_processos[$num_processo] = $audiencias_processos[$num_processo] + 1;
            } else {
                $audiencias_processos[$num_processo] = 1;
            }
        }

        $temp_processos = $audiencias_processos;
        $audiencias_processos = [];
        foreach ($temp_processos as $num_processo => $qtd_processo) {
            if (in_array($num_processo, $lista_processos['processos']) || in_array($num_processo, $lista_processos['precatorios'])) {
                if ($qtd_processo > 1) {
                    $audiencias_processos[$num_processo] = $qtd_processo;
                }
            }
        }

        $maior = 0;
        foreach ($audiencias_processos as $num_processo => $qtd_processo) {
            if ($qtd_processo > $maior) {
                $audiencias_processos['maior'] = [];
                $audiencias_processos['maior'][$num_processo] = $qtd_processo;
                $maior = $qtd_processo;
            }
        }

        return $audiencias_processos;
    }

    private function testarSeExistemClassesRepetidasPorProcesso($classes, $processos)
    {
        $classes_processos = [];
        $lista_processos['processos'] = [];
        $lista_processos['precatorios'] = [];

        foreach ($processos['processos'] as $processo) {
            $num_processo = $processo['Processo'];
            if (!(in_array($num_processo, $lista_processos['processos']))) {
                $lista_processos['processos'][] = $num_processo;
            }
        }

        foreach ($processos['precatorios'] as $processo) {
            $num_processo = $processo['Processo'];
            if (!(in_array($num_processo, $lista_processos['precatorios']))) {
                $lista_processos['precatorios'][] = $num_processo;
            }
        }

        foreach ($classes as $classe) {
            $num_processo = $classe['processo'];
            if (array_key_exists($num_processo, $classes_processos)) {
                $classes_processos[$num_processo] = $classes_processos[$num_processo] + 1;
            } else {
                $classes_processos[$num_processo] = 1;
            }
        }

        $temp_processos = $classes_processos;
        $classes_processos = [];
        foreach ($temp_processos as $num_processo => $qtd_processo) {
            if (in_array($num_processo, $lista_processos['processos']) || in_array($num_processo, $lista_processos['precatorios'])) {
                if ($qtd_processo > 1) {
                    $classes_processos[$num_processo] = $qtd_processo;
                }
            }
        }

        $maior = 0;
        foreach ($classes_processos as $num_processo => $qtd_processo) {
            if ($qtd_processo > $maior) {
                $classes_processos['maior'] = [];
                $classes_processos['maior'][$num_processo] = $qtd_processo;
                $maior = $qtd_processo;
            }
        }

        return $classes_processos;
    }

    private function testarSeExistemPartesRepetidasPorProcesso($partes, $processos)
    {
        $partes_processos = [];
        $lista_processos['processos'] = [];
        $lista_processos['precatorios'] = [];

        foreach ($processos['processos'] as $processo) {
            $num_processo = $processo['Processo'];
            if (!(in_array($num_processo, $lista_processos['processos']))) {
                $lista_processos['processos'][] = $num_processo;
            }
        }

        foreach ($processos['precatorios'] as $processo) {
            $num_processo = $processo['Processo'];
            if (!(in_array($num_processo, $lista_processos['precatorios']))) {
                $lista_processos['precatorios'][] = $num_processo;
            }
        }

        foreach ($partes as $parte) {
            $num_processo = $parte['processo'];
            if (array_key_exists($num_processo, $partes_processos)) {
                $partes_processos[$num_processo] = $partes_processos[$num_processo] + 1;
            } else {
                $partes_processos[$num_processo] = 1;
            }
        }

        $temp_processos = $partes_processos;
        $partes_processos = [];
        foreach ($temp_processos as $num_processo => $qtd_processo) {
            if (in_array($num_processo, $lista_processos['processos']) || in_array($num_processo, $lista_processos['precatorios'])) {

                if ($qtd_processo > 1) {
                    $partes_processos[$num_processo] = $qtd_processo;
                }
            }
        }

        $maior = 0;
        foreach ($partes_processos as $num_processo => $qtd_processo) {
            if ($qtd_processo > $maior) {
                $partes_processos['maior'] = [];
                $partes_processos['maior'][$num_processo] = $qtd_processo;
                $maior = $qtd_processo;
            }
        }

        return $partes_processos;
    }

    private function gerarPlanilha($processos, $audiencias, $classes, $partes, $abas, $inicio)
    {
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=file.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $planilhas_validas = [
            'processos',
            'precatorios',
            //'excluidos'
        ];
        $dados = $this->juntarTudoEmProcessos($processos, $audiencias, $classes, $partes);
        $dados = $this->apenasUmProcessoPorLinha($dados); //filtro
        $dados['repetidos'] = $processos['repetidos'];

        $colunas_processos = array_keys($processos['processos'][array_key_first($processos['processos'])]);
        $colunas_audiencias = array_keys($audiencias[array_key_first($audiencias)][0]);
        $colunas_classes = array_keys($classes[array_key_first($classes)][0]);
        $colunas_partes = array_keys($partes[array_key_first($partes)][0]);

        $num_max_processos_processos = array_values($processos['repetidos']['processos']['maior'])[0];
        $num_max_audiencias_processos = array_values($audiencias['repetidos']['maior'])[0];
        $num_max_classes_processos = array_values($classes['repetidos']['maior'])[0];
        $num_max_partes_processos = array_values($partes['repetidos']['maior'])[0];

        $num_max_processos_precatorios = array_values($processos['repetidos']['precatorios']['maior'])[0];
        $num_max_audiencias_precatorios = array_values($audiencias['repetidos']['maior'])[0];
        $num_max_classes_precatorios = array_values($classes['repetidos']['maior'])[0];
        $num_max_partes_precatorios = array_values($partes['repetidos']['maior'])[0];

        if ($num_max_processos_processos > $num_max_processos_precatorios) {
            $num_max_processos = $num_max_processos_processos;
        } else {
            $num_max_processos = $num_max_processos_precatorios;
        }
        $num_max_processos = 1; //ajuste manual após debug (buscar a causa?)

        if ($num_max_audiencias_processos > $num_max_audiencias_precatorios) {
            $num_max_audiencias = $num_max_audiencias_processos;
        } else {
            $num_max_audiencias = $num_max_audiencias_precatorios;
        }
        $num_max_audiencias += 7; //ajuste manual após debug (buscar a causa?)

        if ($num_max_classes_processos > $num_max_classes_precatorios) {
            $num_max_classes = $num_max_classes_processos;
        } else {
            $num_max_classes = $num_max_classes_precatorios;
        }
        $num_max_classes += 5; //ajuste manual após debug (buscar a causa?)

        if ($num_max_partes_processos > $num_max_partes_precatorios) {
            $num_max_partes = $num_max_partes_processos;
        } else {
            $num_max_partes = $num_max_partes_precatorios;
        }
        $num_max_partes = 736; //ajuste manual após debug (buscar a causa?)

        for ($i = 0; $i < $num_max_processos; $i++) {
            foreach ($colunas_processos as $index_coluna => $nome_coluna) {
                $colunas[] = $nome_coluna . "_processo_" . ($i + 1);
            }
        }
        $colunas[] = 'Numero de Processos';
        $colunas[] = 'Numero de Audiencias';
        $colunas[] = 'Numero de Classes';
        $colunas[] = 'Numero de Partes';

        for ($i = 0; $i < $num_max_audiencias; $i++) {
            foreach ($colunas_audiencias as $index_coluna => $nome_coluna) {
                $colunas[] = $nome_coluna . "_audiencias_" . ($i + 1);
            }
        }

        for ($i = 0; $i < $num_max_classes; $i++) {
            foreach ($colunas_classes as $index_coluna => $nome_coluna) {
                $colunas[] = $nome_coluna . "_classes_" . ($i + 1);
            }
        }

        //* todo: partes ta fazendo exceder numero limite de colunas
        for ($i = 0; $i < $num_max_partes; $i++) {
            foreach ($colunas_partes as $index_coluna => $nome_coluna) {
                $colunas[] = $nome_coluna . "_partes_" . ($i + 1);
            }
        }
        //*/

        foreach ($dados as $nome_planilha => $dados_planilha) {
            if (in_array($nome_planilha, $planilhas_validas)) {
                $linhas = [];
                $i = 0;
                foreach ($dados_planilha as $num_processo => $processo) {
                    $i++;
                    $linhas[] = $this->gerarLinha($dados_planilha, $i, $num_processo, $processo, $num_max_processos, $num_max_audiencias, $num_max_classes, $num_max_partes, $colunas_processos, $colunas_audiencias, $colunas_classes, $colunas_partes, $colunas);
                }

                $linhas_e_colunas[$nome_planilha] = $this->limparColunasVazias($linhas, $colunas, $inicio, $nome_planilha);

                $total_de_processos = count($linhas_e_colunas[$nome_planilha]['linhas']);
                $processos_count = 0;
            }
        }

        //filtro
        foreach ($linhas_e_colunas as $nome_planilha => $dados_planilha) {
            foreach ($dados_planilha['colunas'] as $index_coluna => $coluna) {
                $linhas_e_colunas[$nome_planilha]['colunas'][$index_coluna] = str_replace('_processo_1', '', $coluna);
            }
        }

        //INICIO DA GERAÇÃO DA PLANILHA
        foreach ($dados as $nome_planilha => $dados_planilha) {
            $filename = '/planilhas_filtradas' . '/' .  $nome_planilha . '.csv';
            $filename_full = public_path() . $filename;

            if (!file_exists(public_path() . '/planilhas_filtradas')) {
                mkdir(public_path() . '/planilhas_filtradas', 0777, true);
            }

            $file = fopen($filename_full, 'w');

            if (in_array($nome_planilha, $planilhas_validas)) {
                //a função abaixo serve para separar as colunas de "partes" dos outros processos
                //ela existe porque alguns processos tem muitas partes e isso pode prejudicar a visualização da planilha
                $linhas_e_colunas[$nome_planilha] = $this->separarPartesDeProcessosComMuitasPartes($linhas_e_colunas[$nome_planilha], $dados, $nome_planilha);

                //filtro - remover audiencias, classes, partes
                $linhas_e_colunas[$nome_planilha] = $this->separarProcessosEPartes($linhas_e_colunas[$nome_planilha], $dados, $nome_planilha);
                $linhas_e_colunas[$nome_planilha] = $this->separarProcessosEClasses($linhas_e_colunas[$nome_planilha], $dados, $nome_planilha);
                $linhas_e_colunas[$nome_planilha] = $this->separarProcessosEAudiencias($linhas_e_colunas[$nome_planilha], $dados, $nome_planilha);

                //filtro - remover numero de audiencias(?), classes, partes(?)
                //$linhas_e_colunas[$nome_planilha] = $this->removerColunaPorNomeDosDados($linhas_e_colunas, $nome_planilha, 'Numero de Processos');
                //$linhas_e_colunas[$nome_planilha] = $this->removerColunaPorNomeDosDados($linhas_e_colunas, $nome_planilha, 'Numero de Audiencias');
                $linhas_e_colunas[$nome_planilha] = $this->removerColunaPorNomeDosDados($linhas_e_colunas, $nome_planilha, 'Numero de Classes');
                //$linhas_e_colunas[$nome_planilha] = $this->removerColunaPorNomeDosDados($linhas_e_colunas, $nome_planilha, 'Numero de Partes');

                fputcsv($file, $linhas_e_colunas[$nome_planilha]['colunas'], ';');

                foreach ($linhas_e_colunas[$nome_planilha]['linhas'] as $num_linha => $linha) {
                    $processos_count++;
                    $porcentagem_total = $this->calcularPorcentagem($processos_count, $total_de_processos);

                    $this->info("------------------------------------------------------------------------");
                    $this->info("Processo {$processos_count} de {$total_de_processos} [{$porcentagem_total}%]:");
                    $this->info("Tempo decorrido até agora: {$this->calculaTempo($inicio, Carbon::now())}");

                    fputcsv($file, $linha, ';');
                }

                $this->info("------------------------------------------------------------------------");
                fclose($file);
            }
        }

        $this->info("Planilhas gerado com sucesso! =)");
    }

    private function calculaTempo($inicio, $fim) {
        $seconds = $fim->diffInSeconds($inicio);
        $output = sprintf('%02d:%02d:%02d', ($seconds/ 3600),($seconds/ 60 % 60), $seconds% 60);
        return $output;
    }

    private function calcularPorcentagem($atual, $total) {
        return round(($atual / $total) * 100);
    }

    private function juntarTudoEmProcessos($processos, $audiencias, $classes, $partes)
    {
        $dados = [];
        $abas_validas = ['processos', 'precatorios', 'excluidos'];

        foreach ($processos['processos'] as $processo) {
            $num_processo = $processo['Processo'];
            $dados['processos'][$num_processo][] = $processo;
        }

        foreach ($processos['precatorios'] as $processo) {
            $num_processo = $processo['Processo'];
            $dados['precatorios'][$num_processo][] = $processo;
        }

        $abas = $processos;
        foreach($abas as $nome_aba => $aba) {
            if (!array_key_exists($nome_aba, $dados)) {
                $dados[$nome_aba] = [];
            }
        }

        foreach ($abas as $aba => $aba_dados) {
            foreach ($aba_dados as $processo) {
                if (in_array($aba, $abas_validas)) {
                    $num_processo = $processo['Processo'];

                    foreach ($audiencias['todas'] as $audiencia) {
                        if ($audiencia['processo'] == $num_processo) {
                            $dados[$aba][$num_processo]['audiencias'][] = $audiencia;
                        }
                    }

                    foreach ($classes['todas'] as $classe) {
                        if ($classe['processo'] == $num_processo) {
                            $dados[$aba][$num_processo]['classes'][] = $classe;
                        }
                    }

                    foreach ($partes['todas'] as $parte) {
                        if ($parte['processo'] == $num_processo) {
                            $dados[$aba][$num_processo]['partes'][] = $parte;
                        }
                    }
                }
            }
        }

        return $dados;
    }

    private function gerarLinha($dados_planilha, $index, $num_processo, $processo, $num_max_processos, $num_max_audiencias, $num_max_classes, $num_max_partes, $colunas_processos, $colunas_audiencias, $colunas_classes, $colunas_partes, $colunas)
    {
        $linha = [];

        //PROCESSOS
        $dados_dos_processos = $processo;
        unset($dados_dos_processos['audiencias']);
        unset($dados_dos_processos['classes']);
        unset($dados_dos_processos['partes']);

        $qtd_processos = count($dados_dos_processos);
        $nomes_colunas_processos = array_values($colunas_processos);

        for ($i = $qtd_processos; $i < $num_max_processos; $i++) {
            $dados_dos_processos[] = $this->criarProcessoVazio($nomes_colunas_processos);
        }

        foreach ($dados_dos_processos as $processo_dados) {
            foreach ($processo_dados as $nome_coluna => $dado_coluna) {
                $linha[] = $dado_coluna;
            }
        }

        if (!array_key_exists('audiencias', $processo)) {
            $processo['audiencias'] = [];
        }

        if (!array_key_exists('classes', $processo)) {
            $processo['classes'] = [];
        }

        if (!array_key_exists('partes', $processo)) {
            $processo['partes'] = [];
        }

        //ADICIONAR NUMEROS DE PROCESSOS, AUDIENCIAS, CLASSSES E PARTES
        $linha[] = $qtd_processos;
        $linha[] = count($processo['audiencias']);
        $linha[] = count($processo['classes']);
        $linha[] = count($processo['partes']);

        //AUDIENCIAS
        $dados_das_audiencias = $processo['audiencias'];
        $qtd_audiencias = count($dados_das_audiencias);
        $nomes_colunas_audiencias = array_values($colunas_audiencias);

        for ($i = $qtd_audiencias; $i < $num_max_audiencias; $i++) {
            $dados_das_audiencias[] = $this->criarAudienciaVazia($nomes_colunas_audiencias);
        }

        foreach ($dados_das_audiencias as $audiencia_dados) {
            foreach ($audiencia_dados as $nome_coluna => $dado_coluna) {
                $linha[] = $dado_coluna;
            }
        }

        //CLASSES
        $dados_das_classes = $processo['classes'];
        $qtd_classes = count($dados_das_classes);
        $nomes_colunas_classes = array_values($colunas_classes);

        for ($i = $qtd_classes; $i < $num_max_classes; $i++) {
            $dados_das_classes[] = $this->criarClasseVazia($nomes_colunas_classes);
        }

        foreach ($dados_das_classes as $classe_dados) {
            foreach ($classe_dados as $nome_coluna => $dado_coluna) {
                $linha[] = $dado_coluna;
            }
        }

        //PARTES
        //* todo: partes ta fazendo exceder numero limite de colunas
        $dados_das_partes = $processo['partes'];
        $qtd_partes = count($dados_das_partes);
        $nomes_colunas_partes = array_values($colunas_partes);

        for ($i = $qtd_partes; $i < $num_max_partes; $i++) {
            $dados_das_partes[] = $this->criarParteVazia($nomes_colunas_partes);
        }

        foreach ($dados_das_partes as $parte_dados) {
            foreach ($parte_dados as $nome_coluna => $dado_coluna) {
                $linha[] = $dado_coluna;
            }
        }
        //*/

        $todos_os_dados = [
            'dados_planilha' => $dados_planilha,
            'num_processo' => $num_processo,
            'processo' => $processo,
            'num_max_processos' => $num_max_processos,
            'num_max_audiencias' => $num_max_audiencias,
            'num_max_classes' => $num_max_classes,
            'num_max_partes' => $num_max_partes,
            'colunas_processos' => $colunas_processos,
            'colunas_audiencias' => $colunas_audiencias,
            'colunas_classes' => $colunas_classes,
            'colunas_partes' => $colunas_partes,
            'colunas' => $colunas
        ];

        $linha = $this->validaTiposDasColunas($colunas, $linha);

        if (!$this->validaNumeroDeColunas($colunas, $linha, $todos_os_dados)) {
            $this->error("Numero de Colunas não bate com a quantidade de dado para a linha!");
            dd([
                "Erro!",
                '$index',
                $index,
                '$num_processo',
                $num_processo,
                '$processo',
                $processo,
                //'$colunas',
                //$colunas,
                //'end($colunas)',
                //end($colunas),
                //'end($linha)',
                //end($linha),
                //'end($linha_coluna)',
                //end($linha_coluna),
                //'$linha',
                //$linha,
                'count($colunas)',
                count($colunas),
                'count($linha)',
                count($linha),
                //'linha_coluna',
                //$linha_coluna,
                //'debug["processo"]',
                //$debug['processo'],
                //'debug',
                //$debug,
                //'dados_planilha',
                //$dados_planilha,
                'num_max_processos',
                $num_max_processos,
                'num_max_audiencias',
                $num_max_audiencias,
                'num_max_classes',
                $num_max_classes,
                'num_max_partes',
                $num_max_partes,
                //'colunas_processos',
                //$colunas_processos,
                //'colunas_audiencias',
                //$colunas_audiencias,
                //'colunas_classes',
                //$colunas_classes,
                //'colunas_partes',
                //$colunas_partes,
                'count(colunas_processos)',
                count($colunas_processos),
                'count(colunas_audiencias)',
                count($colunas_audiencias),
                'count(colunas_classes',
                count($colunas_classes),
                'count(colunas_partes',
                count($colunas_partes),
                'count($processo[audiencias]',
                count($processo['audiencias']),
                'count($processo[classes]',
                count($processo['classes']),
                'count($processo[partes]',
                count($processo['partes']),
            ]);
        }

        return $linha;
    }

    private function criarProcessoVazio($nomes_colunas)
    {
        foreach ($nomes_colunas as $index_nome => $nome) {
            $processo[$nome] = null;
        }
        return $processo;
    }

    private function criarAudienciaVazia($nomes_colunas)
    {
        foreach ($nomes_colunas as $index_nome => $nome) {
            $processo[$nome] = null;
        }
        return $processo;
    }

    private function criarClasseVazia($nomes_colunas)
    {
        foreach ($nomes_colunas as $index_nome => $nome) {
            $processo[$nome] = null;
        }
        return $processo;
    }

    private function criarParteVazia($nomes_colunas)
    {
        foreach ($nomes_colunas as $index_nome => $nome) {
            $processo[$nome] = null;
        }
        return $processo;
    }

    private function validaNumeroDeColunas($colunas, $linha, $todos_os_dados)
    {
        $debug = $todos_os_dados;
        unset($debug['dados_planilha']);
        unset($debug['processo']['partes']);

        for ($i = 0; $i < count($linha); $i++) {
            $linha_coluna_temp[$i] = $colunas[$i] ?? $i;
        }

        for ($i = 0; $i < count($linha); $i++) {
            $linha_coluna[$i . ": " . $linha_coluna_temp[$i]] = $linha[$i];
        }

        if (count($colunas) != count($linha)) {
            return false;
        }
        return true;
    }

    private function validaTiposDasColunas($colunas, $dados_linha)
    {
        $tipos_conhecidos = ['string', 'integer', 'null', 'double'];
        $debug = false;

        $linha = $dados_linha;
        foreach ($linha as $posicao_dado => $dado) {
            if (!in_array(strtolower(gettype($dado)), $tipos_conhecidos)) {
                if (gettype($dado) == 'object') {
                    if (get_class($dado) == 'DateTimeImmutable') {
                        $linha[$posicao_dado] = $dado->format('Y-m-d');
                    }
                    else {
                        dd([
                            "Tipo desconhecido: " . gettype($dado),
                            "Dado: " . var_dump($dado)
                        ]);
                    }
                } else {
                    dd([
                        "Tipo desconhecido: " . gettype($dado),
                        "Dado: " . var_dump($dado)
                    ]);
                }
            }
        }

        return $linha;
    }

    private function limparColunasVazias($linhas, $colunas_, $inicio, $aba)
    {
        $linhas_e_colunas = [];
        $colunas_e_linhas = [];

        foreach ($linhas as $num_linha => $linha) {
            foreach ($linha as $index_coluna => $valor_coluna) {
                $nome_coluna = $colunas_[$index_coluna];
                $linhas_e_colunas[$num_linha][$nome_coluna] = $valor_coluna;
                $colunas_e_linhas[$nome_coluna][$num_linha] = $valor_coluna;
            }
        }

        $colunas_vazias = [];
        foreach ($colunas_e_linhas as $nome_coluna => $colunas) {
            $vazio = true;
            foreach ($colunas as $num_linha => $valor_coluna) {
                if (!is_null($valor_coluna)) {
                    $vazio = false;
                }
            }

            if ($vazio) {
                $colunas_vazias[] = $nome_coluna;
            }
        }

        $colunas_sem_as_vazias = [];
        foreach($colunas_ as $index_coluna => $coluna) {
            if (!in_array($coluna, $colunas_vazias)) {
                $colunas_sem_as_vazias[] = $coluna;
            }
        }

        $retorno['colunas'] = $colunas_sem_as_vazias;
        $retorno['linhas'] = $colunas_e_linhas; //temp

        foreach($colunas_vazias as $index_coluna => $nome_coluna) {
            unset($retorno['linhas'][$nome_coluna]);
        }

        $temp_retorno = $retorno;
        $retorno['linhas'] = [];
        foreach ($temp_retorno['linhas'] as $nome_coluna => $linhas_) {
            foreach ($linhas_ as $num_linha => $valor_coluna) {
                $retorno['linhas'][$num_linha][$nome_coluna] = $valor_coluna;
            }
        }

        /*
        $todos_os_dados = [];
        foreach($retorno['linhas'] as $num_linha => $linha) {
            if (!$this->validaNumeroDeColunas($retorno['colunas'], $linha, $todos_os_dados)) {
                $this->error("Numero de Colunas não bate com a quantidade de dado para a linha!");
                dd(
                    //'$retorno[linhas]',
                    //$retorno['linhas'],
                    '$retorno[colunas]',
                    $retorno['colunas'],
                    '$linha',
                    $linha,
                    '$num_linha',
                    $num_linha,
                    //'$todos_os_dados'
                    //$todos_os_dados
                );
            }
        }
        */

        return $retorno;
    }

    private function separarPartesDeProcessosComMuitasPartes($dados, $dados_originais, $aba)
    {
        $dados['todas_colunas'] = $dados['colunas'];
        $dados['todas_linhas'] = $dados['linhas'];
        $dados['colunas'] = [];
        $dados['linhas'] = [];
        $dados['colunas_partes'] = [];
        $dados['linhas_partes'] = [];

        $partes_por_processo = $this->numeroDePartesPorProcesso($dados);
        $processos_com_muitas_partes = [];
        $num_partes_processos_com_muitas_partes = [];
        $quantos_processos = 1;

        $i = 0;
        foreach ($partes_por_processo as $processo_ => $num_partes_) {
            if ($i < $quantos_processos) {
                $processos_com_muitas_partes[] = $processo_;
                $i++;
            }
        }

        $i = 0;
        foreach ($partes_por_processo as $processo_ => $num_partes_) {
            if ($i < $quantos_processos) {
                $num_partes_processos_com_muitas_partes[] = $num_partes_;
                $i++;
            }
        }

        dd($processos_com_muitas_partes, $num_partes_processos_com_muitas_partes);

        $eh_partes = false;
        $index_comeca_partes = 0;
        foreach ($dados['todas_colunas'] as $index => $coluna) {
            if ($coluna == 'id_partes_1') {
                $eh_partes = true;
                $index_comeca_partes = $index;
            }

            if (!$eh_partes) {
                $dados['colunas'][] = $coluna;
            } else {
                $dados['colunas_partes'][] = $coluna;
            }
        }

        foreach ($dados['todas_linhas'] as $index => $conteudo) {
            $linha = [];
            $linha_partes = [];
            $i = 0;
            foreach ($conteudo as $index_ => $conteudo_) {
                if ($i < $index_comeca_partes) {
                    $linha[$index_] = $conteudo_;
                } else {
                    $linha_partes[$index_] = $conteudo_;
                }
                $i++;
            }
            $dados['linhas'][] = $linha;
            $dados['linhas_partes'][] = $linha_partes;
        }

        return $dados;
    }

    private function separarProcessosEPartes($dados, $dados_originais, $aba)
    {
        $dados['todas_colunas'] = $dados['colunas'];
        $dados['todas_linhas'] = $dados['linhas'];
        $dados['colunas'] = [];
        $dados['linhas'] = [];
        $dados['colunas_partes'] = [];
        $dados['linhas_partes'] = [];

        $partes_por_processo = $this->numeroDePartesPorProcesso($dados);

        $processos_com_muitas_partes = [];
        $quantos_processos = 1;
        $i = 0;
        foreach ($partes_por_processo as $processo_ => $num_partes_) {
            if ($i < $quantos_processos) {
                $processos_com_muitas_partes[] = $processo_;
                $i++;
            }
        }

        $eh_partes = false;
        $index_comeca_partes = 0;
        foreach ($dados['todas_colunas'] as $index => $coluna) {
            if ($coluna == 'id_partes_1') {
                $eh_partes = true;
                $index_comeca_partes = $index;
            }

            if (!$eh_partes) {
                $dados['colunas'][] = $coluna;
            } else {
                $dados['colunas_partes'][] = $coluna;
            }
        }

        foreach ($dados['todas_linhas'] as $index => $conteudo) {
            $linha = [];
            $linha_partes = [];
            $i = 0;
            foreach ($conteudo as $index_ => $conteudo_) {
                if ($i < $index_comeca_partes) {
                    $linha[$index_] = $conteudo_;
                } else {
                    $linha_partes[$index_] = $conteudo_;
                }
                $i++;
            }
            $dados['linhas'][] = $linha;
            $dados['linhas_partes'][] = $linha_partes;
        }

        return $dados;
    }

    private function separarProcessosEClasses($dados, $dados_originais, $aba)
    {
        $dados['todas_colunas'] = $dados['colunas'];
        $dados['todas_linhas'] = $dados['linhas'];
        $dados['colunas'] = [];
        $dados['linhas'] = [];
        $dados['colunas_classes'] = [];
        $dados['linhas_classes'] = [];

        $partes_por_processo = $this->numeroDeClassesPorProcesso($dados);

        $processos_com_muitas_partes = [];
        $quantos_processos = 1;
        $i = 0;
        foreach ($partes_por_processo as $processo_ => $num_partes_) {
            if ($i < $quantos_processos) {
                $processos_com_muitas_partes[] = $processo_;
                $i++;
            }
        }

        $eh_partes = false;
        $index_comeca_partes = 0;
        foreach ($dados['todas_colunas'] as $index => $coluna) {
            if ($coluna == 'id_classes_1') {
                $eh_partes = true;
                $index_comeca_partes = $index;
            }

            if (!$eh_partes) {
                $dados['colunas'][] = $coluna;
            } else {
                $dados['colunas_classes'][] = $coluna;
            }
        }

        foreach ($dados['todas_linhas'] as $index => $conteudo) {
            $linha = [];
            $linha_partes = [];
            $i = 0;
            foreach ($conteudo as $index_ => $conteudo_) {
                if ($i < $index_comeca_partes) {
                    $linha[$index_] = $conteudo_;
                } else {
                    $linha_partes[$index_] = $conteudo_;
                }
                $i++;
            }
            $dados['linhas'][] = $linha;
            $dados['linhas_classes'][] = $linha_partes;
        }

        return $dados;
    }

    private function separarProcessosEAudiencias($dados, $dados_originais, $aba)
    {
        $dados['todas_colunas'] = $dados['colunas'];
        $dados['todas_linhas'] = $dados['linhas'];
        $dados['colunas'] = [];
        $dados['linhas'] = [];
        $dados['colunas_audiencias'] = [];
        $dados['linhas_audiencias'] = [];

        $partes_por_processo = $this->numeroDeAudienciasPorProcesso($dados);

        $processos_com_muitas_partes = [];
        $quantos_processos = 1;
        $i = 0;
        foreach ($partes_por_processo as $processo_ => $num_partes_) {
            if ($i < $quantos_processos) {
                $processos_com_muitas_partes[] = $processo_;
                $i++;
            }
        }

        $eh_partes = false;
        $index_comeca_partes = 0;
        foreach ($dados['todas_colunas'] as $index => $coluna) {
            if ($coluna == 'id_audiencias_1') {
                $eh_partes = true;
                $index_comeca_partes = $index;
            }

            if (!$eh_partes) {
                $dados['colunas'][] = $coluna;
            } else {
                $dados['colunas_audiencias'][] = $coluna;
            }
        }

        foreach ($dados['todas_linhas'] as $index => $conteudo) {
            $linha = [];
            $linha_partes = [];
            $i = 0;
            foreach ($conteudo as $index_ => $conteudo_) {
                if ($i < $index_comeca_partes) {
                    $linha[$index_] = $conteudo_;
                } else {
                    $linha_partes[$index_] = $conteudo_;
                }
                $i++;
            }
            $dados['linhas'][] = $linha;
            $dados['linhas_audiencias'][] = $linha_partes;
        }

        return $dados;
    }

    private function numeroDePartesPorProcesso($dados)
    {
        $partes = [];

        foreach ($dados['todas_linhas'] as $index_linha => $linha) {
            $partes[$linha['Processo_processo_1']] = $linha['Numero de Partes'];
        }

        asort($partes);
        return array_reverse($partes);
    }

    private function numeroDeClassesPorProcesso($dados)
    {
        $partes = [];

        foreach ($dados['todas_linhas'] as $index_linha => $linha) {
            $partes[$linha['Processo_processo_1']] = $linha['Numero de Classes'];
        }

        asort($partes);
        return array_reverse($partes);
    }

    private function numeroDeAudienciasPorProcesso($dados)
    {
        $partes = [];

        foreach ($dados['todas_linhas'] as $index_linha => $linha) {
            $partes[$linha['Processo_processo_1']] = $linha['Numero de Audiencias'];
        }

        asort($partes);
        return array_reverse($partes);
    }
}
