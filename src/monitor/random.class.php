<?php

# This is a very simple monitor which is triggered at random based on a probability.
# It can be used as the basis for other monitors

class monitor_random extends monitor_base {

	/*
	Class variables - these are specific to the random monitor - neither is required
	You will probably want other things stored here relating to your monitor
	*/
	private $probability;
	private $message;
	
	
	/*
	The initialize method is called when the monitoring script starts up.
	It is passed the SimpleXML node corresponding to the monitor object from the config file
	It should return either nothing or an error string i.e. if it returns a string, then it is assumed that initialization of the monitor
	was not possible, an error will be logged and the monitor will be ignored.
	Logging can be written to the main log file with call so the logMessage("message",$logLevel) function
	If you want to make use of the interval, tolerance and repeatLimit functionality you should call $this->baseSetup as shown below
	*/

	function initialize($config) {
		$this->baseSetup($config);
		$this->probability = (string) $config['probability'];
		if (strpos($this->probability,'%')) $this->probability = $this->probability *= 0.01;
		if (isset($config['message'])) $this->message = (string) $config['message'];
		else $this->message = $this->getName();
	}

	/*
	The run function actually performs the check
	If you want to use the interval, tolerance and repeatLimit functionality then call $this->intervalExpired as shown below.
	If everything is OK it should call $this->resetEventTimer() and return nothing
		$this->resetEventTimer() returns nothing so you can do both at once with...
		return $this->resetEventTimer();
	If there is a problem it should return a specially structured array. This array is generate for you by calling
		$this->makeAlerts($shortMessage,$longMessage)
	 
	*/	
	function run() {
		if (!$this->intervalExpired()) return;
		if ((rand(0,1000)/1000)<$this->probability) return $this->makeAlerts($this->message,$this->message);
		return $this->resetEventTimer();
	}
}
