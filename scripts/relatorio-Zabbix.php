<?php
/*
* * Criado por Luciano Souza Ramos 
* * luciano.ramos@serpro.gov.br
* * Customização de Relatório de ateste de Serviços
* *
*/

include 'gera_pdf.php';
require '../PhpZabbixApi_Library/ZabbixApiAbstract.class.php';
require '../PhpZabbixApi_Library/ZabbixApi.class.php';
require '../PhpZabbixApi_Library/objecttoarray.php';
 

setlocale(LC_ALL, 'pt_BR');

// Variáveis recebidas pelos parâmetros de execução do script
$URL = $argv[1];
$cliente = $argv[2];
$data_inicio = $argv[3];
$data_inicio = explode('/', $data_inicio);
$data_fim = $argv[4];
$data_fim = explode('/', $data_fim);
$descartes = FALSE;
$servico = $argv[5];

if(isset($argv[6]))
		$descartes = TRUE;

$periodo_inicio = mktime(0, 0, 0, $data_inicio[1], $data_inicio[0], $data_inicio[2]);
$periodo_fim = mktime(23, 59, 59, $data_fim[1], $data_fim[0], $data_fim[2]);

$texto_periodo = 'De ' . date('d/m/Y', $periodo_inicio) . ' a ' . date('d/m/Y', $periodo_fim);

$titulo = 'Relatorio Ocorrências - Cliente '. $cliente . ' Serviço '.$servico;

$file = 'relatorio_'.$cliente. ' Serviço '.$servico;

if($descartes){
	$titulo .= ' Eventos Descartados';
	$file .= '_Descartes';
}

$file .= '_'.date('M-Y', $periodo_fim);
$csv = fopen("relatorios/$servico/$cliente/TESTE/$file.csv", 'a');
$file .= '.pdf';

fwrite($csv, 'Relatorio Ocorrencias - Cliente '. $cliente."\n");
$pdf = new PDF('P','mm','A4');
$pdf->SetMargins(7,7,7);
$pdf->SetDisplayMode('default','continuous');
$pdf->SetTitle($titulo);
$pdf->SetAuthor('Luciano Ramos');

$pdf->lista_eventos($cliente, $periodo_inicio, $periodo_fim, $descartes, $servico, $URL);

$pdf->Output("relatorios/$servico/$cliente/$file", 'F');

fclose($csv);
?>
