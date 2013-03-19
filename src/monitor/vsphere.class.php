<?php

include('ESXiTools.class.php');

class monitor_vsphere extends monitor_base {

	private $config;
	
	function initialize($config) {
		$this->baseSetup($config);
		$this->config = $config;

		foreach ($this->config->includeHost as $host) {
			if (!isset($host['name']) || (string)$host['name']=='') {
				logMessage("Encountered host with no name in vsphere monitor configuration (".$this->config['name'].")",1);
			}
		}

		foreach ($this->config->test as $test) {
			if (!isset($test['name']) || !isset($test['parameter']) || !isset($test['value']) || !isset($test['type'])) {
				logMessage("Encountered a test missing one of either name, parameter, value or type attributes in vsphere monitor definition for ".$this->config['name'],1);
			} else if (!strlen($test['parameter']) || !strlen($test['type'])) {
				logMessage("Encountered a test with an empty parameter or type attribute - both of these are mandatory. Check vsphere monitor ".$this->config['name'],1);
			}
		}
		
		if (!count($this->config->test)) {
			return "Vsphere monitor ".$this->config['name']." defined without any tests - ignoring this monitor";
		}
	}

	function run() {
		if (!$this->intervalExpired()) return;
		
		$username = isset($this->config['username'])?(string) $this->config['username']:'';
		$password = isset($this->config['password'])?(string) $this->config['password']:'';

		# iterate across all the sub-monitors
		$errors = array();
		$errorHosts = array();
		$errorNames = array();
		
		# work out which hosts we're monitoring
		foreach ($this->config->includeHost as $host) {
			if (!isset($host['name']) || (string)$host['name']=='') continue;
			$hostUsername = isset($host['username'])?(string) $host['username']:$username;
			$hostPassword = isset($host['password'])?(string) $host['password']:$password;
			$hostname = (string)$host['name'];
			# getEsxiHealth returns an error string if it has a problem
			# Otherwise it returns an array of parameters=>value pairs for each host
			# although in this case there should only be 1 host
			logMessage("About to query Esxi server $hostname for health state",6); 
			$healthState = ESXiTools::getHealth( $hostname, $username, $password );
			
			if (!is_array($healthState) || !count($healthState) || !is_array($healthState[0])) {
				logMessage("Error getting server health for server $hostname : $healthState",3); 
				$errors[] = "Couldn't get health of server $hostname : $healthState";
				continue;
			}
			$hostData = $healthState[0];
			if (!isset($hostData['/name'])) {
				logMessage("Error getting Esxi server health for server $hostname : no name returned in health data",3); 
				$errors[] = "Health data is invalid for server $hostname (no name parameter)";
				continue;
			}
			logMessage("Got health state for server $hostname",6); 
			
			# now iterate over the parameters
			foreach ($this->config->test as $test) {
				# check validity of the test
				# No need to log errors here - we already did that during initialization above
				if (!isset($test['name']) || !isset($test['parameter']) || !isset($test['value']) || !isset($test['type'])) continue;
				if (!strlen($test['parameter']) || !strlen($test['type'])) continue;
				
				$name = (string)$test['name'];
				$parameter = (string)$test['parameter'];
				$value = (string)$test['value'];
				$type = trim(strtolower($test['type']));
				
				$parameterValue='';
				
				if (isset($hostData[$parameter])) $parameterValue = $hostData[$parameter];

				$negate = '';
				if (substr($type,0,1)==='!') {
					$negate='!';
					$type = substr($type,1);
				}
				
				switch ($type) {
					case 'eq':
						$result = $parameterValue == $value;
						break;
					case 'gt':
						$result = $parameterValue > $value;
						break;
					case 'lt':
						$result = $parameterValue < $value;
						break;
					case 'ge':
						$result = $parameterValue >= $value;
						break;
					case 'le':
						$result = $parameterValue <= $value;
						break;
					case 're':
						$result = preg_match("/$value/",$parameterValue);
						break;
				}
				if ($negate==='!') $result = !$result;
				
				if (!$result) {
					$error = "vSphere test failed $name for server $hostname: \"$parameter\" $negate$type \"$value\" cf \"$parameterValue\"";
					logMessage( $error,3 );
					$errors[] = $error;
					$errorHosts[] = $hostname;
					$errorNames[] = $name;
				}
			}
		}
		
		# Now collate all the errors and decide how to respond
		if (!count($errors)) return $this->resetEventTimer();
		
		$shortMessage = 'vSphere monitor '.$this->config['name'].' encountered errors affecting '.implode(',',$errorHosts).' ('.implode(',',$errorNames).')';
		$longMessage = 'vSphere monitor '.$this->config['name']." encountered errors\n";
		foreach( $errors as $error) {
			$longMessage.="\t".$error;
		}
		
		return $this->makeAlerts($shortMessage,$longMessage);
	}
}
