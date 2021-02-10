<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/MQTTHelper.php';

class Zigbee2MQTTConfigurator extends IPSModule
{
    use MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->SetBuffer('Devices', '{}');
        $this->SetBuffer('Groups', '{}');

        //$this->RegisterAttributeBoolean('ReceiveDataFilterActive', true);
        $this->RegisterTimer('Z2M_ActivateReceiveDataFilter', 0, 'Z2M_ActivateReceiveDataFilter($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Setze Filter fÃ¼r ReceiveData
        $topic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
        $this->getDevices();
        $this->getGroups();
    }

    public function GetConfigurationForm()
    {
        $this->getDevices();
        $this->getGroups();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //Devices
        $Devices = json_decode($this->GetBuffer('Devices'), true);
        $ValuesDevices = [];

        foreach ($Devices as $device) {
            $instanceID = $this->getDeviceInstanceID($device['friendly_name']);
            $Value['name'] = $device['friendly_name'];
            $Value['ieee_address'] = $device['ieeeAddr'];
            $Value['type'] = $device['type'];
            if ($device['type'] != 'Coordinator') {
                $Value['vendor'] = $device['vendor'];
                $Value['modelID'] = $device['modelID'];
                $Value['description'] = $device['description'];
                $Value['power_source'] = (array_key_exists('powerSource', $device) == true ? $this->Translate($device['powerSource']) : $this->Translate('Unknown'));
            }
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

            $Value['create'] = [
                [
                    'moduleID'      => '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}',
                    'configuration' => [
                        'MQTTTopic'    => $group['friendly_name']
                    ]
                ],
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
            if (fnmatch('*/config/devices', $Buffer['Topic'])) {
                $Payload = json_decode($Buffer['Payload'], true);
                $this->SetBuffer('Devices', json_encode($Payload));
            }
            if (fnmatch('*/log', $Buffer['Topic'])) {
                $Payload = json_decode($Buffer['Payload'], true);

                if ($Payload['type'] == 'groups') {
                    $this->SetBuffer('Groups', json_encode($Payload['message']));
                }
            }
        }
    }

    public function getDeviceVariables($FriendlyName)
    {
        $Payload['from'] = $FriendlyName;
        $Payload['to'] = $FriendlyName . 'Z2MSymcon';
        $Payload['homeassistant_rename'] = false;
        $this->Command('request/device/rename', json_encode($Payload));

        $Payload['from'] = $FriendlyName . 'Z2MSymcon';
        $Payload['to'] = $FriendlyName;
        $Payload['homeassistant_rename'] = false;
        $this->Command('request/device/rename', json_encode($Payload));
    }

    public function ActivateReceiveDataFilter()
    {
        $topic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
        $this->SetTimerInterval('Z2M_ActivateReceiveDataFilter', 0);
    }

    private function getDevices()
    {
        $this->SetReceiveDataFilter('');
        $this->Command('config/devices/get', '');
        $this->SetTimerInterval('Z2M_ActivateReceiveDataFilter', 30000);
    }

    private function getGroups()
    {
        $this->Command('config/groups/', '');
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