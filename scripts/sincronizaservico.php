<?php
/**
 * Mantém a arvore de Serviços de TI do ZABBIX 
 * Customizado por Luciano Ramos
 * luciano.ramos@serpro.gov.br
 * 
 */ 

date_default_timezone_set('America/Sao_Paulo'); //Configuração de Timezone da Regiao

require_once '/root/scripts/PhpZabbixApi_Library/ZabbixApiAbstract.class.php';
require_once '/root/scripts/PhpZabbixApi_Library/ZabbixApi.class.php';

$URL = $argv[1]; // Parametro 1 - endereço do servidor zabbix
$SERVICE = $argv[2]; // Parametro 2 - nome do serviço a ser sincronizado 

// URL de acesso ao Servidor Zabbix (fornecido por parametro), usuario com acesso API e senha para autenticação no Zabbix
$api = new ZabbixApi('https://'.$URL.'/api_jsonrpc.php', 'USER', 'PASSWORD');

$i=0;
$GLOBALS['log'] = $log = fopen("/var/log/zabbix/$URL-$SERVICE-log.txt", "a"); //Endereço da log
$GLOBALS['logerror'] = $logerror = fopen("/var/log/zabbix/logError-$SERVICE.txt", "a"); //Endereço da log de erros
fwrite($log, "\nProcessamento ZABBIX ".$URL." - ".date('d/m/Y H:i:s')."\n");
try {
	/*
	 * * Busca o nó raiz "Serviços/$SERVICE"
		*/
		
		echo "Montando árvore cliente\n";
		fwrite($log,"Montando árvore\n");
		$servicoraiz = $api->serviceGet(
				array(
						'output' => 'extend' 
						,'filter' => ['name'=>'Serviços'] //IT Services Raiz "Serviços"
				));
		
		 $servicoraiz = objectToArray($servicoraiz); 
		
		 $serv = $api->serviceGet(
				array(
						'output' => 'extend'
						,'filter' => ['name' => $SERVICE ] //Nome do serviço a ter os IT Services sincronizado
						,'parentids' => $servicoraiz[0]['serviceid']
				));
		$serv = objectToArray($serv);
		
		//coletando Hostgroups dos $SERVICE informado

		$grupos = $api->hostgroupGet(
			array(
					'output' => ['name'],
					'search' => ['name'=> '_'.$SERVICE.'_'], //Padrão de grupo de host do serviço
					//'monitored_hosts' => TRUE
					'selectHosts' => ['host', 'name']
			));
		$grupos = objectToArray($grupos);
		
		foreach($grupos as $grupo){
			$clt = explode('_', $grupo['name']);
			$cliente = $clt[2]; // Utilizando terceiro campo separado por "_"  como cliente no nome do hostgroup
			$lista_clientes[] = $cliente;
			$cliente_serviceid = incluiNo($api, $cliente, $serv[0]['serviceid']);
			$local_serviceid = incluiNo($api, $grupo['name'], $cliente_serviceid);
			foreach($grupo['hosts'] as $host){
				$host_serviceid = incluiNo($api, $host['host'].' - '.$host['name'] , $local_serviceid);
				$trigger = $api->triggerGet(
					array(
							'output' => ['description'],
                            'hostids' => $host['hostid'],
                            //Padrão de string a ser encontrada na descrição da trigger a ser usada para cálculo da disponibilidade
							'search' => ['description'=> ' indisponivel ha mais de 5 minutos'], 
							//'monitored' => TRUE,
					));
				$trigger = objectToArray($trigger);
     
				if(!empty($trigger))
					 $trigger_serviceid = incluiNoTrigger($api, 'Dispositivo '.$host['host'].' indisponivel ha mais de 5 minutos',
					  $host_serviceid, $trigger[0]['triggerid']);
			}
		}
		$lista_clientes = array_unique($lista_clientes);
		
} catch (Exception $e) {
	echo $e->getLine()."\t";
	echo $e->getMessage()."\n";
	exit;
}

/*
 * Exclui nós.
 * $servico tem que ser um/array objeto tipo zabbix.service
*/
function excluiNo($api, $servicos){
	
	if (!is_array($servicos)) {
		$services = array_fill(0,1,$servicos);
	} else $services = $servicos;
	foreach ($services as $servico){
		$consulta = (array) $api->serviceGet(
					array(
							'output' => array('serviceid', 'name')
							,'serviceids' => $servico->serviceid 
							,'selectDependencies' => 'extend'
			));

			if (empty(objectToArray($consulta[0]->dependencies))){
				$api->serviceDelete($consulta[0]->serviceid);
				echo "SERVICE " .$consulta[0]->name. " EXCLUIDO \n";
				fwrite($GLOBALS['log'],"\t".$$consulta[0]->name." EXCLUIDO \n");
				$api->serviceAddDependencies(
					array(
							'serviceid' => $servicoPaiId
							,'dependsOnServiceid' => $servico['serviceids'][0]
							,'soft' => 0
					));
					
			}
			else{
				excluiNo($api, $consulta[0]->dependencies);
				excluiNo($api, $servico);
				
			}	
	}
	return ;
}


/*
 * Essa função pega um ÚNICO Nó e retorna todos as dependências com o nome.
 * $serviceDown tem que ser um objeto tipo service
 */
function getDownServices($api, $serviceDown) {
	$serviceDown = objectToArray($serviceDown);
	for ($a=0;$a<count($serviceDown["dependencies"]);$a++){
		
 		$dependencia = $api->serviceGet(
 			array(
 					'output' => array('serviceid','name')
 					,'serviceids' => $serviceDown["dependencies"][$a]['serviceid']
 			));
 		$dependencia = objectToArray($dependencia);
 		$serviceDown["dependencies"][$a]['name'] = $dependencia[0]["name"];
 		
 }
 	return $serviceDown;
 			
}
/*
 * Inclui nós clientes com o nome do serviço e o nó pai.
 */
function incluiNo($api, $nomeservico, $servicoPaiId){
	
	$servico = $api->serviceGet(
		array(
				'output' => 'extend'
				,'filter' => ['name' => $nomeservico]
				,'parentids' => $servicoPaiId
		));
		$servico = objectToArray($servico);

		if(empty($servico)){
			$servico = $api->serviceCreate(
					array(
							'name' => $nomeservico
							,'algorithm' => 1 //Algoritmo de calculo do ANS
							,'showsla' => 0
							,'goodsla' => 99 //SLA contratado pelo cliente
							,'sortorder' => 0
					));
			$servico = objectToArray($servico);
			//fwrite($GLOBALS['log'],"\n".$nomeservico);

			$api->serviceAddDependencies(
					array(
							'serviceid' => $servicoPaiId
							,'dependsOnServiceid' => $servico['serviceids'][0]
							,'soft' => 0
					));
			echo "SERVICE ".$nomeservico." CRIADO \n";
			fwrite($GLOBALS['log'],"SERVICE ".$nomeservico." CRIADO \n");
			return $servico['serviceids'][0];
		} else {
			//echo "SERVICE $nomeservico JA EXISTE \n";
			return $servico[0]['serviceid'];
		}
}

function incluiNoTrigger($api, $nomeservico, $servicoPaiId, $triggerid){
	
	$servico = $api->serviceGet(
		array(
				'output' => 'extend'
				,'filter' => ['name' => $nomeservico]
				,'parentids' => $servicoPaiId
		));
		$servico = objectToArray($servico);

		if(empty($servico)){
			$servico = $api->serviceCreate(
					array(
							'name' => $nomeservico
							,'algorithm' => 1
							,'showsla' => 0
							,'goodsla' => 99 //SLA contratado pelo cliente
							,'sortorder' => 0
							,'triggerid' => $triggerid
					));
			$servico = objectToArray($servico);
			fwrite($GLOBALS['log'],"\n".$nomeservico);

			$api->serviceAddDependencies(
					array(
							'serviceid' => $servicoPaiId
							,'dependsOnServiceid' => $servico['serviceids'][0]
							,'soft' => 0
					));
			echo "SERVICE ".$nomeservico." CRIADO \n";
			fwrite($GLOBALS['log'],"\t"."SERVICE ".$nomeservico." CRIADO \n");
			//return $servico['serviceids'][0];
		} else {
			if($triggerid != $servico[0]['triggerid']){
				echo "TRIGGER DIFERENTE EM SERVICE $nomeservico - ALTERAR TRIGGER\n";
				updateService($api, $servico[0]['serviceid'], $triggerid, $nomeservico);
			} 
						
		}
}

function updateService($api, $serviceid, $triggerid, $nomeservico){
	$servico_upd = $api->serviceUpdate(
		array(
				'serviceid' => $serviceid
				,'triggerid' => $triggerid
		));
	$servico_upd = objectToArray($servico_upd);
	if(!empty($servico_upd)){
		echo "TRIGGER ATUALIZADA EM SERVICE $nomeservico \n";
		fwrite($GLOBALS['log'], "TRIGGER ATUALIZADA EM SERVICE $nomeservico \n");
		//return $servico['serviceids'][0];
	}
}

function objectToArray($d) {
	if (is_object($d)) {
		// Gets the properties of the given object
		// with get_object_vars function
		$d = get_object_vars($d);
	}

	if (is_array($d)) {
		/*
			* Return array converted to object
		* Using __FUNCTION__ (Magic constant)
		* for recursive call
		*/
		return array_map(__FUNCTION__, $d);
	}
	else {
		// Return array
		return $d;
	}
}
		

function arrayToObject($d) {
	if (is_array($d)) {
		/*
			* Return array converted to object
		* Using __FUNCTION__ (Magic constant)
		* for recursive call
		*/
		return (object) array_map(__FUNCTION__, $d);
	}
	else {
		// Return object
		return $d;
	}
}
