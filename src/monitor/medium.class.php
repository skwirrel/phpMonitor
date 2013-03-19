<?php

class monitor_medium extends monitor_base {

	private $failures;
	
	public function initialize($config) {
		$this->baseSetup($config);
		$this->failures = array();
	}

	public function run() {
		if (!$this->intervalExpired()) return;
		if (!count($this->failures)) return $this->resetEventTimer();
		
		$shortMessage="Alert medium(s) failed: ";
		$longMessage="One or more alert mediums failed as follows:";
		foreach( $this->failures as $medium=>$failures ) {
			$shortMessage .= $medium.' x '.count($failures);
			$longMessage .= "\n".$medium."\n=============================\n";
			foreach( $failures as $monitorType=>$failure ) {
				$longMessage .= "\t".$monitorType.'=>'.$failure."\n";
			}
		}
		
		return $this->makeAlerts($shortMessage,$longMessage);
	}
	
	# This is a special method which is only required for monitor_medium
	# (i.e. other monitors don't need to define this)
	# It accepts the medium that generated the error and the error message
	public function addFailure($medium, $monitorType, $failure) {
		logMessage("Medium monitor informed of failure of $medium for $monitorType",7);
		if (!isset($this->failures[$medium])) {
			$this->failures[$medium] = array();
		}
		$this->failures[$medium][$monitorType] = array($failure);
	}

}
