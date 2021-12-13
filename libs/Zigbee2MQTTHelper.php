<?php

declare(strict_types=1);

trait Zigbee2MQTTHelper
{
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Z2M_Brightness':
                $Payload['brightness'] = strval($Value);
                break;
            case 'Z2M_BrightnessRGB':
                $Payload['brightness_rgb'] = strval($Value);
                break;
            case 'Z2M_BrightnessWhite':
                $Payload['brightness_white'] = strval($Value);
                break;
            case 'Z2M_ColorTemp':
                $Payload['color_temp'] = strval($Value);
                break;
            case 'Z2M_ColorTempKelvin':
                $Payload['color_temp'] = strval(intval(round(1000000 / $Value, 0)));
                break;
            case 'Z2M_ColorTempRGB':
                $Payload['color_temp_rgb'] = strval($Value);
                break;
            case 'Z2M_State':
                $Payload['state'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_RunningState':
                $Payload['running_state'] = strval($Value);
                break;
            case 'Z2M_StateRGB':
                $Payload['state_rgb'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_StateWhite':
                $Payload['state_white'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_LEDDisabledNight':
                $Payload['led_disabled_night'] = strval($Value);
                break;
            case 'Z2M_Statel1':
                $Payload['l1'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_Statel2':
                $Payload['l2'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_Statel3':
                $Payload['l3'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_Statel4':
                $Payload['l4'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_WindowDetection':
                $Payload['window_detection'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_ChildLock':
                $Payload['child_lock'] = strval($this->OnOff($Value));
                break;
            case'Z2M_PowerOutageMemory':
                $Payload['power_outage_memory'] = strval($Value);
                break;
            case'Z2M_PowerOnBehavior':
                $Payload['power_on_behavior'] = strval($Value);
                break;
            case'Z2M_AutoOff':
                $Payload['auto_off'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_StateWindow':
                $Payload['state'] = strval($this->OpenClose($Value));
                break;
            case 'Z2M_Sensitivity':
                $Payload['sensitivity'] = strval($Value);
                break;
            case 'Z2M_RadarSensitivity':
                $Payload['radar_sensitivity'] = strval($Value);
                break;
            case 'Z2M_RadarScene':
                $Payload['radar_scene'] = strval($Value);
                break;
            case 'Z2M_BoostHeating':
                $Payload['boost_heating'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_Force':
                $Payload['boost_heating'] = strval($this->OnOff($Value));
                break;
            case 'Z2M_Moving':
                $Payload['moving'] = strval($Value);
                break;
            case 'Z2M_TRVMode':
                $Payload['trv_mode'] = strval($Value);
                break;
            case 'Z2M_Calibration':
                $Payload['calibration'] = strval($Value);
                break;
            case 'motor_reversal':
                $Payload['motor_reversal'] = strval($Value);
                break;
            case 'Z2M_CurrentHeatingSetpoint':
                $Payload['current_heating_setpoint'] = strval($Value);
                break;
            case 'Z2M_CurrentHeatingSetpointAuto':
                $Payload['current_heating_setpoint_auto'] = strval($Value);
                break;
            case 'Z2M_OccupiedHeatingSetpoint':
                $Payload['occupied_heating_setpoint'] = strval($Value);
                break;
            case 'Z2M_LocalTemperatureCalibration':
                $Payload['local_temperature_calibration'] = strval($Value);
                break;
            case 'Z2M_Preset':
                $Payload['preset'] = strval($Value);
                break;
            case 'Z2M_AwayPresetDays':
                $Payload['away_preset_days'] = strval($Value);
                break;
            case 'Z2M_AwayMode':
                $Payload['away_mode'] = strval($Value);
                break;
            case 'Z2M_BoostTime':
                $Payload['boost_time'] = strval($Value);
                break;
            case 'Z2M_SystemMode':
                $Payload['system_mode'] = strval($Value);
                break;
            case 'Z2M_Color':
                $this->SendDebug(__FUNCTION__ . ' Color', $Value, 0);
                $this->setColor($Value, 'cie');
                return;
            case 'Z2M_ColorRGB':
                $this->SendDebug(__FUNCTION__ . ' :: Color RGB', $Value, 0);
                $this->setColor($Value, 'cie', 'color_rgb');
                return;
            case 'Z2M_Position':
                $Payload['position'] = strval($Value);
                $this->setPosition($Value);
                break;
            case 'Z2M_MotorSpeed':
                $Payload['motor_speed'] = strval($Value);
                $this->setMotorSpeed($Value);
                break;
            case 'Z2M_MotionSensitivity':
                $Payload['motion_sensitivity'] = strval($Value);
                break;
            case 'Z2M_OccupancyTimeout':
                $Payload['occupancy_timeout'] = strval($Value);
                break;
            case 'Z2M_OverloadProtection':
                $Payload['overload_protection'] = strval($Value);
                break;
            case 'Z2M_Mode':
                $Payload['mode'] = strval($Value);
                break;
            case 'Z2M_Level':
                $Payload['level'] = strval($Value);
                break;
            case 'Z2M_StrobeLevel':
                $Payload['strobe_level'] = strval($Value);
                break;
            case 'Z2M_Strobe':
                $Payload['strobe'] = strval($Value);
                break;
            case 'Z2M_StrobeDutyCycle':
                $Payload['strobe_duty_cycle'] = strval($Value);
                break;
            case 'Z2M_Duration':
                $Payload['duration'] = strval($Value);
                break;
            default:
                $this->SendDebug('Request Action', 'No Action defined: ' . $Ident, 0);
                return false;
                break;
        }

        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($PayloadJSON);
    }

    public function getDeviceInfo()
    {
        $this->symconExtensionCommand('getDevice', $this->ReadPropertyString('MQTTTopic'));
    }

    public function getGroupInfo()
    {
        $this->symconExtensionCommand('getGroup', $this->ReadPropertyString('MQTTTopic'));
    }

    public function ReceiveData($JSONString)
    {
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $Buffer = json_decode($JSONString, true);

            $this->SendDebug('MQTT Topic', $Buffer['Topic'], 0);
            $this->SendDebug('MQTT Payload', $Buffer['Payload'], 0);

            if (array_key_exists('Topic', $Buffer)) {
                if (fnmatch('*/availability', $Buffer['Topic'])) {
                    $this->RegisterVariableBoolean('Z2M_Status', $this->Translate('Status'), 'Z2M.DeviceStatus');
                    if ($Buffer['Payload'] == 'online') {
                        $this->SetValue('Z2M_Status', true);
                    } else {
                        $this->SetValue('Z2M_Status', false);
                    }
                }
            }

            $Payload = json_decode($Buffer['Payload'], true);
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/deviceInfo', $Buffer['Topic'])) {
                if (is_array($Payload['_definition'])) {
                    if (is_array($Payload['_definition']['exposes'])) {
                        $this->mapExposesToVariables($Payload['_definition']['exposes']);
                    }
                }
            }
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/groupInfo', $Buffer['Topic'])) {
                if (is_array($Payload)) {
                    $this->mapExposesToVariables($Payload);
                }
            }

            $Payload = json_decode($Buffer['Payload'], true);
            if (is_array($Payload)) {
                if (array_key_exists('temperature', $Payload)) {
                    $this->SetValue('Z2M_Temperature', $Payload['temperature']);
                }
                if (array_key_exists('local_temperature', $Payload)) {
                    $this->SetValue('Z2M_LocalTemperature', $Payload['local_temperature']);
                }
                if (array_key_exists('local_temperature_calibration', $Payload)) {
                    $this->SetValue('Z2M_LocalTemperatureCalibration', $Payload['local_temperature_calibration']);
                }
                if (array_key_exists('max_temperature', $Payload)) {
                    $this->SetValue('Z2M_MaxTemperature', $Payload['max_temperature']);
                }
                if (array_key_exists('min_temperature', $Payload)) {
                    $this->SetValue('Z2M_MinTemperature', $Payload['min_temperature']);
                }
                if (array_key_exists('preset', $Payload)) {
                    $this->SetValue('Z2M_Preset', $Payload['preset']);
                }
                if (array_key_exists('away_mode', $Payload)) {
                    switch ($Payload['away_mode']) {
                    case 'ON':
                        $this->SetValue('Z2M_AwayMode', true);
                        break;
                    case 'OFF':
                        $this->SetValue('Z2M_AwayMode', false);
                        break;
                    default:
                        $this->SendDebug('SetValue AwayMode', 'Invalid Value: ' . $Payload['away_mode'], 0);
                        break;
                    }
                }

                if (array_key_exists('away_preset_days', $Payload)) {
                    $this->SetValue('Z2M_AwayPresetDays', $Payload['away_preset_days']);
                }

                if (array_key_exists('away_preset_temperature', $Payload)) {
                    $this->SetValue('Z2M_AwayPresetTemperature', $Payload['away_preset_temperature']);
                }

                if (array_key_exists('boost_time', $Payload)) {
                    $this->SetValue('Z2M_BoostTime', $Payload['boost_time']);
                }

                if (array_key_exists('comfort_temperature', $Payload)) {
                    $this->SetValue('Z2M_ComfortTemperature', $Payload['comfort_temperature']);
                }

                if (array_key_exists('eco_temperature', $Payload)) {
                    $this->SetValue('Z2M_EcoTemperature', $Payload['eco_temperature']);
                }

                if (array_key_exists('current_heating_setpoint', $Payload)) {
                    $this->SetValue('Z2M_CurrentHeatingSetpoint', $Payload['current_heating_setpoint']);
                }

                if (array_key_exists('current_heating_setpoint_auto', $Payload)) {
                    $this->SetValue('Z2M_CurrentHeatingSetpoint', $Payload['current_heating_setpoint_auto']);
                }
                if (array_key_exists('occupied_heating_setpoint', $Payload)) {
                    $this->SetValue('Z2M_OccupiedHeatingSetpoint', $Payload['occupied_heating_setpoint']);
                }
                if (array_key_exists('pi_heating_demand', $Payload)) {
                    $this->SetValue('Z2M_Pi_Heating_Demand', $Payload['pi_heating_demand']);
                }
                if (array_key_exists('system_mode', $Payload)) {
                    $this->SetValue('Z2M_SystemMode', $Payload['system_mode']);
                }
                if (array_key_exists('running_state', $Payload)) {
                    $this->SetValue('Z2M_RunningState', $Payload['running_state']);
                }

                if (array_key_exists('state_left', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: state_left', KL_WARNING);
                    //$this->RegisterVariableString('Z2M_StateLeft', $this->Translate('State Left'), '');
                    //$this->SetValue('Z2M_StateLeft', $Payload['state_left']);
                }

                if (array_key_exists('state_right', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: state_right', KL_WARNING);
                    //$this->RegisterVariableString('Z2M_StateRight', $this->Translate('State Right'), '');
                    //$this->SetValue('Z2M_StateRight', $Payload['state_right']);
                }

                if (array_key_exists('linkquality', $Payload)) {
                    $this->SetValue('Z2M_Linkquality', $Payload['linkquality']);
                }

                if (array_key_exists('valve_position', $Payload)) {
                    $this->SetValue('Z2M_ValvePosition', $Payload['valve_position']);
                }

                if (array_key_exists('humidity', $Payload)) {
                    $this->SetValue('Z2M_Humidity', $Payload['humidity']);
                }

                if (array_key_exists('pressure', $Payload)) {
                    $this->SetValue('Z2M_Pressure', $Payload['pressure']);
                }
                if (array_key_exists('battery', $Payload)) {
                    $this->SetValue('Z2M_Battery', $Payload['battery']);
                }

                //Da Millivolt und Volt mit dem selben Topic verschickt wird
                if (array_key_exists('voltage', $Payload)) {
                    if ($Payload['voltage'] > 400) { //Es gibt wahrscheinlich keine Zigbee Geräte mit über 400 Volt
                        $this->SetValue('Z2M_Voltage', $Payload['voltage'] / 1000);
                    } else {
                        $this->SetValue('Z2M_Voltage', $Payload['voltage']);
                    }
                }

                if (array_key_exists('current', $Payload)) {
                    $this->SetValue('Z2M_Current', $Payload['current']);
                }

                if (array_key_exists('action', $Payload)) {
                    $this->SetValue('Z2M_Action', $Payload['action']);
                }

                if (array_key_exists('click', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: click', KL_WARNING);
                    //$this->RegisterVariableString('Z2M_Click', $this->Translate('Click'), '');
                    //$this->SetValue('Z2M_Click', $Payload['click']);
                }

                if (array_key_exists('brightness', $Payload)) {
                    $this->SetValue('Z2M_Brightness', $Payload['brightness']);
                }

                if (array_key_exists('brightness_rgb', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: brightness_rgb', KL_WARNING);
                    //$this->RegisterVariableInteger('Z2M_BrightnessRGB', $this->Translate('Brightness RGB'), 'Z2M.Intensity.254');
                    //$this->EnableAction('Z2M_BrightnessRGB');
                    //$this->SetValue('Z2M_BrightnessRGB', $Payload['brightness_rgb']);
                }

                if (array_key_exists('brightness_white', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: brightness_white', KL_WARNING);
                    //$this->RegisterVariableInteger('Z2M_BrightnessWhite', $this->Translate('Brightness White'), 'Z2M.Intensity.254');
                    //$this->EnableAction('Z2M_BrightnessWhite');
                    //$this->SetValue('Z2M_BrightnessWhite', $Payload['brightness_white']);
                }

                if (array_key_exists('position', $Payload)) {
                    $this->SetValue('Z2M_Position', $Payload['position']);
                }

                if (array_key_exists('motor_speed', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: motor_speed', KL_WARNING);
                    //$this->RegisterVariableInteger('Z2M_MotorSpeed', $this->Translate('Motor Speed'), '~Intensity.255');
                    //$this->EnableAction('Z2M_MotorSpeed');
                    //$this->SetValue('Z2M_MotorSpeed', $Payload['motor_speed']);
                }

                if (array_key_exists('occupancy', $Payload)) {
                    $this->SetValue('Z2M_Occupancy', $Payload['occupancy']);
                }

                if (array_key_exists('occupancy_timeout', $Payload)) {
                    $this->SetValue('Z2M_OccupancyTimeout', $Payload['occupancy_timeout']);
                }

                if (array_key_exists('motion_sensitivity', $Payload)) {
                    $this->SetValue('Z2M_MotionSensitivity', $Payload['motion_sensitivity']);
                }

                if (array_key_exists('presence', $Payload)) {
                    $this->SetValue('Z2M_Presence', $Payload['presence']);
                }

                if (array_key_exists('motion', $Payload)) {
                    $this->SetValue('Z2M_Motion', $Payload['motion']);
                }

                if (array_key_exists('motion_state', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: motion_state', KL_WARNING);
                    //$this->RegisterVariableBoolean('Z2M_Motion_State', $this->Translate('Motion State'), '~Motion');
                    //$this->SetValue('Z2M_Motion_State', $Payload['motion_state']);
                }

                if (array_key_exists('motion_direction', $Payload)) {
                    $this->SetValue('Z2M_MotionDirection', $Payload['motion_direction']);
                }

                if (array_key_exists('scene', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: scene', KL_WARNING);
                    //$this->RegisterVariableString('Z2M_Scene', $this->Translate('Scene'), '');
                    //$this->SetValue('Z2M_Scene', $Payload['scene']);
                }

                if (array_key_exists('motion_speed', $Payload)) {
                    $this->SetValue('Z2M_MotionSpeed', $Payload['motion_speed']);
                }

                if (array_key_exists('radar_sensitivity', $Payload)) {
                    $this->SetValue('Z2M_RadarSensitivity', $Payload['radar_sensitivity']);
                }

                if (array_key_exists('radar_scene', $Payload)) {
                    $this->SetValue('Z2M_RadarScene', $Payload['radar_scene']);
                }

                if (array_key_exists('illuminance', $Payload)) {
                    $this->SetValue('Z2M_Illuminance', $Payload['illuminance']);
                }

                if (array_key_exists('illuminance_lux', $Payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux', $Payload['illuminance_lux']);
                    }
                }

                if (array_key_exists('strength', $Payload)) {
                    $this->SetValue('Z2M_Strength', $Payload['strength']);
                }

                if (array_key_exists('water_leak', $Payload)) {
                    $this->SetValue('Z2M_WaterLeak', $Payload['water_leak']);
                }

                if (array_key_exists('contact', $Payload)) {
                    $this->SetValue('Z2M_Contact', $Payload['contact']);
                }

                if (array_key_exists('carbon_monoxide', $Payload)) {
                    $this->SetValue('Z2M_CarbonMonoxide', $Payload['carbon_monoxide']);
                }

                if (array_key_exists('smoke', $Payload)) {
                    $this->SetValue('Z2M_Smoke', $Payload['smoke']);
                }

                if (array_key_exists('smoke_density', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: smoke_density', KL_WARNING);
                    //$this->RegisterVariableInteger('Z2M_SmokeDensity', $this->Translate('Smoke Density'), '');
                    //$this->SetValue('Z2M_SmokeDensity', $Payload['smoke_density']);
                }

                if (array_key_exists('tamper', $Payload)) {
                    $this->SetValue('Z2M_Tamper', $Payload['tamper']);
                }

                if (array_key_exists('enrolled', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: enrolled', KL_WARNING);
                    //$this->RegisterVariableBoolean('Z2M_Enrolled', $this->Translate('Enrolled'), '');
                    //$this->SetValue('Z2M_Enrolled', $Payload['enrolled']);
                }

                if (array_key_exists('restore_reports', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: restore_reports', KL_WARNING);
                    //$this->RegisterVariableBoolean('Z2M_RestoreReports', $this->Translate('Restore Reports'), '');
                    //$this->SetValue('Z2M_RestoreReports', $Payload['restore_reports']);
                }

                if (array_key_exists('supervision_reports', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: supervision_reports', KL_WARNING);
                    //$this->RegisterVariableBoolean('Z2M_SupervisionReports', $this->Translate('Supervision Reports'), '');
                    //$this->SetValue('Z2M_SupervisionReports', $Payload['supervision_reports']);
                }

                if (array_key_exists('trouble', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: trouble', KL_WARNING);
                    //$this->RegisterVariableBoolean('Z2M_Trouble', $this->Translate('Trouble'), '');
                    //$this->SetValue('Z2M_Trouble', $Payload['trouble']);
                }

                if (array_key_exists('battery_low', $Payload)) {
                    $this->SetValue('Z2M_Battery_Low', $Payload['battery_low']);
                }

                if (array_key_exists('action_angle', $Payload)) {
                    $this->SetValue('Z2M_ActionAngle', $Payload['action_angle']);
                }

                if (array_key_exists('angle_x', $Payload)) {
                    $this->SetValue('Z2M_Angle_X', $Payload['angle_x']);
                }

                if (array_key_exists('angle_y', $Payload)) {
                    $this->SetValue('Z2M_Angle_Y', $Payload['angle_y']);
                }

                if (array_key_exists('angle_x_absolute', $Payload)) {
                    $this->LogMessage('Please Contact Module Developer. Undefined Variable angle_x_absolute', KL_WARNING);
                    //$this->RegisterVariableFloat('Z2M_Angle_X_Absolute', $this->Translate('Angle_X_Absolute'), '');
                    //$this->SetValue('Z2M_Angle_X_Absolute', $Payload['angle_x_absolute']);
                }

                if (array_key_exists('angle_y_absolute', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: angle_y_absolute', KL_WARNING);
                    //$this->RegisterVariableFloat('Z2M_Angle_Y_Absolute', $this->Translate('Angle_Y_Absolute'), '');
                    //$this->SetValue('Z2M_Angle_Y_Absolute', $Payload['angle_y_absolute']);
                }

                if (array_key_exists('angle_z', $Payload)) {
                    $this->SetValue('Z2M_Angle_Z', $Payload['angle_z']);
                }

                if (array_key_exists('action_from_side', $Payload)) {
                    $this->SetValue('Z2M_ActionFromSide', $Payload['action_from_side']);
                }

                if (array_key_exists('battaction_sideery_low', $Payload)) {
                    $this->SetValue('Z2M_ActionSide', $Payload['action_side']);
                }

                if (array_key_exists('action_to_side', $Payload)) {
                    $this->SetValue('Z2M_ActionToSide', $Payload['action_to_side']);
                }

                if (array_key_exists('power', $Payload)) {
                    $this->SetValue('Z2M_Power', $Payload['power']);
                }

                if (array_key_exists('consumer_connected', $Payload)) {
                    $this->SetValue('Z2M_Consumer_Connected', $Payload['consumer_connected']);
                }

                if (array_key_exists('consumption', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: consumption', KL_WARNING);
                    //$this->RegisterVariableFloat('Z2M_Consumption', $this->Translate('Consumption'), '~Electricity');
                    //$this->SetValue('Z2M_Consumption', $Payload['consumption']);
                }

                if (array_key_exists('energy', $Payload)) {
                    $this->SetValue('Z2M_Energy', $Payload['energy']);
                }

                if (array_key_exists('overload_protection', $Payload)) {
                    $this->SetValue('Z2M_OverloadProtection', $Payload['overload_protection']);
                }

                if (array_key_exists('duration', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: duration', KL_WARNING);
                    //$this->RegisterVariableFloat('Z2M_Duration', $this->Translate('Duration'), '');
                    //$this->SetValue('Z2M_Duration', $Payload['duration']);
                }

                if (array_key_exists('counter', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: counter', KL_WARNING);
                    //$this->RegisterVariableFloat('Z2M_Counter', $this->Translate('Counter'), '');
                    //$this->SetValue('Z2M_Counter', $Payload['counter']);
                }

                if (array_key_exists('color', $Payload)) {
                    $this->SendDebug(__FUNCTION__ . ' Color', $Payload['color']['x'], 0);
                    if (array_key_exists('brightness', $Payload)) {
                        $RGBColor = ltrim($this->CIEToRGB($Payload['color']['x'], $Payload['color']['y'], $Payload['brightness']), '#');
                    } else {
                        $RGBColor = ltrim($this->CIEToRGB($Payload['color']['x'], $Payload['color']['y']), '#');
                    }
                    $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                    $this->SetValue('Z2M_Color', hexdec(($RGBColor)));
                }

                if (array_key_exists('color_rgb', $Payload)) {
                    $this->SendDebug(__FUNCTION__ . ':: Color X', $Payload['color_rgb']['x'], 0);
                    $this->SendDebug(__FUNCTION__ . ':: Color Y', $Payload['color_rgb']['y'], 0);
                    if (array_key_exists('brightness_rgb', $Payload)) {
                        $RGBColor = ltrim($this->CIEToRGB($Payload['color_rgb']['x'], $Payload['color_rgb']['y'], $Payload['brightness_rgb']), '#');
                    } else {
                        $RGBColor = ltrim($this->CIEToRGB($Payload['color_rgb']['x'], $Payload['color_rgb']['y']), '#');
                    }
                    $this->SendDebug(__FUNCTION__ . ' Color :: RGB HEX', $RGBColor, 0);
                    $this->LogMessage('Please contact module developer. Undefined variable: color_rgb', KL_WARNING);
                    //$this->RegisterVariableInteger('Z2M_ColorRGB', $this->Translate('Color'), 'HexColor');
                    //$this->EnableAction('Z2M_ColorRGB');
                    $this->SetValue('Z2M_ColorRGB', hexdec(($RGBColor)));
                }

                if (array_key_exists('sensitivity', $Payload)) {
                    $this->SetValue('Z2M_Sensitivity', $Payload['sensitivity']);
                }

                if (array_key_exists('color_temp', $Payload)) {
                    $this->SetValue('Z2M_ColorTemp', $Payload['color_temp']);
                    //Color Temperature in Kelvin
                    if ($Payload['color_temp'] > 0) {
                        $this->SetValue('Z2M_ColorTempKelvin', 1000000 / $Payload['color_temp']); //Convert to Kelvin
                    }
                }

                if (array_key_exists('color_temp_rgb', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: color_temp_rgb', KL_WARNING);
                    //$this->RegisterVariableInteger('Z2M_ColorTempRGB', $this->Translate('Color Temperature RGB'), 'Z2M.ColorTemperature');
                    //$this->EnableAction('Z2M_ColorTempRGB');
                    //$this->SetValue('Z2M_ColorTempRGB', $Payload['color_temp_rgb']);
                }

                if (array_key_exists('state', $Payload)) {
                    switch ($Payload['state']) {
                        case 'ON':
                            $this->SetValue('Z2M_State', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_State', false);
                            break;
                        case 'OPEN':
                            $this->LogMessage('Please contact module developer. Undefined variable: Z2M_StateWindow', KL_WARNING);
                            //$this->RegisterVariableBoolean('Z2M_StateWindow', $this->Translate('State'), '~Window');
                            //$this->EnableAction('Z2M_StateWindow');
                            //$this->SetValue('Z2M_StateWindow', true);
                            break;
                        case 'CLOSE':
                            $this->LogMessage('Please contact module developer. Undefined variable: Z2M_StateWindow', KL_WARNING);
                            //$this->RegisterVariableBoolean('Z2M_StateWindow', $this->Translate('State'), '~Window');
                            //$this->EnableAction('Z2M_StateWindow');
                            //$this->SetValue('Z2M_StateWindow', false);
                            break;
                        default:
                        $this->SendDebug('State', 'Undefined State: ' . $Payload['state'], 0);
                        break;
                        }
                }

                if (array_key_exists('led_disabled_night', $Payload)) {
                    $this->SetValue('Z2M_LEDDisabledNight', $Payload['led_disabled_night']);
                }

                if (array_key_exists('state_rgb', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: state_rgb', KL_WARNING);
                    switch ($Payload['state_rgb']) {
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
                        $this->SendDebug('State RGB', 'Undefined State: ' . $Payload['state_rgb'], 0);
                        break;
                        }
                }

                if (array_key_exists('state_white', $Payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: state_white', KL_WARNING);
                    switch ($Payload['state_white']) {
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
                        $this->SendDebug('State White', 'Undefined State: ' . $Payload['state_white'], 0);
                        break;
                        }
                }

                if (array_key_exists('power_outage_memory', $Payload)) {
                    $this->SetValue('Z2M_PowerOutageMemory', $Payload['power_outage_memory']);
                }

                if (array_key_exists('power_on_behavior', $Payload)) {
                    $this->SetValue('Z2M_PowerOnBehavior', $Payload['power_on_behavior']);
                }

                if (array_key_exists('state_l1', $Payload)) {
                    switch ($Payload['state_l1']) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel1', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel1', false);
                            break;
                        default:
                            $this->SendDebug('State 1', 'Undefined State 1: ' . $Payload['state_l1'], 0);
                            break;
                    }
                }
                if (array_key_exists('state_l2', $Payload)) {
                    switch ($Payload['state_l2']) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel2', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel2', false);
                            break;
                        default:
                            $this->SendDebug('State 2', 'Undefined State 2: ' . $Payload['state_l2'], 0);
                            break;
                    }
                }
                if (array_key_exists('state_l3', $Payload)) {
                    switch ($Payload['state_l3']) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel3', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel3', false);
                            break;
                        default:
                            $this->SendDebug('State 3', 'Undefined State 3: ' . $Payload['state_l3'], 0);
                            break;
                    }
                }
                if (array_key_exists('state_l4', $Payload)) {
                    switch ($Payload['state_l4']) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel4', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel4', false);
                            break;
                        default:
                            $this->SendDebug('State 4', 'Undefined State 4: ' . $Payload['state_l4'], 0);
                            break;
                    }
                }
                if (array_key_exists('state_l5', $Payload)) {
                    switch ($Payload['state_l5']) {
                        case 'ON':
                            $this->SetValue('Z2M_Statel5', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_Statel5', false);
                            break;
                        default:
                            $this->SendDebug('State 5', 'Undefined State 5: ' . $Payload['state_l5'], 0);
                            break;
                    }
                }
                if (array_key_exists('window_detection', $Payload)) {
                    switch ($Payload['window_detection']) {
                        case 'ON':
                            $this->SetValue('Z2M_WindowDetection', true);
                            break;
                        case 'OFF':
                            $this->SetValue('Z2M_WindowDetection', false);
                            break;
                        default:
                            $this->SendDebug('Window Detection', 'Undefined State: ' . $Payload['window_detection'], 0);
                            break;
                    }
                }
                if (array_key_exists('child_lock', $Payload)) {
                    switch ($Payload['child_lock']) {
                        case 'LOCK':
                            $this->SetValue('Z2M_ChildLock', true);
                            break;
                        case 'UNLOCK':
                            $this->SetValue('Z2M_ChildLock', false);
                            break;
                        default:
                            $this->SendDebug('Child Lock', 'Undefined State: ' . $Payload['child_lock'], 0);
                            break;
                    }
                }
                if (array_key_exists('update_available', $Payload)) {
                    //Bleibt hier. gibt es nicht als Expose
                    $this->RegisterVariableBoolean('Z2M_Update', $this->Translate('Update'), '');
                    $this->SetValue('Z2M_Update', $Payload['update_available']);
                }
                if (array_key_exists('voc', $Payload)) {
                    $this->SetValue('Z2M_VOC', $Payload['voc']);
                }
                if (array_key_exists('co2', $Payload)) {
                    $this->SetValue('Z2M_CO2', $Payload['co2']);
                }
                if (array_key_exists('formaldehyd', $Payload)) {
                    $this->SetValue('Z2M_Formaldehyd', $Payload['formaldehyd']);
                }
                if (array_key_exists('force', $Payload)) {
                    $this->SetValue('Z2M_Force', $Payload['force']);
                }
                if (array_key_exists('moving', $Payload)) {
                    $this->SetValue('Z2M_Moving', $Payload['moving']);
                }
                if (array_key_exists('trv_mode', $Payload)) {
                    $this->SetValue('Z2M_TRVMode', $Payload['trv_mode']);
                }
                if (array_key_exists('calibration', $Payload)) {
                    $this->SetValue('Z2M_Calibration', $Payload['calibration']);
                }
                if (array_key_exists('motor_reversal', $Payload)) {
                    $this->SetValue('Z2M_MotorReversal', $Payload['motor_reversal']);
                }
                if (array_key_exists('calibration_time', $Payload)) {
                    $this->SetValue('Z2M_CalibrationTime', $Payload['calibration_time']);
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
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileBooleanEx($ProfileName, 'Plug', '', '', [
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
                                    ['schedule', $this->Translate('Schedule'), '', 0x8800FF],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['boost', $this->Translate('Boost'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case'Z2M.preset.1d99b46a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['comfort', $this->Translate('Comfort'), '', 0xFFFF00],
                                    ['complex', $this->Translate('Complex'), '', 0x0000FF],
                                    ['eco', $this->Translate('Eco'), '', 0x00FF00],
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x8800FF],
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
                        case 'Z2M.power_outage_memory.198b1127':
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
                        case 'Z2M.motion_sensitivity.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
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
                        case 'Z2M.force.2bd28f19':
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
                                    ['2', $this->Translate('Automatic'), '', 0xFF8800],
                                ]);
                            }
                            break;
                        case 'Z2M.sensitivity.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.mode.fecb2e2f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['burglar', $this->Translate('Burglar'), '', 0xFFC0CB],
                                    ['emergency', $this->Translate('Emergency'), '', 0xFFFF00],
                                    ['emergency_panic', $this->Translate('Emergency Panic'), '', 0xFF8800],
                                    ['fire', $this->Translate('Fire'), '', 0xFF0000],
                                    ['fire_panic', $this->Translate('Fire Panic'), '', 0x880000],
                                    ['Police_panic', $this->Translate('Police Panic'), '', 0x4169E1],
                                    ['stop', $this->Translate('Stop'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.level.ae420ac':
                        case 'Z2M.strobe_level.ae420ac':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Gear', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000],
                                    ['very_high', $this->Translate('Very High'), '', 0xFF8800],
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
                    case 'Z2M.strobe_duty_cycle':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ', $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'Z2M.duration':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', $this->Translate('Seconds'), 0, 0, 0);
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
                                                $this->EnableAction('Z2M_State');
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
                        case 'vibration':
                            $this->RegisterVariableBoolean('Z2M_Vibration', $this->Translate('Vibration'), '~Alert');
                        break;
                        case 'occupancy':
                            $this->RegisterVariableBoolean('Z2M_Occupancy', $this->Translate('Occupancy'), '~Motion');
                            break;
                        case 'presence':
                            $this->RegisterVariableBoolean('Z2M_Presence', $this->Translate('Presence'), '~Presence');
                            break;
                        case 'motion':
                            $this->RegisterVariableBoolean('Z2M_Motion', $this->Translate('Motion'), '~Motion');
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
                        case 'motion_direction':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MotionDirection', $this->Translate('Motion Direction'), $ProfileName);
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
                        case 'motion_speed':
                            $this->RegisterVariableInteger('Z2M_MotionSpeed', $this->Translate('Motionspeed'), '');
                            break;
                        case 'radar_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_RadarSensitivity', $this->Translate('Radar Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_RadarSensitivity');
                            }
                            break;
                        case 'radar_scene':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_RadarScene', $this->Translate('Radar Scene'), $ProfileName);
                                $this->EnableAction('Z2M_RadarScene');
                            }
                            break;
                        default:
                        $missedVariables[] = $expose;
                        break;
                    }
                    break; //numeric break
                case 'composite':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'strobe':
                                            $this->RegisterVariableBoolean('Z2M_Strobe', $this->Translate('Strobe'), '~Switch');
                                            $this->EnableAction('Z2M_Strobe');
                                            break;
                                        default:
                                        // Default composite binary
                                        $missedVariables['composite'][] = $feature;
                                        break;
                                    }
                                break; //Composite binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'strobe_duty_cycle':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_StrobeDutyCycle', $this->Translate('Strobe Duty Cycle'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_StrobeDutyCycle');
                                            break;
                                        case 'duration':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_Duration', $this->Translate('Duration'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_Duration');
                                            break;
                                        default:
                                        // Default composite binary
                                        $missedVariables['composite'][] = $feature;
                                        break;
                                    }
                                    break; //Composite numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        case 'mode':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_Mode', $this->Translate('Mode'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_Mode');
                                            break;
                                        case 'level':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_Level', $this->Translate('Level'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_Level');
                                            break;
                                        case 'strobe_level':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_StrobeLevel', $this->Translate('Strobe Level'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_StrobeLevel');
                                            break;
                                        default:
                                        // Default composite enum
                                        $missedVariables['composite'][] = $feature;
                                        break;
                                    }
                                    break; //Composite enum break;
                            }
                        }
                    }
                    break; //Lock break
                default: // Expose Type default
                    break;
            }
        }
        $this->SendDebug(__FUNCTION__ . ':: Missed Exposes', json_encode($missedVariables), 0);
    }
}
