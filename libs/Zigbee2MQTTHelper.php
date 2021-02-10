<?php

declare(strict_types=1);

trait Zigbee2MQTTHelper
{
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Z2M_Brightness':
                $this->setDimmer($Value);
                break;
            case 'Z2M_ColorTemp':
                $this->setColorTemperature($Value);
                break;
            case 'Z2M_State':
                $this->SwitchMode($Value);
                break;
            case 'Z2M_Statel1':
                $this->Command('l1/set', $this->OnOff($Value));
                break;
            case 'Z2M_Statel2':
                $this->Command('l2/set', $this->OnOff($Value));
                break;
            case 'Z2M_Sensitivity':
                $this->setSensitivity($Value);
                break;
            case 'Z2M_Color':
                $this->SendDebug(__FUNCTION__ . ' Color', $Value, 0);
                $this->setColor($Value, 'cie');
                break;
            case 'Z2M_Position':
                $this->setPosition($Value);
                break;
            default:
                $this->SendDebug('Request Action', 'No Action defined: ' . $Ident, 0);
                break;
        }
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
                if (fnmatch('*/availability', $Buffer->Topic)) {
                    $this->RegisterVariableBoolean('Z2M_Status', $this->Translate('Status'), 'Z2M.DeviceStatus');
                    if ($Buffer->Payload == 'online') {
                        SetValue($this->GetIDForIdent('Z2M_Status'), true);
                    } else {
                        SetValue($this->GetIDForIdent('Z2M_Status'), false);
                    }
                }
            }
            $Payload = json_decode($Buffer->Payload);
            if (is_object($Payload)) {
                if (property_exists($Payload, 'temperature')) {
                    $this->RegisterVariableFloat('Z2M_Temperature', $this->Translate('Temperature'), '~Temperature');
                    SetValue($this->GetIDForIdent('Z2M_Temperature'), $Payload->temperature);
                }
                if (property_exists($Payload, 'linkquality')) {
                    $this->RegisterVariableInteger('Z2M_Linkquality', $this->Translate('Linkquality'), '');
                    SetValue($this->GetIDForIdent('Z2M_Linkquality'), $Payload->linkquality);
                }
                if (property_exists($Payload, 'humidity')) {
                    $this->RegisterVariableFloat('Z2M_Humidity', $this->Translate('Humidity'), '~Humidity.F');
                    SetValue($this->GetIDForIdent('Z2M_Humidity'), $Payload->humidity);
                }
                if (property_exists($Payload, 'pressure')) {
                    $this->RegisterVariableFloat('Z2M_Pressure', $this->Translate('Pressure'), '~AirPressure.F');
                    SetValue($this->GetIDForIdent('Z2M_Pressure'), $Payload->pressure);
                }
                if (property_exists($Payload, 'battery')) {
                    $this->RegisterVariableInteger('Z2M_Battery', $this->Translate('Battery'), '~Battery.100');
                    SetValue($this->GetIDForIdent('Z2M_Battery'), $Payload->battery);
                }
                if (property_exists($Payload, 'voltage')) {
                    $this->RegisterVariableFloat('Z2M_Voltage', $this->Translate('Voltage'), '~Volt');
                    SetValue($this->GetIDForIdent('Z2M_Voltage'), $Payload->voltage / 1000);
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
                if (property_exists($Payload, 'position')) {
                    $this->RegisterVariableInteger('Z2M_Position', $this->Translate('Position'), '~Intensity.100');
                    $this->EnableAction('Z2M_Position');
                    SetValue($this->GetIDForIdent('Z2M_Position'), $Payload->position);
                }
                if (property_exists($Payload, 'occupancy')) {
                    $this->RegisterVariableBoolean('Z2M_Occupancy', $this->Translate('Occupancy'), '~Motion');
                    SetValue($this->GetIDForIdent('Z2M_Occupancy'), $Payload->occupancy);
                }
                if (property_exists($Payload, 'illuminance')) {
                    $this->RegisterVariableInteger('Z2M_Illuminance', $this->Translate('Illuminance'), '');
                    SetValue($this->GetIDForIdent('Z2M_Illuminance'), $Payload->illuminance);
                }
                if (property_exists($Payload, 'illuminance_lux')) {
                    $this->RegisterVariableInteger('Z2M_Illuminance_Lux', $this->Translate('Illuminance Lux'), '~Illumination');
                    SetValue($this->GetIDForIdent('Z2M_Illuminance_Lux'), $Payload->illuminance_lux);
                }
                if (property_exists($Payload, 'strength')) {
                    $this->RegisterVariableInteger('Z2M_Strength', $this->Translate('Strength'), '');
                    SetValue($this->GetIDForIdent('Z2M_Strength'), $Payload->strength);
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
                    $this->RegisterVariableFloat('Z2M_Angle', $this->Translate('Angle'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle'), $Payload->angle);
                }
                if (property_exists($Payload, 'angle_x')) {
                    $this->RegisterVariableFloat('Z2M_Angle_X', $this->Translate('Angle X'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_X'), $Payload->angle_x);
                }
                if (property_exists($Payload, 'angle_y')) {
                    $this->RegisterVariableFloat('Z2M_Angle_Y', $this->Translate('Angle Y'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_Y'), $Payload->angle_y);
                }
                if (property_exists($Payload, 'angle_x_absolute')) {
                    $this->RegisterVariableFloat('Z2M_Angle_X_Absolute', $this->Translate('Angle_X_Absolute'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_X_Absolute'), $Payload->angle_x_absolute);
                }
                if (property_exists($Payload, 'angle_y_absolute')) {
                    $this->RegisterVariableFloat('Z2M_Angle_Y_Absolute', $this->Translate('Angle_Y_Absolute'), '');
                    SetValue($this->GetIDForIdent('Z2M_Angle_Y_Absolute'), $Payload->angle_y_absolute);
                }
                if (property_exists($Payload, 'angle_z')) {
                    $this->RegisterVariableFloat('Z2M_Angle_Z', $this->Translate('Angle Z'), '');
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
                if (property_exists($Payload, 'power')) {
                    $this->RegisterVariableFloat('Z2M_Power', $this->Translate('Power'), '');
                    SetValue($this->GetIDForIdent('Z2M_Power'), $Payload->power);
                }
                if (property_exists($Payload, 'consumption')) {
                    $this->RegisterVariableFloat('Z2M_Consumption', $this->Translate('Consumption'), '');
                    SetValue($this->GetIDForIdent('Z2M_Consumption'), $Payload->consumption);
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
                        $RGBColor = ltrim($this->CIEToRGB($Payload->color->x, $Payload->color->y, $Payload->brightness), '#');
                    } else {
                        $RGBColor = ltrim($this->CIEToRGB($Payload->color->x, $Payload->color->y), '#');
                    }
                    $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                    $this->RegisterVariableInteger('Z2M_Color', $this->Translate('Color'), 'HexColor');
                    $this->EnableAction('Z2M_Color');
                    SetValue($this->GetIDForIdent('Z2M_Color'), hexdec(($RGBColor)));
                }
                if (property_exists($Payload, 'sensitivity')) {
                    $this->RegisterVariableInteger('Z2M_Sensitivity', $this->Translate('Sensitivity'), 'Z2M.Sensitivity');
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
                if (property_exists($Payload, 'color_temp')) {
                    $this->RegisterVariableInteger('Z2M_ColorTemp', $this->Translate('Color Temperature'), 'Z2M.ColorTemperature');
                    $this->EnableAction('Z2M_ColorTemp');
                    SetValue($this->GetIDForIdent('Z2M_ColorTemp'), $Payload->color_temp);
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
                if (property_exists($Payload, 'state_l1')) {
                    $this->RegisterVariableBoolean('Z2M_Statel1', $this->Translate('State 1'), '~Switch');
                    $this->EnableAction('Z2M_Statel1');
                    switch ($Payload->state_l1) {
                        case 'ON':
                            SetValue($this->GetIDForIdent('Z2M_Statel1'), true);
                            break;
                        case 'OFF':
                            SetValue($this->GetIDForIdent('Z2M_Statel1'), false);
                            break;
                        default:
                            $this->SendDebug('State 1', 'Undefined State 1: ' . $Payload->state_l1, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'state_l2')) {
                    $this->RegisterVariableBoolean('Z2M_Statel2', $this->Translate('State 2'), '~Switch');
                    $this->EnableAction('Z2M_Statel2');
                    switch ($Payload->state_l2) {
                        case 'ON':
                            SetValue($this->GetIDForIdent('Z2M_Statel2'), true);
                            break;
                        case 'OFF':
                            SetValue($this->GetIDForIdent('Z2M_Statel2'), false);
                            break;
                        default:
                            $this->SendDebug('State 2', 'Undefined State 2: ' . $Payload->l2, 0);
                            break;
                    }
                }
            }
        }
    }

    public function setDimmer(int $value)
    {
        $Payload['brightness'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    public function setColorTemperature(int $value)
    {
        $Payload['color_temp'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    public function setPosition(int $value)
    {
        $Payload['position'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    public function SwitchMode(bool $value)
    {
        $state = $this->OnOff($value);
        $Payload['state'] = $state;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    public function setColorMode(int $mode)
    {
        $Payload['color_mode'] = strval($mode);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    public function setColor(int $color, string $mode)
    {
        switch ($mode) {
            case 'cie':
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToCIE($RGB[0], $RGB[1], $RGB[2]);

                $Payload['color'] = $cie;
                $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
                $this->Z2MSet($PayloadJSON);
                break;
            default:
                $this->SendDebug('setColor', 'Invalid Mode ' . $mode, 0);
                break;
        }
    }

    public function setSensitivity(int $value)
    {
        $Payload['sensitivity'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    protected function createVariableProfiles()
    {
        if (!IPS_VariableProfileExists('Z2M.Sensitivity')) {
            $Associations = [];
            $Associations[] = [1, $this->Translate('Medium'), '', -1];
            $Associations[] = [2, $this->Translate('Low'), '', -1];
            $Associations[] = [3, $this->Translate('High'), '', -1];
            $this->RegisterProfileIntegerEx('Z2M.Sensitivity', '', '', '', $Associations);
        }

        if (!IPS_VariableProfileExists('Z2M.ColorTemperature')) {
            IPS_CreateVariableProfile('Z2M.ColorTemperature', 1);
        }
        IPS_SetVariableProfileDigits('Z2M.ColorTemperature', 0);
        IPS_SetVariableProfileIcon('Z2M.ColorTemperature', 'Bulb');
        IPS_SetVariableProfileText('Z2M.ColorTemperature', '', ' Mired');
        IPS_SetVariableProfileValues('Z2M.ColorTemperature', 50, 400, 1);

        if (!IPS_VariableProfileExists('Z2M.DeviceStatus')) {
            $this->RegisterProfileBooleanEx('Z2M.DeviceStatus', 'Network', '', '', [
                [false, 'Offline',  '', 0xFF0000],
                [true, 'Online',  '', 0x00FF00]
            ]);
        }
    }

    private function OnOff(bool $Value)
    {
        switch ($Value) {
            case true:
                $state = 'ON';
                break;
            case false:
                $state = 'OFF';
                break;
        }
        return $state;
    }

    private function Z2MSet($payload)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/set';
        $Data['Payload'] = $payload;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
    }
}