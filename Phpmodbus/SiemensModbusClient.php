<?php

/**
 * SiemensPlcModbusDecoder Copyright (c) 2023, 2023 Jonathan Lerin Lahuerta
 *
 *
 * @author Jonathan Lerín Lahuerta
 * @copyright Copyright (c) 2023, 2023 Jonathan Lerin Lahuerta
 * @license Opensource
 *
 */

/**
 * SiemensPlcModbusDecoder
 *
 * This class complement "Phpmodbus Copyright (c) 2004, 2012 Jan Krakora"
 * to decode succesfully data package send by plc siemens S7-1200 programed
 * with TIA Portal V17 using modbus protocol.
 *
 *
 */

 require_once dirname(__FILE__) . '/../Phpmodbus/ModbusMaster.php';

class SiemensModbusClient {


  const siemensValidDataType = [ // used to validate type
    'BOOL',
    'BYTE',
    'WORD',
    'DWORD',
    'INT',
    'DINT',
    'REAL',
    'S5TIME',
    'TIME',
    'DATE',
    'TIME _OF_DAY',
    'CHAR'
    
  ];

  const siemensDataTypeLenght = [ //size in bits
    'BOOL' => 1,
    'BYTE' => 8,
    'WORD' => 16,
    'DWORD' => 32,
    'INT' => 16,
    'DINT' => 32,
    'REAL' => 32,
    'S5TIME' => 16,
    'TIME' => 32,
    'DATE' => 32,
    'TIME _OF_DAY' => 16,
    'CHAR' => 8
    
  ];

  const siemensDataTypeByteLenght = [ //size in Byte
    'BOOL' => 2,
    'BYTE' => 2,
    'WORD' => 2,
    'DWORD' => 4,
    'INT' => 2,
    'DINT' => 4,
    'REAL' => 4,
    'S5TIME' => 2,
    'TIME' => 4,
    'DATE' => 4,
    'TIME _OF_DAY' => 2,
    'CHAR' => 1
    
  ];

  private $status;
  private $packages;
  private $ip;
  private $port;
  private $protocol;
  public $modBusClient;
  private $plcAddres;
  private $startRegister;
  private $endRegister;

  public function __construct(string $ip, string $port,string $protocol,int $plcAddres,int $startRegister)
  {
    $this->status = '';
    $this->packages  = [];

    $this->ip = $ip;
    $this->port = $port;
    $this->protocol = $protocol;
    $this->modBusClient = new ModbusMaster($ip,$protocol,$port);
    $this->plcAddres = $plcAddres;
    $this->startRegister = $startRegister/2; // I divided by two because siemens addres work byte by byte and ModbusMaster() work 2 byte by 2 byte
    $this->endRegister = 0;
    

  }


  public function getPlcData(array $dataScheme)
  {
    $dbVarNames = array_keys($dataScheme);
    $types = [];

    $totalBytes = 0;
    for($i=0; $i < sizeof($dbVarNames); $i++)
    {
      $types[$i] = $dataScheme[$dbVarNames[$i]];
      $totalBytes += self::siemensDataTypeByteLenght[$types[$i]];

    }

    $this->endRegister= $totalBytes/2;

    $recivedData = $this->modBusClient->readMultipleRegisters($this->plcAddres, $this->startRegister, $this->endRegister);

    return $this->recData2array($types, $dbVarNames, $recivedData);
  }

  /**
   * Convert modbus data package to assoc array
   */
  private function recData2array(array $types, array $dbVarNames, array $bytesPackage)
  {
    if(!$this->validateTypes($types))
    {
      return [];
    }

    $vars = [];

    //TypePacking
    $bytesProcesed = 0;
    for($i=0; $i < sizeof($types); $i++)
    {

      for($j=0; $j < self::siemensDataTypeByteLenght[$types[$i]];$j++)
      {
        $vars[$dbVarNames[$i]][$j] =  $bytesPackage[$bytesProcesed];
        $bytesProcesed++;
      }
     
      
    }

    //Decode
    $decodeVars = [];
    for($i=0; $i < sizeof($vars); $i++)
    {
      if(strcasecmp($types[$i],'INT')==0)
      {
        $decodeVars[$dbVarNames[$i]] = $this->bytesArray2Int($vars[$dbVarNames[$i]]);
        continue;
      }

      if(strcasecmp($types[$i],'REAL')==0)
      {
        $decodeVars[$dbVarNames[$i]] = $this->bytesArray2Float($vars[$dbVarNames[$i]]);
        continue;
      }

      if(strcasecmp($types[$i],'BOOL')==0)
      {
        $decodeVars[$dbVarNames[$i]] = $this->bytesArray2Bool($vars[$dbVarNames[$i]]);
        continue;
      }
      
    }

    return $decodeVars;
  }

  private function validateTypes(array $types)
  {
    for($i=0; $i < sizeof($types); $i++)
    {
      $coincidencia = false;
      for($j=0; $j < sizeof(self::siemensValidDataType); $j++)
      {
        if(strcasecmp($types[$i],self::siemensValidDataType[$j])==0)
        {
          $coincidencia = true;
        }
      }

      if(!$coincidencia)
      {
        $this->status = 'Data type error  <b>'.$types[$i]. '</b>  is not compatible with plc siemens.';
        return false;
      }
     
    }

    return true;
  }

  public function getStatus()
  {
    return $this->status;
  }


  //DECODE FUCNTIONS AND TOOLS

  public function bytesArray2Bool(array $bytesArray):int
  {
    if(sizeof($bytesArray) != self::siemensDataTypeByteLenght['BOOL'])
    {
      return 0;
    }

    return $bytesArray[0];
   
  }


  public function bytesArray2Int(array $bytesArray):int
  {
    if(sizeof($bytesArray) != self::siemensDataTypeByteLenght['INT'])
    {
      return 0;
    }

    $hex2digitsArray = $this->decimalsArray2Hex2digitsArray($bytesArray);
    $this->packages = $hex2digitsArray;
    $intSiemensBin = $this->getBinData($hex2digitsArray);

    return $this->intSiemensBin2Int($intSiemensBin);
  }

  public function bytesArray2Float(array $bytesArray):float
  {
    if(sizeof($bytesArray) != self::siemensDataTypeByteLenght['REAL'])
    {
      return 0.0;
    }

    $hex2digitsArray = $this->decimalsArray2Hex2digitsArray($bytesArray);
    $this->packages = $hex2digitsArray;
    $realSiemensBien = $this->getBinData($hex2digitsArray);

    return $this->realSiemensBin2Float($realSiemensBien);
  }

  private function dec2hex2digits(int $decimal)
  {
    $hexadecimal = ''.dechex($decimal);
    if(strlen($hexadecimal)<2)
    {
      $hexadecimal = '0'.$hexadecimal; // if 1 ==> 01    
    }

    return $hexadecimal;
  }

  private function decimalsArray2Hex2digitsArray(array $decimalsArray)
  {
    for($i = 0; $i < sizeof($decimalsArray); $i++)
    {
      $decimalsArray[$i] = $this->dec2hex2digits($decimalsArray[$i]);
    }
    return  $decimalsArray;
  }

  private function getBinData(array $hex2digitsArray)
  {
    $binBuffer = '';

    for($i = 0; $i < sizeof($hex2digitsArray); $i++)
    {
      $binBuffer .= "\x".$hex2digitsArray[$i];
    }

    $temp = str_replace('\x', '', $binBuffer);
    $bin = pack('H*', $temp);

    return $bin;
  }

  private function realSiemensBin2Float($realSiemensBin):float
  {
    $a = unpack('f', strrev($realSiemensBin));
    $float = $a[1];

    return $float;
  }

  private function intSiemensBin2Int($intSiemensBin):int
  {
    $a = unpack('s', strrev($intSiemensBin));
    $int = $a[1];

    return $int;
  }

}