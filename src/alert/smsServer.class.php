<?php

class alert_smsServer {

private $outgoingDir;
private $sentDir;
private $failedDir;
private $tmpDir;
private $invisible;
private $flash;

public function initialize($config) {
	logMessage("Initializing SMS Server");
	# check the requisite bits are present
	if ( !isset($config['outgoingDir']) || !isset($config['sentDir']) || !isset($config['failedDir']) ) {
		return( "Couldn't find all required configuration attributes: outgoingDir, sentDir and failedDir" );
	}
	$this->outgoingDir = (string) $config['outgoingDir'];
	$this->sentDir = (string) $config['sentDir'];
	$this->failedDir = (string) $config['failedDir'];
	$this->tmpDir = isset($config['tempDir']) ? (string) $config['tempDir']:'/tmp/';
	$this->timeout = isset($config['timeout']) ? (int) $config['timeout']:0;
	if (!$this->timeout) $this->timeout = 30;
	print_r($config['invisible']);
	$this->invisible = isset($config['invisible']) && strtolower($config['invisible']) == 'yes';
	$this->flash = isset($config['flash']) && strtolower($config['invisible']) == 'yes';
	
	if (!file_exists($this->outgoingDir)) return("Couldn't find outgoing SMS directory: $this->outgoingDir");
	if (!file_exists($this->sentDir)) return("Couldn't find sent SMS directory: $this->sentDir");
	if (!file_exists($this->failedDir)) return("Couldn't find failed SMS directory: $this->failedDir");
}

# If the details look OK this function should return either false or empty string
# If there is a problem this function should return a string explaining the problem
public function checkValidDetails($details) {
	# all we need is a phone number 
	if (!isset($details['number'])) return("You must provide a mobile phone number in the \"number\" attribute");
	if (preg_match('/\D/',$details['number'])) return("The mobile number should be just digits - do not include a + at the start");
	if (strlen($details['number'])<12) return("The mobile number is not long enough - it should start with the country code");
	return false;
}

public function send( $recipientName, $details, $shortMessage, $longMessage ) {
	$smsFilename = getmypid().'_'.microtime(1).'.sms';
	$tmpSmsFile = $this->tmpDir.DIRECTORY_SEPARATOR.$smsFilename;
	$outgoingSmsFile = $this->outgoingDir.DIRECTORY_SEPARATOR.$smsFilename;
	$failedSmsFile = $this->failedDir.DIRECTORY_SEPARATOR.$smsFilename;
	$sentSmsFile = $this->sentDir.DIRECTORY_SEPARATOR.$smsFilename;
	
	$number = $details['number'];
	
	# Create the SMS file in a temporary location and then move it into place
	# this protects against it being read when we're only half way through writing it
	logMessage("Creating sms file $tmpSmsFile",7);
	$smsFile = @fopen($tmpSmsFile,'w');
	if (!is_resource($smsFile)) {
		return "Problem creating temporary SMS file: $tmpSmsFile";
	}
	
	fwrite( $smsFile, "To: $number\n" );
	fwrite( $smsFile, "Alphabet: ISO\n" );
	if ($this->flash) fwrite( $smsFile, "flash: yes\n" );
	fwrite( $smsFile, "\n" );
	fwrite( $smsFile, $shortMessage );

	if (!@rename($tmpSmsFile,$outgoingSmsFile)) {
		return "Problem moving SMS file from $tmpSmsFile to $outgoingSmsFile";
	}
	logMessage("Moved sms file to $outgoingSmsFile",8);
	
	# now wait up to <timeout>s for the file to appear in either the failed or sent directories
	$c=0;
	while($c++ < $this->timeout) {
		logMessage("Looking for either failed or sent SMS",9);
		if (file_exists($sentSmsFile)) {
			unlink($sentSmsFile);
			logMessage("Sending SMS succeeded",9);
			return $this->invisible?false:true;
		}
		if (file_exists($failedSmsFile)) {
			unlink($failedSmsFile);
			logMessage("Sending SMS failed",9);
			return "Delivery of SMS to $number failed";
		}
		sleep(1);
	}
	# Try and delete the SMS from the outgoing directory just in case
	# If SMSServer is switched off then they'll back up in there and then suddenly
	# flood out when its switched back on!!
	@unlink($outgoingSmsFile);
	logMessage("Sending SMS timed out",9);
	return "Timed out waiting for sms $smsFilename to either succeed or fail";
}

}
