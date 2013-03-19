<?php

class monitor_base {
	private $name;
	private $recipients;
	private $interval;
	private $lastRun;
	private $lastAlert;
	private $repeatLimit;
	private $tolerance;
	private $eventStart;
	
	function baseSetup($config) {
		$this->extractRecipients($config);
		$this->interval = 600;
		$this->repeatLimit = 3600;
		$this->name = (string) $config['name'];
		if (isset($GLOBALS['CONFIG']['logLevel'])) $this->interval = $GLOBALS['CONFIG']['logLevel'];
		if (isset($config['interval'])) $this->interval = $config['interval'];
		if (isset($config['repeatLimit'])) $this->repeatLimit = (string) $config['repeatLimit'];
		if (isset($config['tolerance'])) $this->tolerance = (string) $config['tolerance'];
		$this->eventStart = 0;
		$this->lastRun = 0;
	}
	
	function getName() {
		return $this->name;
	}

	function intervalExpired() {
		# Check the repeat limit
		# no point checking if we're still in the cooling off period after an alert
		if ($this->repeatLimit && $this->lastAlert+$this->repeatLimit > time()) {
			return false;
		}
		# Check we're not in the tolerance period
		if ($this->tolerance>0 && $this->eventStart && $this->eventStart+$this->tolerance > time()) {
			return false;
		}
		if ((time() - $this->lastRun) >= $this->interval) {
			$this->lastRun = time();
			return true;
		}
		return false;
	}
	
	function extractRecipients($config) {
		$this->recipients = array();
		foreach ($config->recipient as $recipient) {
			$mediums = '';
			if (isset($recipient['mediums'])) $mediums = $recipient['mediums'];
			$this->recipients[(string) $recipient['name']] = strtolower($mediums);
		}
	}

	# this is called when the monitor is run and found to be healthy
	function resetEventTimer() {
		$this->eventStart=0;
	}
		
	function makeAlerts($shortMessage, $longMessage='') {
		# Is there any tolerance to failure?
		if ($this->tolerance>0) {
			# Has this monitor failed recently?
			if (!$this->eventStart) {
				# If not then start the clock ticking on this "event"
				$this->eventStart=time();
				# but don't return any alerts
				return array();
			}
			# Has the tolerance period expired?
			if ( $this->eventStart+$this->tolerance > time() ) {
				# nope... so just sit tight for now, don't send any alerts
				return array();
			}
			# OK so tolerance period has expired
			# now we can start to think about actually sending an alert
			# but before we do - reset the event timer
			$this->eventStart=0;
		}

		$this->lastAlert=time();
		$alerts = array();
		foreach ($this->recipients as $recipient=>$mediums) {
			$alerts[] = array( $recipient, $shortMessage, $longMessage, $mediums );
		}
		return $alerts;
	}
	
}
