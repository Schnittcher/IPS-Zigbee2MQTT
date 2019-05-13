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
        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $Buffer = json_decode($JSONString);
            // Buffer decodieren und in eine Variable schreiben
            $this->SendDebug('MQTT Topic', $Buffer->Topic, 0);
            $this->SendDebug('MQTT Payload', $Buffer->Payload, 0);
            if (property_exists($Buffer, 'Topic')) {
                $Payload = json_decode($Buffer->Payload);
                if (property_exists($Payload, 'temperature')) {
                    $this->RegisterVariableFloat('Z2M_Temperature', $this->Translate('Temperature'), '~Temperature');
                    SetValue($this->GetIDForIdent('Z2M_Temperature'), $Payload->temperature);
                }
                if (property_exists($Payload, 'linkquality')) {
                    $this->RegisterVariableInteger('Z2M_Linkquality', $this->Translate('Linkquality'), '');
                    SetValue($this->GetIDForIdent('Z2M_Linkquality'), $Payload->linkquality);
                }
                if (property_exists($Payload, 'humidity')) {
                    $this->RegisterVariableFloat('Z2M_Humidity', $this->Translate('Humidity'), '');
                    SetValue($this->GetIDForIdent('Z2M_Humidity'), $Payload->humidity);
                }
                if (property_exists($Payload, 'pressure')) {
                    $this->RegisterVariableFloat('Z2M_Pressure', $this->Translate('Pressure'), '');
                    SetValue($this->GetIDForIdent('Z2M_Pressure'), $Payload->pressure);
                }
                if (property_exists($Payload, 'battery')) {
                    $this->RegisterVariableFloat('Z2M_Battery', $this->Translate('Battery'), '');
                    SetValue($this->GetIDForIdent('Z2M_Battery'), $Payload->battery);
                }
                if (property_exists($Payload, 'voltage')) {
                    $this->RegisterVariableFloat('Z2M_Voltage', $this->Translate('Voltage'), '');
                    SetValue($this->GetIDForIdent('Z2M_Voltage'), $Payload->voltage);
                }
                if (property_exists($Payload, 'action')) {
                    $this->RegisterVariableString('Z2M_Action', $this->Translate('Action'), '');
                    SetValue($this->GetIDForIdent('Z2M_Action'), $Payload->action);
                }
                if (property_exists($Payload, 'click')) {
                    $this->RegisterVariableString('Z2M_Click', $this->Translate('Click'), '');
                    SetValue($this->GetIDForIdent('Z2M_Click'), $Payload->action);
                }
                if (property_exists($Payload, 'brightness')) {
                    $this->RegisterVariableInteger('Z2M_Brightness', $this->Translate('Brightness'), '~Intensity.255');
                    $this->EnableAction('Z2M_Brightness');
                    SetValue($this->GetIDForIdent('Z2M_Brightness'), $Payload->brightness);
                }
                if (property_exists($Payload, 'occupancy')) {
                    $this->RegisterVariableBoolean('Z2M_Occupancy', $this->Translate('Occupancy'), '');
                    SetValue($this->GetIDForIdent('Z2M_Occupancy'), $Payload->occupancy);
                }
                if (property_exists($Payload, 'illuminance')) {
                    $this->RegisterVariableInteger('Z2M_Illuminance', $this->Translate('Illuminance'), '');
                    SetValue($this->GetIDForIdent('Z2M_Illuminance'), $Payload->illuminance);
                }
                if (property_exists($Payload, 'water_leak')) {
                    $this->RegisterVariableBoolean('Z2M_WaterLeak', $this->Translate('Water Leak'), '');
                    SetValue($this->GetIDForIdent('Z2M_WaterLeak'), $Payload->water_leak);
                }
                if (property_exists($Payload, 'contact')) {
                    $this->RegisterVariableBoolean('Z2M_Contact', $this->Translate('Contact'), '');
                    SetValue($this->GetIDForIdent('Z2M_Contact'), $Payload->contact);
                }

                if (property_exists($Payload, 'state')) {
                    $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                    $this->EnableAction('Z2M_State');
                    switch ($Payload->state) {
                        case 'ON':
                            SetValue($this->GetIDForIdent('Z2M_State'), true);
                            break;
                        case 'OFF':
                            SetValue($this->GetIDForIdent('Z2M_State'), false);
                            break;
                        default:
                            $this->SendDebug('State', 'Undefined State: ' . $Payload->state, 0);
                            break;
                    }
                }
            }
        }
    }
}
