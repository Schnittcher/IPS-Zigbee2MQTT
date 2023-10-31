<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/MQTTHelper.php';

class Zigbee2MQTTConfigurator extends IPSModule
{
    use \Zigbee2MQTT\MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zigbee2mqtt');

        $this->SetBuffer('Devices', '{}');
        $this->SetBuffer('Groups', '{}');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Setze Filter für ReceiveData
        $topic = 'symcon/' . $this->ReadPropertyString('MQTTBaseTopic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            $this->getDevices();
            $this->getGroups();
        }

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            $this->getDevices();
            $this->getGroups();
        }
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //Devices
        $Devices = json_decode($this->GetBuffer('Devices'), true);
        $this->SendDebug('Buffer Devices', json_encode($Devices), 0);
        $ValuesDevices = [];

        foreach ($Devices as $device) {
            $instanceID = $this->getDeviceInstanceID($device['friendly_name']);
            $Value['name'] = $device['friendly_name'];
            $Value['ieee_address'] = $device['ieeeAddr'];
            $Value['networkAddress'] = $device['networkAddress'];
            $Value['type'] = $device['type'];
            $Value['vendor'] = $device['vendor'];
            $Value['modelID'] = (array_key_exists('modelID', $device) == true ? $device['modelID'] : $this->Translate('Unknown'));
            $Value['description'] = $device['description'];
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

        //Groups
        $Groups = json_decode($this->GetBuffer('Groups'), true);
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
        return json_encode($Form);
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        $Buffer = json_decode($JSONString, true);

        if (array_key_exists('Topic', $Buffer)) {
            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer['Payload'] = utf8_decode($Buffer['Payload']);
            }
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/devices', $Buffer['Topic'])) {
                $Payload = json_decode($Buffer['Payload'], true);
                $this->SetBuffer('Devices', json_encode($Payload));
            }
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/groups', $Buffer['Topic'])) {
                $Payload = json_decode($Buffer['Payload'], true);
                $this->SetBuffer('Groups', json_encode($Payload));
            }
        }
    }

    private function getDevices()
    {
        $this->symconExtensionCommand('getDevices', '');
    }

    private function getGroups()
    {
        $this->symconExtensionCommand('getGroups', '');
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