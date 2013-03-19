<?php

class recipient {

private $details;

function __construct($config) {
	# Extract the various contact details from the config
	foreach ($config->details as $detail) {
		if (isset($detail['medium'])) {
			$medium = strtolower($detail['medium']);
			if (!isset($this->details[$medium])) $this->details[$medium] = array();
			foreach( $detail->attributes() as $attr=>$value ) {
				$attr = strtolower((string)$attr);
				$value = (string)$value;
				if ($attr=='medium') continue;
				$this->details[$medium][$attr] = $value;
			}
		}
	}
}

function getMediums() {
	return array_keys($this->details);
}

# Remove the details associated with a medium
# This is mainly for when the details are invalid for some reason
# (either the alert medium wasn't defined or the details aren't valid)
function removeMedium($medium) {
	if (!isset($this->details[$medium])) return false;
	unset($this->details[$medium]);
	return true;
}

function getDetailsForMedium($medium) {
	if (isset($this->details[$medium])) return $this->details[$medium];
	else return false;
}

}
