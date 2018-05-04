<?php
/*
* * Criado por Luciano Ramos 
* * luciano.ramos@serpro.gov.br
* * Funções utilizadas em algumas customizações do Zabbix
* *
*/
?>
<?php

require_once dirname(__FILE__).'/events.inc.php';

//Funções usadas no relatório Indicadores de TI (indicadores_ti.php) e no relatorio de hosts (report-hosts.php)

//Função para criar um Widget para cada cliente
function Cria_Widget_Cliente($div, $cliente, $local, $period_start, $period_end, $servico,$servico_nome){
	$div
		->addItem(BR())
		->addItem(new CTag('h2', true, "Disponibilidade de Serviço ".$servico_nome.((isset($cliente))?' - '._($cliente['name']): '')))
		->addItem(new CTag('hr'))
	;
		
	
	if ($local == 0){
		$localidades = lista_localidades($cliente['key']);

		foreach($localidades as $localidade){
			$div->addItem(new CTag('h6', true, _("$localidade[name]")));
			
			$table = new CTableInfo(_('Nenhum serviço de TI encontrado.'));
			lista_servicos($localidade['serviceid'], $period_start, $period_end, $table, $servico_nome);
			$div->addItem($table);
			$div->addItem(BR());
		}
	} else {
		$service = API::Service()->get(array(
							'serviceids' => $local,
							'output' => array('name')));
		$div->addItem(new CTag('h6', true, _($service[0]['name'])));
		
		$table = new CTableInfo(_('Nenhum serviço de TI encontrado.'));
		lista_servicos($local, $period_start, $period_end, $table, $servico_nome);
		
		$div->addItem($table);
		$div->addItem(BR());
	}
	
}

// Função para listar as localidades de determinado cliente
function lista_localidades($cliente){
		$localidades = array();
		$options = array(
				'output' => array('serviceid','name',)
				,'parentids' => $cliente
				,'selectDependencies' => array('serviceid')
 				,'sortfield' => array('name')
		);
		$services = API::Service()->get($options);
		foreach ($services as $service){
			if (!empty($service['dependencies']))
				$localidades[] = $service;
		}	
		return $localidades;	
	
}


//Função que cria a tabela com os dados dos serviços de TI por localidade
function lista_servicos($localidade, $period_start, $period_end, $table, $servico_nome = ''){
	$table->setHeader(array(
			_('Host'),
			_('SLA'),
			_('Disponibilidade'),
			_('Ver Eventos'),
	));

	global $backdata;

	$servicos = API::Service()->get(array(
			'output' => array('serviceid','name','goodsla', 'triggerid')
			,'parentids' => $localidade
			,'selectDependencies' => array('serviceid', 'name')
			,'sortfield' => 'name'
	));

	foreach($servicos as $servico){

		$table->addRow(array(
				$servico['name'],
		));

		$servicosfilhos = API::Service()->get(array(
				'output' => array('serviceid','name','goodsla', 'triggerid')
				,'parentids' => $servico['serviceid']
				,'selectDependencies' => array('serviceid', 'name')
				,'sortfield' => 'name'
		));
			
		foreach ($servicosfilhos as $servico){
			$slaData = API::Service()->getSla(array(
					'serviceids' => $servico['serviceid'],
					'intervals' => array(array(
							'from' => $period_start,
							'to' => $period_end
					))
			));
				
			foreach ($slaData as &$serviceSla) {
				foreach ($serviceSla['sla'] as $detalhes){
					$table->addRow(array(
							$servico['name'],
							round($servico['goodsla'],2) . ' %',
							round($detalhes['sla'], 2) . ' %',
							new CLink(('Ver Eventos'),
									'events.php?triggerid='.$servico['triggerid'].'&from='.$period_start.'&till='.$period_end.
									'&name='.$servico['name'].'&backdata='."$backdata".'&serviceid='.$servico['serviceid'],
									'action'
									),
					));
					unset($serviceSla);
				}
			}
		}
	}
}

//Funções usadas para descarte de eventos da aferição de disponibilidade

// Retorna array contendo informações do service alarm associado ao evento
function getAlarm($eventid, $serviceid){
	$q = 'SELECT '.
			'sa.servicealarmid, '.
			'sa.value AS valoralarme, '.
			'sa.clock AS clockalarme, '.
			'e.value AS valorevento, '.
			't.priority, '.
			't.description, '.
			's.serviceid '.
			'FROM services AS s, '.
			'service_alarms AS sa, '.
			'triggers AS t, '.
			'events AS e '.
			'WHERE e.objectid=s.triggerid '.
			'AND s.triggerid=t.triggerid '.
			'AND s.serviceid=sa.serviceid '.
			"AND s.serviceid= $serviceid " . 
			'AND sa.clock=e.clock '.
			'AND e.eventid= '. $eventid;

	return  DBfetch(DBSelect($q));
}


//Executa atualização no banco: altera o valor do service alarm para -1 se for para descartar o evento OU retorna o valor para o valor original se for para voltar a considerar o evento.
//Registra um Acknowledge no evento com o nome do usuário e a operação executada.
function discardEvent($servicealarm, $discard, $eventid){
	//	$message = '';
	if($discard) {
		if ($servicealarm['valoralarme'] > 0){
			$events = API::Event()->get(array(
					'eventids' => $eventid,
					'acknowledged' => TRUE));
			
			if(empty($events)){
				echo "<script>
				alert(\"Este evento ainda não foi Reconhecido. Um evento deve ser justificado para que possa ser desconsiderado do Cálculo de Disponibilidade.\");
				window.history.back(-1);
				</script>";
			} else {
				$q = 'UPDATE service_alarms SET value=-1 WHERE servicealarmid=' . $servicealarm['servicealarmid'] . ' AND clock = ' .  $servicealarm['clockalarme'];
				$msg = '(EVENTO DESCARTADO) por '. CWebUser::$data['name'] . ' ' . CWebUser::$data['surname'];
			}
		}
    } else {
		if($servicealarm['valoralarme'] == -1){
			$q = 'UPDATE service_alarms SET value=' . $servicealarm['priority']  . ' WHERE servicealarmid=' . $servicealarm['servicealarmid'] . ' AND clock = ' .  $servicealarm['clockalarme'];
			$msg = '(EVENTO RECONSIDERADO) por '. CWebUser::$data['name'] . ' ' . CWebUser::$data['surname'];
		}
    }
    
	if(!empty($q)){
		if (DBexecute($q)){
			$acknowledgeEvent = API::Event()->acknowledge(array(
					'eventids' => $eventid,
					'message' => $msg
			));
		}
	}
}


//Funcao que define periodos customizados

function getPeriodos(){
// Definição de períodos customizados

// Período de 11 a 10
if (date('d') < 11){
	$atual2_start = date('d/m/Y', mktime(0, 0, 0, date('n') -1, 11, date('Y')));
	$atual2_end = date('d/m/Y', mktime(0, 0, 0, date('n'), 10, date('Y')));
	$anterior2a_start = date('d/m/Y', mktime(0, 0, 0, date('n') -2, 11, date('Y')));
	$anterior2a_end = date('d/m/Y', mktime(0, 0, 0, date('n') -1, 10, date('Y')));
	$anterior2b_start = date('d/m/Y', mktime(0, 0, 0, date('n') -3, 11, date('Y')));
	$anterior2b_end = date('d/m/Y', mktime(0, 0, 0, date('n') -2, 10, date('Y')));
	$anterior2c_start = date('d/m/Y', mktime(0, 0, 0, date('n') -4, 11, date('Y')));
	$anterior2c_end = date('d/m/Y', mktime(0, 0, 0, date('n') -3, 10, date('Y')));
} else {
	$atual2_start = date('d/m/Y', mktime(0, 0, 0, date('n'), 11, date('Y')));
	$atual2_end = date('d/m/Y', mktime(0, 0, 0, date('n') + 1, 10, date('Y')));
	$anterior2a_start = date('d/m/Y', mktime(0, 0, 0, date('n') -1, 11, date('Y')));
	$anterior2a_end = date('d/m/Y', mktime(0, 0, 0, date('n'), 10, date('Y')));
	$anterior2b_start = date('d/m/Y', mktime(0, 0, 0, date('n') -2, 11, date('Y')));
	$anterior2b_end = date('d/m/Y', mktime(0, 0, 0, date('n') -1, 10, date('Y')));
	$anterior2c_start = date('d/m/Y', mktime(0, 0, 0, date('n') -3, 11, date('Y')));
	$anterior2c_end = date('d/m/Y', mktime(0, 0, 0, date('n') -2, 10, date('Y')));
}

//Período de 1 ao último dia do mês
$atual3_start = date('d/m/Y', mktime(0, 0, 0, date('n'), 1, date('Y')));
$atual3_end = date('d/m/Y', mktime(0, 0, 0, date('n') +1, 0, date('Y')));
$anterior3_start = date('d/m/Y', mktime(0, 0, 0, date('n') -1, 1, date('Y')));
$anterior3_end = date('d/m/Y', mktime(0, 0, 0, date('n'), 0, date('Y')));


$periodos = array(
		'hoje' => 'Hoje',
		'semana' => 'Essa semana',
		'mes' => 'Esse mês',
		'ano' => 'Esse ano',
		24 => 'Últimas 24 horas',
		24*7 => 'Últimos 7 dias',
		24*30 => 'Últimos 30 dias',
		24*365 => 'Últimos 365 dias',
		'Ciclo2 atual' => 'De ' . $atual2_start . ' a ' . $atual2_end,
		'Ciclo2a anterior' => 'De ' . $anterior2a_start . ' a ' . $anterior2a_end,
		'Ciclo2b anterior' => 'De ' . $anterior2b_start . ' a ' . $anterior2b_end,
		'Ciclo2c anterior' => 'De ' . $anterior2c_start . ' a ' . $anterior2c_end,
		'Ciclo3 atual' => 'De ' . $atual3_start . ' a ' . $atual3_end,
		'Ciclo3 anterior' => 'De ' . $anterior3_start . ' a ' . $anterior3_end,
);

return $periodos;
}


function checaTimes($alarm, $nextAlarmClock) {
	$q = 'SELECT * FROM services_times WHERE serviceid='.$alarm['serviceid'].' AND type!=2';
	$times = DBFetchArray(DBSelect($q));
	if(empty($nextAlarmClock))
		$nextAlarmClock = time();
	
	if(!empty($times)){
		$alarm_inicio = alarm_times($times, $alarm['clockalarme']);
		$alarm_fim = alarm_times($times, $nextAlarmClock);
		if($alarm_inicio || $alarm_fim){
			$alarm_ch['flag']= 1;
		} else { 
			$week_inicio = getdate($alarm['clockalarme']);
			$week_fim = getdate($nextAlarmClock);
			$week = 0;
					
			$diff = $nextAlarmClock - $alarm['clockalarme'];
			$q = 'SELECT min(ts_from) FROM services_times WHERE serviceid='.$alarm['serviceid'].' AND type =0';
			$seg = DBFetch(DBSelect($q));
			$q = 'SELECT max(ts_to) FROM services_times WHERE serviceid='.$alarm['serviceid'].' AND type =0';
			$sex = DBFetch(DBSelect($q));
			$week = $alarm['clockalarme'] - $week_inicio['wday'] * SEC_PER_DAY - $week_inicio['hours'] * SEC_PER_HOUR - $week_inicio['minutes'] * SEC_PER_MIN - $week_inicio['seconds'];
			$time_sex = $week + $sex['max'];
			$week = $nextAlarmClock - $week_fim['wday'] * SEC_PER_DAY - $week_fim['hours'] * SEC_PER_HOUR - $week_fim['minutes'] * SEC_PER_MIN - $week_fim['seconds'];
			$time_seg = $time_sex + 216000;
			
			if($alarm['clockalarme'] >= $time_sex AND $nextAlarmClock <= $time_seg){
				$alarm_ch['flag']= 0;
			} else {
				if($diff >= 43200 AND (($week_inicio['wday'] > 0 AND $week_inicio['wday'] < 6) OR ($week_fim['wday'] > 0 AND $week_fim['wday'] < 6))){
					$alarm_ch['flag']= 1;
				} else {
					$alarm_ch['flag']= 0;
				}
			}
		}
	} else {
		$alarm_ch['flag']= 1;
	}
	return $alarm_ch;
}

function alarm_times($times, $alarm){
	foreach ($times as $time){
		if($time['type'] == 0){
			$week = getdate($alarm);
			
			$week = $alarm - $week['wday'] * SEC_PER_DAY - $week['hours'] * SEC_PER_HOUR - $week['minutes'] * SEC_PER_MIN - $week['seconds'];
			
			$time_from = $week + $time['ts_from'];
			$time_to = $week + $time['ts_to'];
			
			if(($alarm > $time_from) AND ($alarm < $time_to) )
				$value = 1;
			
		}
	}
	if(isset($value))
		return $value;
}

function checa_downtime_unico($alarm, $nextAlarmClock){
	$q = 'SELECT * FROM services_times WHERE serviceid='.$alarm['serviceid'].' AND type=2';
	$times = DBFetchArray(DBSelect($q));

	if(empty($nextAlarmClock))
		$nextAlarmClock = time();
	
	$n=0;
	foreach ($times as $time){
		if(($alarm['clockalarme'] < $time['ts_from'] && $nextAlarmClock < $time['ts_from']) || ($alarm['clockalarme'] > $time['ts_to'] && $nextAlarmClock > $time['ts_to'])){
			// Encontrou um Downtime Unico que nao afeta o evento. Nao deve fazer nada
		} elseif(($time['ts_from'] < $alarm['clockalarme']) AND ($time['ts_to'] > $nextAlarmClock) ){
			// Encontrou um Downtime Unico que afeta todo o evento. Evento fica "Fora do Período de Ateste"
			$alarm_ch['flag'] = 0;
		} else {
			// Encontrou um Downtime Unico que afeta parte do evento. Evento fica "Parcialmente Descartado"
			if(!isset($alarm_ch)) $alarm_ch['flag'] = 2;
			$from = ($time['ts_from'] < $alarm['clockalarme']) ? $alarm['clockalarme'] : $time['ts_from'];
			$to = ($time['ts_to'] > $nextAlarmClock) ? $nextAlarmClock : $time['ts_to'];
			@$alarm_ch['duracao'] += ($to - $from);
			$n++;
		}
	}
	if(isset($alarm_ch['duracao']))
		$alarm_ch['descartes'] = $n;

	if(isset($alarm_ch))
		return $alarm_ch;
}

?>
