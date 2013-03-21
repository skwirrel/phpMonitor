<?php

class alert_log {

private $fh;
private $logLevel;
private $invisible;

        /*
        The initialize method is called when the monitoring script starts up.
        It is passed the SimpleXML node corresponding to the alert object from the config file
	It should return nothing if everything is OK
	It should return a string containing an error message if the alert can't be initialized
  	*/
	public function initialize($config) {
		logMessage("Initializing LOG alert",4);
		$file = '';
		$this->logLevel=0;
		$this->fh = false;
		if (isset($config['file'])) $file = (string) $config['file'];
		if (isset($config['logLevel'])) $file = (string) $config['logLevel'];
		$this->invisible = isset($config['invisible']) && strtolower($config['invisible']) == 'yes';

		if ( strlen($file) ) {
			$thif->fh = @fopen($file,'a');
			if (!is_resource($this->fh)) return("Couldn't open $file for writing log data (".get_last_error().")");
		}
	}

	/*
	This function is passed an associative array corresponding to the attributes of the <details> object for this alert to each user
	If the details look OK this function should return either nothing, false or empty string
	If there is a problem this function should return a string explaining the problem
	*/
	public function checkValidDetails($details) {
		return;
	}
	
	/*
	The send method does whatever is required to actually send the alert
	It should return either true or false depending on whether the alert has been sent or not
	Alerts that want to be invisible (i.e. pretend they haven't worked when they have) can also return false even if
		they succeed so the system tries subsequent alert mechanisms
	*/
	public function send( $recipientName, $details, $shortMessage, $longMessage ) {
		if (!$this->fh) {
			logMessage("ALERT: ".$longMessage,$this->logLevel);
		} else {
			fwrite( $this->fh, $logMessage."\n");
		}

		return $this->invisible?false:true;
	}

}
