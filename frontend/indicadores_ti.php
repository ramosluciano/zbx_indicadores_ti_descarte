<?php
/*
 ** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** Customizado por Luciano Ramos
** luciano.ramos@serpro.gov.br
** Módulo Indicadores de TI - Listagem de serviços de TI com percentual de disponibilidade calculado
** e acesso aos eventos para gestão e possibilidade de descarte do cálculo do indicador.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';
require_once dirname(__FILE__).'/include/funcoes_customizadas.php';

$page['title'] = 'Indicadores de TI';
$page['file'] = 'indicadores_ti.php';
$sessao = new CSession();
// Libera as chaves de sessão do botão volta.
$sessao->unsetValue(array('servico', 'cliente', 'local', 'period'));

// Monta a lista de serviços
$options = array(
		'output' => array('serviceid','name')
		,'filter' => array('name' => 'Serviços')
		,'sortfield' => 'name'
);

$result = @API::Service()->get($options);
$servicos_serviceid = @$result[0]['serviceid'];

$options = array(
		'output' => array('serviceid','name')
		,'parentids' => $servicos_serviceid
		,'selectDependencies' => array('serviceid')
		,'sortfield' => 'name'
);

$root_servicos = API::Service()->get($options);
$servicos = array();

foreach($root_servicos as $registro){
	if (!empty($registro['dependencies'])){
		$servicos[$registro['serviceid']] =_($registro['name']);
	}
}

// Parametros
$_servico = (isset($_REQUEST['servico']) /*|| $_REQUEST['servico'] != 0*/)? $_REQUEST['servico'] : null;
$_cliente = (isset($_REQUEST['cliente']))? $_REQUEST['cliente'] : null;
$_local = (isset($_REQUEST['local']))? $_REQUEST['local'] : null;
$_period = (isset($_REQUEST['period']))? $_REQUEST['period'] : getRequest('period', 7 * 24);


define('ZBX_PAGE_DO_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// Limpa os filtros de pesquisa
if(hasRequest('filter_rst')){
	$_REQUEST['servico'] = $_servico = null;
	$_REQUEST['cliente'] = $_cliente = null;
	$_REQUEST['local'] = $_local = null;
	$_REQUEST['period'] = $_period = getRequest('period', null);

	$url = (new CUrl())
	->removeArgument('servico')
	->removeArgument('cliente')
	->removeArgument('local')
	->removeArgument('period')
	->removeArgument('filter_set')
	->removeArgument('filter_rst')
	;
	$clientes = array();
}
else $url = (new CUrl());



// Monta a lista de clientes
$clientes = array();
$locais = array();

if (isset($_servico)){
	$options = array(
			array('serviceid','name'),
			'parentids' => array($_servico),
			'selectDependencies' => array('serviceid'),
			'sortfield' => 'name'
	);
	
	$result = API::Service()->get($options);
	
	foreach($result as $cliente){
		if (!empty($cliente['dependencies'])){
			$clientes[$cliente['serviceid']] = array('key' => $cliente['serviceid'], 'name' => _($cliente['name']),'dependencies' => $cliente['dependencies']);
		}
	}
}

// Gera os periodos customizados
$periods = getPeriodos();

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
		'servicos'  =>	[T_ZBX_INT, O_OPT, P_SYS,	IN('"'.implode('","', array_keys($servicos)).'"'),	null],
		'local	'  =>	[T_ZBX_INT, O_OPT, P_SYS,	IN('"'.implode('","', array_keys($locais)).'"'),	null],
		'period' =>		[T_ZBX_STR, O_OPT, P_SYS,	IN('"'.implode('","', array_keys($periods)).'"'),	null],
	    // Botões
		'filter_rst'=>			[T_ZBX_STR,	O_OPT,	P_SYS,			null,		null],
		'filter_set' =>			[T_ZBX_STR,	O_OPT,	P_SYS,			null,		null],
		
];
check_fields($fields);

/** 
 *  Cria form com os atributos:
 *  Servico, Cliente, local e Periodo
 */
$r_form = (new CForm('get'))
		->setAttribute('name', 'servico')
		->setAttribute('name', 'cliente')
		->setAttribute('name', 'local')
		->setAttribute('name', 'period_choice')
;

		// Monta o combo dos serviços.
		$servico_combo = new CComboBox('servico', $servicos, 'javascript: submit();');
		$servico_combo->addItem(0, _("Selecione serviço"));
		foreach ($servicos as $key => $val) {
			if ($key == $_servico)
				$servico_combo->addItem($key, $val,true);
			else
				$servico_combo->addItem($key, $val);
		}

		// Combo de clientes
		unset($cmbCliente);
		if (!empty($clientes)){
			$cmbCliente = new CComboBox('cliente', $_cliente, 'submit()');
			if ($_servico == array_search('WIFI', $servicos)){
				$cmbCliente->addItem('-1', _("Selecione um nível de serviço"));
				$cmbCliente->addItem(0, _("Todos os níveis"));
			} else {
				$cmbCliente->addItem('-1', _("Selecione um cliente"));
				$cmbCliente->addItem(0, _("Todos os clientes"));
			}
			foreach ($clientes as $key => $val) {
				$cmbCliente->addItem($key, $val['name']);
			}				
		}

		/**
		 * Monta o Combo de localidades. 
		 * 
		 */
		if(!is_null($_cliente)){
			$cmbLocalidade = new CComboBox('local', $_local);
			$cmbLocalidade->addItem('XX', _("Selecione uma Localidade"));
			$cmbLocalidade->addItem(0, _("Todas as Localidades"));
			
			$locais = lista_localidades($_cliente);
			foreach($locais as $local){
				if (!empty($local['dependencies']))
					$cmbLocalidade->addItem("$local[serviceid]", _("$local[name]"));
			}
		}
	
		/**
		 * Combo de Periodos
		 */
		$period_combo = new CComboBox('period', $periods);
		foreach ($periods as $key => $val) {
			if ($key == $_period)
				$period_combo->addItem($key, $val,true);
			else
				$period_combo->addItem($key, $val);
		}
		// controls
		$r_form->addItem((new CList())
				->addItem(['Serviço', SPACE, $servico_combo])
				->addItem((isset($cmbCliente))?['Cliente', SPACE, $cmbCliente]:[])
				->addItem((isset($cmbLocalidade))?['Localidade', SPACE, $cmbLocalidade]:[])
				->addItem([_('Period'), SPACE, $period_combo])
				// Botões
				->addItem((new CSubmitButton(_('Apply'), 'filter_set', 1)))
				->addItem((new CSubmitButton(_('Limpar'), 'filter_rst', 1))
								->addClass(ZBX_STYLE_BTN_ALT))
				);
		$div = new CDiv();
				
		if(hasRequest('filter_set') && isset($_servico) && $_servico != 0){

		 	/** =============================================================================================================  */
		 	$period_end = time();
		 	switch($_period){
		 		case 'hoje': $period_start = mktime(0, 0, 0, date('n'), date('j'), date('Y')); break;
		 		case 'semana': $period_start = strtotime('last sunday'); break;
		 		case 'mes': $period_start = mktime(0, 0, 0, date('n'), 1, date('Y'));break;
		 		case 'ano': $period_start = mktime(0, 0, 0, 1, 1, date('Y')); break;
		 		case 24: $period_start = $period_end - (24 * 3600); break;
		 		case 24*7: $period_start = $period_end - (24*7 * 3600); break;
		 		case 24*30: $period_start = $period_end - (24*30 * 3600); break;
		 		case 24*365: $period_start = $period_end - (24*365 * 3600);
		 		case 'Ciclo2 atual':
		 			if (date('d') < 11){
		 				$period_start = mktime(0, 0, 0, date('n') -1, 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n'), 10, date('Y'));
		 			}
		 			else {
		 				$period_start = mktime(0, 0, 0, date('n'), 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n') +1, 10, date('Y'));
		 			}
		 			break;
		 		case 'Ciclo2a anterior':
		 			if (date('d') < 11){
		 				$period_start = mktime(0, 0, 0, date('n') -2, 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n') -1, 10, date('Y'));
		 			}
		 			else {
		 				$period_start = mktime(0, 0, 0, date('n') -1, 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n'), 10, date('Y'));
		 			}
		 			break;
		 		case 'Ciclo2b anterior':
		 			if (date('d') < 11){
		 				$period_start = mktime(0, 0, 0, date('n') -3, 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n') -2, 10, date('Y'));
		 			}
		 			else {
		 				$period_start = mktime(0, 0, 0, date('n') -2, 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n') -1, 10, date('Y'));
		 			}
		 			break;
		 		case 'Ciclo2c anterior':
		 			if (date('d') < 11){
		 				$period_start = mktime(0, 0, 0, date('n') -4, 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n') -3, 10, date('Y'));
		 			}
		 			else {
		 				$period_start = mktime(0, 0, 0, date('n') -3, 11, date('Y'));
		 				$period_end = mktime(23, 59, 59, date('n') -2, 10, date('Y'));
		 			}
		 			break;
		 		case 'Ciclo3 atual':
		 			$period_start = mktime(0, 0, 0, date('n'), 1, date('Y'));
		 			$period_end = mktime(23, 59, 59, date('n') +1, 0, date('Y'));
		 			break;
		 		case 'Ciclo3 anterior':
		 			$period_start = mktime(0, 0, 0, date('n') -1, 1, date('Y'));
		 			$period_end = mktime(23, 59, 59, date('n'), 0, date('Y'));
		 			break;
		 	
		 	}
		 	/**
		 	 * Seta as variaveis para fazer o botão "Voltar" do Eventos
		 	 */
		 	$sessao->setValue('servico', $_servico);
		 	$sessao->setValue('cliente',$_cliente);
		 	$sessao->setValue('local', $_local);
		 	$sessao->setValue('period', $_period);
		 	
		 	$textoperiodo = $periods[$_REQUEST['period']];
		 	$div->addItem((new CTag('h4', true, _("Período de coleta: $textoperiodo")))->addStyle('border-bottom: 1px solid #dfe4e7'));

	 		if ($_cliente == '0'){
	 			$_local = 0;
	 			foreach($clientes as $cliente){
	 				Cria_Widget_Cliente($div, $cliente, $_local, $period_start, $period_end, $_servico, $servicos[$_servico]);
	 			}
	 		} elseif ($_cliente != -1) {	 			
	 			Cria_Widget_Cliente($div, (isset($_cliente)) ? $clientes[$_cliente]: null, $_local, $period_start, $period_end, $_servico, $servicos[$_servico]);
	 		}

		 	/** =============================================================================================================  */				
		}
		
		$srv_wdgt = (new CWidget())
		->setTitle('Indicadores de TI')
		->setControls($r_form)
		->addItem($div)
		->show();

require_once dirname(__FILE__).'/include/page_footer.php';
