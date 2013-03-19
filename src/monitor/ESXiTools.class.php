<?php

 // This code is based on Example PHP code to get Performance Statistics from a VMware Vsphere Server (v5.x)
 // written by: Richard Garsthagen - the.anykey@gmail.com - www.run-virtual.com
 // 
 // This code uses the nusoap library, not the buildin PHP soap library.
 //
 // Some small alterations have been made by Ben Jefferson - skwirrel@gmail.com


require_once("lib/nusoap.php");

class ESXiTools {

private static function find($connectionDetails, $objectType, $details)
{
    $detailObjects = '';
    foreach ($details as $detail ) {
       $detailObjects .= "<pathSet>" . $detail . "</pathSet>";
    }

    $soapmsg ="<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
<SOAP-ENV:Envelope
    SOAP-ENV:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\"
    xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\"
    xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"
    xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:SOAP-ENC=\"http://schemas.xmlsoap.org/soap/encoding/\">
<SOAP-ENV:Body>
    
<RetrieveProperties xmlns=\"urn:vim25\">
    <_this type=\"PropertyCollector\">{$connectionDetails['propertyCollector']}</_this>
    <specSet>
        <propSet>
            <type>$objectType</type>
            $detailObjects
        </propSet>
        <objectSet>
            <obj type=\"Folder\">{$connectionDetails['rootFolder']}</obj>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>folderTraversalSpec</name>
                <type>Folder</type>
                <path>childEntity</path>
                <selectSet>
                    <name>folderTraversalSpec</name>
                </selectSet>
                <selectSet>
                    <name>datacenterHostTraversalSpec</name>
                </selectSet>
                <selectSet>
                    <name>datacenterVmTraversalSpec</name>
                </selectSet>
                <selectSet>
                    <name>computeResourceRpTraversalSpec</name>
                </selectSet>
                <selectSet>
                    <name>computeResourceHostTraversalSpec</name>
                </selectSet>
                <selectSet>
                    <name>hostVmTraversalSpec</name>
                </selectSet>
                <selectSet>
                    <name>resourcePoolVmTraversalSpec</name>
                </selectSet>
            </selectSet>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>datacenterVmTraversalSpec</name>
                <type>Datacenter</type>
                <path>vmFolder</path>
                <selectSet>
                    <name>folderTraversalSpec</name>
                </selectSet>
            </selectSet>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>datacenterHostTraversalSpec</name>
                <type>Datacenter</type>
                <path>hostFolder</path>
                <selectSet>
                    <name>folderTraversalSpec</name>
                </selectSet>
            </selectSet>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>computeResourceHostTraversalSpec</name>
                <type>ComputeResource</type>
                <path>host</path>
            </selectSet>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>computeResourceRpTraversalSpec</name>
                <type>ComputeResource</type>
                <path>resourcePool</path>
                <selectSet>
                    <name>resourcePoolTraversalSpec</name>
                </selectSet>
            </selectSet>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>resourcePoolTraversalSpec</name>
                <type>ResourcePool</type>
                <path>resourcePool</path>
                <selectSet>
                    <name>resourcePoolTraversalSpec</name>
                </selectSet>
                <selectSet>
                    <name>resourcePoolVmTraversalSpec</name>
                </selectSet>
            </selectSet>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>hostVmTraversalSpec</name>
                <type>HostSystem</type>
                <path>vm</path>
                <selectSet>
                    <name>folderTraversalSpec</name>
                </selectSet>
            </selectSet>

            <selectSet xsi:type=\"TraversalSpec\">
                <name>resourcePoolVmTraversalSpec</name>
                <type>ResourcePool</type>
                <path>vm</path>
            </selectSet>
        </objectSet>
    </specSet>
</RetrieveProperties>
</SOAP-ENV:Body></SOAP-ENV:Envelope>
";
    
    return $soapmsg;

}

private static function extractStatus( &$fieldName ) {
    if (strpos($fieldName,': ')!==false) {
        list( $fieldName, $status ) = explode( ': ',$fieldName,2);
        return $status;
    }
    if (strpos($fieldName,' --- ')!==false) {
        list( $fieldName, $status ) = explode( ' --- ',$fieldName,2);
        return $status;
    }
    if (strpos($fieldName,' - ')!==false) {
        list( $fieldName, $status ) = explode( ' - ',$fieldName,2);
        return $status;
    }
    return '';
}

private static function extractVersionData( &$fieldName ) {
    $versionBits = '';
    while (preg_match('!\s+((?:\\S+\\.){2,}\S+|[\\!-\\?]{3,})(\\s+|\\Z)!',$fieldName,$matches)) {
        $version = trim($matches[0]);
        $versionBits .= ' '.$version;
        $fieldName = str_replace($version,' ',$fieldName);
    }
    $fieldName = trim(preg_replace('!\\s\\s+!',' ',$fieldName));

    return trim($versionBits);
}

private static function extractDataFrom( &$data, $object, $path ) {
    foreach ($object as $fieldName=>$fieldValue) {
        if (!is_array($fieldValue)) {
            $data[$path.'/'.$fieldName]=$fieldValue;
        } else {
            if ((0+$fieldName)==$fieldName && isset($fieldValue['name'])) $fieldName=$fieldValue['name'];
            $status = trim( ESXiTools::extractStatus( $fieldName ) );
            $versionData = ESXiTools::extractVersionData( $fieldName );
            if ($versionData !== '') $fieldValue['_versionData'] = $versionData;
            if ($status !== '') $fieldValue['_status'] = $status;
            ESXiTools::extractDataFrom( $data, $fieldValue, $path.'/'.$fieldName );
        }
    }
}

public static function getHealth( $host, $username, $password, $debug=false ) {

	$testData = array( array(
'/name' => 'esxServer.brighter-connections.com',
'/obj' => 'ha-host',
'/connectionState' => 'connected',
'/powerState' => 'poweredOn',
'/inMaintenanceMode' => 'false',
'/bootTime' => '2012-11-28T15:30:50.027903Z',
'/Power Supply 1/name' => 'Power Supply 1: Running/Full Power-Enabled',
'/Power Supply 1/healthState/label' => 'Green',
'/Power Supply 1/healthState/summary' => 'Sensor is operating under normal conditions',
'/Power Supply 1/healthState/key' => 'green',
'/Power Supply 1/healthState/_status' => '',
'/Power Supply 1/currentReading' => '0',
'/Power Supply 1/unitModifier' => '0',
'/Power Supply 1/baseUnits' => '',
'/Power Supply 1/sensorType' => 'power',
'/Power Supply 1/_status' => 'Running/Full Power-Enabled',
'/Power Supply 2/name' => 'Power Supply 2: Running/Full Power-Enabled',
'/Power Supply 2/healthState/label' => 'Green',
'/Power Supply 2/healthState/summary' => 'Sensor is operating under normal conditions',
'/Power Supply 2/healthState/key' => 'green',
'/Power Supply 2/healthState/_status' => '',
'/Power Supply 2/currentReading' => '0',
'/Power Supply 2/unitModifier' => '0',
'/Power Supply 2/baseUnits' => '',
'/Power Supply 2/sensorType' => 'power',
'/Power Supply 2/_status' => 'Running/Full Power-Enabled',
'/Power Supply 3 Power Supplies/name' => 'Power Supply 3 Power Supplies - Fully redundant',
'/Power Supply 3 Power Supplies/healthState/label' => 'Green',
'/Power Supply 3 Power Supplies/healthState/summary' => 'Sensor is operating under normal conditions',
'/Power Supply 3 Power Supplies/healthState/key' => 'green',
'/Power Supply 3 Power Supplies/healthState/_status' => '',
'/Power Supply 3 Power Supplies/currentReading' => '0',
'/Power Supply 3 Power Supplies/unitModifier' => '0',
'/Power Supply 3 Power Supplies/baseUnits' => '',
'/Power Supply 3 Power Supplies/sensorType' => 'power',
'/Power Supply 3 Power Supplies/_status' => 'Fully redundant'
	));
	
	# return $testData;
	
    $objectType = "HostSystem";
    //$objecttype = "VirtualMachine";

    $myConnection = new nusoap_client("https://".$host."/sdk"); 

    $namespace = "urn:vim25";
    $soapmsg = array( 'data' => new soapval('_this','ServiceInstance','ServiceInstance') );
    $connectionDetails = $myConnection->call("RetrieveServiceContent",$soapmsg,$namespace);

    if (!$connectionDetails) return "Unable to connect to ESXi Server $host";

    if ($debug) print_r($connectionDetails);

    if ($debug) print "Connected to version: " . $connectionDetails['about']['fullName'] . "\n";
    unset($soapmsg);

    $soapmsg = array(
        'this' => new soapval('_this','SessionManager',$connectionDetails['sessionManager']),
        'userName' => $username,
        'password' => $password
    );

    $result = $myConnection->call("Login",$soapmsg,$namespace);
    if (isset($result['faultstring'])) {
        return "Problem authenticating to ESXi server $host: ".$result['faultstring'];
    }

    unset($soapmsg);

    if ($debug) print_r($result);

    // Do a Retrieve Properties with extensive traversal to find anything.
    $soapmsg = ESXiTools::find($connectionDetails,$objectType,array("name","runtime"));  
    $result = $myConnection->send($soapmsg,30);

    if ($debug) print_r($result);
    unset($soapmsg);

    // if a single object is returned place this back into the object as array at index 0 
    // so we have a uniform way to process the data with one or multiple objects
    $objects=$result['returnval'];
    if (!isset($objects[0])) { 
        $objects = array($objects); 
    }

    $healthData = array();
    // iterate across the Hosts
    foreach ($objects as $object) {

        // check how many fields are returned, if only one, place in array 
        // so it can be uniformly be treated
        if (!is_array($object['propSet'][0])) {
            $object['propSet'][0] = $object['propSet'];
        }

        $data = array('/name'=>$object['propSet'][0]['val']);
        ESXiTools::extractDataFrom( $data, $object, '' );

        # prune useless stuff off the front of the parameter names...
        $healthState = array();
        foreach( $data as $key=>$value ) {
            if (preg_match('!^/propSet/(runtime/)?name!',$key)) continue;
            $key = preg_replace('!^/propSet/runtime/val/healthSystemRuntime/(systemHealthInfo/numericSensorInfo|hardwareStatusInfo)!','',$key);
            $key = preg_replace('!^/propSet/runtime/val!','',$key);
            $healthState[$key] = $value;
        }
        $healthData[] = $healthState;
    }

    return $healthData;
}

}
