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
            case 'Z2M_Statel3':
                $this->Command('l3/set', $this->OnOff($Value));
                break;
            case 'Z2M_Statel4':
                $this->Command('l4/set', $this->OnOff($Value));
                break;
            case'Z2M_PowerOutageMemory':
                $this->setPowerOutageMemory($Value);
                break;
            case 'Z2M_StateWindow':
                $this->StateWindow($Value);
                break;
            case 'Z2M_Sensitivity':
                $this->setSensitivity($Value);
                break;
            case 'Z2M_CurrentHeatingSetpoint':
                $this->setCurrentHeatingSetpoint($Value);
                break;
            case 'Z2M_OccupiedHeatingSetpoint':
                $this->setOccupiedHeatingSetpoint($Value);
                break;
            case 'Z2M_Preset':
                $this->setThermostatPreset($Value);
                break;
            case 'Z2M_AwayPresetDays':
                $this->setThermostatAwayPresetDays(intval($Value));
                break;
            case 'Z2M_AwayMode':
                $this->setThermostatAwayMode($Value);
                break;
            case 'Z2M_BoostTime':
                $this->setThermostatBoostTime(intval($Value));
                break;
            case 'Z2M_SystemMode':
                $this->setSystemMode($Value);
                break;
            case 'Z2M_Color':
                $this->SendDebug(__FUNCTION__ . ' Color', $Value, 0);
                $this->setColor($Value, 'cie');
                break;
            case 'Z2M_Position':
                $this->setPosition($Value);
                break;
            case 'Z2M_MotorSpeed':
                $this->setMotorSpeed($Value);
                break;
            case 'Z2M_MotionSensitivity':
                $this->setMotionSensitivity($Value);
                break;
            case 'Z2M_OccupancyTimeout':
                $this->setOccupancyTimeout($Value);
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
                if (property_exists($Payload, 'local_temperature')) {
                    $this->RegisterVariableFloat('Z2M_LocalTemperature', $this->Translate('Local Temperature'), '~Temperature');
                    SetValue($this->GetIDForIdent('Z2M_LocalTemperature'), $Payload->local_temperature);
                }
                if (property_exists($Payload, 'max_temperature')) {
                    $this->RegisterVariableFloat('Z2M_MaxTemperature', $this->Translate('Max Temperature'), '~Temperature');
                    $this->EnableAction('Z2M_MaxTemperature');
                    SetValue($this->GetIDForIdent('Z2M_MaxTemperature'), $Payload->max_temperature);
                }

                if (property_exists($Payload, 'min_temperature')) {
                    $this->RegisterVariableFloat('Z2M_MinTemperature', $this->Translate('Min Temperature'), '~Temperature');
                    $this->EnableAction('Z2M_MinTemperature');
                    SetValue($this->GetIDForIdent('Z2M_MinTemperature'), $Payload->min_temperature);
                }

                if (property_exists($Payload, 'preset')) {
                    $this->RegisterVariableInteger('Z2M_Preset', $this->Translate('Preset'), 'Z2M.ThermostatPreset');
                    $this->EnableAction('Z2M_Preset');
                    switch ($Payload->preset) {
                        case 'manual':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 1);
                            break;
                        case 'boost':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 2);
                            break;
                        case 'complex':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 3);
                            break;
                        case 'comfort':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 4);
                            break;
                        case 'eco':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 5);
                            break;
                        case 'heat':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 6);
                            break;
                        case 'heat':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 6);
                            break;
                        case 'schedule':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 7);
                            break;
                        case 'away':
                            SetValue($this->GetIDForIdent('Z2M_Preset'), 8);
                            break;
                        default:
                        $this->SendDebug('SetValue Preset', 'Invalid Value: ' . $Payload->preset, 0);
                            break;
                        }
                }

                if (property_exists($Payload, 'away_mode')) {
                    $this->RegisterVariableBoolean('Z2M_AwayMode', $this->Translate('Away Mode'), '~Switch');
                    $this->EnableAction('Z2M_AwayMode');
                    switch ($Payload->away_mode) {
                    case 'ON':
                        SetValue($this->GetIDForIdent('Z2M_AwayMode'), true);
                        break;
                    case 'OFF':
                        SetValue($this->GetIDForIdent('Z2M_AwayMode'), false);
                        break;
                    default:
                        $this->SendDebug('SetValue AwayMode', 'Invalid Value: ' . $Payload->away_mode, 0);
                        break;
                    }
                }

                if (property_exists($Payload, 'away_preset_days')) {
                    $this->RegisterVariableInteger('Z2M_AwayPresetDays', $this->Translate('Away Preset Days'), '');
                    $this->EnableAction('Z2M_AwayPresetDays');
                    SetValue($this->GetIDForIdent('Z2M_AwayPresetDays'), $Payload->away_preset_days);
                }

                if (property_exists($Payload, 'away_preset_temperature')) {
                    $this->RegisterVariableFloat('Z2M_AwayPresetTemperature', $this->Translate('Away Preset Temperature'), '~Temperature.Room');
                    $this->EnableAction('Z2M_AwayPresetTemperature');
                    SetValue($this->GetIDForIdent('Z2M_AwayPresetTemperature'), $Payload->away_preset_temperature);
                }

                if (property_exists($Payload, 'boost_time')) {
                    $this->RegisterVariableInteger('Z2M_BoostTime', $this->Translate('Boost Time'), '');
                    $this->EnableAction('Z2M_BoostTime');
                    SetValue($this->GetIDForIdent('Z2M_BoostTime'), $Payload->boost_time);
                }

                if (property_exists($Payload, 'comfort_temperature')) {
                    $this->RegisterVariableFloat('Z2M_ComfortTemperature', $this->Translate('Comfort Temperature'), '~Temperature.Room');
                    $this->EnableAction('Z2M_ComfortTemperature');
                    SetValue($this->GetIDForIdent('Z2M_ComfortTemperature'), $Payload->comfort_temperature);
                }

                if (property_exists($Payload, 'eco_temperature')) {
                    $this->RegisterVariableFloat('Z2M_EcoTemperature', $this->Translate('Eco Temperature'), '~Temperature.Room');
                    $this->EnableAction('Z2M_EcoTemperature');
                    SetValue($this->GetIDForIdent('Z2M_EcoTemperature'), $Payload->eco_temperature);
                }

                if (property_exists($Payload, 'current_heating_setpoint')) {
                    $this->RegisterVariableFloat('Z2M_CurrentHeatingSetpoint', $this->Translate('Current Heating Setpoint'), '~Temperature.Room');
                    $this->EnableAction('Z2M_CurrentHeatingSetpoint');
                    SetValue($this->GetIDForIdent('Z2M_CurrentHeatingSetpoint'), $Payload->current_heating_setpoint);
                }
                if (property_exists($Payload, 'occupied_heating_setpoint')) {
                    $this->RegisterVariableFloat('Z2M_OccupiedHeatingSetpoint', $this->Translate('Occupied Heating Setpoint'), '~Temperature.Room');
                    $this->EnableAction('Z2M_OccupiedHeatingSetpoint');
                    SetValue($this->GetIDForIdent('Z2M_OccupiedHeatingSetpoint'), $Payload->occupied_heating_setpoint);
                }
                if (property_exists($Payload, 'system_mode')) {
                    $this->RegisterVariableInteger('Z2M_SystemMode', $this->Translate('Mode'), 'Z2M.SystemMode');
                    $this->EnableAction('Z2M_SystemMode');
                    switch ($Payload->system_mode) {
                        case 'off':
                            SetValue($this->GetIDForIdent('Z2M_SystemMode'), 1);
                            break;
                        case 'auto':
                            SetValue($this->GetIDForIdent('Z2M_SystemMode'), 2);
                            break;
                        case 'heat':
                            SetValue($this->GetIDForIdent('Z2M_SystemMode'), 3);
                            break;
                        case 'cool':
                            SetValue($this->GetIDForIdent('Z2M_SystemMode'), 4);
                            break;
                        default:
                            $this->SendDebug('SetValue SystemMode', 'Invalid Value: ' . $Payload->system_mode, 0);
                            break;
                        }
                }
                if (property_exists($Payload, 'running_state')) {
                    $this->RegisterVariableString('Z2M_RunningState', $this->Translate('Running State'), '');
                    SetValue($this->GetIDForIdent('Z2M_RunningState'), $Payload->running_state);
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
                //Da Millivolt und Volt mit dem selben Topic verschickt wird
                if (property_exists($Payload, 'voltage')) {
                    $this->RegisterVariableFloat('Z2M_Voltage', $this->Translate('Voltage'), '~Volt');
                    if ($Payload->voltage > 400) { //Es gibt wahrscheinlich keine Zigbee Geräte mit über 400 Voltm
                        SetValue($this->GetIDForIdent('Z2M_Voltage'), $Payload->voltage / 1000);
                    } else {
                        SetValue($this->GetIDForIdent('Z2M_Voltage'), $Payload->voltage);
                    }
                }
                if (property_exists($Payload, 'current')) {
                    $this->RegisterVariableFloat('Z2M_Current', $this->Translate('Current'), '~Ampere');
                    SetValue($this->GetIDForIdent('Z2M_Current'), $Payload->current);
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
                if (property_exists($Payload, 'motor_speed')) {
                    $this->RegisterVariableInteger('Z2M_MotorSpeed', $this->Translate('Motor Speed'), '~Intensity.255');
                    $this->EnableAction('Z2M_MotoSpeed');
                    SetValue($this->GetIDForIdent('Z2M_MotoSpeed'), $Payload->motor_speed);
                }
                if (property_exists($Payload, 'occupancy')) {
                    $this->RegisterVariableBoolean('Z2M_Occupancy', $this->Translate('Occupancy'), '~Motion');
                    SetValue($this->GetIDForIdent('Z2M_Occupancy'), $Payload->occupancy);
                }
                if (property_exists($Payload, 'occupancy_timeout')) {
                    $this->RegisterVariableInteger('Z2M_OccupancyTimeout', $this->Translate('Occupancy Timeout'), '');
                    $this->EnableAction('Z2M_OccupancyTimeout');
                    SetValue($this->GetIDForIdent('Z2M_OccupancyTimeout'), $Payload->occupancy_timeout);
                }
                if (property_exists($Payload, 'motion_sensitivity')) {
                    $this->RegisterVariableInteger('Z2M_MotionSensitivity', $this->Translate('Motion Sensitivity'), 'Z2M.Sensitivity');
                    $this->EnableAction('Z2M_MotionSensitivity');
                    switch ($Payload->motion_sensitivity) {
                        case 'medium':
                            SetValue($this->GetIDForIdent('Z2M_MotionSensitivity'), 1);
                            break;
                        case 'low':
                            SetValue($this->GetIDForIdent('Z2M_MotionSensitivity'), 2);
                            break;
                        case 'high':
                            SetValue($this->GetIDForIdent('Z2M_MotionSensitivity'), 3);
                            break;
                        default:
                            $this->SendDebug('SetValue MotionSensitivity', 'Invalid Value: ' . $Payload->motion_sensitivity, 0);
                            break;
                        }
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
                if (property_exists($Payload, 'carbon_monoxide')) {
                    $this->RegisterVariableBoolean('Z2M_CarbonMonoxide', $this->Translate('Carbon Monoxide'), '~Alert');
                    SetValue($this->GetIDForIdent('Z2M_CarbonMonoxide'), $Payload->carbon_monoxide);
                }
                if (property_exists($Payload, 'smoke')) {
                    $this->RegisterVariableBoolean('Z2M_Smoke', $this->Translate('Smoke'), '~Alert');
                    SetValue($this->GetIDForIdent('Z2M_Smoke'), $Payload->smoke);
                }
                if (property_exists($Payload, 'smoke_density')) {
                    $this->RegisterVariableInteger('Z2M_SmokeDensity', $this->Translate('Smoke Density'), '');
                    SetValue($this->GetIDForIdent('Z2M_SmokeDensity'), $Payload->smoke_density);
                }
                if (property_exists($Payload, 'tamper')) {
                    $this->RegisterVariableBoolean('Z2M_Tamper', $this->Translate('Tamper'), '~Alert');
                    SetValue($this->GetIDForIdent('Z2M_Tamper'), $Payload->tamper);
                }
                if (property_exists($Payload, 'enrolled')) {
                    $this->RegisterVariableBoolean('Z2M_Enrolled', $this->Translate('Enrolled'), '');
                    SetValue($this->GetIDForIdent('Z2M_Enrolled'), $Payload->enrolled);
                }
                if (property_exists($Payload, 'restore_reports')) {
                    $this->RegisterVariableBoolean('Z2M_RestoreReports', $this->Translate('Restore Reports'), '');
                    SetValue($this->GetIDForIdent('Z2M_RestoreReports'), $Payload->restore_reports);
                }
                if (property_exists($Payload, 'supervision_reports')) {
                    $this->RegisterVariableBoolean('Z2M_SupervisionReports', $this->Translate('Supervision Reports'), '');
                    SetValue($this->GetIDForIdent('Z2M_SupervisionReports'), $Payload->supervision_reports);
                }
                if (property_exists($Payload, 'trouble')) {
                    $this->RegisterVariableBoolean('Z2M_Trouble', $this->Translate('Trouble'), '');
                    SetValue($this->GetIDForIdent('Z2M_Trouble'), $Payload->trouble);
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
                    $this->RegisterVariableFloat('Z2M_Power', $this->Translate('Power'), '~Watt.3680');
                    SetValue($this->GetIDForIdent('Z2M_Power'), $Payload->power);
                }
                if (property_exists($Payload, 'consumer_connected')) {
                    $this->RegisterVariableBoolean('Z2M_Consumer_Connected', $this->Translate('Consumer connected'), 'Z2M.ConsumerConnected');
                    SetValue($this->GetIDForIdent('Z2M_Consumer_Connected'), $Payload->consumer_connected);
                }
                if (property_exists($Payload, 'consumption')) {
                    $this->RegisterVariableFloat('Z2M_Consumption', $this->Translate('Consumption'), '~Electricity');
                    SetValue($this->GetIDForIdent('Z2M_Consumption'), $Payload->consumption);
                }
                if (property_exists($Payload, 'energy')) {
                    $this->RegisterVariableFloat('Z2M_Energy', $this->Translate('Energy'), '~Electricity');
                    SetValue($this->GetIDForIdent('Z2M_Energy'), $Payload->energy);
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
                    switch ($Payload->state) {
                        case 'ON':
                            $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                            $this->EnableAction('Z2M_State');
                            SetValue($this->GetIDForIdent('Z2M_State'), true);
                            break;
                        case 'OFF':
                            $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                            $this->EnableAction('Z2M_State');
                            SetValue($this->GetIDForIdent('Z2M_State'), false);
                            break;
                        case 'OPEN':
                            $this->RegisterVariableBoolean('Z2M_StateWindow', $this->Translate('State'), '~Window');
                            $this->EnableAction('Z2M_StateWindow');
                            SetValue($this->GetIDForIdent('Z2M_StateWindow'), true);
                            break;
                        case 'CLOSE':
                            $this->RegisterVariableBoolean('Z2M_StateWindow', $this->Translate('State'), '~Window');
                            $this->EnableAction('Z2M_StateWindow');
                            SetValue($this->GetIDForIdent('Z2M_StateWindow'), false);
                            break;
                        default:
                            $this->SendDebug('State', 'Undefined State: ' . $Payload->state, 0);
                            break;
                        }
                }
                if (property_exists($Payload, 'power_outage_memory')) {
                    $this->RegisterVariableInteger('Z2M_PowerOutageMemory', $this->Translate('Power Outage Memory'), 'Z2M.PowerOutageMemory');
                    $this->EnableAction('Z2M_PowerOutageMemory');
                    switch ($Payload->power_outage_memory) {
                        case 'ON':
                            SetValue($this->GetIDForIdent('Z2M_PowerOutageMemory'), 1);
                            break;
                        case 'OFF':
                            SetValue($this->GetIDForIdent('Z2M_PowerOutageMemory'), 2);
                            break;
                        case 'restore':
                            SetValue($this->GetIDForIdent('Z2M_PowerOutageMemory'), 2);
                            break;
                        default:
                            $this->SendDebug('Power Outage Memory', 'Undefined Power Outage Memory: ' . $Payload->power_outage_memory, 0);
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
                if (property_exists($Payload, 'state_l3')) {
                    $this->RegisterVariableBoolean('Z2M_Statel3', $this->Translate('State 3'), '~Switch');
                    $this->EnableAction('Z2M_Statel3');
                    switch ($Payload->state_l3) {
                        case 'ON':
                            SetValue($this->GetIDForIdent('Z2M_Statel3'), true);
                            break;
                        case 'OFF':
                            SetValue($this->GetIDForIdent('Z2M_Statel3'), false);
                            break;
                        default:
                            $this->SendDebug('State 3', 'Undefined State 3: ' . $Payload->l3, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'state_l4')) {
                    $this->RegisterVariableBoolean('Z2M_Statel4', $this->Translate('State 4'), '~Switch');
                    $this->EnableAction('Z2M_Statel4');
                    switch ($Payload->state_l4) {
                        case 'ON':
                            SetValue($this->GetIDForIdent('Z2M_Statel4'), true);
                            break;
                        case 'OFF':
                            SetValue($this->GetIDForIdent('Z2M_Statel4'), false);
                            break;
                        default:
                            $this->SendDebug('State 4', 'Undefined State 4: ' . $Payload->l4, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'update_available')) {
                    $this->RegisterVariableBoolean('Z2M_Update', $this->Translate('Update'), '');
                    SetValue($this->GetIDForIdent('Z2M_Update'), $Payload->update_available);
                }
            }
        }
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

        if (!IPS_VariableProfileExists('Z2M.SystemMode')) {
            $Associations = [];
            $Associations[] = [1, $this->Translate('Off'), '', -1];
            $Associations[] = [2, $this->Translate('Auto'), '', -1];
            $Associations[] = [3, $this->Translate('Heat'), '', -1];
            $Associations[] = [4, $this->Translate('Cool'), '', -1];
            $this->RegisterProfileIntegerEx('Z2M.SystemMode', '', '', '', $Associations);
        }

        if (!IPS_VariableProfileExists('Z2M.PowerOutageMemory')) {
            $Associations = [];
            $Associations[] = [1, $this->Translate('Off'), '', -1];
            $Associations[] = [2, $this->Translate('On'), '', -1];
            $Associations[] = [3, $this->Translate('Restore'), '', -1];
            $this->RegisterProfileIntegerEx('Z2M.PowerOutageMemory', '', '', '', $Associations);
        }

        if (!IPS_VariableProfileExists('Z2M.ThermostatPreset')) {
            $Associations = [];
            $Associations[] = [1, $this->Translate('Manual'), '', -1];
            $Associations[] = [2, $this->Translate('Boost'), '', -1];
            $Associations[] = [3, $this->Translate('Complexes Program'), '', -1];
            $Associations[] = [4, $this->Translate('Comfort'), '', -1];
            $Associations[] = [5, $this->Translate('Eco'), '', -1];
            $Associations[] = [6, $this->Translate('Heat'), '', -1];
            $Associations[] = [7, $this->Translate('Schedule'), '', -1];
            $Associations[] = [8, $this->Translate('Away'), '', -1];
            $this->RegisterProfileIntegerEx('Z2M.ThermostatPreset', '', '', '', $Associations);
        }

        if (!IPS_VariableProfileExists('Z2M.ColorTemperature')) {
            IPS_CreateVariableProfile('Z2M.ColorTemperature', 1);
        }
        IPS_SetVariableProfileDigits('Z2M.ColorTemperature', 0);
        IPS_SetVariableProfileIcon('Z2M.ColorTemperature', 'Bulb');
        IPS_SetVariableProfileText('Z2M.ColorTemperature', '', ' Mired');
        IPS_SetVariableProfileValues('Z2M.ColorTemperature', 50, 500, 1);

        if (!IPS_VariableProfileExists('Z2M.ConsumerConnected')) {
            $this->RegisterProfileBooleanEx('Z2M.ConsumerConnected', 'Plug', '', '', [
                [false, $this->Translate('not connected'),  '', 0xFF0000],
                [true, $this->Translate('connected'),  '', 0x00FF00]
            ]);
        }

        if (!IPS_VariableProfileExists('Z2M.DeviceStatus')) {
            $this->RegisterProfileBooleanEx('Z2M.DeviceStatus', 'Network', '', '', [
                [false, 'Offline',  '', 0xFF0000],
                [true, 'Online',  '', 0x00FF00]
            ]);
        }
    }

    private function setDimmer(int $value)
    {
        $Payload['brightness'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setColorTemperature(int $value)
    {
        $Payload['color_temp'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setPosition(int $value)
    {
        $Payload['position'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setMotorSpeed(int $value)
    {
        $Payload['motor_speed'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function SwitchMode(bool $value)
    {
        $Payload['state'] = $this->OnOff($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setPowerOutageMemory(int $value)
    {
        switch ($value) {
            case 1:
                $PowerOutageMemory = 'on';
                break;
            case 2:
                $PowerOutageMemory = 'off';
                break;
            case 3:
                $PowerOutageMemory = 'restore';
                break;
            default:
            $this->SendDebug('Invalid Power Outage Memory', $value, 0);
                return;
        }

        $Payload['power_outage_memory'] = $PowerOutageMemory;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function StateWindow(bool $value)
    {
        $Payload['state'] = $this->OpenClose($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setColorMode(int $mode)
    {
        $Payload['color_mode'] = strval($mode);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setCurrentHeatingSetpoint(float $value)
    {
        $Payload['current_heating_setpoint'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setOccupiedHeatingSetpoint(float $value)
    {
        $Payload['occupied_heating_setpoint'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setThermostatPreset(int $value)
    {
        switch ($value) {
            case 1:
                $preset = 'manual';
                break;
            case 2:
                $preset = 'boost';
                break;
            case 3:
                $preset = 'complex';
                break;
            case 4:
                $preset = 'comfort';
                break;
            case 5:
                $preset = 'eco';
                break;
            default:
            $this->SendDebug('Invalid Set Thermostat Preset', $value, 0);
                return;
        }

        $Payload['preset'] = $preset;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setThermostatAwayMode(bool $Value)
    {
        $Payload['away_mode'] = $this->OnOff($Value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setThermostatAwayPresetDays(int $Value)
    {
        $Payload['away_preset_days'] = strval($Value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setThermostatBoostTime(int $Value)
    {
        $Payload['boost_time'] = strval($Value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setColor(int $color, string $mode)
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

    private function setSensitivity(int $value)
    {
        $Payload['sensitivity'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setOccupancyTimeout(int $value)
    {
        $Payload['occupancy_timeout'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setMotionSensitivity(int $value)
    {
        switch ($value) {
            case 1:
                $Payload['motion_sensitivity'] = 'medium';
                break;
            case 2:
                $Payload['motion_sensitivity'] = 'low';
                break;
            case 3:
                $Payload['motion_sensitivity'] = 'high';
                break;
            default:
                return;
            }
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setSystemMode(int $value)
    {
        switch ($value) {
            case 1: //off
                $Payload['system_mode'] = 'off';
                break;
            case 2: //auto
                $Payload['system_mode'] = 'auto';
                break;
            case 3: //heat
                $Payload['system_mode'] = 'heat';
                break;
            case 4: //cool
                $Payload['system_mode'] = 'cool';
                break;
            default:
                $this->SendDebug('Invalid System Mode', $value, 0);
                return;
        }

        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
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
    private function OpenClose(bool $Value)
    {
        switch ($Value) {
            case true:
                $state = 'OPEN';
                break;
            case false:
                $state = 'CLOSE';
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