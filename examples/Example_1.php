<?php

require_once dirname(__FILE__) . '/../Phpmodbus/ModbusMaster.php';
require_once dirname(__FILE__) . '/../Phpmodbus/SiemensModbusClient.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create Modbus object
$initOffsetToTransmite = 1;
$endOffsetToTransmite = 8;

$client = new SiemensModbusClient("192.168.1.111","502", "TCP",$initOffsetToTransmite, $endOffsetToTransmite); //Max 255 bytes by request 

try {
    
    echo '<pre>';

    //Array must content name vars and type with same arrange
    // that DB use as holding register.

    $dataDefinition = [
        'int_temperature' => 'INT',
        'Data_5' => 'INT',
        'Data_6' => 'BOOL',
        'Data_7' => 'REAL'
    ];

    $recivedData = $client->getPlcData($dataDefinition);

    /* Example $recivedData

    [
        'int_temperature' => 253,
        'Data_5' => 5,
        'Data_6' => 1,
        'Data_7' => -387.23
    ]
    
    */

    print_r($recivedData);
    echo '</pre>';
    echo '<br>';
    
}
catch (Exception $e) {
    // Print error information if any
    echo $client->modBusClient;
    echo $e;
    exit;
}

?>