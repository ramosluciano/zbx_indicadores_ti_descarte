<?php
/*
* * Criado por Luciano Souza Ramos
* * luciano.ramos@serpro.gov.br
* * Customização de Relatório de Eventos em formato PDF
* *
*/

require_once 'fpdf.php';
require '/var/www/zabbix/include/funcoes_customizadas.php'; //Endereço de instalação do Frontend do Zabbix

class PDF extends FPDF
{

	function Header()
			{
				// Page 
				global $cliente, $texto_periodo, $descartes, $servico;
		
				$titulo = 'Relatório de Ocorrências';
				$titulo2 = 'Serviço '.$servico.' - Cliente '. $cliente;
				
				// Logo
				$this->Image('imagens/'.strtolower($cliente).'.jpg',10,6,30);
				// Arial bold 15
				$this->SetFont('Times','BU',15);
				$this->SetTextColor(0,0,0);
				// Move to the right
				$this->Cell(60);
				// Title
				$this->MultiCell(100,5,utf8_decode($titulo),0,'C',FALSE);
				$this->Ln(1);
				$this->Cell(60);
				$this->MultiCell(100,5,utf8_decode($titulo2),0,'C',FALSE);
				
				if($descartes){
					$this->Ln(2);
					$this->Cell(60);
					$this->SetFont('Times','BI',10);
					$this->MultiCell(100,3,'Eventos Descartados',0,'C',FALSE);
				}
					
				$this->Ln(4);
				//$this->Cell(50);
				$this->SetFont('Times','BI',10);
				$this->MultiCell(100,3,utf8_decode("Período: $texto_periodo"),0,'L',FALSE);
				// Line break
				$this->Ln(7);
			}
		

	function Footer()
	{
		// Page footer
		$this->SetY(-15);
		$this->SetFont('Arial','I',8);
		$this->SetTextColor(128);
		$this->Cell(0,10,utf8_decode('Página '.$this->PageNo().'/{nb}'),0,0,'C');
	}

	function Titulo_Grupo($grupo)
	{
		// Title
		$this->SetFont('Arial','',12);
		$this->SetFillColor(200,220,255);
		$this->Cell(200,6,"$grupo",0,1,'L',true);
		$this->Ln(4);
		// Save ordinate
		$this->y0 = $this->GetY();
	}

	function Titulo_Host($host, $sla)
	{
		global $csv;
		// Title
		$pos = stripos($host['name'], trim($host['host']));
		$ponto = substr($host['name'], 0, $pos -1);
		$this->SetFont('Arial','B',9);
		$this->SetFillColor(205,205,205);
		$this->Cell(200,4,"$ponto - SLA: ".round($sla, 2).'%',0,1,'L',true);
		$this->Ln(2);
		// Save ordinate
		$this->y0 = $this->GetY();
		
		//fwrite($csv, "$ponto;SLA: ".round($sla, 2).'%'."\n");
	}

	

	function TabelaDados($eventos, $servico, $host, $descartes)
	{
		global $csv, $URL, $api;
		
		date_default_timezone_set('America/Sao_Paulo');

		$this->SetFont('Arial','',8);
		
		// Processando os dados de eventos
		//$this->SetFont('Arial','',9);
		if(empty($eventos)){
			$this->Cell(0,4,'Nenhum evento encontrado para essa localidade.',0,0,'C',false);
			$this->Ln(6);
		} else {
			$this->SetFillColor(220,220,220);
			$this->Cell(26,4,utf8_decode('ID do evento'),'LRT',0,'C',true);
			$this->Cell(62,4,'Ponto de Acesso','LRT',0,'C',true);
			$this->Cell(5,4,'UF','LRT',0,'C',true);
			$this->Cell(24,4,'Tempo (min)','LRT',0,'C',true);
			$this->Cell(32,4,utf8_decode('Data/Hora Início'),'LRT',0,'C',true);
			$this->Cell(32,4,utf8_decode('Data/Hora Fim'),'LRT',0,'C',true);
			$this->Ln();
			
			$hostname = strtoupper(trim($host['host']));
			$pos = stripos($host['name'], $hostname);
			$ponto = substr($host['name'], 0, $pos -1);
			$this->SetFillColor(255,255,255);
			
			$parciais = FALSE;
			foreach($eventos  as $evento){
		
				$next_event = get_next_event($evento, $eventos);
				//print_r($next_event);
				
				if($evento['value'] == 1){
					$x = explode('_', $host['name']);
					$uf = $x[0];
					
					$value = '';
					foreach ($servico[0]['alarms'] as $alarm){
						if($evento['clock'] == $alarm['clock']){
							$alarme = [
								'serviceid' => $alarm['serviceid'],
								'valoralarme' => $alarm['value'],
								'clockalarme' => $alarm['clock']
							];
							break;
						}
					}
					
					$duracao = ($next_event['clock'] - $evento['clock']) / 60;
					
					if($alarme['valoralarme'] == -1){
						$status = -1;
						$rel_descartes = TRUE;
						$duracao_p = $duracao;
					} else {
						
						$alarm_ch = checa_downtime_unico($alarme, $next_event['clock'], $servico[0]['times']);
						if(!isset($alarm_ch))
							$alarm_ch = checaTimes($alarme, $nextEvent['clock'], $servico[0]['times']);
						
						switch($alarm_ch['flag']){
							case 0:
								// Evento Fora do Periodo de Ateste
								$status = 0;
								break;
							case 1:
								// Evento Considerado
								$status = 1;
								$rel_descartes = FALSE;
								break;
							case 2:
								//Evento Parcialmente Descartado
								$status = 2;
								$rel_descartes = TRUE;
								break;
						}
					}
					
					if($alarm_ch['flag'] == 2){
							$duracao_p = ($descartes) ? round($alarm_ch['duracao'] / 60) . ' *' : $duracao - round($alarm_ch['duracao'] / 60) . ' *';
							$parciais = TRUE;
					}

					$data_ini = zbx_date2str('d M Y H:i:s', $evento['clock']);
					$data_fim = zbx_date2str('d M Y H:i:s', $next_event['clock']);
					
					if($descartes == $rel_descartes OR $alarm_ch['flag'] == 2){

						(strlen($ponto) > 34) ? $align = 'L' : $align = 'C';
						$this->Cell(26,4,$evento['eventid'],1,0,'C',true);
						$this->SetFontSize(7);
						$this->Cell(62,4,$ponto,1,0,$align,true);
						$this->SetFontSize(8);
						$this->Cell(5,4,$uf,1,0,'C',true);
						if($descartes){
							$this->Cell(24,4,$duracao,1,0,'C',true); 
							$this->Cell(24,4,$duracao_p,1,0,'C',true);
						} else {
							($alarm_ch['flag'] == 2) ? $this->Cell(24,4,$duracao_p,1,0,'C',true) :
								$this->Cell(24,4,$duracao,1,0,'C',true);
						}
						$this->Cell(32,4,"$data_ini",1,0,'C',true);
						$this->Cell(32,4,"$data_fim",1,0,'C',true);
						$this->Ln();
						
						if($descartes){
							$message = [];
							foreach($evento['acknowledges'] as $msg){
								if (strlen($msg['message']) > 145){
                                     $msge = wordwrap($msg['message'], 145, "\t");
                                     $spl_msge = explode("\t", $msge);
                                     foreach($spl_msge as $xmsge){
                                             $message[] = $xmsge;
                                     }
                                }else{
									$message[] = $msg['message'];
								}
								//echo strlen($msg['message']) . ' - ' . $msg['message'] . "\n";
							}
							foreach($message as $msge){
								$this->Cell(200,4,utf8_decode($msge),1,1,'L',true);
							}
						}
						
						fwrite($csv, $evento['eventid'].";$ponto;$uf;$duracao;$data_ini;$data_fim.\n");
					}
					
				}
			}
			if($parciais)
							($descartes) ? $this->Cell(0,4,'* Evento parcialmente descartado.',0,1,'L',false) :
								$this->Cell(0,4,utf8_decode('* Evento parcialmente descartado. Para mais informações, consulte o 
													Relatório de Eventos Descartados.'),0,1,'L',false);
						
		}

		$this->Ln(4);
	}


	function lista_eventos($cliente, $periodo_inicio, $periodo_fim, $descartes, $servico, $URL){
	
		global $csv, $reg;
		
		require_once '/var/www/zabbix/include/config.inc.php'; // Diretorio de instalação do frontend do Zabbix
		
		$this->AddPage();
		$this->AliasNbPages();
		$this->SetFont('Times','',8);
		
		$api = new ZabbixApi('https://'.$URL.'/api_jsonrpc.php', 'USER', 'PASSWORD'); // Inserir nome de usuario e senha para acesso a API do Zabbix
		
		$options = array(
				'output' => array('groupid', 'name'),
				'search' => array('name' => '_'.$servico.'_'.$cliente),
				'sortfield' => array('name')
		);
		$groups = objectToArray($api->hostgroupGet($options));
			
		fwrite($csv, "ID do Evento;Ponto de Acesso;UF;Tempo Decorrido;Data/Hora Inicio;Data/Hora Fim\n");
		
		foreach($groups as $group){
			//echo "\n".'HOSTS DO GRUPO '.$group['name'] . "\n\n";
			$this->Titulo_Grupo($group['name']);
		
			$options = array(
					'output' => array('hostid', 'host', 'name'),
					'groupids' => $group['groupid'],
					'selectInventory' => array('type_full'),
					'monitored_hosts' => TRUE,
					'sortfield' => array('host')
			);
			$hosts = objectToArray($api->hostGet($options));
			
			foreach (@$hosts as $host){
				$options = array(
						'output' => array('serviceid','name','goodsla', 'triggerid'),
						'search' => array('name' => 'Dispositivo '.$host['host'].' indisponivel'),
						//,'parentids' => $localidade['serviceid']
						'selectTimes' => 'extend',
						'selectAlarms' => 'extend'
				);
				$servico = objectToArray($api->serviceGet($options));
				$x = count($servico);
				
				if(!empty($servico)){
					if($x > 1)
					//	echo 'Mais de um IT Service para Host '.$host['host'].' do grupo '.$group['name']." - Utilizado o Primeiro\n";
					
					$var = $api->servicegetSla(
							array(
									'serviceids' => $servico[0]['serviceid'],
									'intervals' => array(array(
											'from' => $periodo_inicio,
											'to' => $periodo_fim
									))
							));
					$serv_sla = objectToArray($var);
					
					$sla = $serv_sla[$servico[0]['serviceid']]['sla'][0]['sla'];
					
					$this->Titulo_Host($host, $sla);
					$options = array(
							'output' => 'extend',
							'objectids' => $servico[0]['triggerid'],
							'time_from' => $periodo_inicio,
							'time_till' => $periodo_fim,
							'select_acknowledges' => array('message'));
					$eventos = objectToArray($api->eventget($options));

				$this->TabelaDados($eventos, $servico, $host, $descartes);
					
				} else {
					//echo 'Nao foi encontrado o IT Service para o Host '.$host['host'].' do grupo '.$group['name']."\n";
				}
		
				
			}
		}
	}

}

?>