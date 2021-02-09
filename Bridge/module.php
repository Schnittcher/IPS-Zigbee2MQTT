<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/MQTTHelper.php';
require_once __DIR__ . '/../libs/Zigbee2MQTTBridgeHelper.php';
require_once __DIR__ . '/../libs/Zigbee2MQTTHelper.php';

class Zigbee2MQTTBridge extends IPSModule
{
    use Zigbee2MQTTBridgeHelper;
    use MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTTopic', 'bridge');
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
        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $Buffer = json_decode($JSONString);
            // Buffer decodieren und in eine Variable schreiben
            $this->SendDebug('MQTT Topic', $Buffer->Topic, 0);
            $this->SendDebug('MQTT Payload', $Buffer->Payload, 0);
            if (property_exists($Buffer, 'Topic')) {
                $Payload = json_decode($Buffer->Payload);
                if (fnmatch('*state*', $Buffer->Topic)) {
                    $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '');
                    switch ($Buffer->Payload) {
                            case 'online':
                                SetValue($this->GetIDForIdent('Z2M_State'), true);
                                break;
                            case 'offline':
                                SetValue($this->GetIDForIdent('Z2M_State'), false);
                                break;
                            default:
                                $this->SendDebug('Bridge State', 'Invalid Payload' . $Buffer->Payload, 0);
                        }
                }
                if (fnmatch('*config*', $Buffer->Topic)) {
                    if (property_exists($Payload, 'log_level')) {
                        $this->RegisterVariableString('Z2M_Log_Level', $this->Translate('Log Level'), '');
                        SetValue($this->GetIDForIdent('Z2M_Log_Level'), $Payload->log_level);
                    }
                    if (property_exists($Payload, 'permit_join')) {
                        $this->RegisterVariableString('Z2M_Permit_Join', $this->Translate('Permit Join'), '');
                        SetValue($this->GetIDForIdent('Z2M_Permit_Join'), $Payload->permit_join);
                    }
                }
                if (fnmatch('*log*', $Buffer->Topic)) {
                    if (property_exists($Payload, 'type')) {
                        switch ($Payload->type) {
                            case 'pairing':
                                $this->RegisterVariableString('Z2M_Pairing', $this->Translate('Pairing'), '');
                                SetValue($this->GetIDForIdent('Z2M_Pairing'), $Buffer->Payload);
                                break;
                            case 'device_connected':
                                $this->RegisterVariableString('Z2M_Device_Connected', $this->Translate('Device Connected'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Connected'), $Buffer->Payload);
                                break;
                            case 'device removed':
                                $this->RegisterVariableString('Z2M_Device_Removed', $this->Translate('Device Removed'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Removed'), $Buffer->Payload);
                                break;
                            case 'device_banned':
                                $this->RegisterVariableString('Z2M_Device_Banned', $this->Translate('Device Banned'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Banned'), $Buffer->Payload);
                                break;
                            case 'device_renamed':
                                $this->RegisterVariableString('Z2M_Device_Renamed', $this->Translate('Device Renamed'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Renamed'), $Payload->message->from . ' >> ' . $Payload->message->to);
                                break;
                            case 'device_bind':
                                $this->RegisterVariableString('Z2M_Device_bind', $this->Translate('Device Bind'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_bind'), $Buffer->Payload);
                                break;
                            case 'device_unbind':
                                $this->RegisterVariableString('Z2M_Device_Unbind', $this->Translate('Device Unbind'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Unbind'), $Buffer->Payload);
                                break;
                            case 'device_group_add':
                                $this->RegisterVariableString('Z2M_Device_Group_Add', $this->Translate('Device Group Add'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Group_Add'), $Buffer->Payload);
                                break;
                            case 'device_group_remove':
                                $this->RegisterVariableString('Z2M_Device_Group_Remove', $this->Translate('Device Group Remove'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Group_Remove'), $Buffer->Payload);
                                break;
                            case 'device_group_remove_all':
                                $this->RegisterVariableString('Z2M_Device_Group_Remove_All', $this->Translate('Device Group Remove All'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Group_Remove_All'), $Buffer->Payload);
                                break;
                            case 'devices':
                                $this->RegisterVariableString('Z2M_Devices', $this->Translate('Devices'), '');
                                SetValue($this->GetIDForIdent('Z2M_Devices'), $Buffer->Payload);
                                break;
                            case 'device_publish_error':
                                $this->RegisterVariableString('Z2M_Device_Publish_Error', $this->Translate('Device Publish Error'), '');
                                SetValue($this->GetIDForIdent('Z2M_Device_Publish_Error'), $Payload->device_publish_error);
                                break;
                        }
                    }
                }
            }
        }
    }
}
