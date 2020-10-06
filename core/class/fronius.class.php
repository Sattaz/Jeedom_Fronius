<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class fronius extends eqLogic {
    /*     * *************************Attributs****************************** */

    /*     * ***********************Methode static*************************** */

    //Fonction exécutée automatiquement toutes les minutes par Jeedom
    public static function cron() {
		foreach (self::byType('fronius') as $fronius) {//parcours tous les équipements du plugin fronius
			if ($fronius->getIsEnable() == 1) {//vérifie que l'équipement est actif
				$cmd = $fronius->getCmd(null, 'refresh');//retourne la commande "refresh si elle existe
				if (!is_object($cmd)) {//Si la commande n'existe pas
					continue; //continue la boucle
				}
				$cmd->execCmd(); // la commande existe on la lance
			}
		}
    }
    
	/*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */



    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
		$this->setDisplay("width","400px");
		$this->setDisplay("height","350px");
    }

    public function postSave() {
		$this->loadCmdFromConf($this->getConfiguration('type'));
		$this->loadWebhook();
			
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
		$cmd = $this->getCmd(null, 'refresh'); // On recherche la commande refresh de l’équipement
		if (is_object($cmd)) { //elle existe et on lance la commande
			 $cmd->execCmd();
		}
    }

    public function preRemove() {
       
    }

    public function postRemove() {
        
    }
  
	public function loadCmdFromConf($type) {
		log::add('fronius','debug','Loading cmd for type : ' . $type . ' on ' . $this->getName());
		if (!is_file(dirname(__FILE__) . '/../config/devices/' . $type . '.json')) {
			return;
		}
		$content = file_get_contents(dirname(__FILE__) . '/../config/devices/' . $type . '.json');
        log::add('fronius','debug','Loading  contenu : ' . $content . ' on ' . $this->getName());
		if (!is_json($content)) {
          	log::add('fronius','debug','Loading  contenu : pas ok');
			return;
		}
		$device = json_decode($content, true);
        log::add('fronius','debug','Loading  device : ' . $device);
		if (!is_array($device) || !isset($device['commands'])) {
			return true;
		}
		foreach ($device['commands'] as $command) {
			$cmd = null;
			foreach ($this->getCmd() as $liste_cmd) {
              	log::add('fronius','debug','Loading  logical id : ' . $command['logicalId']);
				if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
				|| (isset($command['name']) && $liste_cmd->getName() == $command['name'])) {
					$cmd = $liste_cmd;
					break;
				}
			}
			if ($cmd == null || !is_object($cmd)) {
				log::add('fronius','debug','Creating cmd : ' . $command['name'] );
				$cmd = new shellyCmd();
				$cmd->setEqLogic_id($this->getId());
				utils::a2o($cmd, $command);
				$cmd->save();
			}
          	
		}
        log::add('fronius','debug','Refresh : ' . print_r($data,true));
	}
  
    public function loadWebhook() {
      
      
      	$Fronius_IP = $this->getConfiguration("IP");
		$Fronius_Port = $this->getConfiguration("Port");
		$VersionAPI = '';
		$Url = '';
        
      
        if (strlen($Fronius_IP) == 0) {
			log::add('fronius', 'debug','No IP defined for PV inverter interface ...');
			$this->checkAndUpdateCmd('status', 'IP onduleur manquante ...');
			return;
		}
		
		if (strlen($Fronius_Port) == 0) {
			log::add('fronius', 'debug','No port defined for PV inverter interface. port 80 used');
          	$Fronius_Port = 80;
		}
		log::add('fronius', 'debug','ip= '.$Fronius_IP);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		// READ STORED API VERSION
		try {
			$cmd = $this->getCmd(null, 'VersionAPI'); // On recherche la commande refresh de l’équipement
			if (is_object($cmd)) { //elle existe et on lance la commande
				$VersionAPI = $cmd->execCmd();
			}
		} catch (Exception $e) {
			$VersionAPI = '';
			log::add('fronius', 'error','Error reading API Version: '.$e);
		}
		
		if ($VersionAPI == '') {
			curl_setopt($ch, CURLOPT_URL, 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/GetAPIVersion.cgi');
			$data = curl_exec($ch);
          log::add('fronius', 'debug','data= '.$data);
			if (curl_errno($ch)) {
				curl_close ($ch);
				log::add('fronius', 'error','Error getting inverter API Version: '.curl_error($ch));
				$this->checkAndUpdateCmd('status', 'Erreur Version API');
				return;
			}
			$json = json_decode($data, true);
			$VersionAPI = $json['APIVersion'];
			$this->checkAndUpdateCmd('API', $VersionAPI);
		}
		
		switch ($VersionAPI) {		
			case '0':
				$Url = 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/GetInverterRealtimeData.cgi?Scope=Device&DeviceId=1&DataCollection=CommonInverterData';
				break;	

			case '1';
				if ($this->getConfiguration('type') == "Primo") {
                    $Url = 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/v1/GetInverterRealtimeData.cgi?Scope=Device&DeviceId=1&DataCollection=CommonInverterData';
                }
                if ($this->getConfiguration('type') == "SymoGen24") {
                    $Url = 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/v1/GetInverterRealtimeData.cgi?scope=Device&DataCollection=CommonInverterData';
                }
                if ($this->getConfiguration('type') == "SmartMeter1ph") {
                    $Url = 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/v1/GetMeterRealtimeData.cgi?Scope=Device&DeviceId=0';
                }
		if ($this->getConfiguration('type') == "SmartMeter3ph") {
                    $Url = 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/v1/GetMeterRealtimeData.cgi?Scope=Device&DeviceId=0';
                }
            	
				break;
		
			default:
				log::add('fronius', 'error','Error getting inverter API Version: '.curl_error($ch));
				$this->checkAndUpdateCmd('status', 'Version API non supportée');
				return;
		}
      
      
      
        log::add('fronius','debug','Call : ' . $Url);
      
        curl_setopt($ch, CURLOPT_URL, $Url);
		$data = curl_exec($ch);
		
		if (curl_errno($ch)) {
			curl_close ($ch);
			log::add('fronius', 'error','Error getting inverter values: '.curl_error($ch));
			$this->checkAndUpdateCmd('status', 'Erreur Données');
			return;
		}
        log::add('fronius','debug','Call : ' . $data);
        $json = json_decode($data, true);
		log::add('fronius', 'debug','All good22: json='. $json['Body']['Data']['IAC']['Value']);
      
      	if (isset($json['Body']['Data']['IAC']['Value']) == true && ($this->getConfiguration('type') == 'Primo' || $this->getConfiguration('type') == 'SymoGen24')) {
          log::add('fronius', 'debug','All good33: Data='. $data);
        
			curl_close ($ch);
			$this->checkAndUpdateCmd('PV_Prod', $json['Body']['Data']['PAC']['Value']);
			$this->checkAndUpdateCmd('PV_Tot', $json['Body']['Data']['TOTAL_ENERGY']['Value']);
			$this->checkAndUpdateCmd('Freq', $json['Body']['Data']['FAC']['Value']);
			$this->checkAndUpdateCmd('VoltsAC', $json['Body']['Data']['UAC']['Value']);
			$this->checkAndUpdateCmd('VoltsDC', $json['Body']['Data']['UDC']['Value']);
			$this->checkAndUpdateCmd('AmpsAC', $json['Body']['Data']['IAC']['Value']);
			$this->checkAndUpdateCmd('AmpsDC', $json['Body']['Data']['IDC']['Value']);
			$this->checkAndUpdateCmd('PV_Jour', $json['Body']['Data']['DAY_ENERGY']['Value']);
			$this->checkAndUpdateCmd('PV_An', $json['Body']['Data']['YEAR_ENERGY']['Value']);
          log::add('fronius', 'debug','Status = '.$json['Body']['Data']['DeviceStatus']['InverterState']);
			if ($json['Body']['Data']['DeviceStatus']['InverterState'] == 'Running') {
				$this->checkAndUpdateCmd('StatusString', 'En ligne');
              	$this->checkAndUpdateCmd('StatusBinaire', '1');
				log::add('fronius', 'debug','Status OK= ');
			//return;
			} else {
				$this->checkAndUpdateCmd('StatusString', 'Hors Ligne ...');
              	$this->checkAndUpdateCmd('StatusBinaire', '0');
				log::add('fronius', 'debug','Inverter is off-line ...');
          		//return;
			}
		}
        if (isset($json['Body']['Data']['IAC']['Value']) == true && $this->getConfiguration('type') == 'SymoGen24') {
          log::add('fronius', 'debug','Symo='. $data);

			$this->checkAndUpdateCmd('VoltsACL2', $json['Body']['Data']['UAC_L1']['Value']);
			$this->checkAndUpdateCmd('VoltsACL3', $json['Body']['Data']['UAC_L2']['Value']);
			$this->checkAndUpdateCmd('VoltsDCL2', $json['Body']['Data']['UDC_2']['Value']);
			$this->checkAndUpdateCmd('AmpsACL1', $json['Body']['Data']['IAC_L1']['Value']);
			$this->checkAndUpdateCmd('AmpsACL2', $json['Body']['Data']['IAC_L2']['Value']);
			$this->checkAndUpdateCmd('AmpsACL3', $json['Body']['Data']['IAC_L3']['Value']);
			$this->checkAndUpdateCmd('AmpsDC2', $json['Body']['Data']['IDC_2']['Value']);
			return;
		}
	if ((isset($json['Body']['Data']['Details']['Model']) == "Smart Meter 63A" || isset($json['Body']['Data']['Details']['Model']) == "Smart Meter 5kA-3" ) && ($this->getConfiguration('type') == 'SmartMeter3p')) {
          log::add('fronius', 'debug','All good33: Data='. $data);
        
			curl_close ($ch);
			$this->checkAndUpdateCmd('Production', $json['Body']['Data']['SMARTMETER_ENERGYACTIVE_PRODUCED_SUM_F64']);
			$this->checkAndUpdateCmd('Consommation', $json['Body']['Data']['SMARTMETER_ENERGYACTIVE_CONSUMED_SUM_F64']);
			$this->checkAndUpdateCmd('Freq', $json['Body']['Data']['GRID_FREQUENCY_MEAN_F32']);
			$this->checkAndUpdateCmd('VoltsAC', $json['Body']['Data']['SMARTMETER_VOLTAGE_MEAN_01_F64']);
			$this->checkAndUpdateCmd('VoltsACL2', $json['Body']['Data']['SMARTMETER_VOLTAGE_MEAN_02_F64']);
			$this->checkAndUpdateCmd('VoltsACL3', $json['Body']['Data']['SMARTMETER_VOLTAGE_MEAN_03_F64']);
			//$this->checkAndUpdateCmd('AmpsAC', $json['Body']['Data']['IDC']['Value']);
			$this->checkAndUpdateCmd('AmpsACL1', $json['Body']['Data']['ACBRIDGE_CURRENT_ACTIVE_MEAN_01_F32']);
			$this->checkAndUpdateCmd('AmpsACL2', $json['Body']['Data']['ACBRIDGE_CURRENT_ACTIVE_MEAN_02_F32']);
			$this->checkAndUpdateCmd('AmpsACL3', $json['Body']['Data']['ACBRIDGE_CURRENT_ACTIVE_MEAN_03_F32']);
			$this->checkAndUpdateCmd('WattL1', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_01_F64']);
			$this->checkAndUpdateCmd('WattL2', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_02_F64']);
			$this->checkAndUpdateCmd('WattL3', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_03_F64']);
			$this->checkAndUpdateCmd('Watt', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_SUM_F64']);
			$this->checkAndUpdateCmd('visible', $json['Body']['Data']['COMPONENTS_MODE_VISIBLE_U16']);
			$this->checkAndUpdateCmd('StatusBinaire', $json['Body']['Data']['COMPONENTS_MODE_ENABLE_U16']);
          
			
		}
	   if (isset($json['Body']['Data']['Details']['Model']) == "Smart Meter 63A-1" && ($this->getConfiguration('type') == 'SmartMeter1p')) {
          log::add('fronius', 'debug','All good33: Data='. $data);
        
			curl_close ($ch);
			$this->checkAndUpdateCmd('Production', $json['Body']['Data']['SMARTMETER_ENERGYACTIVE_PRODUCED_SUM_F64']);
			$this->checkAndUpdateCmd('Consommation', $json['Body']['Data']['SMARTMETER_ENERGYACTIVE_CONSUMED_SUM_F64']);
			$this->checkAndUpdateCmd('Freq', $json['Body']['Data']['GRID_FREQUENCY_MEAN_F32']);
			$this->checkAndUpdateCmd('VoltsAC', $json['Body']['Data']['SMARTMETER_VOLTAGE_MEAN_01_F64']);
			//$this->checkAndUpdateCmd('VoltsACL2', $json['Body']['Data']['SMARTMETER_VOLTAGE_MEAN_02_F64']);
			//$this->checkAndUpdateCmd('VoltsACL3', $json['Body']['Data']['SMARTMETER_VOLTAGE_MEAN_03_F64']);
			//$this->checkAndUpdateCmd('AmpsAC', $json['Body']['Data']['IDC']['Value']);
			$this->checkAndUpdateCmd('AmpsAC', $json['Body']['Data']['ACBRIDGE_CURRENT_ACTIVE_MEAN_01_F32']);
			//$this->checkAndUpdateCmd('AmpsACL2', $json['Body']['Data']['ACBRIDGE_CURRENT_ACTIVE_MEAN_02_F32']);
			//$this->checkAndUpdateCmd('AmpsACL3', $json['Body']['Data']['ACBRIDGE_CURRENT_ACTIVE_MEAN_03_F32']);
			//$this->checkAndUpdateCmd('WattL1', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_01_F64']);
			//$this->checkAndUpdateCmd('WattL2', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_02_F64']);
			//$this->checkAndUpdateCmd('WattL3', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_03_F64']);
			$this->checkAndUpdateCmd('Watt', $json['Body']['Data']['SMARTMETER_POWERACTIVE_MEAN_SUM_F64']);
			$this->checkAndUpdateCmd('visible', $json['Body']['Data']['COMPONENTS_MODE_VISIBLE_U16']);
			$this->checkAndUpdateCmd('StatusBinaire', $json['Body']['Data']['COMPONENTS_MODE_ENABLE_U16']);
          
			
		}
      
    } 
/*
      
		$request_http = new com_http($Url);
		$request_http->setNoReportError(true);
		if ($this->getConfiguration('user', '') != '' || $this->getConfiguration('password', '') != '') {
			$auth = base64_encode(trim($this->getConfiguration('user')) . ':' . trim($this->getConfiguration('password')));
			$request_http->setHeader(array("Authorization: Basic $auth"));
		}
		return $request_http->exec(30);
      
		
		$status = $this->sendCommand('status');
		$data = json_decode($status,true);
		log::add('fronius','debug','Refresh : ' . print_r($data,true));
		if (isset($data['relays']) == true && $this->getConfiguration('type') != 'shelly2-roller') {
			$i = 0;
			$url = 'http://' . config::byKey('internalAddr') . ':8122/id=' . $this->getId();
			foreach ($data['relays'] as $relay) {
				$url = $url . '&relay=' . $i . '&value=';
				$this->sendCommand('settings/relay/' . $i . '?out_on_url=' . urlencode($url . 'out_on_url'));
				$this->sendCommand('settings/relay/' . $i . '?out_off_url=' . urlencode($url . 'out_off_url'));
				$settings = json_decode($this->sendCommand('settings/relay/' . $i), true);
				log::add('shelly', 'debug', 'Button : ' . print_r($settings, true));
				if ($settings['btn_type'] == 'detached') {
					$cmd = shellyCmd::byEqLogicIdAndLogicalId($this->getId(),'button' . $i);
					if (!is_object($cmd)) {
						$cmd = new shellyCmd();
						$cmd->setName('Bouton ' . $i);
						$cmd->setEqLogic_id($this->id);
						$cmd->setEqType('shelly');
						$cmd->setLogicalId('button' . $i);
						$cmd->setType('info');
						$cmd->setSubType('binary');
						$cmd->save();
					}
					$this->sendCommand('settings/relay/' . $i . '?btn_on_url=' . urlencode($url . 'btn_on_url'));
					$this->sendCommand('settings/relay/' . $i . '?btn_off_url=' . urlencode($url . 'btn_off_url'));
				}
				if ($this->getConfiguration('type') != 'shelly4') {
					$this->sendCommand('settings/relay/' . $i . '?longpush_url=' . urlencode($url . 'longpush_url'));
					$this->sendCommand('settings/relay/' . $i . '?shortpush_url=' . urlencode($url . 'shortpush_url'));
				}
				$i++;
			}
		}
		if (isset($data['inputs']) == true) {
			$i = 0;
			$url = 'http://' . config::byKey('internalAddr') . ':8122/id=' . $this->getId();
			foreach ($data['inputs'] as $relay) {
				$url = $url . '&input=' . $i . '&';
				if ($this->getConfiguration('type') == 'button1') {
					$this->sendCommand('settings/input/' . $i . '?shortpush_url=' . urlencode($url . 'event=s'));
					$this->sendCommand('settings/input/' . $i . '?longpush_url=' . urlencode($url . 'event=l'));
					$this->sendCommand('settings/input/' . $i . '?double_shortpush_url=' . urlencode($url . 'event=ss'));
					$this->sendCommand('settings/input/' . $i . '?triple_shortpush_url=' . urlencode($url . 'event=sss'));
				} else {
					$settings = json_decode($this->sendCommand('settings/input/' . $i), true);
					if ($settings['btn_type'] == "momentary") {
						$this->sendCommand('settings/input/' . $i . '?shortpush_url=' . urlencode($url . 'event=s'));
						$this->sendCommand('settings/input/' . $i . '?longpush_url=' . urlencode($url . 'event=l'));
						$this->sendCommand('settings/input/' . $i . '?double_shortpush_url=' . urlencode($url . 'event=ss'));
						$this->sendCommand('settings/input/' . $i . '?triple_shortpush_url=' . urlencode($url . 'event=sss'));
						$this->sendCommand('settings/input/' . $i . '?shortpush_longpush_url=' . urlencode($url . 'event=sl'));
						$this->sendCommand('settings/input/' . $i . '?longpush_shortpush_url=' . urlencode($url . 'event=ls'));
					} else {
						$this->sendCommand('settings/input/' . $i . '?btn_on_url=' . urlencode($url . 'value=1'));
						$this->sendCommand('settings/input/' . $i . '?btn_off_url=' . urlencode($url . 'value=0'));
					}
				}
				$i++;
			}
		}
		if (isset($data['emeters']) == true && ($this->getConfiguration('type') == 'shellyem' || $this->getConfiguration('type') == 'shelly3em')) {
			$i = 0;
			$url = 'http://' . config::byKey('internalAddr') . ':8122/id=' . $this->getId();
			foreach ($data['emeters'] as $relay) {
				$this->sendCommand('settings/?over_power_url=' . urlencode($url . 'out_on=' . $i));
				$this->sendCommand('settings/?under_power_url=' . urlencode($url . 'out_off=' . $i));
				$i++;
			}
		}
		if (isset($data['hum']) == true) {
			$url = 'http://' . config::byKey('internalAddr') . ':8122/id=' . $this->getId() . '&';
			$this->sendCommand('settings/?report_url=' . urlencode($url));
		}
		if (isset($data['lux.value']) == true) {
			$url = 'http://' . config::byKey('internalAddr') . ':8122/id=' . $this->getId() . '&';
			$this->sendCommand('settings/?dark_threshold=' . urlencode($url . "illumination=dark"));
			$this->sendCommand('settings/?twilight_threshold=' . urlencode($url . "illumination=twilight"));
		}
		if (isset($data['gas_sensor']) == true) {
			$url = 'http://' . config::byKey('internalAddr') . ':8122/id=' . $this->getId() . '&';
			$this->sendCommand('settings/?alarm_off_url=' . urlencode($url . "alarm_state=none"));
			$this->sendCommand('settings/?alarm_mild_url=' . urlencode($url . "alarm_state=mild"));
			$this->sendCommand('settings/?alarm_heavy_url=' . urlencode($url . "alarm_state=heavy"));
		}
	}
  */
  
	public function getFroniusData() {
		$Fronius_IP = $this->getConfiguration("IP");
		$Fronius_Port = $this->getConfiguration("Port");
		$VersionAPI = '';
		$Url = '';
		
		if (strlen($Fronius_IP) == 0) {
			log::add('fronius', 'debug','No IP defined for PV inverter interface ...');
			$this->checkAndUpdateCmd('status', 'IP onduleur manquante ...');
			return;
		}
		
		if (strlen($Fronius_Port) == 0) {
			log::add('fronius', 'debug','No port defined for PV inverter interface. port 80 used');
          	$Fronius_Port = 80;
		}
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		// READ STORED API VERSION
		try {
			$cmd = $this->getCmd(null, 'VersionAPI'); // On recherche la commande refresh de l’équipement
			if (is_object($cmd)) { //elle existe et on lance la commande
				$VersionAPI = $cmd->execCmd();
			}
		} catch (Exception $e) {
			$VersionAPI = '';
			log::add('fronius', 'error','Error reading API Version: '.$e);
		}
		
		if ($VersionAPI == '') {
			curl_setopt($ch, CURLOPT_URL, 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/GetAPIVersion.cgi');
			$data = curl_exec($ch);
			if (curl_errno($ch)) {
				curl_close ($ch);
				log::add('fronius', 'error','Error getting inverter API Version: '.curl_error($ch));
				$this->checkAndUpdateCmd('status', 'Erreur Version API');
				return;
			}
			$json = json_decode($data, true);
			$VersionAPI = $json['APIVersion'];
			$this->checkAndUpdateCmd('VersionAPI', $VersionAPI);
		}
		
		switch ($VersionAPI) {		
			case '0':
				$Url = 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/GetInverterRealtimeData.cgi?Scope=Device&DeviceId=1&DataCollection=CommonInverterData';
				break;	

			case '1';
				$Url = 'http://'.$Fronius_IP.':'.$Fronius_Port.'/solar_api/v1/GetInverterRealtimeData.cgi?Scope=Device&DeviceId=1&DataCollection=CommonInverterData';
				break;
		
			default:
				log::add('fronius', 'error','Error getting inverter API Version: '.curl_error($ch));
				$this->checkAndUpdateCmd('status', 'Version API non supportée');
				return;
		}
		
		// COLLECTING VALUES
		curl_setopt($ch, CURLOPT_URL, $Url);
		$data = curl_exec($ch);
		
		if (curl_errno($ch)) {
			curl_close ($ch);
			log::add('fronius', 'error','Error getting inverter values: '.curl_error($ch));
			$this->checkAndUpdateCmd('status', 'Erreur Données');
			return;
		}
		
		$json = json_decode($data, true);
		
		$pv_power = $json['Body']['Data']['PAC']['Value'];
		$pv_total = $json['Body']['Data']['TOTAL_ENERGY']['Value'];
		$frequency = $json['Body']['Data']['FAC']['Value'];
		$voltage_AC = $json['Body']['Data']['UAC']['Value'];
		$voltage_DC = $json['Body']['Data']['UDC']['Value'];
		$current_AC = $json['Body']['Data']['IAC']['Value'];
		$current_DC = $json['Body']['Data']['IDC']['Value'];
		$pv_day = $json['Body']['Data']['DAY_ENERGY']['Value'];
		$pv_year = $json['Body']['Data']['YEAR_ENERGY']['Value'];
		
		if ($pv_power == '') {
			$this->checkAndUpdateCmd('pv_power', 0);
			$this->checkAndUpdateCmd('pv_total', 0);
			$this->checkAndUpdateCmd('frequency', 0);
			$this->checkAndUpdateCmd('voltage_AC', 0);
			$this->checkAndUpdateCmd('voltage_DC', 0);
			$this->checkAndUpdateCmd('current_AC', 0);
			$this->checkAndUpdateCmd('current_DC', 0);
			$this->checkAndUpdateCmd('pv_day', 0);
			$this->checkAndUpdateCmd('pv_year', 0);
								
			$this->checkAndUpdateCmd('status', 'Hors Ligne ...');
			log::add('fronius', 'debug','Inverter is off-line ...');
			return;
		} else {
			curl_close ($ch);
			$this->checkAndUpdateCmd('pv_power', $pv_power);
			$this->checkAndUpdateCmd('pv_total', $pv_total);
			$this->checkAndUpdateCmd('frequency', $frequency);
			$this->checkAndUpdateCmd('voltage_AC', $voltage_AC);
			$this->checkAndUpdateCmd('voltage_DC', $voltage_DC);
			$this->checkAndUpdateCmd('current_AC', $current_AC);
			$this->checkAndUpdateCmd('current_DC', $current_DC);
			$this->checkAndUpdateCmd('pv_day', $pv_day);
			$this->checkAndUpdateCmd('pv_year', $pv_year);
			
			$this->checkAndUpdateCmd('status', 'OK');
			log::add('fronius', 'debug','All good: Data2='.$data);
			return;
		}
		
	}
	
    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class froniusCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {
				$eqlogic = $this->getEqLogic();
				switch ($this->getLogicalId()) {		
					case 'refresh':
						$info = $eqlogic->loadWebhook();
						break;					
		}
    }
    /*     * **********************Getteur Setteur*************************** */
}





class shellyCmd extends cmd {
	//add functions for "virtual equipements" internal to the plugin for gsh compatibility
	public function preSave() {
		
	}

	public function virtualCmd($_options) {
		$cmd = cmd::byId($this->getConfiguration('idVirtual'));
		if (!is_object($cmd)) {return;}
		$cmd->execCmd($_options);
	}

	public function execute($_options = null) {
		if ($this->getConfiguration('virtual') == 1) {
			$this->virtualCmd($_options);
			return;
		}
		switch ($this->getType()) {
			case 'action' :
			$eqLogic = $this->getEqLogic();
			if ($this->getLogicalId() != 'refresh') {
				$request = $this->getConfiguration('request');
				switch ($this->getSubType()) {
					case 'color':
					list($red, $green, $blue) = sscanf($_options['color'], "#%02x%02x%02x");
					$request = trim(str_replace('#red#',$red, $request));
					$request = trim(str_replace('#green#',$green, $request));
					$request = trim(str_replace('#blue#',$blue, $request));
					break;
					case 'slider':
					$request = trim(str_replace('#slider#',$_options['slider'], $request));
					break;
					case 'message':
					$request = trim(str_replace('#title#',$_options['title'], $request));
					break;
					case 'select':
					$request = trim(str_replace('#select#',$_options['select'], $request));
					break;
				}
				if ($eqLogic->getConfiguration('cloud') != 1) {
					$eqLogic->sendCommand($request);
				} else {
					$explode = explode('&',$request);
					foreach ($explode as $arg) {
						$final = explode('=',$arg);
						$array[$final[0]] = $final[1];
					}
					$eqLogic->sendCloud($this->getConfiguration('cloud_url'),$array);
				}
			}
			$eqLogic->refresh();
		}
	}
}
