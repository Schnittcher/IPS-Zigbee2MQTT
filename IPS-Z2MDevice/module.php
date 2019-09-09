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
        $this->RegisterPropertyBoolean('HUEIlluminance', false);

        if (!IPS_VariableProfileExists('Z2M.Sensitivity')) {
            $Associations = array();
            $Associations[] = array(1, $this->Translate('Medium'), '', -1);
            $Associations[] = array(2, $this->Translate('Low'), '', -1);
            $Associations[] = array(3, $this->Translate('High'), '', -1);
            $this->RegisterProfileIntegerEx('Z2M.Sensitivity', '', '', '', $Associations);
        }
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
                    SetValue($this->GetIDForIdent('Z2M_Click'), $Payload->click);
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
                    $this->RegisterVariableInteger('Z2M_Illuminance', $this->Translate('Illuminance'), '~Illumination');
                    if ($this->ReadPropertyBoolean('HUEIlluminance')) {
                        SetValue($this->GetIDForIdent('Z2M_Illuminance'), intval(pow(10, $Payload->illuminance / 10000)));
                    } else {
                        SetValue($this->GetIDForIdent('Z2M_Illuminance'), $Payload->illuminance);
                    }
                }
                if (property_exists($Payload, 'water_leak')) {
                    $this->RegisterVariableBoolean('Z2M_WaterLeak', $this->Translate('Water Leak'), '');
                    SetValue($this->GetIDForIdent('Z2M_WaterLeak'), $Payload->water_leak);
                }
                if (property_exists($Payload, 'contact')) {
                    $this->RegisterVariableBoolean('Z2M_Contact', $this->Translate('Contact'), '');
                    SetValue($this->GetIDForIdent('Z2M_Contact'), $Payload->contact);
                }
                if (property_exists($Payload, 'smoke')) {
                    $this->RegisterVariableBoolean('Z2M_Smoke', $this->Translate('Smoke'), '');
                    SetValue($this->GetIDForIdent('Z2M_Smoke'), $Payload->smoke);
                }
                if (property_exists($Payload, 'battery_low')) {
                    $this->RegisterVariableBoolean('Z2M_Battery_Low', $this->Translate('Battery Low'), '');
                    SetValue($this->GetIDForIdent('Z2M_Battery_Low'), $Payload->battery_low);
                }
                if (property_exists($Payload, 'angle')) {
                    $this->RegisterVariableInteger('Z2M_Angle', $this->Translate('Angle'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle'), $Payload->angle);
                }
                if (property_exists($Payload, 'angle_x')) {
                    $this->RegisterVariableInteger('Z2M_Angle_X', $this->Translate('Angle X'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_X'), $Payload->angle_x);
                }
                if (property_exists($Payload, 'angle_y')) {
                    $this->RegisterVariableInteger('Z2M_Angle_Y', $this->Translate('Angle Y'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_Y'), $Payload->angle_y);
                }
                if (property_exists($Payload, 'angle_x_absolute')) {
                    $this->RegisterVariableInteger('Z2M_Angle_X_Absolute', $this->Translate('Angle_X_Absolute'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_X_Absolute'), $Payload->angle_x_absolute);
                }
                if (property_exists($Payload, 'angle_y_absolute')) {
                    $this->RegisterVariableInteger('Z2M_Angle_Y_Absolute', $this->Translate('Angle_Y_Absolute'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_Y_Absolute'), $Payload->angle_y_absolute);
                }
                if (property_exists($Payload, 'angle_z')) {
                    $this->RegisterVariableInteger('Z2M_Angle_Z', $this->Translate('Angle Z'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_Z'), $Payload->angle_z);
                }
                if (property_exists($Payload, 'from_side')) {
                    $this->RegisterVariableInteger('Z2M_From_Side', $this->Translate('From Side'), '');
                    SetValue($this->GetIDForIdent('Z2M_From_Side'), $Payload->from_side);
                }
                if (property_exists($Payload, 'to_side')) {
                    $this->RegisterVariableInteger('Z2M_To_Side', $this->Translate('To Side'), '');
                    SetValue($this->GetIDForIdent('Z2M_To_Side'), $Payload->to_side);
                }
                if (property_exists($Payload, 'angle')) {
                    $this->RegisterVariableFloat('Z2M_Angle', $this->Translate('Angle'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle'), $Payload->angle);
                }
                if (property_exists($Payload, 'power')) {
                    $this->RegisterVariableFloat('Z2M_Power', $this->Translate('Power'), '');
                    SetValue($this->GetIDForIdent('Z2M_Power'), $Payload->power);
                }
                if (property_exists($Payload, 'duration')) {
                    $this->RegisterVariableFloat('Z2M_Duration', $this->Translate('Duration'), '');
                    SetValue($this->GetIDForIdent('Z2M_Duration'), $Payload->duration);
                }
                if (property_exists($Payload, 'counter')) {
                    $this->RegisterVariableFloat('Z2M_Counter', $this->Translate('Counter'), '');
                    SetValue($this->GetIDForIdent('Z2M_Counter'), $Payload->counter);
                }
                if (property_exists($Payload, 'color')) {
                    $this->SendDebug(__FUNCTION__ . ' Color', $Payload->color->x, 0);
                    if (property_exists($Payload, 'brightness')) {
                        $RGBColor = $this->CIEToRGB($Payload->color->x, $Payload->color->y, $Payload->brightness);
                    } else {
                        $RGBColor = $this->CIEToRGB($Payload->color->x, $Payload->color->y);
                    }
                    $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                    $this->RegisterVariableInteger('Z2M_Color', $this->Translate('Color'), 'HexColor');
                    $this->EnableAction('Z2M_Color');
                    SetValue($this->GetIDForIdent('Z2M_Color'), hexdec(($RGBColor)));
                }
                if (property_exists($Payload, 'sensitivity')) {
                    $this->RegisterVariableInteger('Z2M_Sensitivity', $this->Translate('Sensitivity'), 'Z2M.Sensitivity');
                    SetValue($this->GetIDForIdent('Z2M_Sensitivity'), $Payload->sensitivity);
                    $this->EnableAction('Z2M_Sensitivity');
                    switch ($Payload->sensitivity) {
                        case 'medium':
                            SetValue($this->GetIDForIdent('Z2M_Sensitivity'), 1);
                            break;
                        case 'low':
                            SetValue($this->GetIDForIdent('Z2M_Sensitivity'), 2);
                            break;
                        case 'high':
                            SetValue($this->GetIDForIdent('Z2M_Sensitivity'), 3);
                            break;
                        default:
                            $this->SendDebug('SetValue Sensitivity', 'Invalid Value: ' . $Payload->sensitivity, 0);
                            break;
                    }
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
