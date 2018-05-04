<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** Customizado por Luciano Ramos
** luciano.ramos@serpro.gov.br
** Tela que lista os eventos associados aos alarmes que geraram o indicador de disponibilidade calculado pelo Zabbix
** Possibilidade de descartes de eventos
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/events.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';
require_once dirname(__FILE__).'/include/funcoes_customizadas.php';

define('EVENTS_ACTION_TIME_FORMAT', _('d M Y H:i:s'));

$page['title'] = 'Histórico de Eventos';
$page['file'] = 'events.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['scripts'] = array('class.calendar.js', 'gtlc.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$sessao = new CSession();

if (PAGE_TYPE_HTML == $page['type']) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

//}

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'triggerid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'from'=>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'till'=>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'descartar'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'eventid'=>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'name'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'serviceid'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'rel'=>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
		'serviceid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
		'name' => 							[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
		'algorithm' =>						[T_ZBX_INT, O_OPT, null,	IN('0,1,2'),'isset({add}) || isset({update})'],
		'showsla' =>						[T_ZBX_INT, O_OPT, null,	IN([SERVICE_SHOW_SLA_OFF, SERVICE_SHOW_SLA_ON]),	null],
		'goodsla' => 						[T_ZBX_DBL, O_OPT, null,	BETWEEN(0, 100), null, _('Calculate SLA, acceptable SLA (in %)')],
		'sortorder' => 						[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 999), null, _('Sort order (0->999)')],
		'times' =>							[T_ZBX_STR, O_OPT, null,	null,		null],
		'triggerid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
		'trigger' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
		'new_service_time' =>				[T_ZBX_STR, O_OPT, null,	null,		null],
		'new_service_time_from_day' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_from_month' =>	[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_from_year' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_from_hour' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_from_minute' =>	[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_to_day' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_to_month' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_to_year' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_to_hour' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'new_service_time_to_minute' =>		[T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null],
		'children' =>						[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
		'parentid' =>						[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
		'parentname' =>						[T_ZBX_STR, O_OPT, null,	null,		null],
		// actions
		'add' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
		'update' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
		'add_service_time' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
		'delete' =>							[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null],
		// others
		'form' =>							[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
		'form_refresh' =>					[T_ZBX_INT, O_OPT, null,	null,		null],
		'pservices' =>						[T_ZBX_INT, O_OPT, null,	null,		null],
		'cservices' =>						[T_ZBX_INT, O_OPT, null,	null,		null]
);
check_fields($fields);

$from = isset($_REQUEST['from']) ? $_REQUEST['from'] : mktime(0, 0, 0, date('n') -2, date('d'), date('Y'));
$till = isset($_REQUEST['till']) ? $_REQUEST['till'] : time();
$triggerid = (isset($_REQUEST['triggerid']))? $_REQUEST['triggerid']: null;
$servicename = $_REQUEST['name'];
$serviceid = $_REQUEST['serviceid'];
$eventid = @$_REQUEST['eventid'];


$sessao->setValue('serviceid', $serviceid);
$sessao->setValue('triggerid', $triggerid);
$sessao->setValue('name', $servicename);
$sessao->setValue('from', $from);
$sessao->setValue('till', $till);

if (isset($_REQUEST['descartar'])){
	$services = API::Service()->get([
			'output' => ['name', 'serviceid', 'algorithm'],
			'serviceids' => $_REQUEST['serviceid'],
			'selectParent' => ['serviceid'],
			'selectDependencies' => ['servicedownid', 'soft', 'linkid'],
			'selectTrigger' => ['description', 'triggerid', 'expression'],
			'preservekeys' => true,
			'sortfield' => 'sortorder',
			'sortorder' => ZBX_SORT_UP
	]);
	if ($_REQUEST['descartar'] == 'parcial'){
		$url = 'descarte_parcial.php?descartar=parcial&serviceid='.$serviceid.'&eventid=' . $eventid.'&from='.$from.'&till='. $till.'&name='.$servicename;
		redirect($url);
	}
	elseif($_REQUEST['descartar'] == 'descartado'){
		$msgOk = 'Período de descarte adicionado';
		$msgFail = 'Falha ao adicionar período de descarte';
		$result = true;
		show_messages($result, $msgOk, $msgFail);
		unset($_REQUEST['descartar']);
	
	} else {
		$alarm = getAlarm($_REQUEST['eventid'], $serviceid);
		if ($_REQUEST['descartar'] == 'sim'){
			discardEvent($alarm, 1, $_REQUEST['eventid']);
		} elseif ($_REQUEST['descartar'] == 'nao'){
			discardEvent($alarm, 0, $_REQUEST['eventid']);
		}
		unset($_REQUEST['descartar']);
	}
	
}

$eventsWidget = new CWidget();

/*
 * Display
 */
$table = new CTableInfo(_('No events found.'));

// source not discovery i.e. trigger

$table->setHeader(array(
	_('Time'),
	_('Description'),
	_('Severity'),
	_('Status'),
	_('Duration'),
	_('Período Descartado'),
	_('Message'),
	_('Cálculo de Disponibilidade'),
	_('Descarte Parcial')
));


$options = array(
	'output' => array('triggerid'),
	'monitored' => true
);
if (isset($triggerid) && $triggerid > 0) {
	$options['triggerids'] = $triggerid;
}

$triggers = API::Trigger()->get($options);

// query event with short data
$events = API::Event()->get(array(
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'objectids' => zbx_objectValues($triggers, 'triggerid'),
	'time_from' => $from,
	'time_till' => $till,
	'output' => array('eventid'),
	'sortfield' => array('clock', 'eventid'),
	'sortorder' => ZBX_SORT_DOWN,
	'limit' => $config['search_limit'] + 1,
	'select_acknowledges' => array('message')
));

// get pagging
//$paging = getPagingLine($events);

// query event with extend data
$events = API::Event()->get(array(
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'eventids' => zbx_objectValues($events, 'eventid'),
	'output' => API_OUTPUT_EXTEND,
	'select_acknowledges' => API_OUTPUT_COUNT,
	'sortfield' => array('clock', 'eventid'),
	'sortorder' => ZBX_SORT_DOWN,
	'nopermissions' => true
));

$triggers = API::Trigger()->get(array(
	'triggerids' => zbx_objectValues($events, 'objectid'),
	'selectHosts' => array('hostid', 'name','status'),
	'selectItems' => array('itemid', 'hostid', 'name', 'key_', 'value_type'),
	'output' => array('description', 'expression', 'priority', 'flags', 'url')
));
$triggers = zbx_toHash($triggers, 'triggerid');

// fetch hosts
$hosts = array();
foreach ($triggers as $trigger) {
	$hosts[] = reset($trigger['hosts']);
}
$hostids = zbx_objectValues($hosts, 'hostid');
$hosts = API::Host()->get(array(
	'output' => array('name', 'hostid', 'status'),
	'hostids' => $hostids,
	'selectScreens' => API_OUTPUT_COUNT,
	'preservekeys' => true
));

		

// events
foreach ($events as $event) {
	
	$trigger = $triggers[$event['objectid']];

	$host = reset($trigger['hosts']);
	$host = $hosts[$host['hostid']];

	$triggerItems = array();

	$trigger['items'] = CMacrosResolverHelper::resolveItemNames($trigger['items']);

	foreach ($trigger['items'] as $item) {
		$triggerItems[] = array(
			'name' => $item['name_expanded'],
			'params' => array(
				'itemid' => $item['itemid'],
				'action' => in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
					? 'showgraph' : 'showvalues'
			)
		);
	}

	$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, array(
		'clock' => $event['clock'],
		'ns' => $event['ns']
	)));

	$triggerDescription = (new CSpan($description, 'pointer link_menu'))
						->addClass(ZBX_STYLE_LINK_ACTION)
						->setMenuPopup(CMenuPopupHelper::getTrigger($trigger));

	// duration
	$event['duration'] = ($nextEvent = get_next_event($event, $events))
		? zbx_date2age($event['clock'], $nextEvent['clock'])
		: zbx_date2age($event['clock']);

	$statusSpan = new CSpan(trigger_value2str($event['value']));

	// add colors and blinking to span depending on configuration and trigger parameters
	addTriggerValueStyle(
		$statusSpan,
		$event['value'],
		$event['clock'],
		$event['acknowledged']
	);

	// host JS menu link
	$hostName = null;

	// action
	$action = isset($actions[$event['eventid']]) ? $actions[$event['eventid']] : ' - ';

	$message = array();
	if($event['acknowledged']){
		$messages = API::Event()->get(array(
				'eventids' => $event['eventid'],
				'output' => array('eventid'),
				'select_acknowledges' => array('message'),
		));
		foreach($messages[0]['acknowledges'] as $msge){
				if (strlen($msge['message']) > 100){
					$msge = wordwrap($msge['message'], 100, "\t");
					$spl_msge = explode("\t", $msge);
					foreach($spl_msge as $xmsge){
						$message[] = $xmsge;
					}
				}else{
					$message[] = $msge['message'];
				}
		}
	} else {
		$message[] = '';
	}
	
	
	
	$alarm = getAlarm($event['eventid'], $serviceid);
	
	(isset($_REQUEST['rel'])) ? $ans3 = '&rel=ans3' : $ans3 = '';
	
	unset($descarte_parcial_flag);
	$tempo_descarte = '';
	
	if(!empty($alarm)){
		if ($alarm['valoralarme'] == -1) {
			$statusCaption = _('Evento Descartado');
			$statusClass = ZBX_STYLE_RED;
			$confirm_message = _('Deseja considerar este evento no cálculo de disponibilidade?');
			$statusUrl = 'events.php?eventid=' . $event['eventid'] . '&triggerid=' .$event['objectid'] . '&from=' .  $from .
			'&till=' . $till.'&descartar=nao' . '&name=' . $servicename . '&backdata=' . $ans3 . '&serviceid='.$serviceid;
			$descarte_parcial_flag = TRUE;
			$tempo_descarte = $event['duration'] .' (total)';
		} else {
			$statusCaption = _('Descartar este Evento');
			$statusUrl = 'events.php?eventid=' . $event['eventid'] . '&triggerid=' .$event['objectid'] . '&from=' .  $from .
			'&till=' . $till.'&descartar=sim'. '&name=' . $servicename . '&backdata=' . $ans3 . '&serviceid='.$serviceid;
			$confirm_message = _('Deseja descartar este evento do cálculo de disponibilidade?');
			$statusClass = ZBX_STYLE_GREEN;
			
			$parcialCaption = _('Descartar Parcialmente');
			$parcialUrl = 'events.php?eventid=' . $event['eventid'] . '&triggerid=' .$event['objectid'] . '&from=' .  $from .
			'&till=' . $till.'&descartar=parcial'. '&name=' . $servicename . '&backdata=' . $ans3 . '&serviceid='.$serviceid;
			$parcial_message = _('Deseja descartar parcialmente este evento do cálculo de disponibilidade?');
			$parcialClass = ZBX_STYLE_GREEN;
		}
		
		$considera = (new CLink($statusCaption, $statusUrl))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass($statusClass)
		->addConfirmation($confirm_message)
		->addSID();
		
		$parcial = (new CLink($parcialCaption, $parcialUrl))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass($parcialClass)
		->addConfirmation($parcial_message)
		->addSID();
		
		//Parcialmente descartado
		$parcialCaption = _('Evento parcialmente descartado');
		$parcialClass = ZBX_STYLE_YELLOW;
		$parcial_descartado = (new CLink($parcialCaption, $parcialUrl))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass($parcialClass)
		->addConfirmation($parcial_message)
		->addSID();
		
		unset($alarm_ch);
		
		
		if($event['value'] > 0){
			$alarm_ch = checa_downtime_unico($alarm, $nextEvent['clock']);
			
			if(!isset($alarm_ch))
				$alarm_ch = checaTimes($alarm, $nextEvent['clock']);
			
			switch($alarm_ch['flag']){
				case 0:
					$calc_dispo = [(new CSpan('Evento fora do período de ateste', 'on'))
								->addClass(ZBX_STYLE_ORANGE)];
					$descarte_parcial = '';
					break;
				case 1:
					$calc_dispo = $considera;
					$descarte_parcial = $parcial;
					break;
				case 2:
					$calc_dispo = $considera;
					$descarte_parcial = $parcial_descartado;
					break;
			}

		} else {
			$calc_dispo = '';
			$descarte_parcial = '';
		}
		if (isset($descarte_parcial_flag))
			$descarte_parcial = '';
		
		$n=0;

		if(isset($alarm_ch['duracao']) && $alarm['valoralarme'] > 0)
			$tempo_descarte = convertUnitsS(abs($alarm_ch['duracao'])) . ' ('.$alarm_ch['descartes'].')';
				
		foreach($message as $msg){
				if ($n==0){
					
					$table->addRow([
						new CLink(zbx_date2str(EVENTS_ACTION_TIME_FORMAT, $event['clock']),
								'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
							'action'
						),
						$triggerDescription,
						getSeverityCell($trigger['priority'], $config, null, !$event['value']),
						$statusSpan,								
						$event['duration'],
						$tempo_descarte,
						$msg,
						$calc_dispo,
						$descarte_parcial
					]);
					
				} else {
					$table->addRow(['', '', '', '', '', '', $msg, '','']);
				}
				$n++;
			}
	
	}

}

if($sessao->getValue('rel') == 'ans3'){
	$file = 'indicadores_ti_ans3.php?jur='.$sessao->getValue('jur');
} else {
	$file = 'indicadores_ti.php?servico='.$sessao->getValue('servico');
}
$url = $file;
if (!is_null($sessao->getValue('cliente'))) $url .='&cliente='.$sessao->getValue('cliente');
if (!is_null($sessao->getValue('local'))) $url .= '&local='.$sessao->getValue('local');
$url .=	'&period='.$sessao->getValue('period').'&filter_set=1'; 
$botao = (new CRedirectButton(SPACE, null))
	->addClass(ZBX_STYLE_BTN_RESET)
	->setTitle(_('Back'))
	->onClick('redirect("'.$url.'")');

$r_form = (new CForm('get'))
	->setAttribute('title_page', 'period_choice')
	->addVar('Voltar', 0);

// controls
$r_form->addItem((new CList())
		->addItem($botao)
		);


$eventsWidget
	->setTitle('Eventos de '.$servicename)
	->setControls($r_form)
	->addItem(new CTag('h4', true,"Histórico de Eventos".SPACE.' de '. zbx_date2str(EVENTS_ACTION_TIME_FORMAT, $from) .' a ' . zbx_date2str(EVENTS_ACTION_TIME_FORMAT, $till)))
	->addItem(BR())
	->addItem($table)
	->show();


require_once dirname(__FILE__).'/include/page_footer.php';
