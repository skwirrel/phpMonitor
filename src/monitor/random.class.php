<?php

class monitor_random extends monitor_base {

	private $probability;
	private $message;
	
	function initialize($config) {
		$this->baseSetup($config);
		$this->probability = (string) $config['probability'];
		if (strpos($this->probability,'%')) $this->probability = $this->probability *= 0.01;
		if (isset($config['message'])) $this->message = (string) $config['message'];
		else $this->message = $this->getName();
	}

	function run() {
		if (!$this->intervalExpired()) return;
		if ((rand(0,1000)/1000)<$this->probability) return $this->makeAlerts($this->message,$this->message);
		return $this->resetEventTimer();
	}
}
