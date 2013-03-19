<?php

class alert_log {

private $fh;
private $logLevel;

public function initialize($config) {
	logMessage("Initializing LOG alert",4);
	$file = '';
	$this->logLevel=0;
	$this->fh = false;
	if (isset($config['file'])) $file = (string) $config['file'];
	if (isset($config['logLevel'])) $file = (string) $config['logLevel'];

	if ( strlen($file) ) {
		$thif->fh = @fopen($file,'a');
		if (!is_resource($this->fh)) return("Couldn't open $file for writing log data (".get_last_error().")");
	}
}

# If the details look OK this function should return either false or empty string
# If there is a problem this function should return a string explaining the problem
public function checkValidDetails($details) {
	return false;
}

public function send( $recipientName, $details, $shortMessage, $longMessage ) {
	if (!$fh) {
		logMessage($longMessage,$this->logLevel);
	} else {
		fwrite( $fh, $logMessage."\n");
	}

	return $this->invisible?false:true;
}

}
