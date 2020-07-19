<?php
include("config.php");

function callHass($path, $payload = '') {
    global $config;
    $requestHeaders = array (
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['hassToken']
    );
    $ch = curl_init($config['hassUrl'] . $path);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $season_data = curl_exec($ch);
    if (curl_errno($ch)) {
        print "Error: " . curl_error($ch);
        exit();
    }
    curl_close($ch);
    return json_decode($season_data, true);
}

function getSensor($sensor, $data) {
    foreach ($data as &$entity) {
        if ($entity['entity_id'] == $sensor) return $entity;
    }
}

$hass = callHass('states');
$spaceState = getSensor($config['spacestate'], $hass);
$openedBy = getSensor($config['spaceopenedby'], $hass);
$apiBasics['state']['lastchange'] = strtotime($spaceState['last_changed']);
$apiBasics['state']['message'] = ($spaceState['state']=="on")?"OMG space open":"Nooz space closed";
$apiBasics['state']['open'] = ($spaceState['state']=="on")?true:false;
$apiBasics['state']['trigger_person'] = $openedBy['state'];

foreach ($config['sensors'] as &$sensor) {
    $data = getSensor($sensor, $hass);
    $apiSensor = array(
        "value" => $data['state'],
        "unit" => $data['attributes']['unit_of_measurement'],
        "name" => $data['attributes']['friendly_name']
    );
    if ($data['attributes']['unit_of_measurement'] == "°C")
        $apiBasics['sensor']['temperature'][] = $apiSensor;
    elseif ($data['attributes']['unit_of_measurement'] == "W")
        $apiBasics['sensor']['power_consumption'][] = $apiSensor;
    elseif ($data['attributes']['unit_of_measurement'] == "mW")
        $apiBasics['sensor']['power_consumption'][] = $apiSensor;
    elseif ($data['attributes']['icon'] == "mdi:thermometer")
        $apiBasics['sensor']['temperature'][] = $apiSensor;
    elseif ($data['attributes']['icon'] == "mdi:water-percent")
        $apiBasics['sensor']['humidity'][] = $apiSensor;
    else
        $apiBasics['sensor']['generic'][] = $apiSensor;
}
//echo json_encode($apiBasics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$myfile = fopen($config['jsonfile'], "w") or die("Unable to open file!");
fwrite($myfile, json_encode($apiBasics, JSON_UNESCAPED_SLASHES));
fclose($myfile);
