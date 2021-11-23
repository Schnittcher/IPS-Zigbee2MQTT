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
            case 'Z2M_BrightnessRGB':
                $this->setBrightnessRGB($Value);
                break;
            case 'Z2M_BrightnessWhite':
                $this->setBrightnessWhite($Value);
                break;
            case 'Z2M_ColorTemp':
                $this->setColorTemperature($Value);
                break;
            case 'Z2M_ColorTempKelvin':
                $this->setColorTemperature(intval(round(1000000 / $Value, 0)));
                break;
            case 'Z2M_ColorTempRGB':
                $this->setColorTemperature($Value, 'color_temp_rgb');
                break;
            case 'Z2M_State':
                $this->SwitchMode($Value);
                break;
            case 'Z2M_RunningState':
                $this->setRunningState($Value);
                break;
            case 'Z2M_StateRGB':
                $this->SwitchModeRGB($Value);
                break;
            case 'Z2M_StateWhite':
                $this->SwitchModeWhite($Value);
                break;
            case 'Z2M_LEDDisabledNight':
                $this->SwitchModeLEDDisabledNight($Value);
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
            case 'Z2M_WindowDetection':
                $this->setWindowDetection($Value);
                break;
            case 'Z2M_ChildLock':
                $this->setChildLock($Value);
                break;
            case'Z2M_PowerOutageMemory':
                $this->setPowerOutageMemory(strval($Value));
                break;
            case'Z2M_PowerOnBehavior':
                $this->setPowerOnBehavior(strval($Value));
                break;
            case'Z2M_AutoOff':
                $this->setAutoOff($this->OnOff($Value));
                break;
            case 'Z2M_StateWindow':
                $this->StateWindow($Value);
                break;
            case 'Z2M_Sensitivity':
                $this->setSensitivity($Value);
                break;
            case 'Z2M_RadarSensitivity':
                $this->setRadarSensitivity($Value);
                break;
            case 'Z2M_RadarScene':
                $this->setRadarScene($Value);
                break;
            case 'Z2M_BoostHeating':
                $this->setBoostHeating($Value);
                break;
            case 'Z2M_Force':
                $this->setForce($Value);
                break;
            case 'Z2M_Moving':
                $this->setMoving($Value);
                break;
            case 'Z2M_TRVMode':
                $this->setTRVMode($Value);
                break;
            case 'Z2M_Calibration':
                $this->setCalibration($Value);
                break;
            case 'motor_reversal':
                $this->setMotorReversal($Value);
                break;
            case 'Z2M_CurrentHeatingSetpoint':
                $this->setCurrentHeatingSetpoint($Value);
                break;
            case 'Z2M_CurrentHeatingSetpointAuto':
                $this->setCurrentHeatingSetpointAuto($Value);
                break;
            case 'Z2M_OccupiedHeatingSetpoint':
                $this->setOccupiedHeatingSetpoint($Value);
                break;
            case 'Z2M_LocalTemperatureCalibration':
                $this->setLocalTemperatureCalibration($Value);
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
            case 'Z2M_ColorRGB':
                $this->SendDebug(__FUNCTION__ . ' :: Color RGB', $Value, 0);
                $this->setColor($Value, 'cie', 'color_rgb');
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
            case 'Z2M_OverloadProtection':
                $this->setOverloadProtection($Value);
                break;
            default:
                $this->SendDebug('Request Action', 'No Action defined: ' . $Ident, 0);
                break;
        }
    }

    public function getDeviceInfo()
    {
        $this->Command('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/getDevice', $this->ReadPropertyString('MQTTTopic'));
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
                        $this->SetValue('Z2M_Status', true);
                    } else {
                        $this->SetValue('Z2M_Status', false);
                    }
                }
            }

            $Payload = json_decode($Buffer->Payload, true);
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/deviceInfo', $Buffer->Topic)) {
                if (is_array($Payload['_definition'])) {
                    if (is_array($Payload['_definition']['exposes'])) {
                        $this->mapExposesToVariables($Payload['_definition']['exposes']);
                    }
                }
            }

            $Payload = json_decode($Buffer->Payload);
            if (is_object($Payload)) {
                if (property_exists($Payload, 'temperature')) {
                    $this->SetValue('Z2M_Temperature', $Payload->temperature);
                }
                if (property_exists($Payload, 'local_temperature')) {
                    $this->SetValue('Z2M_LocalTemperature', $Payload->local_temperature);
                }
                if (property_exists($Payload, 'local_temperature_calibration')) {
                    $this->SetValue('Z2M_LocalTemperatureCalibration', $Payload->local_temperature_calibration);
                }
                if (property_exists($Payload, 'max_temperature')) {
                    $this->SetValue('Z2M_MaxTemperature', $Payload->max_temperature);
                }

                if (property_exists($Payload, 'min_temperature')) {
                    $this->SetValue('Z2M_MinTemperature', $Payload->min_temperature);
                }

                if (property_exists($Payload, 'preset')) {
                    $this->SetValue('Z2M_Preset', $Payload->preset);
                }

                if (property_exists($Payload, 'away_mode')) {
                    switch ($Payload->away_mode) {
                    case 'ON':
                        $this->SetValue('Z2M_AwayMode', true);
                        break;
                    case 'OFF':
                        $this->SetValue('Z2M_AwayMode', false);
                        break;
                    default:
                        $this->SendDebug('SetValue AwayMode', 'Invalid Value: ' . $Payload->away_mode, 0);
                        break;
                    }
                }

                if (property_exists($Payload, 'away_preset_days')) {
                    $this->SetValue('Z2M_AwayPresetDays', $Payload->away_preset_days);
                }

                if (property_exists($Payload, 'away_preset_temperature')) {
                    $this->SetValue('Z2M_AwayPresetTemperature', $Payload->away_preset_temperature);
                }

                if (property_exists($Payload, 'boost_time')) {
                    $this->SetValue('Z2M_BoostTime', $Payload->boost_time);
                }

                if (property_exists($Payload, 'comfort_temperature')) {
                    $this->SetValue('Z2M_ComfortTemperature', $Payload->comfort_temperature);
                }

                if (property_exists($Payload, 'eco_temperature')) {
                    $this->SetValue('Z2M_EcoTemperature', $Payload->eco_temperature);
                }

                if (property_exists($Payload, 'current_heating_setpoint')) {
                    $this->SetValue('Z2M_CurrentHeatingSetpoint', $Payload->current_heating_setpoint);
                }

                if (property_exists($Payload, 'current_heating_setpoint_auto')) {
                    $this->SetValue('Z2M_CurrentHeatingSetpoint', $Payload->current_heating_setpoint_auto);
                }
                if (property_exists($Payload, 'occupied_heating_setpoint')) {
                    $this->SetValue('Z2M_OccupiedHeatingSetpoint', $Payload->occupied_heating_setpoint);
                }
                if (property_exists($Payload, 'pi_heating_demand')) {
                    $this->SetValue('Z2M_Pi_Heating_Demand', $Payload->pi_heating_demand);
                }
                if (property_exists($Payload, 'system_mode')) {
                    $this->SetValue('Z2M_SystemMode', $Payload->system_mode);
                }
                if (property_exists($Payload, 'running_state')) {
                    $this->SetValue('Z2M_RunningState', $Payload->running_state);
                }
                if (property_exists($Payload, 'state_left')) {
                    $this->RegisterVariableString('Z2M_StateLeft', $this->Translate('State Left'), '');
                    $this->SetValue('Z2M_StateLeft', $Payload->state_left);
                }
                if (property_exists($Payload, 'state_right')) {
                    $this->RegisterVariableString('Z2M_StateRight', $this->Translate('State Right'), '');
                    $this->SetValue('Z2M_StateRight', $Payload->state_right);
                }
                if (property_exists($Payload, 'linkquality')) {
                    $this->SetValue('Z2M_Linkquality', $Payload->linkquality);
                }
                if (property_exists($Payload, 'valve_position')) {
                    $this->SetValue('Z2M_ValvePosition', $Payload->valve_position);
                }

                if (property_exists($Payload, 'humidity')) {
                    $this->SetValue('Z2M_Humidity', $Payload->humidity);
                }
                if (property_exists($Payload, 'pressure')) {
                    $this->SetValue('Z2M_Pressure', $Payload->pressure);
                }
                if (property_exists($Payload, 'battery')) {
                    $this->SetValue('Z2M_Battery', $Payload->battery);
                }
                //Da Millivolt und Volt mit dem selben Topic verschickt wird
                if (property_exists($Payload, 'voltage')) {
                    if ($Payload->voltage > 400) { //Es gibt wahrscheinlich keine Zigbee Geräte mit über 400 Volt
                        $this->SetValue('Z2M_Voltage', $Payload->voltage / 1000);
                    } else {
                        $this->SetValue('Z2M_Voltage', $Payload->voltage);
                    }
                }
                if (property_exists($Payload, 'current')) {
                    $this->SetValue('Z2M_Current', $Payload->current);
                }
                if (property_exists($Payload, 'action')) {
                    $this->SetValue('Z2M_Action', $Payload->action);
                }
                if (property_exists($Payload, 'click')) {
                    $this->RegisterVariableString('Z2M_Click', $this->Translate('Click'), '');
                    $this->SetValue('Z2M_Click', $Payload->click);
                }
                if (property_exists($Payload, 'brightness')) {
                    $this->SetValue('Z2M_Brightness', $Payload->brightness);
                }
                if (property_exists($Payload, 'brightness_rgb')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: brightness_rgb');
                    //$this->RegisterVariableInteger('Z2M_BrightnessRGB', $this->Translate('Brightness RGB'), 'Z2M.Intensity.254');
                    //$this->EnableAction('Z2M_BrightnessRGB');
                    //$this->SetValue('Z2M_BrightnessRGB', $Payload->brightness_rgb);
                }
                if (property_exists($Payload, 'brightness_white')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: brightness_white');
                    //$this->RegisterVariableInteger('Z2M_BrightnessWhite', $this->Translate('Brightness White'), 'Z2M.Intensity.254');
                    //$this->EnableAction('Z2M_BrightnessWhite');
                    //$this->SetValue('Z2M_BrightnessWhite', $Payload->brightness_white);
                }
                if (property_exists($Payload, 'position')) {
                    $this->SetValue('Z2M_Position', $Payload->position);
                }
                if (property_exists($Payload, 'motor_speed')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: motor_speed');
                    //$this->RegisterVariableInteger('Z2M_MotorSpeed', $this->Translate('Motor Speed'), '~Intensity.255');
                    //$this->EnableAction('Z2M_MotorSpeed');
                    //$this->SetValue('Z2M_MotorSpeed', $Payload->motor_speed);
                }
                if (property_exists($Payload, 'occupancy')) {
                    $this->SetValue('Z2M_Occupancy', $Payload->occupancy);
                }
                if (property_exists($Payload, 'occupancy_timeout')) {
                    $this->SetValue('Z2M_OccupancyTimeout', $Payload->occupancy_timeout);
                }
                if (property_exists($Payload, 'motion_sensitivity')) {
                    $this->SetValue('Z2M_MotionSensitivity', $Payload->motion_sensitivity);
                }
                if (property_exists($Payload, 'presence')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: presence');
                    //$this->RegisterVariableBoolean('Z2M_Presence', $this->Translate('Presence'), '~Presence');
                    //$this->SetValue('Z2M_Presence', $Payload->presence);
                }
                if (property_exists($Payload, 'motion')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: motion');
                    //$this->RegisterVariableBoolean('Z2M_Motion', $this->Translate('Motion'), '~Motion');
                    //$this->SetValue('Z2M_Motion', $Payload->motion);
                }
                if (property_exists($Payload, 'motion_state')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: motion_state');
                    //$this->RegisterVariableBoolean('Z2M_Motion_State', $this->Translate('Motion State'), '~Motion');
                    //$this->SetValue('Z2M_Motion_State', $Payload->motion_state);
                }
                if (property_exists($Payload, 'motion_direction')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: motion_direction');
                    //$this->RegisterVariableString('Z2M_Motion_Direction', $this->Translate('Motion Direction'), '');
                    //$this->SetValue('Z2M_Motion_Direction', $Payload->motion_direction);
                }
                if (property_exists($Payload, 'scene')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: scene');
                    //$this->RegisterVariableString('Z2M_Scene', $this->Translate('Scene'), '');
                    //$this->SetValue('Z2M_Scene', $Payload->scene);
                }
                if (property_exists($Payload, 'motion_speed')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: motion_speed');
                    //$this->RegisterVariableInteger('Z2M_Motion_Speed', $this->Translate('Motionspeed'), '');
                    //$this->SetValue('Z2M_Motion_Speed', $Payload->motion_speed);
                }
                if (property_exists($Payload, 'radar_sensitivity')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: radar_sensitivity');
                    //$this->RegisterVariableInteger('Z2M_Radar_Sensitivity', $this->Translate('Radar Sensitivity'), 'Z2M.RadarSensitivity');
                    //$this->EnableAction('Z2M_Radar_Sensitivity');
                    //$this->SetValue('Z2M_Radar_Sensitivity', $Payload->radar_sensitivity);
                }
                if (property_exists($Payload, 'radar_scene')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: radar_scene');
                    //$this->RegisterVariableString('Z2M_Radar_Scene', $this->Translate('Radar Scene'), 'Z2M.RadarScene');
                    //$this->EnableAction('Z2M_Radar_Scene');
                    //$this->SetValue('Z2M_Radar_Scene', $Payload->radar_scene);
                }
                if (property_exists($Payload, 'illuminance')) {
                    $this->SetValue('Z2M_Illuminance', $Payload->illuminance);
                }
                if (property_exists($Payload, 'illuminance_lux')) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux', $Payload->illuminance_lux);
                    }
                }
                if (property_exists($Payload, 'strength')) {
                    $this->SetValue('Z2M_Strength', $Payload->strength);
                }
                if (property_exists($Payload, 'water_leak')) {
                    $this->SetValue('Z2M_WaterLeak', $Payload->water_leak);
                }
                if (property_exists($Payload, 'contact')) {
                    $this->SetValue('Z2M_Contact', $Payload->contact);
                }
                if (property_exists($Payload, 'carbon_monoxide')) {
                    $this->SetValue('Z2M_CarbonMonoxide', $Payload->carbon_monoxide);
                }
                if (property_exists($Payload, 'smoke')) {
                    $this->SetValue('Z2M_Smoke', $Payload->smoke);
                }
                if (property_exists($Payload, 'smoke_density')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: smoke_density');
                    //$this->RegisterVariableInteger('Z2M_SmokeDensity', $this->Translate('Smoke Density'), '');
                    //$this->SetValue('Z2M_SmokeDensity', $Payload->smoke_density);
                }
                if (property_exists($Payload, 'tamper')) {
                    $this->SetValue('Z2M_Tamper', $Payload->tamper);
                }
                if (property_exists($Payload, 'enrolled')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: enrolled');
                    //$this->RegisterVariableBoolean('Z2M_Enrolled', $this->Translate('Enrolled'), '');
                    //$this->SetValue('Z2M_Enrolled', $Payload->enrolled);
                }
                if (property_exists($Payload, 'restore_reports')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: restore_reports');
                    //$this->RegisterVariableBoolean('Z2M_RestoreReports', $this->Translate('Restore Reports'), '');
                    //$this->SetValue('Z2M_RestoreReports', $Payload->restore_reports);
                }
                if (property_exists($Payload, 'supervision_reports')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: supervision_reports');
                    //$this->RegisterVariableBoolean('Z2M_SupervisionReports', $this->Translate('Supervision Reports'), '');
                    //$this->SetValue('Z2M_SupervisionReports', $Payload->supervision_reports);
                }
                if (property_exists($Payload, 'trouble')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: trouble');
                    //$this->RegisterVariableBoolean('Z2M_Trouble', $this->Translate('Trouble'), '');
                    //$this->SetValue('Z2M_Trouble', $Payload->trouble);
                }
                if (property_exists($Payload, 'battery_low')) {
                    $this->SetValue('Z2M_Battery_Low', $Payload->battery_low);
                }
                if (property_exists($Payload, 'action_angle')) {
                    $this->SetValue('Z2M_ActionAngle', $Payload->action_angle);
                }
                if (property_exists($Payload, 'angle_x')) {
                    $this->SetValue('Z2M_Angle_X', $Payload->angle_x);
                }
                if (property_exists($Payload, 'angle_y')) {
                    $this->SetValue('Z2M_Angle_Y', $Payload->angle_y);
                }
                if (property_exists($Payload, 'angle_x_absolute')) {
                    $this->LogMessage('Please Contact Module Developer. Undefined Variable angle_x_absolute');
                    //$this->RegisterVariableFloat('Z2M_Angle_X_Absolute', $this->Translate('Angle_X_Absolute'), '');
                    //$this->SetValue('Z2M_Angle_X_Absolute', $Payload->angle_x_absolute);
                }
                if (property_exists($Payload, 'angle_y_absolute')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: angle_y_absolute');
                    //$this->RegisterVariableFloat('Z2M_Angle_Y_Absolute', $this->Translate('Angle_Y_Absolute'), '');
                    //$this->SetValue('Z2M_Angle_Y_Absolute', $Payload->angle_y_absolute);
                }
                if (property_exists($Payload, 'angle_z')) {
                    $this->SetValue('Z2M_Angle_Z', $Payload->angle_z);
                }
                if (property_exists($Payload, 'action_from_side')) {
                    $this->SetValue('Z2M_ActionFromSide', $Payload->action_from_side);
                }
                if (property_exists($Payload, 'action_side')) {
                    $this->SetValue('Z2M_ActionSide', $Payload->action_side);
                }
                if (property_exists($Payload, 'action_to_side')) {
                    $this->SetValue('Z2M_ActionToSide', $Payload->action_to_side);
                }
                if (property_exists($Payload, 'power')) {
                    $this->SetValue('Z2M_Power', $Payload->power);
                }
                if (property_exists($Payload, 'consumer_connected')) {
                    $this->SetValue('Z2M_Consumer_Connected', $Payload->consumer_connected);
                }
                if (property_exists($Payload, 'consumption')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: consumption');
                    //$this->RegisterVariableFloat('Z2M_Consumption', $this->Translate('Consumption'), '~Electricity');
                    //$this->SetValue('Z2M_Consumption', $Payload->consumption);
                }
                if (property_exists($Payload, 'energy')) {
                    $this->SetValue('Z2M_Energy', $Payload->energy);
                }
                if (property_exists($Payload, 'overload_protection')) {
                    $this->SetValue('Z2M_OverloadProtection', $Payload->overload_protection);
                }
                if (property_exists($Payload, 'duration')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: duration');
                    //$this->RegisterVariableFloat('Z2M_Duration', $this->Translate('Duration'), '');
                    //$this->SetValue('Z2M_Duration', $Payload->duration);
                }
                if (property_exists($Payload, 'counter')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: counter');
                    //$this->RegisterVariableFloat('Z2M_Counter', $this->Translate('Counter'), '');
                    //$this->SetValue('Z2M_Counter', $Payload->counter);
                }
                if (property_exists($Payload, 'color')) {
                    $this->SendDebug(__FUNCTION__ . ' Color', $Payload->color->x, 0);
                    if (property_exists($Payload, 'brightness')) {
                        $RGBColor = ltrim($this->CIEToRGB($Payload->color->x, $Payload->color->y, $Payload->brightness), '#');
                    } else {
                        $RGBColor = ltrim($this->CIEToRGB($Payload->color->x, $Payload->color->y), '#');
                    }
                    $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                    $this->SetValue('Z2M_Color', hexdec(($RGBColor)));
                }

                if (property_exists($Payload, 'color_rgb')) {
                    $this->SendDebug(__FUNCTION__ . ':: Color X', $Payload->color_rgb->x, 0);
                    $this->SendDebug(__FUNCTION__ . ':: Color Y', $Payload->color_rgb->y, 0);
                    if (property_exists($Payload, 'brightness_rgb')) {
                        $RGBColor = ltrim($this->CIEToRGB($Payload->color_rgb->x, $Payload->color_rgb->y, $Payload->brightness_rgb), '#');
                    } else {
                        $RGBColor = ltrim($this->CIEToRGB($Payload->color_rgb->x, $Payload->color_rgb->y), '#');
                    }
                    $this->SendDebug(__FUNCTION__ . ' Color :: RGB HEX', $RGBColor, 0);
                    $this->LogMessage('Please contact module developer. Undefined variable: color_rgb');
                    //$this->RegisterVariableInteger('Z2M_ColorRGB', $this->Translate('Color'), 'HexColor');
                    //$this->EnableAction('Z2M_ColorRGB');
                    $this->SetValue('Z2M_ColorRGB', hexdec(($RGBColor)));
                }

                if (property_exists($Payload, 'sensitivity')) {
                    $this->SetValue('Z2M_Sensitivity', $Payload->sensitivity);
                }
                if (property_exists($Payload, 'color_temp')) {
                    $this->SetValue('Z2M_ColorTemp', $Payload->color_temp);
                    //Color Temperature in Kelvin
                    if ($Payload->color_temp > 0) {
                        $this->SetValue('Z2M_ColorTempKelvin', 1000000 / $Payload->color_temp); //Convert to Kelvin
                    }
                }

                if (property_exists($Payload, 'color_temp_rgb')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: color_temp_rgb');
                    //$this->RegisterVariableInteger('Z2M_ColorTempRGB', $this->Translate('Color Temperature RGB'), 'Z2M.ColorTemperature');
                    //$this->EnableAction('Z2M_ColorTempRGB');
                    //$this->SetValue('Z2M_ColorTempRGB', $Payload->color_temp_rgb);
                }

                if (property_exists($Payload, 'state')) {
                    switch ($Payload->state) {
                        case 'ON':
                            $this->SetValue('Z2M_State', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_State', false);
                            break;
                        case 'OPEN':
                            $this->LogMessage('Please contact module developer. Undefined variable: Z2M_StateWindow');
                            //$this->RegisterVariableBoolean('Z2M_StateWindow', $this->Translate('State'), '~Window');
                            $this->EnableAction('Z2M_StateWindow');
                            $this->SetValue('Z2M_StateWindow', true);
                            break;
                        case 'CLOSE':
                            $this->LogMessage('Please contact module developer. Undefined variable: Z2M_StateWindow');
                            //$this->RegisterVariableBoolean('Z2M_StateWindow', $this->Translate('State'), '~Window');
                            //$this->EnableAction('Z2M_StateWindow');
                            //$this->SetValue('Z2M_StateWindow', false);
                            break;
                        default:
                        $this->SendDebug('State', 'Undefined State: ' . $Payload->state, 0);
                        break;
                        }
                }
                if (property_exists($Payload, 'led_disabled_night')) {
                    $this->SetValue('Z2M_LEDDisabledNight', $Payload->led_disabled_night);
                }
                if (property_exists($Payload, 'state_rgb')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: state_rgb');
                    switch ($Payload->state_rgb) {
                        case 'ON':
                            //$this->RegisterVariableBoolean('Z2M_StateRGB', $this->Translate('State RGB'), '~Switch');
                            //$this->EnableAction('Z2M_StateRGB');
                            //$this->SetValue('Z2M_StateRGB', true);
                            break;
                        case 'OFF':
                            //$this->RegisterVariableBoolean('Z2M_StateRGB', $this->Translate('State RGB'), '~Switch');
                            //$this->EnableAction('Z2M_StateRGB');
                            //$this->SetValue('Z2M_StateRGB', false);
                            break;
                        default:
                        $this->SendDebug('State RGB', 'Undefined State: ' . $Payload->state_rgb, 0);
                        break;
                        }
                }

                if (property_exists($Payload, 'state_white')) {
                    $this->LogMessage('Please contact module developer. Undefined variable: state_white');
                    switch ($Payload->state_white) {
                        case 'ON':
                            //$this->RegisterVariableBoolean('Z2M_StateWhite', $this->Translate('State White'), '~Switch');
                            //$this->EnableAction('Z2M_StateWhite');
                            //$this->SetValue('Z2M_StateWhite', true);
                            break;
                        case 'OFF':
                            //$this->RegisterVariableBoolean('Z2M_StateWhite', $this->Translate('State White'), '~Switch');
                            //$this->EnableAction('Z2M_StateWhite');
                            //$this->SetValue('Z2M_StateWhite', false);
                            break;
                        default:
                        $this->SendDebug('State White', 'Undefined State: ' . $Payload->state_white, 0);
                        break;
                        }
                }

                if (property_exists($Payload, 'power_outage_memory')) {
                    $this->SetValue('Z2M_PowerOutageMemory', $Payload->power_outage_memory);
                }
                if (property_exists($Payload, 'power_on_behavior')) {
                    $this->SetValue('Z2M_PowerOnBehavior', $Payload->power_on_behavior);
                }
                if (property_exists($Payload, 'state_l1')) {
                    switch ($Payload->state_l1) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel1', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel1', false);
                            break;
                        default:
                            $this->SendDebug('State 1', 'Undefined State 1: ' . $Payload->state_l1, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'state_l2')) {
                    switch ($Payload->state_l2) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel2', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel2', false);
                            break;
                        default:
                            $this->SendDebug('State 2', 'Undefined State 2: ' . $Payload->state_l2, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'state_l3')) {
                    switch ($Payload->state_l3) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel3', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel3', false);
                            break;
                        default:
                            $this->SendDebug('State 3', 'Undefined State 3: ' . $Payload->state_l3, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'state_l4')) {
                    switch ($Payload->state_l4) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel4', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel4', false);
                            break;
                        default:
                            $this->SendDebug('State 4', 'Undefined State 4: ' . $Payload->state_l4, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'state_l5')) {
                    switch ($Payload->state_l5) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel5', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel5', false);
                            break;
                        default:
                            $this->SendDebug('State 5', 'Undefined State 5: ' . $Payload->state_l5, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'window_detection')) {
                    switch ($Payload->window_detection) {
                        case 'ON':
                            $this->SetValue('Z2M_WindowDetection', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_WindowDetection', false);
                            break;
                        default:
                            $this->SendDebug('Window Detection', 'Undefined State: ' . $Payload->window_detection, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'child_lock')) {
                    switch ($Payload->child_lock) {
                        case 'LOCK':
                            $this->SetValue('Z2M_ChildLock', true);
                            break;
                        case 'UNLOCK':
                            $this->SetValue('Z2M_ChildLock', false);
                            break;
                        default:
                            $this->SendDebug('Child Lock', 'Undefined State: ' . $Payload->child_lock, 0);
                            break;
                    }
                }
                if (property_exists($Payload, 'update_available')) {
                    //Bleibt hier. gibt es nicht als Expose
                    $this->RegisterVariableBoolean('Z2M_Update', $this->Translate('Update'), '');
                    $this->SetValue('Z2M_Update', $Payload->update_available);
                }
                if (property_exists($Payload, 'voc')) {
                    $this->SetValue('Z2M_VOC', $Payload->voc);
                }
                if (property_exists($Payload, 'co2')) {
                    $this->SetValue('Z2M_CO2', $Payload->co2);
                }
                if (property_exists($Payload, 'formaldehyd')) {
                    $this->SetValue('Z2M_Formaldehyd', $Payload->formaldehyd);
                }
                if (property_exists($Payload, 'force')) {
                    $this->SetValue('Z2M_Force', $Payload->force);
                }
                if (property_exists($Payload, 'moving')) {
                    $this->SetValue('Z2M_Moving', $Payload->moving);
                }
                if (property_exists($Payload, 'trv_mode')) {
                    $this->SetValue('Z2M_TRVMode', $Payload->Z2M_TRVMode);
                }
                if (property_exists($Payload, 'calibration')) {
                    $this->SetValue('Z2M_Calibration', $Payload->calibration);
                }
                if (property_exists($Payload, 'motor_reversal')) {
                    $this->SetValue('Z2M_MotorReversal', $Payload->motor_reversal);
                }
                if (property_exists($Payload, 'calibration_time')) {
                    $this->SetValue('Z2M_CalibrationTime', $Payload->calibration_time);
                }
            }
        }
    }

    protected function createVariableProfiles()
    {

        /**
         * if (!IPS_VariableProfileExists('Z2M.Sensitivity')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Medium'), '', -1];
         * $Associations[] = [2, $this->Translate('Low'), '', -1];
         * $Associations[] = [3, $this->Translate('High'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.Sensitivity', '', '', '', $Associations);
         * }
         */
        /**
         * if (!IPS_VariableProfileExists('Z2M.Intensity.254')) {
         * $this->RegisterProfileInteger('Z2M.Intensity.254', 'Intensity', '', '%', 0, 254, 1);
         * }
         */
        if (!IPS_VariableProfileExists('Z2M.RadarSensitivity')) {
            $this->RegisterProfileInteger('Z2M.RadarSensitivity', 'Intensity', '', '', 0, 10, 1);
        }

        /**
         * if (!IPS_VariableProfileExists('Z2M.ColorTemperatureKelvin')) {
         * $this->RegisterProfileInteger('Z2M.ColorTemperatureKelvin', 'Intensity', '', '', 2000, 6535, 1);
         * }
         */
        if (!IPS_VariableProfileExists('Z2M.RadarScene')) {
            $this->RegisterProfileStringEx('Z2M.RadarScene', 'Menu', '', '', [
                ['default', $this->Translate('Default'), '', 0xFFFFFF],
                ['area', $this->Translate('Area'), '', 0x0000FF],
                ['toilet', $this->Translate('Toilet'), '', 0x0000FF],
                ['bedroom', $this->Translate('Bedroom'), '', 0x0000FF],
                ['parlour', $this->Translate('Parlour'), '', 0x0000FF],
                ['office', $this->Translate('Office'), '', 0x0000FF],
                ['hotel', $this->Translate('Hotel'), '', 0x0000FF]
            ]);
        }

        /**
         * if (!IPS_VariableProfileExists('Z2M.SystemMode')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Off'), '', -1];
         * $Associations[] = [2, $this->Translate('Auto'), '', -1];
         * $Associations[] = [3, $this->Translate('Heat'), '', -1];
         * $Associations[] = [4, $this->Translate('Cool'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.SystemMode', '', '', '', $Associations);
         * }
         */
        /**
         * if (!IPS_VariableProfileExists('Z2M.PowerOutageMemory')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Off'), '', -1];
         * $Associations[] = [2, $this->Translate('On'), '', -1];
         * $Associations[] = [3, $this->Translate('Restore'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.PowerOutageMemory', '', '', '', $Associations);
         * }
         */

        /**
         * if (!IPS_VariableProfileExists('Z2M.ThermostatPreset')) {
         * $Associations = [];
         * $Associations[] = [1, $this->Translate('Manual'), '', -1];
         * $Associations[] = [2, $this->Translate('Boost'), '', -1];
         * $Associations[] = [3, $this->Translate('Complexes Program'), '', -1];
         * $Associations[] = [4, $this->Translate('Comfort'), '', -1];
         * $Associations[] = [5, $this->Translate('Eco'), '', -1];
         * $Associations[] = [6, $this->Translate('Heat'), '', -1];
         * $Associations[] = [7, $this->Translate('Schedule'), '', -1];
         * $Associations[] = [8, $this->Translate('Away'), '', -1];
         * $this->RegisterProfileIntegerEx('Z2M.ThermostatPreset', '', '', '', $Associations);
         * }
         */
        /**
         * if (!IPS_VariableProfileExists('Z2M.ColorTemperature')) {
         * IPS_CreateVariableProfile('Z2M.ColorTemperature', 1);
         * }
         * IPS_SetVariableProfileDigits('Z2M.ColorTemperature', 0);
         * IPS_SetVariableProfileIcon('Z2M.ColorTemperature', 'Bulb');
         * IPS_SetVariableProfileText('Z2M.ColorTemperature', '', ' Mired');
         * IPS_SetVariableProfileValues('Z2M.ColorTemperature', 50, 500, 1);
         */

        /**
         * if (!IPS_VariableProfileExists('Z2M.ConsumerConnected')) {
         * $this->RegisterProfileBooleanEx('Z2M.ConsumerConnected', 'Plug', '', '', [
         * [false, $this->Translate('not connected'),  '', 0xFF0000],
         * [true, $this->Translate('connected'),  '', 0x00FF00]
         * ]);
         * }
         */
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

    private function setBrightnessWhite(int $value)
    {
        $Payload['brightness_white'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setBrightnessRGB(int $value)
    {
        $Payload['brightness_rgb'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setColorTemperature(int $value, $z2mmode = 'color_temp')
    {
        if ($Z2MMode = 'color_temp') {
            $Payload['color_temp'] = strval($value);
        } elseif ($Z2MMode == 'color_temp_rgb') {
            $Payload['color_temp_rgb'] = strval($value);
        } else {
            return;
        }

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

    private function setWindowDetection(bool $value)
    {
        $Payload['window_detection'] = $this->OnOff($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setChildLock(bool $value)
    {
        $Payload['child_lock'] = $this->OnOff($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setRunningState(string $value)
    {
        $Payload['running_state'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function SwitchMode(bool $value)
    {
        $Payload['state'] = $this->OnOff($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function SwitchModeRGB(bool $value)
    {
        $Payload['state_rgb'] = $this->OnOff($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function SwitchModeWhite(bool $value)
    {
        $Payload['state_white'] = $this->OnOff($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function SwitchModeLEDDisabledNight(bool $value)
    {
        $Payload['led_disabled_night'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setPowerOutageMemory(string $value)
    {
        $Payload['power_outage_memory'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setPowerOnBehavior(string $value)
    {
        $Payload['power_on_behavior'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setAutoOff(string $Value)
    {
        $Payload['auto_off'] = $value;
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

    private function setForce(string $value)
    {
        $Payload['force'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setMoving(string $value)
    {
        $Payload['moving'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setTRVMode(string $value)
    {
        $Payload['trv_mode'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setCalibration($value)
    {
        $Payload['calibration'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setMotorReversal($value)
    {
        $Payload['motor_reversal'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setBoostHeating(bool $value)
    {
        $Payload['boost_heating'] = $this->OnOff($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setCurrentHeatingSetpoint(float $value)
    {
        $Payload['current_heating_setpoint'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setCurrentHeatingSetpointAuto(float $value)
    {
        $Payload['current_heating_setpoint_auto'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setOccupiedHeatingSetpoint(float $value)
    {
        $Payload['local_temperature_calibration'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setLocalTemperatureCalibration(float $value)
    {
        $Payload['occupied_heating_setpoint'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setOverloadProtection(int $Value)
    {
        $Payload['overload_protection'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setThermostatPreset(string $value)
    {
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

    private function setColor(int $color, string $mode, string $Z2MMode = 'color')
    {
        switch ($mode) {
            case 'cie':
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToCIE($RGB[0], $RGB[1], $RGB[2]);
                if ($Z2MMode = 'color') {
                    $Payload['color'] = $cie;
                } elseif ($Z2MMode == 'color_rgb') {
                    $Payload['color_rgb'] = $cie;
                } else {
                    return;
                }

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

    private function setRadarSensitivity(int $value)
    {
        $Payload['radar_sensitivity'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setRadarScene(string $value)
    {
        $Payload['radar_scene'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setOccupancyTimeout(int $value)
    {
        $Payload['occupancy_timeout'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setMotionSensitivity(string $value)
    {
        $Payload['motion_sensitivity'] = $value;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    private function setSystemMode(string $value)
    {
        $Payload['system_mode'] = $value;
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

    private function registerVariableProfile($expose)
    {
        $ProfileName = 'Z2M.' . $expose['name'];
        $tmpProfileName = '';

        switch ($expose['type']) {
            case 'binary':
                switch ($expose['property']) {
                    case 'consumer_connected':
                        if (!IPS_VariableProfileExists('Z2M.ConsumerConnected')) {
                            $this->RegisterProfileBooleanEx('Z2M.ConsumerConnected', 'Plug', '', '', [
                                [false, $this->Translate('not connected'),  '', 0xFF0000],
                                [true, $this->Translate('connected'),  '', 0x00FF00]
                            ]);
                        }
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                        break;

                }
                break;
            case 'enum':
                if (array_key_exists('values', $expose)) {
                    //Sortieren, damit der Hash auch dann passt, wenn die Values von Z2M in einer anderen Reihenfolge geliefert werden.
                    sort($expose['values']);
                    $tmpProfileName = implode('', $expose['values']);
                    $ProfileName .= '.';
                    $ProfileName .= dechex(crc32($tmpProfileName));
                    switch ($ProfileName) {
                        case 'Z2M.system_mode.3aabe70a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['auto', $this->Translate('Auto'), '', 0xFFA500],
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['off', $this->Translate('Off'), '', 0x000000]
                                ]);
                            }
                            break;
                        case'Z2M.preset.9fca219c':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0xFFFF00],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['boost', $this->Translate('Boost'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.running_state.8d38f7dc':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['idle', $this->Translate('Idle'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.effect.efbfc77e':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['blink', $this->Translate('Blink'), '', 0xFFFFFF],
                                    ['breathe', $this->Translate('Breathe'), '', 0x0000FF],
                                    ['okay', $this->Translate('Okay'), '', 0x0000FF],
                                    ['channel_change', $this->Translate('Channel Change'), '', 0x0000FF],
                                    ['finish_effect', $this->Translate('Finish Effect'), '', 0x0000FF],
                                    ['stop_effect', $this->Translate('Stop Effect'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.sensitivity.848c69b5':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.power_outage_memory.201b7646':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['restore', $this->Translate('Restore'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.power_on_behavior.b0d55aad':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['previous', $this->Translate('Previous'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.motion_sensitivity.848c69b5':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.force.85dac8d5':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['normal', $this->Translate('Normal'), '', 0x00FF00],
                                    ['open', $this->Translate('Open'), '', 0xFF8800],
                                    ['close', $this->Translate('Close'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.moving.fe5886c':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Move', '', '', [
                                    ['UP', $this->Translate('Up'), '', 0x00FF00],
                                    ['STOP', $this->Translate('Stop'), '', 0xFF8800],
                                    ['DOWN', $this->Translate('Down'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.trv_mode.4f5344cd':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Climate', '', '', [
                                    ['1', $this->Translate('Manual (Valve Position)'), '', 0x00FF00],
                                    ['2', $this->Translate('Automaitc'), '', 0xFF8800],
                                ]);
                            }
                            break;
                        default:
                        $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                        $this->SendDebug(__FUNCTION__ . ':: ProfileName Values', json_encode($expose['values']), 0);
                            return false;
                            break;
                    }
                }
                break;
            case 'numeric':
                switch ($expose['property']) {
                    case 'brightness':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', '%', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'color_temp':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Bulb', '', ' mired', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'valve_position':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' %', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'current_heating_setpoint_auto':
                    case 'current_heating_setpoint':
                    case 'occupied_heating_setpoint':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Temperature', '', ' °C', $expose['value_min'], $expose['value_max'], $expose['value_step'], 1);
                        }
                        break;
                    case 'linkquality':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' lqi', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'co2':
                    case 'voc':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Leaf', '', ' ' . $expose['unit'], 0, 0, 0);
                        }
                        break;
                    case 'occupancy_timeout':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $this->Translate('Seconds'), $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'boost_heating_countdown':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $this->Translate('Minutes'), 0, 0, 0);
                        }
                        break;
                    case 'boost_time':
                    case 'calibration_time':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $this->Translate('Seconds'), 0, 0, 0);
                        }
                        break;
                    case 'overload_protection':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Electricity', '', ' ' . $this->Translate('Watt'), $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                        $this->SendDebug(__FUNCTION__ . ':: ProfileName Values', json_encode($expose['values']), 0);
                        break;
                }

                break;
            default:
                # code...
                break;
        }
        return $ProfileName;
    }

    private function mapExposesToVariables(array $exposes)
    {
        $missedVariables = [];
        $missedVariables['light'] = [];
        $missedVariables['switch'] = [];

        $this->SendDebug(__FUNCTION__ . ':: All Exposes', json_encode($exposes), 0);

        foreach ($exposes as $key => $expose) {
            switch ($expose['type']) {
                case 'switch':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'state':
                                                $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                                                $this->EnableAction('Z2M_State');
                                            break;
                                        case 'state_l1':
                                            $this->RegisterVariableBoolean('Z2M_Statel1', $this->Translate('State 1'), '~Switch');
                                            $this->EnableAction('Z2M_Statel1');
                                            break;
                                        case 'state_l2':
                                            $this->RegisterVariableBoolean('Z2M_Statel2', $this->Translate('State 2'), '~Switch');
                                            $this->EnableAction('Z2M_Statel2');
                                            break;
                                        case 'state_l3':
                                            $this->RegisterVariableBoolean('Z2M_Statel3', $this->Translate('State 3'), '~Switch');
                                            $this->EnableAction('Z2M_Statel3');
                                            break;
                                        case 'state_l4':
                                            $this->RegisterVariableBoolean('Z2M_Statel4', $this->Translate('State 4'), '~Switch');
                                            $this->EnableAction('Z2M_Statel4');
                                            break;
                                        case 'state_l5':
                                            $this->RegisterVariableBoolean('Z2M_Statel5', $this->Translate('State 5'), '~Switch');
                                            $this->EnableAction('Z2M_Statel5');
                                            break;
                                        case 'window_detection':
                                            $this->RegisterVariableBoolean('Z2M_WindowDetection', $this->Translate('Window Detection'), '~Window');
                                            $this->EnableAction('Z2M_WindowDetection');
                                            break;
                                        default:
                                        // Default Switch binary
                                        $missedVariables['switch'][] = $feature;
                                        break;
                                    }
                                    break; //Switch binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        default:
                                        // Default Switch binary
                                        $missedVariables['switch'][] = $feature;
                                        break;
                                    }
                                    break; //Switch numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        default:
                                        // Default Switch enum
                                        $missedVariables['switch'][] = $feature;
                                        break;
                                    }
                                    break; //Switch enum break;
                            }
                        }
                    }
                    break; //Switch break

                case 'light':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'state':
                                            //Variable with Profile ~Switch
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                                            }
                                            break;
                                        default:
                                            // Default light binary
                                            $missedVariables['light'][] = $feature;
                                            break;
                                    }
                                    break; //Light binary break
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'brightness':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_Brightness', $this->Translate('Brightness'), $ProfileName);
                                                $this->EnableAction('Z2M_Brightness');
                                            }
                                            break;
                                        case 'color_temp':
                                            //Color Temperature Mired
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_ColorTemp', $this->Translate('Color Temperature'), $ProfileName);
                                                $this->EnableAction('Z2M_ColorTemp');
                                            }
                                            //TODO: Color Temp Presets

                                            // Color Temperature in Kelvin nicht automatisiert, deswegen nicht über die Funktion registerVariableProfile
                                            if (!IPS_VariableProfileExists('Z2M.ColorTemperatureKelvin')) {
                                                $this->RegisterProfileInteger('Z2M.ColorTemperatureKelvin', 'Intensity', '', '', 2000, 6535, 1);
                                            }
                                            $this->RegisterVariableInteger('Z2M_ColorTempKelvin', $this->Translate('Color Temperature Kelvin'), 'Z2M.ColorTemperatureKelvin');
                                            $this->EnableAction('Z2M_ColorTempKelvin');
                                            break;
                                        default:
                                        // Default light numeric
                                        $missedVariables['light'][] = $feature;
                                    }
                                    break; //Light numeric break
                                case 'composite':
                                    switch ($feature['property']) {
                                        case 'color':
                                            if ($feature['name'] == 'color_xy') {
                                                $this->RegisterVariableInteger('Z2M_Color', $this->Translate('Color'), 'HexColor');
                                                $this->EnableAction('Z2M_Color');
                                            }
                                            break;
                                        default:
                                            // Default light composite
                                            $missedVariables['light'][] = $feature;
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                    break; //Light break;
                case 'climate':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        default:
                                        // Default climate binary
                                        $missedVariables['climate'][] = $feature;
                                        break;
                                    }
                                    break; //Climate binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'current_heating_setpoint':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableFloat('Z2M_CurrentHeatingSetpoint', $this->Translate('Current Heating Setpoint'), $ProfileName);
                                                $this->EnableAction('Z2M_CurrentHeatingSetpoint');
                                            }
                                            break;
                                        case 'local_temperature':
                                            $this->RegisterVariableFloat('Z2M_LocalTemperature', $this->Translate('Local Temperature'), '~Temperature');
                                            break;
                                        case 'local_temperature_calibration':
                                            $this->RegisterVariableFloat('Z2M_LocalTemperatureCalibration', $this->Translate('Local Temperature Calibration'), '~Temperature');
                                            $this->EnableAction('Z2M_LocalTemperatureCalibration');
                                            break;
                                        case 'occupied_heating_setpoint':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableFloat('Z2M_OccupiedHeatingSetpoint', $this->Translate('Occupied Heating Setpoint'), $ProfileName);
                                                $this->EnableAction('Z2M_OccupiedHeatingSetpoint');
                                            }
                                            break;
                                        case 'pi_heating_demand':
                                            $this->RegisterVariableInteger('Z2M_Pi_Heating_Demand', $this->Translate('Valve Position (Heating Demand)'), '~Intensity.100');
                                            break;
                                        default:
                                        // Default Climate binary
                                        $missedVariables['climate'][] = $feature;
                                        break;
                                    }
                                    break; //Climate numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        case 'system_mode':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_SystemMode', $this->Translate('Mode'), $ProfileName);
                                                $this->EnableAction('Z2M_SystemMode');
                                            }
                                            break;
                                        case 'preset':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_Preset', $this->Translate('Preset'), $ProfileName);
                                                $this->EnableAction('Z2M_Preset');
                                            }
                                            break;
                                        case 'running_state':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_RunningState', $this->Translate('Running State'), $ProfileName);
                                                $this->EnableAction('Z2M_RunningState');
                                            }
                                            break;
                                        default:
                                        // Default Climate enum
                                        $missedVariables['climate'][] = $feature;
                                        break;
                                    }
                                    break; //Climate enum break;
                            }
                        }
                    }
                    break; //Climate break
                case 'lock':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'child_lock':
                                            $this->RegisterVariableBoolean('Z2M_ChildLock', $this->Translate('Child Lock'), '~Switch');
                                            $this->EnableAction('Z2M_ChildLock');
                                            break;
                                        default:
                                        // Default lock binary
                                        $missedVariables['lock'][] = $feature;
                                        break;
                                    }
                                break; //Lock binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        default:
                                        // Default lock binary
                                        $missedVariables['lock'][] = $feature;
                                        break;
                                    }
                                    break; //Lock numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        default:
                                        // Default lock enum
                                        $missedVariables['lock'][] = $feature;
                                        break;
                                    }
                                    break; //Lock enum break;
                            }
                        }
                    }
                    break; //Lock break
                case 'binary':
                    switch ($expose['property']) {
                        case 'state':
                            //Variable with Profile ~Switch
                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                            }
                            break;
                        case 'occupancy':
                            $this->RegisterVariableBoolean('Z2M_Occupancy', $this->Translate('Occupancy'), '~Motion');
                        break;
                        case 'battery_low':
                            $this->RegisterVariableBoolean('Z2M_Battery_Low', $this->Translate('Battery Low'), '~Battery');
                            break;
                        case 'tamper':
                            $this->RegisterVariableBoolean('Z2M_Tamper', $this->Translate('Tamper'), '~Alert');
                            break;
                        case 'water_leak':
                            $this->RegisterVariableBoolean('Z2M_WaterLeak', $this->Translate('Water Leak'), '~Alert');
                            break;
                        case 'contact':
                            $this->RegisterVariableBoolean('Z2M_Contact', $this->Translate('Contact'), '~Window.Reversed');
                            break;
                        case 'window':
                            $this->RegisterVariableBoolean('Z2M_Window', $this->Translate('Window'), '~Window.Reversed');
                            break;
                        case 'smoke':
                            $this->RegisterVariableBoolean('Z2M_Smoke', $this->Translate('Smoke'), '~Alert');
                            break;
                        case 'carbon_monoxide':
                            $this->RegisterVariableBoolean('Z2M_CarbonMonoxide', $this->Translate('Carbon Monoxide'), '~Alert');
                            break;
                        case 'heating':
                            $this->RegisterVariableBoolean('Z2M_Heating', $this->Translate('Heating'), '~Switch');
                            break;
                        case 'boost_heating':
                            $this->RegisterVariableBoolean('Z2M_BoostHeating', $this->Translate('Boost Heating'), '~Switch');
                            break;
                        case 'away_mode':
                            $this->RegisterVariableBoolean('Z2M_AwayMode', $this->Translate('Away Mode'), '~Switch');
                            $this->EnableAction('Z2M_AwayMode');
                            break;
                        case 'consumer_connected':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableBoolean('Z2M_Consumer_Connected', $this->Translate('Consumer connected'), $ProfileName);
                            }
                            break;
                        case 'led_disabled_night':
                            $this->RegisterVariableBoolean('Z2M_LEDDisabledNight', $this->Translate('LED disabled night'), '~Switch');
                            break;
                        case 'power_outage_memory':
                            $this->RegisterVariableBoolean('Z2M_PowerOutageMemory', $this->Translate('Power Outage Memory'), '~Switch');
                            $this->EnableAction('Z2M_PowerOutageMemory');
                            break;
                        case 'auto_off':
                            $this->RegisterVariableBoolean('Z2M_AutoOff', $this->Translate('Auto Off'), '~Switch');
                            $this->EnableAction('Z2M_AutoOff');
                            break;
                        case 'calibration':
                            $this->RegisterVariableBoolean('Z2M_Calibration', $this->Translate('Calibration'), '~Switch');
                            $this->EnableAction('Z2M_Calibration');
                            break;
                        case 'motor_reversal':
                            $this->RegisterVariableBoolean('Z2M_MotorReversal', $this->Translate('Motor Reversal'), '~Switch');
                            $this->EnableAction('Z2M_MotorReversal');
                            break;

                    default:
                        $missedVariables[] = $expose;
                        break;
                    }
                    break; //binary break
                case 'enum':
                    switch ($expose['property']) {
                        case 'effect':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Effect', $this->Translate('Effect'), $ProfileName);
                                $this->EnableAction('Z2M_Effect');
                            }
                        break;
                        case 'action':
                            $this->RegisterVariableString('Z2M_Action', $this->Translate('Action'), '');
                            break;
                        case 'sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Sensitivity', $this->Translate('Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_Sensitivity');
                            }
                            break;
                        case 'power_outage_memory':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PowerOutageMemory', $this->Translate('Power Outage Memory'), $ProfileName);
                                $this->EnableAction('Z2M_PowerOutageMemory');
                            }
                            break;
                        case 'power_on_behavior':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PowerOnBehavior', $this->Translate('Power on behavior'), $ProfileName);
                                $this->EnableAction('Z2M_PowerOnBehavior');
                            }
                            break;
                        case 'motion_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MotionSensitivity', $this->Translate('Motion Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_MotionSensitivity');
                            }
                            break;
                        case 'force':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Force', $this->Translate('Force'), $ProfileName);
                                $this->EnableAction('Z2M_Force');
                            }
                            break;
                        case 'moving':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Moving', $this->Translate('Moving'), $ProfileName);
                                $this->EnableAction('Z2M_Moving');
                            }
                            break;
                        case 'trv_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_TRVMode', $this->Translate('TRV Mode'), $ProfileName);
                                $this->EnableAction('Z2M_TRVMode');
                            }
                            break;
                        default:
                        $missedVariables[] = $expose;
                        break;
                        }
                    break; //enum break
                case 'numeric':
                    switch ($expose['property']) {
                        case 'linkquality':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Linkquality', $this->Translate('Linkquality'), $ProfileName);
                            }
                            break;
                        case 'valve_position':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_ValvePosition', $this->Translate('Valve Position'), $ProfileName);
                                $this->EnableAction('Z2M_ValvePosition');
                            }
                            break;
                        case 'battery':
                            $this->RegisterVariableInteger('Z2M_Battery', $this->Translate('Battery'), '~Battery.100');
                            break;
                        case 'temperature':
                            $this->RegisterVariableFloat('Z2M_Temperature', $this->Translate('Temperature'), '~Temperature');
                            break;
                        case 'humidity':
                            $this->RegisterVariableFloat('Z2M_Humidity', $this->Translate('Humidity'), '~Humidity.F');
                            break;
                        case 'pressure':
                            $this->RegisterVariableFloat('Z2M_Pressure', $this->Translate('Pressure'), '~AirPressure.F');
                            break;
                        case 'co2':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_CO2', $this->Translate('CO2'), $ProfileName);
                            }
                            break;
                        case 'voc':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_VOC', $this->Translate('VOC'), $ProfileName);
                            }
                            break;
                        case 'formaldehyd':
                            $this->RegisterVariableInteger('Z2M_Formaldehyd', $this->Translate('Formaldehyd'), '');
                            break;
                        case 'voltage':
                            $this->RegisterVariableFloat('Z2M_Voltage', $this->Translate('Voltage'), '~Volt');
                            break;
                        case 'illuminance_lux':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux', $this->Translate('Illuminance Lux'), '~Illumination');
                            break;
                        case 'illuminance':
                            $this->RegisterVariableInteger('Z2M_Illuminance', $this->Translate('Illuminance'), '');
                            break;
                        case 'strength':
                            $this->RegisterVariableInteger('Z2M_Strength', $this->Translate('Strength'), '');
                            break;
                        case 'angle_x':
                            $this->RegisterVariableFloat('Z2M_Angle_X', $this->Translate('Angle X'), '');
                            break;
                        case 'angle_y':
                            $this->RegisterVariableFloat('Z2M_Angle_Y', $this->Translate('Angle Y'), '');
                            break;
                        case 'angle_z':
                            $this->RegisterVariableFloat('Z2M_Angle_Z', $this->Translate('Angle Z'), '');
                            break;
                        case 'power':
                            $this->RegisterVariableFloat('Z2M_Power', $this->Translate('Power'), '~Watt.3680');
                            break;
                        case 'current':
                            $this->RegisterVariableFloat('Z2M_Current', $this->Translate('Current'), '~Ampere');
                            break;
                        case 'energy':
                            $this->RegisterVariableFloat('Z2M_Energy', $this->Translate('Energy'), '~Electricity');
                            break;
                        case 'occupancy_timeout':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_OccupancyTimeout', $this->Translate('Occupancy Timeout'), $ProfileName);
                                $this->EnableAction('Z2M_OccupancyTimeout');
                            }
                            break;
                        case 'max_temperature':
                            $this->RegisterVariableFloat('Z2M_MaxTemperature', $this->Translate('Max Temperature'), '~Temperature');
                            $this->EnableAction('Z2M_MaxTemperature');
                            break;
                        case 'min_temperature':
                            $this->RegisterVariableFloat('Z2M_MinTemperature', $this->Translate('Min Temperature'), '~Temperature');
                            $this->EnableAction('Z2M_MinTemperature');
                            break;
                        case 'position':
                            $this->RegisterVariableInteger('Z2M_Position', $this->Translate('Position'), '~Intensity.100');
                            //TODO nicht immer hat diese Variable eine Action
                            $this->EnableAction('Z2M_Position');
                            break;
                        case 'boost_heating_countdown':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_BoostHeatingCountdown', $this->Translate('Boost Heating Countdown'), 'Z2M.Minutes');
                            }
                            break;
                        case 'away_preset_days':
                            $this->RegisterVariableInteger('Z2M_AwayPresetDays', $this->Translate('Away Preset Days'), '');
                            $this->EnableAction('Z2M_AwayPresetDays');
                            break;
                        case 'boost_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_BoostTime', $this->Translate('Boost Time'), $ProfileName);
                                $this->EnableAction('Z2M_BoostTime');
                            }
                            break;
                        case 'comfort_temperature':
                            $this->RegisterVariableFloat('Z2M_ComfortTemperature', $this->Translate('Comfort Temperature'), '~Temperature.Room');
                            $this->EnableAction('Z2M_ComfortTemperature');
                            break;
                        case 'eco_temperature':
                            $this->RegisterVariableFloat('Z2M_EcoTemperature', $this->Translate('Eco Temperature'), '~Temperature.Room');
                            $this->EnableAction('Z2M_EcoTemperature');
                            break;
                        case 'away_preset_temperature':
                            $this->RegisterVariableFloat('Z2M_AwayPresetTemperature', $this->Translate('Away Preset Temperature'), '~Temperature.Room');
                            $this->EnableAction('Z2M_AwayPresetTemperature');
                            break;
                        case 'current_heating_setpoint_auto':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CurrentHeatingSetpointAuto', $this->Translate('Current Heating Setpoint Auto'), $ProfileName);
                                $this->EnableAction('Z2M_CurrentHeatingSetpointAuto');
                            }
                            break;
                        case 'overload_protection':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_OverloadProtection', $this->Translate('Overload Protection'), $ProfileName);
                                $this->EnableAction('Z2M_OverloadProtection');
                            }
                            break;
                        case 'calibration_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_CalibrationTime', $this->Translate('Calibration Time'), $ProfileName);
                            }
                            break;
                        case 'action_angle':
                            $this->RegisterVariableInteger('Z2M_ActionAngle', $this->Translate('Action angle'), '');
                            break;
                        case 'action_from_side':
                            $this->RegisterVariableInteger('Z2M_ActionFromSide', $this->Translate('Action from side'), '');
                            break;
                        case 'action_side':
                            $this->RegisterVariableInteger('Z2M_ActionSide', $this->Translate('Action side'), '');
                            break;
                        case 'action_to_side':
                            $this->RegisterVariableInteger('Z2M_ActionToSide', $this->Translate('Action to side'), '');
                            break;
                        default:
                        $missedVariables[] = $expose;
                        break;
                    }
                    break; //numeric break
                default: // Expos Type default
                    break;
            }
        }
        $this->SendDebug(__FUNCTION__ . ':: Missed Exposes', json_encode($missedVariables), 0);
    }
}
