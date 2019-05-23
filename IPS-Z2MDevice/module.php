<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/Zigbee2MQTTHelper.php';

class IPS_Z2MDevice extends IPSModule
{
    use Zigbee2MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $Devices = json_decode(file_get_contents(__DIR__ . '/../libs/devices.json'));

        $this->RegisterPropertyString('MQTTTopic', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $Devices = json_decode(file_get_contents(__DIR__ . '/../libs/devices.json'));

        IPS_LogMessage('Devices', print_r($Devices, true));

        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $Buffer = json_decode($JSONString);
            // Buffer decodieren und in eine Variable schreiben
            $this->SendDebug('MQTT Topic', $Buffer->Topic, 0);
            $this->SendDebug('MQTT Payload', $Buffer->Payload, 0);
            if (property_exists($Buffer, 'Topic')) {
                $Payload = json_decode($Buffer->Payload);

                foreach ($Devices as $key => $value) {
                    if (property_exists($Payload, $key)) {
                        switch ($value->type) {
                            case 0:
                                $this->RegisterVariableBoolean('Z2M_' . $key, $this->Translate($value->name), $value->profile);
                                if (is_object($value->boolean)) {
                                    switch ($Payload->state) {
                                        case $value->boolean->true:
                                            SetValue($this->GetIDForIdent('Z2M_' . $key), true);
                                            break;
                                        case $value->boolean->false:
                                            SetValue($this->GetIDForIdent('Z2M_' . $key), false);
                                            break;
                                        default:
                                            $this->SendDebug('State', 'Undefined State: ' . $Payload->{$key}, 0);
                                            break;
                                    }
                                }
                                if ($value->action == 1) {
                                    $this->EnableAction('Z2M_' . $key);
                                }
                                break;
                            case 1:
                                if (property_exists($value, 'integer')) {
                                    if ($value->profile != '') {
                                        if (!IPS_VariableProfileExists($value->profile)) {
                                            $Associations = array();
                                            foreach ($value->integer as $IntKey => $IntValue) {
                                                $Associations[] = array($IntValue, $this->Translate($IntKey), '', -1);
                                            }
                                            $this->RegisterProfileIntegerEx($value->profile, '', '', '', $Associations);
                                        }
                                    }
                                    $this->RegisterVariableInteger('Z2M_' . $key, $this->Translate($value->name), $value->profile);
                                    IPS_LogMessage('test', print_r($value, true));

                                    $this->SendDebug('set Integer', ucfirst($Payload->{$key}), 0);
                                    $tmpkey = ucfirst($Payload->{$key});
                                    SetValue($this->GetIDForIdent('Z2M_' . $key), $value->integer->{$tmpkey});
                                } else {
                                    $this->RegisterVariableInteger('Z2M_' . $key, $this->Translate($value->name), $value->profile);
                                    SetValue($this->GetIDForIdent('Z2M_' . $key), $Payload->{$key});
                                }
                                if ($value->action == 1) {
                                    $this->EnableAction('Z2M_' . $key);
                                }

                                break;
                            case 2:
                                $this->RegisterVariableFloat('Z2M_' . $key, $this->Translate($value->name), $value->profile);
                                SetValue($this->GetIDForIdent('Z2M_' . $key), $Payload->{$key});
                                if ($value->action == 1) {
                                    $this->EnableAction('Z2M_' . $key);
                                }
                                break;
                            case 3:
                                $this->RegisterVariableString('Z2M_' . $key, $this->Translate($value->name), $value->profile);
                                SetValue($this->GetIDForIdent('Z2M_' . $key), $Payload->{$key});
                                if ($value->action == 1) {
                                    $this->EnableAction('Z2M_' . $key);
                                }
                                break;
                            default:
                                $this->SendDebug(__FUNCTION__, $value->type . ' is not valid', 0);
                                break;
                        }
                    }
                }
            }
        }
    }
}
