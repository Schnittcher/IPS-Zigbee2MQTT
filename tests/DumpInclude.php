<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

class DumpInclude extends TestCase
{
    private $deviceModuleID = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    private $groupModuleID = '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}';
    private $bridgeModuleID = '{00160D82-9E2F-D1BD-6D0B-952F945332C5}';
    private static $MQTTDataArray = [
        'DataID'           => '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}',
        'PacketType'       => 3,
        'QualityOfService' => 0,
        'Retain'           => false,
        'Topic'            => '',
        'Payload'          => ''
    ];

    public function setUp(): void
    {
        //Reset
        IPS\Kernel::reset();

        //Register our library we need for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/IOStubs/library.json');

        //Load required actions
        IPS\ActionPool::loadActions(__DIR__ . '/../actions');

        parent::setUp();
        IPS_CreateVariableProfile('~Alert.Reversed', VARIABLETYPE_BOOLEAN);
        IPS_CreateVariableProfile('~Lock', VARIABLETYPE_BOOLEAN);
        IPS_CreateVariableProfile('~Switch', VARIABLETYPE_BOOLEAN);
        IPS_CreateVariableProfile('~Temperature', VARIABLETYPE_FLOAT);
        IPS_CreateVariableProfile('~Temperature.Room', VARIABLETYPE_FLOAT);
        IPS_CreateVariableProfile('~Valve', VARIABLETYPE_INTEGER);
        IPS_CreateVariableProfile('~UnixTimestamp', VARIABLETYPE_INTEGER);
        IPS_CreateVariableProfile('~Shutter.Reversed', VARIABLETYPE_INTEGER);
        IPS_CreateVariableProfile('~Battery.100', VARIABLETYPE_INTEGER);
    }
    public function testCreateBridge()
    {
        $previousCount = count(IPS_GetInstanceListByModuleID($this->bridgeModuleID));
        $iid = IPS_CreateInstance($this->bridgeModuleID);
        $this->assertEquals($previousCount + 1, count(IPS_GetInstanceListByModuleID($this->bridgeModuleID)));
    }

    public function testCreateDevice()
    {
        $previousCount = count(IPS_GetInstanceListByModuleID($this->deviceModuleID));
        IPS_CreateInstance($this->deviceModuleID);
        $this->assertEquals($previousCount + 1, count(IPS_GetInstanceListByModuleID($this->deviceModuleID)));
    }

    public function createTestInstance(string $File)
    {
        $iid = IPS_CreateInstance($this->deviceModuleID);
        $intf = IPS\InstanceManager::getInstanceInterface($iid);
        $this->assertTrue($intf instanceof Zigbee2MQTTDevice); // Instanz angelegt?
        // Lade Z2M_Debug.json
        $Debug = json_decode(file_get_contents(__DIR__ . '/TestDumps/' . $File), true);
        // Config aus Debug JSON
        IPS_SetConfiguration($iid, json_encode($Debug['Config']));
        IPS_ApplyChanges($iid);
        $intf->BUFFER_MQTT_SUSPENDED = false; // Instanz zwangsweise aktivieren, da keine MessageSink vorhanden ist.
        $Topic = $Debug['Config']['MQTTBaseTopic'] . '/' . $Debug['Config']['MQTTTopic']; // Topic aus Debug JSON Config ableiten
        if (isset($Debug['LastPayload']['device'])) {
            unset($Debug['LastPayload']['device']);
        }
        $Payload = $Debug['LastPayload']; // Payload aus Debug JSON laden
        $Payload['exposes'] = $Debug['Exposes']; // Exposes ergänzen
        $intf->ReceiveData(self::BuildRequest($Topic . '/availability', ['state'=>'online'])); // Daten an die Instanz senden
        $intf->ReceiveData(self::BuildRequest($Topic, $Payload)); // Daten an die Instanz senden
        unset($Payload['exposes']);
        return [$iid, $Debug];
    }

    public static function getExportDebugData(int $iid): array
    {
        return json_decode(base64_decode(substr(Z2M_UIExportDebugData($iid), strlen('data:application/json;base64,'))), true);
    }

    public static function count_recursive(array $array): int
    {
        $count = 0;
        foreach ($array as $_array) {
            if (is_countable($_array)) {
                $count += self::count_recursive($_array);
            } else {
                $count += 1;
            }
        }
        return $count;
    }
    private static function BuildRequest(string $Topic, array $Payload)
    {
        return json_encode(
            array_merge(
                self::$MQTTDataArray,
                [
                    'Topic'  => $Topic,
                    'Payload'=> utf8_encode(json_encode($Payload))
                ]
            ),
            JSON_UNESCAPED_SLASHES
        );
    }
}