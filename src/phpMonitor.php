#!/usr/bin/php
<?php
/*

Copyright Ben Jefferson 2013, ben.jefferson@brighter-connections.com

This file is part of the phpMonitor tool

phpMonitor is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

phpMonitor is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Foobar.  If not, see <http://www.gnu.org/licenses/>.

*/


if (isset($argv[1])) {
	$configFile=$argv[1];
} else {
	$configFile='/etc/monitorConfig.xml';
}


$classDir = dirname(__FILE__);

# don't timeout
ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
set_time_limit(0);

function __autoload($class) {
	global $classDir;

	$classFile = $classDir.DIRECTORY_SEPARATOR;
	
	if (strpos($class,'_')) {
		list( $type, $name ) = explode('_',$class,2);
		$classFile .= $type.DIRECTORY_SEPARATOR.$name.'.class.php';
	} else {
		$classFile .= $class.'.class.php';
	}
	
#	echo "trying to find class $classFile";
	if (file_exists($classFile)) {
		return include( $classFile );
	} else {
	}
}

function error($msg) {
	logMessage($msg,0);
	file_put_contents('php://stderr',$msg."\n");
	exit;
}

function logMessage($msg,$level=0) {
	static $lastLogMessage=0;
	if ($level<=$GLOBALS['CONFIG']['logLevel']) {
		if (time()-$lastLogMessage >= $GLOBALS['CONFIG']['logTimestampInterval']) {
			$msg = date('[Ymd h:i:s] ').$msg;
		}
		fwrite( $GLOBALS['logFileHandle'], $msg."\n");
		$lastLogMessage = time();
	}
}

function warn($msg) {
	logMessage($msg,0);
	file_put_contents('php://stderr',$msg."\n");
}

# Load in the XML configuration file
if (!file_exists($configFile)) error("Couldn't find configuration file: $configFile");
$xml = simplexml_load_file($configFile);

# Extract any global config parameters
$CONFIG=array();
if ($xml->global) {
	foreach ($xml->global->attributes() as $attr => $value) {
		$CONFIG[$attr] = trim($value);
	}
}

# Setup file for logging
$logFile = "php://output";
if (isset($CONFIG['logFile']) && $CONFIG['logFile']!=='-' && strlen($CONFIG['logFile'])) $logFile=$CONFIG['logFile'];
$logFileHandle = fopen($logFile,"a");
if (!is_resource($logFileHandle)) error("Couldn't open $logFile for writing");
if (!isset($CONFIG['logTimestampInterval']))  $CONFIG['logTimestampInterval'] = 600;
logMessage( "Starting monitor process ".getmypid()." at ".date('Y-m-d H:i:s'), 0);

# Check that the main config elements are present
if (!$xml->alertMediums->count()) error("Configuration must include alertMediums object");
if (!$xml->alertRecipients->count()) error("Configuration must include alertRecipients object");
if (!$xml->monitors->count()) error("Configuration must include monitors object");

# ============================== Initialize Alert Mediums ===================================
# Load in the alert medium config and initialize the alert mediums
$alertMediumConfigs = $xml->alertMediums->children();
$alertMediums=array();
$alertMediumsOrdered=array();

# iterate across all the alertConfiguration objects
foreach ($alertMediumConfigs as $alertMediumConfig) {
	$type = $alertMediumConfig->getName();
	$name = strtolower($alertMediumConfig['name']);
	
	$className = 'alert_'.$type;
	# The class_exists function will trigger a call to __auto_load
	if (class_exists($className)) {
		$alertMediums[$name] = new $className;
		
		# The initialize method will return an error string if there is a problem
		# otherwise it will return empty string
		$error = $alertMediums[$name]->initialize( $alertMediumConfig );
		if ($error) {
			warn("Error initializing medium $name : $error");
			unset($alertMediums[$name]);
		} else {
			$alertMediumsOrdered[]=$name;
		}
	} else {
		warn("Couldn't find alert medium class $className for medium $name");
	}
	
}
# ============================== Initialize Recipients ===================================
# Load in the monitor config and initialize the monitors
$recipientConfigs = $xml->alertRecipients->alertRecipient;
$recipients=array();

# iterate across all the recipient objects
foreach ($recipientConfigs as $recipientConfig) {
	$name = (string) $recipientConfig['name'];
	if (!$name) {
		warn("Recipient must have a name - skipping recipient with missing name");
		continue;
	}
	$recipients[$name] = new recipient($recipientConfig);	
}

# ============================== Initialize Monitors ===================================
# Load in the monitor config and initialize the monitors
$monitorConfigs = $xml->monitors->children();
$monitors=array();
$mediumMonitor = false;

# iterate across all the monitor objects
foreach ($monitorConfigs as $monitorConfig) {
	$type = $monitorConfig->getName();
	if (!isset($monitorConfig['name'])) {
		warn("Ignoring $type monitor without a name");
		continue;
	}
	$name = (string) $monitorConfig['name'];
	if (isset($monitors[$name])) {
		warn("Duplicate monitor name encountered ($name) - each monitor name must be unique. Ignoring all but the first one.");
		continue;
	}
	
	$className = 'monitor_'.$type;
	$type = strtolower( $type );
	if (class_exists($className)) {
		$monitors[$name] = new $className;
		
		$error = $monitors[$name]->initialize( $monitorConfig );
		if ($error) {
			warn("Error initializing monitor $type : $error");
			unset($monitors[$name]);
		} else {
			if ($type == 'medium') $mediumMonitor = $monitors[$name];
		}
	} else {
		warn("Couldn't find monitor class $className");
	}
	
}

# Check that all the recipient details provided are valid and sufficient
foreach ($recipients as $name=>$recipient) {
	foreach( $recipient->getMediums() as $medium ) {

		# Check that we actually have a definition for this medium
		if (!isset($alertMediums[$medium])) {
			warn("User $name has details for \"$medium\" but no such alert medium has been defined");
			$recipient->removeMedium($medium);
			continue;
		}

		# mediums don't have to supply a validity check function
		if (!method_exists($alertMediums[$medium],'checkValidDetails')) continue;

		# but if it does then check the details supplied
		$validityError = $alertMediums[$medium]->checkValidDetails($recipient->getDetailsForMedium($medium));
		if ($validityError) {
			warn("Recipient \"$name\" has invalid details the for \"$medium\" alert medium: $validityError");
			$recipient->removeMedium($medium);
			continue;
		}
	}
}

# ============================== MAIN LOOP ===================================

while (1) {
	$alerts = array();
	
	# ============================== Run all monitors ===================================
	foreach ($monitors as $monitorType=>$monitor) {
		# The run method should return an array of alert details
		# Each set of alert details is an array: [ recipientName, short message, long message, mediums ]
		# If no mediums are specified then all mediums for which the user has details will be tried
		# In the order they are specified until one succeeds
		# Mediums are specified in a comma separated list
		logMessage("About to run monitor $monitorType",9);
		$newAlerts = $monitor->run();
		logMessage("Monitor $monitorType returned ".count($newAlerts)." alerts" ,9);
		# Add the name of the monitor to the front of the array that was returned
		if (is_array($newAlerts) && count($newAlerts)) {
			logMessage("Monitor $monitorType returned failure:".$newAlerts[0][1],2);
			foreach ($newAlerts as $alertData) {
				logMessage("Monitor $monitorType triggered alert to be sent to {$alertData[0]} via {$alertData[3]}",7);
				array_unshift($alertData, $monitorType);
				$alerts[] = $alertData;
			}
		}
	}

	# ============================== Process any alerts ===================================
	foreach ($alerts as $idx=>$alertData) {
		list( $monitorType, $recipientName, $shortMessage, $longMessage, $mediums ) = $alertData;
		# Check we know have some (any) details for the recipient
		if (!isset($recipients[$recipientName])) {
			warn("Couldn't find recipient $recipientName to send alert from $monitorType with the following message: \"$message\"");
			continue;
		}

		if ($mediums=='') {
			# if no mediums are specified then use all of the mediums
			$mediums = $alertMediumsOrdered;
		} else {
			$mediums = explode(',',$mediums);
		}

		# Iterate across the mediums until one of them is successful
		foreach ($mediums as $mediumName) {
			$details = $recipients[$recipientName]->getDetailsForMedium($mediumName);
			# Skip this medium if we don't have any corresponding details for this user
			if (!is_array($details)) {
				logMessage("Skipping medium $mediumName for user $recipientName because there are no details for this medium for this user",8);
				continue;
			}
									
			# The medium should return true for sent and visible, false for sent but invisible
			# or a string containing an error message if sending the alert failed
			# It is up to the alert whether it flags up a failed invisible alert
			logMessage("About to send $mediumName alert to $recipientName regarding $monitorType failure",3);
			$result = $alertMediums[$mediumName]->send($recipientName, $details, $shortMessage, $longMessage);
			
			if (strlen($result)>1) {
				logMessage("Sending alert $mediumName alert to $recipientName returned error: $result",2);
				# Register the failure with the medium monitor if we have one
				if (is_object($mediumMonitor)) $mediumMonitor->addFailure( $mediumName, $monitorType, $result );
			} else {
				logMessage("Sending alert $mediumName alert to $recipientName succeeded",3);
				if ($result===false) logMessage("Alert was invisible so trying more alerts",3);
				if ($result === true) break;
			}
		}
	}

	sleep(1);
}
