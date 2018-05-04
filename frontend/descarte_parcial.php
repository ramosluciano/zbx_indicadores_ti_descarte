<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** Customizado por Luciano Ramos 
** luciano.ramos@serpro.gov.br
** Interface para criação de Downtime Unico para descarte parcial de eventos que impactam na disponibilidade medida pelos IT Services.
** 
**/
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';


$page['title'] = _('Descarte Parcial de Eventos');
$page['file'] = 'descarte_parcial.php';
$page['scripts'] = ['class.calendar.js'];

require_once dirname(__FILE__).'/include/page_header.php';
include('include/views/js/configuration.services.edit.js.php');

$triggerid = @$_REQUEST['triggerid'];
$serviceid = @$_REQUEST['serviceid'];
$eventid = @$_REQUEST['eventid'];
$event_from = @$_REQUEST['from'];
$event_till = @$_REQUEST['till'];
$name = @$_REQUEST['name'];
$backURL = 'events.php?eventid='.$eventid.'&from='.$event_from.'&till='.$event_till.'&serviceid='.$serviceid.'&name='.$name; //.'&descartar=descartado';

 if($_REQUEST['descartar'] == 'descartado'){
 	$msgOk = 'Período de descarte adicionado';
 	$msgFail = 'Falha ao adicionar período de descarte';
 	
 	show_messages($result, $msgOk, $msgFail);
 	redirect($backURL);
 } 

$events = API::Event()->get(array(
		'eventids' => $eventid,
		'acknowledged' => TRUE));

if(empty($events)){
	echo "<script>
				alert(\"Este evento ainda não foi Reconhecido. Um evento deve ser justificado para que possa ser desconsiderado do Cálculo de Disponibilidade.\");
				window.history.back(-1);
				</script>";
} else {

	if(isset($_REQUEST['update']) && $_REQUEST['update'] == 'Adicionar'){
		if (isset($_REQUEST['update']) && isset($_REQUEST['new_service_time'])) {
			$_REQUEST['times'] = getRequest('times', []);
			$new_service_time['type'] = SERVICE_TIME_TYPE_ONETIME_DOWNTIME;
			$result = true;
			if (!validateDateTime($_REQUEST['new_service_time_from_year'],
					$_REQUEST['new_service_time_from_month'],
					$_REQUEST['new_service_time_from_day'],
					$_REQUEST['new_service_time_from_hour'],
					$_REQUEST['new_service_time_from_minute'])) {
						$result = false;
						error(_s('Invalid date "%s".', _('From')));
			}
			if (!validateDateInterval($_REQUEST['new_service_time_from_year'],
				$_REQUEST['new_service_time_from_month'],
				$_REQUEST['new_service_time_from_day'])) {
				$result = false;
				error(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('From')));
			}
			if (!validateDateTime($_REQUEST['new_service_time_to_year'],
				$_REQUEST['new_service_time_to_month'],
				$_REQUEST['new_service_time_to_day'],
				$_REQUEST['new_service_time_to_hour'],
				$_REQUEST['new_service_time_to_minute'])) {
				$result = false;
				error(_s('Invalid date "%s".', _('Till')));
			}
			if (!validateDateInterval($_REQUEST['new_service_time_to_year'],
			$_REQUEST['new_service_time_to_month'],
			$_REQUEST['new_service_time_to_day'])) {
				$result = false;
				error(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Till')));
			}
			if ($result) {
				$new_service_time['ts_from'] = mktime($_REQUEST['new_service_time_from_hour'],
						$_REQUEST['new_service_time_from_minute'],
						0,
						$_REQUEST['new_service_time_from_month'],
						$_REQUEST['new_service_time_from_day'],
						$_REQUEST['new_service_time_from_year']);
				
				$new_service_time['ts_to'] = mktime($_REQUEST['new_service_time_to_hour'],
						$_REQUEST['new_service_time_to_minute'],
						0,
						$_REQUEST['new_service_time_to_month'],
						$_REQUEST['new_service_time_to_day'],
						$_REQUEST['new_service_time_to_year']);
				
				$new_service_time['note'] = $_REQUEST['new_service_time']['note'];
			}
			
		} else {
				$new_service_time['ts_from'] = dowHrMinToSec($_REQUEST['new_service_time']['from_week'], $_REQUEST['new_service_time']['from_hour'], $_REQUEST['new_service_time']['from_minute']);
				$new_service_time['ts_to'] = dowHrMinToSec($_REQUEST['new_service_time']['to_week'], $_REQUEST['new_service_time']['to_hour'], $_REQUEST['new_service_time']['to_minute']);
				$new_service_time['note'] = $_REQUEST['new_service_time']['note'];
			}
		
		if ($result) {
			try {
				checkServiceTime($new_service_time);
				
				// if this time is not already there, adding it for inserting
				if (!str_in_array($_REQUEST['times'], $new_service_time)) {
					$_REQUEST['new_time'] = $new_service_time;
					
					unset($_REQUEST['new_service_time']['from_week']);
					unset($_REQUEST['new_service_time']['to_week']);
					unset($_REQUEST['new_service_time']['from_hour']);
					unset($_REQUEST['new_service_time']['to_hour']);
					unset($_REQUEST['new_service_time']['from_minute']);
					unset($_REQUEST['new_service_time']['to_minute']);
					
					try{
						$result = API::Service()->addtimes([
								'serviceid' => $serviceid,
								'type' => $_REQUEST['new_time']['type'],
								'ts_from' => $_REQUEST['new_time']['ts_from'],
								'ts_to' => $_REQUEST['new_time']['ts_to'],
								'note' => $_REQUEST['new_time']['note'],
						]);
						
 						if($result){
 							$msg = '(EVENTO PARCIALMENTE DESCARTADO) por '. CWebUser::$data['name'] . ' ' . CWebUser::$data['surname']. ' - '. $_REQUEST['new_time']['note'];
 							$acknowledgeEvent = API::Event()->acknowledge(array(
 									'eventids' => $eventid,
 									'message' => $msg
 							));
  							redirect($backURL .'&descartar=descartado');
 						}
					}
					catch(APIException $e) {
						error($e->getMessage());
					}
				}
			}
			catch (APIException $e) {
				error($e->getMessage());
			}
		}
		
	} else {
	
		$widget = (new CWidget())->setTitle(_('IT services'));
		
		// create form
		$servicesForm = (new CForm())
		->setName('servicesForm');
		
		if (isset($serviceid)) {
			$servicesForm->addVar('serviceid', $serviceid);
		}
		
		
		/*
		 * Service times tab
		 */
		$servicesTimeFormList = new CFormList('servicesTimeFormList');
		$servicesTimeTable = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Type'), _('Interval'), _('Note')]);
		
		//$service_type = SERVICE_TIME_TYPE_ONETIME_DOWNTIME;
		
		$services = API::Service()->get([
				'output' => ['name', 'serviceid', 'algorithm'],
				'serviceids' => $serviceid,
				'selectParent' => ['serviceid'],
				'selectTimes' => ['type', 'ts_from', 'ts_to', 'note'],
				'selectDependencies' => ['servicedownid', 'soft', 'linkid'],
				'selectTrigger' => ['description', 'triggerid', 'expression'],
				'preservekeys' => true,
				'sortfield' => 'sortorder',
				'sortorder' => ZBX_SORT_UP
		]);
		
		$service = $services[$serviceid];
		$i = 0;
		foreach (@$services[$serviceid]['times'] as $serviceTime) {
			if($serviceTime['type'] == SERVICE_TIME_TYPE_ONETIME_DOWNTIME) {
				$type = (new CSpan(_('One-time downtime')))->addClass('enabled');
				$from = zbx_date2str(DATE_TIME_FORMAT, $serviceTime['ts_from']);
				$to = zbx_date2str(DATE_TIME_FORMAT, $serviceTime['ts_to']);
			
				$row = new CRow([
						[
								$type,
								new CVar('times['.$i.'][type]', $serviceTime['type']),
								new CVar('times['.$i.'][ts_from]', $serviceTime['ts_from']),
								new CVar('times['.$i.'][ts_to]', $serviceTime['ts_to']),
								new CVar('times['.$i.'][note]', $serviceTime['note'])
						],
						$from.' - '.$to,
						htmlspecialchars($serviceTime['note']),
				]);
				$row->setId('times_'.$i);
				$servicesTimeTable->addRow($row);
				$i++;
			}
		}
		
		$servicesTimeFormList->addRow(_('Períodos Descartados'),
				(new CDiv($servicesTimeTable))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				);
		
		// create service time table
		$serviceTimeTable = (new CTable());
			// downtime since
			if (isset($_REQUEST['new_service_time']['from'])) {
				$fromYear = getRequest('new_service_time_from_year');
				$fromMonth = getRequest('new_service_time_from_month');
				$fromDay = getRequest('new_service_time_from_day');
				$fromHours = getRequest('new_service_time_from_hour');
				$fromMinutes = getRequest('new_service_time_from_minute');
				$fromDate = [
						'y' => $fromYear,
						'm' => $fromMonth,
						'd' => $fromDay,
						'h' => $fromHours,
						'i' => $fromMinutes
				];
				$serviceTimeFrom = $fromYear.$fromMonth.$fromDay.$fromHours.$fromMinutes;
			}
			else {
				$downtimeSince = date(TIMESTAMP_FORMAT_ZERO_TIME);
				$fromDate = zbxDateToTime($downtimeSince);
				$serviceTimeFrom = $downtimeSince;
			}
			$servicesForm->addVar('new_service_time[from]', $serviceTimeFrom);
			
			// downtime till
			if (isset($_REQUEST['new_service_time']['to'])) {
				$toYear = getRequest('new_service_time_to_year');
				$toMonth = getRequest('new_service_time_to_month');
				$toDay = getRequest('new_service_time_to_day');
				$toHours = getRequest('new_service_time_to_hour');
				$toMinutes = getRequest('new_service_time_to_minute');
				$toDate = [
						'y' => $toYear,
						'm' => $toMonth,
						'd' => $toDay,
						'h' => $toHours,
						'i' => $toMinutes
				];
				$serviceTimeTo = $toYear.$toMonth.$toDay.$toHours.$toMinutes;
			}
			else {
				$downtimeTill = date(TIMESTAMP_FORMAT_ZERO_TIME, time() + SEC_PER_DAY);
				$toDate = zbxDateToTime($downtimeTill);
				$serviceTimeTo = $downtimeTill;
			}
			$servicesForm->addVar('new_service_time[to]', $serviceTimeTo);
			
			$serviceTimeTable
			->addRow([
					_('Note'),
					(new CTextBox('new_service_time[note]'))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('placeholder', _('short description'))
			])
			->addRow([_('From'), createDateSelector('new_service_time_from', $fromDate, 'new_service_time_to')])
			->addRow([_('Till'), createDateSelector('new_service_time_to', $toDate, 'new_service_time_from')]);
		
		@$servicesTimeFormList->addVar(SERVICE_TIME_TYPE_ONETIME_DOWNTIME, $type);
		$servicesTimeFormList->addRow(_('Novo Período de Descarte'),
				(new CDiv([
						$serviceTimeTable,
				]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
				);
		
		/*
		 * Append tabs to form
		 */
		$servicesTab = new CTabView();
		$servicesTab
		->addTab('servicesTimeTab', _('Time'), $servicesTimeFormList);
		
		// append buttons to form
		$buttons = [(new CButtonCancel(SPACE, null))
				->setTitle(_('Back'))
				->onClick('redirect("'.$backURL.'")')];
		
		$servicesTab->setFooter(makeFormFooter(
					(new CSubmit('update', _('Add')))->onClick('javascript: document.forms[0].action += \'?saction=1\';'),
					$buttons
					));
		
		$servicesForm->addItem($servicesTab);
		$servicesForm->addVar('eventid', $eventid);
		$servicesForm->addVar('from', $event_from);
		$servicesForm->addVar('till', $event_till);
		$servicesForm->addVar('name', $name);
		// append form to widget
		$widget->addItem($servicesForm);
		$widget->show();
	}
}

require_once dirname(__FILE__).'/include/page_footer.php';
