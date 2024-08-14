<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';

/**
 * @property array $TransactionData
 */
class Zigbee2MQTTConfigurator extends IPSModule
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zigbee2mqtt');
        $this->TransactionData = [];
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        if (empty($BaseTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
            return;
        }
        $this->SetStatus(IS_ACTIVE);
        //Setze Filter für ReceiveData
        $this->SetReceiveDataFilter('.*"Topic":"' . $BaseTopic . '/SymconExtension/lists/response.*');
    }

    public function GetConfigurationForm()
    {
        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        $Devices = [];
        $Groups = [];
        if (!empty($BaseTopic)) {
            if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                $Devices = $this->getDevices();
                $Groups = $this->getGroups();
            }
        }
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        /**
         * Todo:
         * Wenn beides Arrays leer sind, Hinweis das die Erweiterung fehlt oder veraltet ist
         * PopUp mit Button um zur Bridge zu springen? -> Sofern Instanz gefunden wurde
         * Bridge mit Aufnehmen in die Geräteliste?!
         */

        //Devices
        $ValuesDevices = [];
        foreach ($Devices as $device) {
            $instanceID = $this->getDeviceInstanceID($device['friendly_name']);
            $Value['name'] = $device['friendly_name'];
            $Value['ieee_address'] = $device['ieeeAddr'];
            $Value['networkAddress'] = $device['networkAddress'];
            $Value['type'] = $device['type'];
            $Value['vendor'] = (array_key_exists('vendor', $device) == true ? $device['vendor'] : $this->Translate('Unknown'));
            $Value['modelID'] = (array_key_exists('modelID', $device) == true ? $device['modelID'] : $this->Translate('Unknown'));
            $Value['description'] = (array_key_exists('description', $device) == true ? $device['description'] : $this->Translate('Unknown'));
            $Value['power_source'] = (array_key_exists('powerSource', $device) == true ? $this->Translate($device['powerSource']) : $this->Translate('Unknown'));

            $Value['instanceID'] = $instanceID;

            $Value['create'] =
                [
                    'moduleID'      => '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}',
                    'configuration' => [
                        'MQTTTopic'    => $device['friendly_name']
                    ]
                ];
            array_push($ValuesDevices, $Value);
        }
        $Form['actions'][0]['items'][0]['values'] = $ValuesDevices;
        $Form['actions'][0]['items'][0]['rowCount'] = count($ValuesDevices);

        //Groups
        $ValuesGroups = [];
        foreach ($Groups as $group) {
            $instanceID = $this->getGroupInstanceID($group['friendly_name']);
            $Value['ID'] = $group['ID'];
            $Value['name'] = $group['friendly_name'];
            $Value['instanceID'] = $instanceID;

            $Value['create'] =
                [
                    'moduleID'      => '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}',
                    'configuration' => [
                        'MQTTTopic'    => $group['friendly_name']
                    ]
                ];
            array_push($ValuesGroups, $Value);
        }
        $Form['actions'][1]['items'][0]['values'] = $ValuesGroups;
        $Form['actions'][1]['items'][0]['rowCount'] = count($ValuesGroups);
        return json_encode($Form);
    }

    public function ReceiveData($JSONString)
    {
        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        if (empty($BaseTopic)) {
            return '';
        }
        $this->SendDebug('JSON', $JSONString, 0);
        $Buffer = json_decode($JSONString, true);

        if (!isset($Buffer['Topic'])) {
            return '';
        }

        $ReceiveTopic = $Buffer['Topic'];
        $this->SendDebug('MQTT FullTopic', $ReceiveTopic, 0);
        $Topic = substr($ReceiveTopic, strlen($BaseTopic . '/SymconExtension/lists/'));
        $Topics = explode('/', $Topic);
        $Topic = array_shift($Topics);
        $this->SendDebug('MQTT Topic', $Topic, 0);
        $this->SendDebug('MQTT Payload', utf8_decode($Buffer['Payload']), 0);
        if ($Topic != 'response') {
            return '';
        }
        $Payload = json_decode(utf8_decode($Buffer['Payload']), true);
        if (isset($Payload['transaction'])) {
            $this->UpdateTransaction($Payload);
        }
        return '';
    }

    public function getDevices()
    {
        $Result = @$this->SendData('/SymconExtension/lists/request/getDevices');
        if ($Result) {
            return $Result['list'];
        }
        return [];
    }

    public function getGroups()
    {
        $Result = @$this->SendData('/SymconExtension/lists/request/getGroups');
        if ($Result) {
            return $Result['list'];
        }
        return [];
    }

    private function getDeviceInstanceID($FriendlyName)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}');
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'MQTTTopic') == $FriendlyName) {
                return $id;
            }
        }
        return 0;
    }

    private function getGroupInstanceID($FriendlyName)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{11BF3773-E940-469B-9DD7-FB9ACD7199A2}');
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'MQTTTopic') == $FriendlyName) {
                return $id;
            }
        }
        return 0;
    }
}