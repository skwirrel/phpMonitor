This package contains the php

Refere to LICENSE.md for details of the copyright and license.

What limited documentation there is at present can be found in three places:
1. The pdf file which is a copy of a presentation I prepared for FLOSS UK's annual Large Installation Systems Administration (LISA) conference (http://www.flossuk.org/Events/Spring2013)
2. The annotation in the configuration files included in the cfg directory
3. Comments in the code - particularly 
	src/monitor/random.class.php 	
	src/alert/log.class.php

N.B. Not all functionality hinted at in the example configs is implemented - this is a TODO list as well as a config file!
The following is all that is currently implemented

Alerts
	log - writes to phpMonitor's log or another file of your choosing
	smsServer - writes to SMS Server Tools using /var/spool/sms/

Monitors
	random - can also be used for heartbeat with probability="100%"
	vsphere

Notes on vSphere monitor...
If you want to know what parameters you have to play with you can use the test script: src/monitor/testEsxiHealth.php
Usage: ./testEsxiHealth.php <ESXi host> <username> <password>
This will then spit out the key-value pairs that it has harvested from the server
