<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

trait Zigbee2MQTTHelper
{
    private $stateTypeMapping = [
        // Gehört zu RequestAction
        'Z2M_ChildLock'          => ['type' => 'lockunlock', 'dataType' =>'string'],
        'Z2M_StateWindow'        => ['type' => 'openclose', 'dataType' =>'string'],
        'Z2M_AutoLock'           => ['type' => 'automode', 'dataType' => 'string'],
        'Z2M_ValveState'         => ['type' => 'valve', 'dataType' => 'string'],
        'Z2M_EcoTemperature'     => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_MaxTemperature'     => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
    ];

    public function RequestAction($ident, $value)
    {
        // Behandle spezielle Fälle separat
        switch ($ident) {
        case 'Z2M_Color':
            $this->SendDebug(__FUNCTION__ . ' Color', $value, 0);
            $this->setColor($value, 'cie');
            return;
        case 'Z2M_ColorHS':
            $this->SendDebug(__FUNCTION__ . ' Color HS', $value, 0);
            $this->setColor($value, 'hs');
            return;
        case 'Z2M_ColorRGB':
            $this->SendDebug(__FUNCTION__ . ' :: Color RGB', $value, 0);
            $this->setColor($value, 'cie', 'color_rgb');
            return;
        }
        // Generelle Logik für die meisten anderen Fälle
        $variableID = $this->GetIDForIdent($ident);
        $variableInfo = IPS_GetVariable($variableID);
        $variableType = $variableInfo['VariableType'];
        $payloadKey = $this->convertIdentToPayloadKey($ident);
        $payload = [$payloadKey => $this->convertStateBasedOnMapping($ident, $value, $variableType)];
        $payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $this->Z2MSet($payloadJSON);
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

            if (IPS_GetKernelDate() > 1670886000) {
                $Buffer['Payload'] = utf8_decode($Buffer['Payload']);
            }

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

            $payload = json_decode($Buffer['Payload'], true);
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/deviceInfo', $Buffer['Topic'])) {
                if (is_array($payload['exposes'])) {
                    $this->mapExposesToVariables($payload['exposes']);
                }
            }
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/groupInfo', $Buffer['Topic'])) {
                if (is_array($payload)) {
                    $this->mapExposesToVariables($payload);
                }
            }

            $payload = json_decode($Buffer['Payload'], true);
            if (is_array($payload)) {
                if (array_key_exists('last_seen', $payload)) {
                    //Last Seen ist nicht in den Exposes enthalten, deswegen hier.
                    $this->RegisterVariableInteger('Z2M_LastSeen', $this->Translate('Last Seen'), '~UnixTimestamp');
                    $this->SetValue('Z2M_LastSeen', ($payload['last_seen'] / 1000));
                }
                if (array_key_exists('execute_if_off', $payload)) {
                    $this->handleStateChange('execute_if_off', 'Z2M_ExecuteIfOff', 'Execute If Off', $payload);
                }
                if (array_key_exists('illumination', $payload)) {
                    $this->SetValue('Z2M_Illumination', $payload['illumination']);
                }
                if (array_key_exists('occupancy_sensitivity', $payload)) {
                    $this->SetValue('Z2M_OccupancySensitivity', $payload['occupancy_sensitivity']);
                }
                if (array_key_exists('smoke_alarm_state', $payload)) {
                    $this->handleStateChange('smoke_alarm_state', 'Z2M_SmokeAlarmState', 'Smoke Alarm State', $payload);
                }
                if (array_key_exists('intruder_alarm_state', $payload)) {
                    $this->handleStateChange('intruder_alarm_state', 'Z2M_IntruderAlarmState', 'Intruder Alarm State', $payload);
                }
                if (array_key_exists('voc_index', $payload)) {
                    $this->SetValue('Z2M_VOCIndex', $payload['voc_index']);
                }
                if (array_key_exists('auto_off', $payload)) {
                    $this->handleStateChange('auto_off', 'Z2M_AutoOff', 'Auto Off', $payload);
                }
                if (array_key_exists('schedule_settings', $payload)) {
                    $this->SetValue('Z2M_ScheduleSettings', $payload['schedule_settings']);
                }
                if (array_key_exists('schedule', $payload)) {
                    $this->handleStateChange('schedule', 'Z2M_Schedule', 'Schedule', $payload);
                }
                if (array_key_exists('valve_alarm', $payload)) {
                    $this->handleStateChange('valve_alarm', 'Z2M_ValveAlarm', 'Valve Alarm', $payload);
                }
                if (array_key_exists('setup', $payload)) {
                    $this->handleStateChange('setup', 'Z2M_Setup', 'Setup', $payload);
                }
                if (array_key_exists('calibrated', $payload)) {
                    $this->handleStateChange('calibrated', 'Z2M_Calibration', 'Calibrated', $payload);
                }
                if (array_key_exists('external_temperature_input', $payload)) {
                    $this->SetValue('Z2M_ExternalTemperatureInput', $payload['external_temperature_input']);
                }
                if (array_key_exists('calibrate', $payload)) {
                    $this->SetValue('Z2M_Calibrate', $payload['calibrate']);
                }
                if (array_key_exists('voltage_a', $payload)) {
                    $this->SetValue('Z2M_VoltageA', $payload['voltage_a']);
                }
                if (array_key_exists('voltage_b', $payload)) {
                    $this->SetValue('Z2M_VoltageB', $payload['voltage_b']);
                }
                if (array_key_exists('voltage_c', $payload)) {
                    $this->SetValue('Z2M_VoltageC', $payload['voltage_c']);
                }
                if (array_key_exists('voltage_X', $payload)) {
                    $this->SetValue('Z2M_VoltageX', $payload['voltage_X']);
                }
                if (array_key_exists('voltage_x', $payload)) {
                    $this->SetValue('Z2M_VoltageX', $payload['voltage_x']);
                }
                if (array_key_exists('voltage_Y', $payload)) {
                    $this->SetValue('Z2M_VoltageY', $payload['voltage_Y']);
                }
                if (array_key_exists('voltage_y', $payload)) {
                    $this->SetValue('Z2M_VoltageX', $payload['voltage_y']);
                }
                if (array_key_exists('voltage_Z', $payload)) {
                    $this->SetValue('Z2M_VoltageZ', $payload['voltage_Z']);
                }
                if (array_key_exists('voltage_z', $payload)) {
                    $this->SetValue('Z2M_VoltageZ', $payload['voltage_X']);
                }
                if (array_key_exists('power_a', $payload)) {
                    $this->SetValue('Z2M_PowerA', $payload['power_a']);
                }
                if (array_key_exists('power_b', $payload)) {
                    $this->SetValue('Z2M_PowerB', $payload['power_b']);
                }
                if (array_key_exists('power_c', $payload)) {
                    $this->SetValue('Z2M_PowerC', $payload['power_c']);
                }
                if (array_key_exists('power_X', $payload)) {
                    $this->SetValue('Z2M_PowerX', $payload['power_X']);
                }
                if (array_key_exists('power_y', $payload)) {
                    $this->SetValue('Z2M_PowerY', $payload['power_y']);
                }
                if (array_key_exists('power_Y', $payload)) {
                    $this->SetValue('Z2M_PowerY', $payload['power_Y']);
                }
                if (array_key_exists('power_z', $payload)) {
                    $this->SetValue('Z2M_PowerZ', $payload['power_z']);
                }
                if (array_key_exists('power_Z', $payload)) {
                    $this->SetValue('Z2M_PowerZ', $payload['power_Z']);
                }
                if (array_key_exists('current_a', $payload)) {
                    $this->SetValue('Z2M_CurrentA', $payload['current_a']);
                }
                if (array_key_exists('current_b', $payload)) {
                    $this->SetValue('Z2M_CurrentB', $payload['current_b']);
                }
                if (array_key_exists('current_c', $payload)) {
                    $this->SetValue('Z2M_CurrentC', $payload['current_c']);
                }
                if (array_key_exists('current_X', $payload)) {
                    $this->SetValue('Z2M_CurrentX', $payload['current_X']);
                }
                if (array_key_exists('current_x', $payload)) {
                    $this->SetValue('Z2M_CurrentX', $payload['current_x']);
                }
                if (array_key_exists('current_y', $payload)) {
                    $this->SetValue('Z2M_CurrentY', $payload['current_y']);
                }
                if (array_key_exists('current_Y', $payload)) {
                    $this->SetValue('Z2M_CurrentY', $payload['current_Y']);
                }
                if (array_key_exists('current_z', $payload)) {
                    $this->SetValue('Z2M_CurrentZ', $payload['current_z']);
                }
                if (array_key_exists('current_Z', $payload)) {
                    $this->SetValue('Z2M_CurrentZ', $payload['current_Z']);
                }
                if (array_key_exists('produced_energy', $payload)) {
                    $this->SetValue('Z2M_ProducedEnergy', $payload['produced_energy']);
                }
                if (array_key_exists('temperature_periodic_report', $payload)) {
                    $this->SetValue('Z2M_TemperaturePeriodicReport', $payload['temperature_periodic_report']);
                }
                if (array_key_exists('humidity_periodic_report', $payload)) {
                    $this->SetValue('Z2M_HumidityPeriodicReport', $payload['humidity_periodic_report']);
                }
                if (array_key_exists('temperature_sensitivity', $payload)) {
                    $this->SetValue('Z2M_TemperatureSensitivity', $payload['temperature_sensitivity']);
                }
                if (array_key_exists('humidity_sensitivity', $payload)) {
                    $this->SetValue('Z2M_HumiditySensitivity', $payload['humidity_sensitivity']);
                }
                if (array_key_exists('alarm_ringtone', $payload)) {
                    $this->SetValue('Z2M_AlarmRingtone', $payload['alarm_ringtone']);
                }
                if (array_key_exists('opening_mode', $payload)) {
                    $this->SetValue('Z2M_OpeningMode', $payload['opening_mode']);
                }
                if (array_key_exists('set_upper_limit', $payload)) {
                    $this->SetValue('Z2M_SetUpperLimit', $payload['set_upper_limit']);
                }
                if (array_key_exists('set_bottom_limit', $payload)) {
                    $this->SetValue('Z2M_SetBottomLimit', $payload['set_bottom_limit']);
                }
                if (array_key_exists('temperature_alarm', $payload)) {
                    $this->SetValue('Z2M_TemperatureAlarm', $payload['temperature_alarm']);
                }
                if (array_key_exists('humidity_alarm', $payload)) {
                    $this->SetValue('Z2M_HumidityAlarm', $payload['humidity_alarm']);
                }
                if (array_key_exists('max_temperature_alarm', $payload)) {
                    $this->SetValue('Z2M_MaxTemperatureAlarm', $payload['max_temperature_alarm']);
                }
                if (array_key_exists('min_temperature_alarm', $payload)) {
                    $this->SetValue('Z2M_MinTemperatureAlarm', $payload['min_temperature_alarm']);
                }
                if (array_key_exists('max_humidity_alarm', $payload)) {
                    $this->SetValue('Z2M_MaxHumidityAlarm', $payload['max_humidity_alarm']);
                }
                if (array_key_exists('min_humidity_alarm', $payload)) {
                    $this->SetValue('Z2M_MinHumidityAlarm', $payload['min_humidity_alarm']);
                }
                if (array_key_exists('online', $payload)) {
                    $this->SetValue('Z2M_Online', $payload['online']);
                }
                if (array_key_exists('error_status', $payload)) {
                    $this->SetValue('Z2M_ErrorStatus', $payload['error_status']);
                }
                if (array_key_exists('working_day', $payload)) {
                    $this->SetValue('Z2M_WorkingDay', $payload['working_day']);
                }
                if (array_key_exists('week_day', $payload)) {
                    $this->SetValue('Z2M_WeekDay', $payload['week_day']);
                }
                if (array_key_exists('cycle_irrigation_num_times', $payload)) {
                    $this->SetValue('Z2M_CycleIrrigationNumTimes', $payload['cycle_irrigation_num_times']);
                }
                if (array_key_exists('irrigation_start_time', $payload)) {
                    $this->SetValue('Z2M_IrrigationStartTime', $payload['irrigation_start_time']);
                }
                if (array_key_exists('irrigation_end_time', $payload)) {
                    $this->SetValue('Z2M_IrrigationEndTime', $payload['irrigation_end_time']);
                }
                if (array_key_exists('last_irrigation_duration', $payload)) {
                    $this->SetValue('Z2M_LastIrrigationDuration', $payload['last_irrigation_duration']);
                }
                if (array_key_exists('water_consumed', $payload)) {
                    $this->SetValue('Z2M_WaterConsumed', $payload['water_consumed']);
                }
                if (array_key_exists('irrigation_target', $payload)) {
                    $this->SetValue('Z2M_IrrigationTarget', $payload['irrigation_target']);
                }
                if (array_key_exists('cycle_irrigation_interval', $payload)) {
                    $this->SetValue('Z2M_CycleIrrigationInterval', $payload['cycle_irrigation_interval']);
                }
                if (array_key_exists('countdown_l1', $payload)) {
                    $this->SetValue('Z2M_CountdownL1', $payload['countdown_l1']);
                }
                if (array_key_exists('countdown_l2', $payload)) {
                    $this->SetValue('Z2M_CountdownL2', $payload['countdown_l2']);
                }
                if (array_key_exists('presence_timeout', $payload)) {
                    $this->SetValue('Z2M_Presence_Timeout', $payload['presence_timeout']);
                }
                if (array_key_exists('radar_range', $payload)) {
                    $this->SetValue('Z2M_RadarRange', $payload['radar_range']);
                }
                if (array_key_exists('move_sensitivity', $payload)) {
                    $this->SetValue('Z2M_MoveSensitivity', $payload['move_sensitivity']);
                }
                if (array_key_exists('distance', $payload)) {
                    $this->SetValue('Z2M_Distance', $payload['distance']);
                }
                if (array_key_exists('power_reactive', $payload)) {
                    $this->SetValue('Z2M_PowerReactive', $payload['power_reactive']);
                }
                if (array_key_exists('illuminance_above_threshold', $payload)) {
                    $this->SetValue('Z2M_IlluminanceAboveThreshold', $payload['illuminance_above_threshold']);
                }
                if (array_key_exists('requested_brightness_percent', $payload)) {
                    $this->SetValue('Z2M_RequestedBrightnessPercent', $payload['requested_brightness_percent']);
                }
                if (array_key_exists('requested_brightness_level', $payload)) {
                    $this->SetValue('Z2M_RequestedBrightnessLevel', $payload['requested_brightness_level']);
                }
                if (array_key_exists('x_axis', $payload)) {
                    $this->SetValue('Z2M_XAxis', $payload['x_axis']);
                }
                if (array_key_exists('y_axis', $payload)) {
                    $this->SetValue('Z2M_YAxis', $payload['y_axis']);
                }
                if (array_key_exists('z_axis', $payload)) {
                    $this->SetValue('Z2M_ZAxis', $payload['z_axis']);
                }
                if (array_key_exists('power_factor', $payload)) {
                    $this->SetValue('Z2M_PowerFactor', $payload['power_factor']);
                }
                if (array_key_exists('ac_frequency', $payload)) {
                    $this->SetValue('Z2M_AcFrequency', $payload['ac_frequency']);
                }
                if (array_key_exists('valve_adapt_process', $payload)) {
                    $this->SetValue('Z2M_ValveAdaptProcess', $payload['valve_adapt_process']);
                }
                if (array_key_exists('valve_adapt_status', $payload)) {
                    $this->SetValue('Z2M_ValveAdaptStatus', $payload['valve_adapt_status']);
                }
                if (array_key_exists('indicator', $payload)) {
                    $this->handleStateChange('indicator', 'Z2M_Indicator', 'Indicator', $payload);
                }
                if (array_key_exists('small_detection_sensitivity', $payload)) {
                    $this->SetValue('Z2M_SmallDetectionSensitivity', $payload['small_detection_sensitivity']);
                }
                if (array_key_exists('small_detection_distance', $payload)) {
                    $this->SetValue('Z2M_SmallDetectionDistance', $payload['small_detection_distance']);
                }
                if (array_key_exists('medium_motion_detection_distance', $payload)) {
                    $this->SetValue('Z2M_MediumMotionDetectionDistance', $payload['medium_motion_detection_distance']);
                }
                if (array_key_exists('medium_motion_detection_sensitivity', $payload)) {
                    $this->SetValue('Z2M_MediumMotionDetectionSensitivity', $payload['medium_motion_detection_sensitivity']);
                }
                if (array_key_exists('large_motion_detection_distance', $payload)) {
                    $this->SetValue('Z2M_LargeMotionDetectionDistance', $payload['large_motion_detection_distance']);
                }
                if (array_key_exists('large_motion_detection_sensitivity', $payload)) {
                    $this->SetValue('Z2M_LargeMotionDetectionSensitivity', $payload['large_motion_detection_sensitivity']);
                }
                if (array_key_exists('motion_state', $payload)) {
                    $this->SetValue('Z2M_MotionState', $payload['motion_state']);
                }
                if (array_key_exists('detection_distance', $payload)) {
                    $this->SetValue('Z2M_DetectionDistance', $payload['detection_distance']);
                }
                if (array_key_exists('transmit_power', $payload)) {
                    $this->SetValue('Z2M_TransmitPower', $payload['transmit_power']);
                }
                if (array_key_exists('presence_sensitivity', $payload)) {
                    $this->SetValue('Z2M_PresenceSensitivity', $payload['presence_sensitivity']);
                }
                if (array_key_exists('detection_distance_min', $payload)) {
                    $this->SetValue('Z2M_DetectionDistanceMin', $payload['detection_distance_min']);
                }
                if (array_key_exists('detection_distance_max', $payload)) {
                    $this->SetValue('Z2M_DetectionDistanceMax', $payload['detection_distance_max']);
                }
                if (array_key_exists('presence_state', $payload)) {
                    $this->SetValue('Z2M_PresenceState', $payload['presence_state']);
                }
                if (array_key_exists('self_test_result', $payload)) {
                    $this->SetValue('Z2M_SelfTestResult', $payload['self_test_result']);
                }
                if (array_key_exists('presence_event', $payload)) {
                    $this->SetValue('Z2M_PresenceEvent', $payload['presence_event']);
                }
                if (array_key_exists('action_zone', $payload)) {
                    $this->SetValue('Z2M_ActionZone', $payload['action_zone']);
                }
                if (array_key_exists('device_mode', $payload)) {
                    $this->SetValue('Z2M_DeviceMode', $payload['device_mode']);
                }
                if (array_key_exists('monitoring_mode', $payload)) {
                    $this->SetValue('Z2M_MonitoringMode', $payload['monitoring_mode']);
                }
                if (array_key_exists('approach_distance', $payload)) {
                    $this->SetValue('Z2M_ApproachDistance', $payload['approach_distance']);
                }
                if (array_key_exists('reset_nopresence_status', $payload)) {
                    $this->SetValue('Z2M_ResetNopresenceStatus', $payload['reset_nopresence_status']);
                }
                if (array_key_exists('scale_protection', $payload)) {
                    $this->handleStateChange('scale_protection', 'Z2M_ScaleProtection', 'Scale Protection', $payload);
                }
                if (array_key_exists('led_indication', $payload)) {
                    $this->handleStateChange('led_indication', 'Z2M_LEDIndication', 'LED Indication', $payload);
                }
                if (array_key_exists('silence', $payload)) {
                    $this->SetValue('Z2M_Silence', $payload['silence']);
                }
                if (array_key_exists('learn_ir_code', $payload)) {
                    $this->handleStateChange('learn_ir_caode', 'Z2M_LearnIRCode', 'Learn IR Code', $payload);
                }
                if (array_key_exists('error', $payload)) {
                    $this->SetValue('Z2M_TRVError', $payload['error']);
                }
                if (array_key_exists('learned_ir_code', $payload)) {
                    $this->SetValue('Z2M_LearnedIRCode', $payload['learned_ir_code']);
                }
                if (array_key_exists('fan_mode', $payload)) {
                    $this->SetValue('Z2M_FanMode', $payload['fan_mode']);
                }
                if (array_key_exists('alarm_time', $payload)) {
                    $this->SetValue('Z2M_AlarmTime', $payload['alarm_time']);
                }
                if (array_key_exists('alarm_mode', $payload)) {
                    $this->SetValue('Z2M_AlarmMode', $payload['alarm_mode']);
                }
                if (array_key_exists('charge_state', $payload)) {
                    $this->handleStateChange('charge_state', 'Z2M_ChargeState', 'Charge State', $payload);
                }
                if (array_key_exists('alarm_melody', $payload)) {
                    $this->SetValue('Z2M_AlarmMelody', $payload['alarm_melody']);
                }
                if (array_key_exists('tamper_alarm', $payload)) {
                    $this->handleStateChange('tamper_alarm', 'Z2M_TamperAlarm', 'Tamper Alarm', $payload);
                }
                if (array_key_exists('tamper_alarm_switch', $payload)) {
                    $this->handleStateChange('tamper_alarm_switch', 'Z2M_TamperAlarmSwitch', 'Tamper Alarm Switch', $payload);
                }
                if (array_key_exists('alarm_switch', $payload)) {
                    $this->handleStateChange('alarm_switch', 'Z2M_AlarmSwitch', 'Alarm Switch', $payload);
                }
                if (array_key_exists('alarm_state', $payload)) {
                    $this->SetValue('Z2M_AlarmState', $payload['alarm_state']);
                }
                if (array_key_exists('do_not_disturb', $payload)) {
                    $this->SetValue('Z2M_DoNotDisturb', $payload['do_not_disturb']);
                }
                if (array_key_exists('color_power_on_behavior', $payload)) {
                    $this->SetValue('Z2M_ColorPowerOnBehavior', $payload['color_power_on_behavior']);
                }
                if (array_key_exists('displayed_temperature', $payload)) {
                    $this->SetValue('Z2M_DisplayedTemperature', $payload['displayed_temperature']);
                }
                if (array_key_exists('remote_temperature', $payload)) {
                    $this->SetValue('Z2M_RemoteTemperature', $payload['remote_temperature']);
                }
                if (array_key_exists('co', $payload)) {
                    $this->SetValue('Z2M_CO', $payload['co']);
                }
                if (array_key_exists('battery_state', $payload)) {
                    $this->SetValue('Z2M_BatteryState', $payload['battery_state']);
                }
                if (array_key_exists('temperature_unit', $payload)) {
                    $this->SetValue('Z2M_TemperatureUnit', $payload['temperature_unit']);
                }
                if (array_key_exists('soil_moisture', $payload)) {
                    $this->SetValue('Z2M_SoilMoisture', $payload['soil_moisture']);
                }
                if (array_key_exists('mute', $payload)) {
                    $this->SetValue('Z2M_Mute', $payload['mute']);
                }
                if (array_key_exists('mute_buzzer', $payload)) {
                    $this->SetValue('Z2M_MuteBuzzer', $payload['mute_buzzer']);
                }
                if (array_key_exists('adaptation_run_control', $payload)) {
                    $this->SetValue('Z2M_AdaptationRunControl', $payload['adaptation_run_control']);
                }
                if (array_key_exists('adaptation_run_status', $payload)) {
                    $this->SetValue('Z2M_AdaptationRunStatus', $payload['adaptation_run_status']);
                }
                if (array_key_exists('day_of_week', $payload)) {
                    $this->SetValue('Z2M_Day_Of_Week', $payload['day_of_week']);
                }
                if (array_key_exists('regulation_setpoint_offset', $payload)) {
                    $this->SetValue('Z2M_RegulationSetpointOffset', $payload['regulation_setpoint_offset']);
                }
                if (array_key_exists('load_estimate', $payload)) {
                    $this->SetValue('Z2M_LoadEstimate', $payload['load_estimate']);
                }
                if (array_key_exists('load_room_mean', $payload)) {
                    $this->SetValue('Z2M_LoadRoomMean', $payload['load_room_mean']);
                }
                if (array_key_exists('algorithm_scale_factor', $payload)) {
                    $this->SetValue('Z2M_AlgorithmScaleFactor', $payload['algorithm_scale_factor']);
                }
                if (array_key_exists('trigger_time', $payload)) {
                    $this->SetValue('Z2M_TriggerTime', $payload['trigger_time']);
                }
                if (array_key_exists('window_open_internal', $payload)) {
                    $this->SetValue('Z2M_WindowOpenInternal', $payload['window_open_internal']);
                }
                if (array_key_exists('adaptation_run_settings', $payload)) {
                    $this->SetValue('Z2M_AdaptationRunSettings', $payload['adaptation_run_settings']);
                }
                if (array_key_exists('preheat_status', $payload)) {
                    $this->SetValue('Z2M_PreheatStatus', $payload['preheat_status']);
                }
                if (array_key_exists('load_balancing_enable', $payload)) {
                    $this->SetValue('Z2M_LoadBalancingEnable', $payload['load_balancing_enable']);
                }
                if (array_key_exists('window_open_external', $payload)) {
                    $this->SetValue('Z2M_WindowOpenExternal', $payload['window_open_external']);
                }
                if (array_key_exists('window_open_feature', $payload)) {
                    $this->SetValue('Z2M_WindowOpenFeature', $payload['window_open_feature']);
                }
                if (array_key_exists('radiator_covered', $payload)) {
                    $this->SetValue('Z2M_RadiatorCovered', $payload['radiator_covered']);
                }
                if (array_key_exists('external_measured_room_sensor', $payload)) {
                    $this->SetValue('Z2M_ExternalMeasuredRoomSensor', $payload['external_measured_room_sensor']);
                }
                if (array_key_exists('occupied_heating_setpoint_scheduled', $payload)) {
                    $this->SetValue('Z2M_OccupiedHeatingSetpointScheduled', $payload['occupied_heating_setpoint_scheduled']);
                }
                if (array_key_exists('setpoint_change_source', $payload)) {
                    $this->SetValue('Z2M_SetpointChangeSource', $payload['setpoint_change_source']);
                }
                if (array_key_exists('heat_required', $payload)) {
                    $this->SetValue('Z2M_HeatRequired', $payload['heat_required']);
                }
                if (array_key_exists('heat_available', $payload)) {
                    $this->SetValue('Z2M_HeatAvailable', $payload['heat_available']);
                }
                if (array_key_exists('viewing_direction', $payload)) {
                    $this->SetValue('Z2M_ViewingDirection', $payload['viewing_direction']);
                }
                if (array_key_exists('thermostat_vertical_orientation', $payload)) {
                    $this->SetValue('Z2M_ThermostatVerticalOrientation', $payload['thermostat_vertical_orientation']);
                }
                if (array_key_exists('mounted_mode_control', $payload)) {
                    $this->SetValue('Z2M_MountedModeControl', $payload['mounted_mode_control']);
                }
                if (array_key_exists('programming_operation_mode', $payload)) {
                    $this->SetValue('Z2M_ProgrammingOperationMode', $payload['programming_operation_mode']);
                }
                if (array_key_exists('keypad_lockout', $payload)) {
                    $this->SetValue('Z2M_KeypadLockout', $payload['keypad_lockout']);
                }
                if (array_key_exists('linkage_alarm_state', $payload)) {
                    $this->SetValue('Z2M_LinkageAlarmState', $payload['linkage_alarm_state']);
                }
                if (array_key_exists('linkage_alarm', $payload)) {
                    $this->SetValue('Z2M_LinkageAlarm', $payload['linkage_alarm']);
                }
                if (array_key_exists('heartbeat_indicator', $payload)) {
                    $this->SetValue('Z2M_HeartbeatIndicator', $payload['heartbeat_indicator']);
                }
                if (array_key_exists('buzzer_manual_mute', $payload)) {
                    $this->SetValue('Z2M_BuzzerManualMute', $payload['buzzer_manual_mute']);
                }
                if (array_key_exists('buzzer_manual_alarm', $payload)) {
                    $this->SetValue('Z2M_BuzzerManualAlarm', $payload['buzzer_manual_alarm']);
                }
                if (array_key_exists('buzzer', $payload)) {
                    $this->SetValue('Z2M_Buzzer', $payload['buzzer']);
                }
                if (array_key_exists('smoke_density_dbm', $payload)) {
                    $this->SetValue('Z2M_SmokeDensitiyDBM', $payload['smoke_density_dbm']);
                }
                if (array_key_exists('display_brightness', $payload)) {
                    $this->SetValue('Z2M_DisplayBrightness', $payload['display_brightness']);
                }
                if (array_key_exists('display_ontime', $payload)) {
                    $this->SetValue('Z2M_DisplayOntime', $payload['display_ontime']);
                }
                if (array_key_exists('display_orientation', $payload)) {
                    $this->SetValue('Z2M_DisplayOrientation', $payload['display_orientation']);
                }
                if (array_key_exists('fan_state', $payload)) {
                    $this->handleStateChange('fan_state', 'Z2M_FanState', 'Fan State', $payload);
                }
                if (array_key_exists('boost', $payload)) {
                    $this->handleStateChange('boost', 'Z2M_Boost', 'Boost', $payload);
                }
                if (array_key_exists('boost_heating', $payload)) {
                    $this->handleStateChange('boost_heating', 'Z2M_BoostHeating', 'Boost Heating', $payload);
                }
                if (array_key_exists('boost_heating_countdown_time_set', $payload)) {
                    $this->SetValue('Z2M_BoostHeatingCountdownTimeSet', $payload['boost_heating_countdown_time_set']);
                }
                if (array_key_exists('valve_state', $payload)) {
                    $this->handleStateChange('valve_state', 'Z2M_ValveState', 'Valve State', $payload, ['OPEN' => true, 'CLOSED' => false]);
                }
                if (array_key_exists('eco_mode', $payload)) {
                    $this->handleStateChange('eco_mode', 'Z2M_EcoMode', 'Eco Mode', $payload);
                }
                if (array_key_exists('side', $payload)) {
                    $this->SetValue('Z2M_Side', $payload['side']);
                }
                if (array_key_exists('power_outage_count', $payload)) {
                    $this->SetValue('Z2M_PowerOutageCount', $payload['power_outage_count']);
                }
                if (array_key_exists('switch_type', $payload)) {
                    $this->SetValue('Z2M_SwitchType', $payload['switch_type']);
                }
                if (array_key_exists('indicator_mode', $payload)) {
                    $this->SetValue('Z2M_IndicatorMode', $payload['indicator_mode']);
                }
                if (array_key_exists('temperature_alarm', $payload)) {
                    $this->SetValue('Z2M_TemperatureAlarm', $payload['temperature_alarm']);
                }
                if (array_key_exists('humidity_alarm', $payload)) {
                    $this->SetValue('Z2M_HumidityAlarm', $payload['humidity_alarm']);
                }
                if (array_key_exists('alarm', $payload)) {
                    $this->SetValue('Z2M_Alarm', $payload['alarm']);
                }
                if (array_key_exists('melody', $payload)) {
                    $this->SetValue('Z2M_Melody', $payload['melody']);
                }
                if (array_key_exists('power_type', $payload)) {
                    $this->SetValue('Z2M_PowerType', $payload['power_type']);
                }
                if (array_key_exists('volume', $payload)) {
                    $this->SetValue('Z2M_Volume', $payload['volume']);
                }
                if (array_key_exists('humidity_max', $payload)) {
                    $this->SetValue('Z2M_HumidityMax', $payload['humidity_max']);
                }
                if (array_key_exists('humidity_min', $payload)) {
                    $this->SetValue('Z2M_HumidityMin', $payload['humidity_min']);
                }
                if (array_key_exists('temperature_max', $payload)) {
                    $this->SetValue('Z2M_TemperatureMax', $payload['temperature_max']);
                }
                if (array_key_exists('temperature_min', $payload)) {
                    $this->SetValue('Z2M_TemperatureMin', $payload['temperature_min']);
                }
                if (array_key_exists('backlight_mode', $payload)) {
                    if (in_array($payload['backlight_mode'], ['ON', 'OFF'])) {
                        $this->handleStateChange('backlight_mode', 'Z2M_BacklightMode', 'backlight mode', $payload);
                    } elseif (in_array($payload['backlight_mode'], ['low', 'high'])) {
                        $this->SetValue('Z2M_BacklightMode', $payload['backlight_mode']);
                    } else {
                        $this->SendDebug('backlight mode', 'Undefined State: ' . $payload['backlight_mode'], 0);
                    }
                }
                if (array_key_exists('self_test', $payload)) {
                    $this->SetValue('Z2M_SelfTest', $payload['self_test']);
                }
                if (array_key_exists('preheat', $payload)) {
                    $this->SetValue('Z2M_Preheat', $payload['preheat']);
                }
                if (array_key_exists('led_state', $payload)) {
                    $this->SetValue('Z2M_LedState', $payload['led_state']);
                }
                if (array_key_exists('duration_of_absence', $payload)) {
                    $this->SetValue('Z2M_Absence', $payload['duration_of_absence']);
                }
                if (array_key_exists('duration_of_attendance', $payload)) {
                    $this->SetValue('Z2M_Attendance', $payload['duration_of_attendance']);
                }
                if (array_key_exists('action_rate', $payload)) {
                    $this->RegisterVariableInteger('Z2M_ActionRate', $this->Translate('Action Rate'), $ProfileName);
                    $this->EnableAction('Z2M_ActionRate');
                    $this->SetValue('Z2M_ActionRate', $payload['action_rate']);
                }
                if (array_key_exists('action_level', $payload)) {
                    $this->RegisterVariableInteger('Z2M_ActionLevel', $this->Translate('Action Level'), $ProfileName);
                    $this->EnableAction('Z2M_ActionLevel');
                    $this->SetValue('Z2M_ActionLevel', $payload['action_level']);
                }
                if (array_key_exists('action_step_size', $payload)) {
                    $this->SetValue('Z2M_ActionStepSize', $payload['action_step_size']);
                }
                if (array_key_exists('action_transition_time', $payload)) {
                    $this->RegisterVariableInteger('Z2M_ActionTransTime', $this->Translate('Action Transition Time'), $ProfileName);
                    $this->EnableAction('Z2M_ActionTransTime');
                    $this->SetValue('Z2M_ActionTransTime', $payload['action_transition_time']);
                }
                if (array_key_exists('action_group', $payload)) {
                    $this->SetValue('Z2M_ActionGroup', $payload['action_group']);
                }
                if (array_key_exists('action_color_temperature', $payload)) {
                    $this->SetValue('Z2M_ActionColorTemp', $payload['action_color_temperature']);
                }
                if (array_key_exists('temperature', $payload)) {
                    $this->SetValue('Z2M_Temperature', $payload['temperature']);
                }
                if (array_key_exists('temperature_l1', $payload)) {
                    $this->SetValue('Z2M_TemperatureL1', $payload['temperature_l1']);
                }
                if (array_key_exists('temperature_l2', $payload)) {
                    $this->SetValue('Z2M_TemperatureL2', $payload['temperature_l2']);
                }
                if (array_key_exists('temperature_l3', $payload)) {
                    $this->SetValue('Z2M_TemperatureL3', $payload['temperature_l3']);
                }
                if (array_key_exists('temperature_l4', $payload)) {
                    $this->SetValue('Z2M_TemperatureL4', $payload['temperature_l4']);
                }
                if (array_key_exists('temperature_l5', $payload)) {
                    $this->SetValue('Z2M_TemperatureL5', $payload['temperature_l5']);
                }
                if (array_key_exists('temperature_l6', $payload)) {
                    $this->SetValue('Z2M_TemperatureL6', $payload['temperature_l6']);
                }
                if (array_key_exists('temperature_l7', $payload)) {
                    $this->SetValue('Z2M_TemperatureL7', $payload['temperature_l7']);
                }
                if (array_key_exists('temperature_l8', $payload)) {
                    $this->SetValue('Z2M_TemperatureL8', $payload['temperature_l8']);
                }
                if (array_key_exists('device_temperature', $payload)) {
                    $this->SetValue('Z2M_DeviceTemperature', $payload['device_temperature']);
                }
                if (array_key_exists('local_temperature', $payload)) {
                    $this->SetValue('Z2M_LocalTemperature', $payload['local_temperature']);
                }
                if (array_key_exists('local_temperature_calibration', $payload)) {
                    $this->SetValue('Z2M_LocalTemperatureCalibration', $payload['local_temperature_calibration']);
                }
                if (array_key_exists('max_temperature', $payload)) {
                    $this->SetValue('Z2M_MaxTemperature', $payload['max_temperature']);
                }
                if (array_key_exists('min_temperature', $payload)) {
                    $this->SetValue('Z2M_MinTemperature', $payload['min_temperature']);
                }
                if (array_key_exists('eco_temperature', $payload)) {
                    $this->SetValue('Z2M_EcoTemperature', $payload['eco_temperature']);
                }
                if (array_key_exists('preset', $payload)) {
                    $this->SetValue('Z2M_Preset', $payload['preset']);
                }
                if (array_key_exists('away_mode', $payload)) {
                    $this->handleStateChange('away_mode', 'Z2M_AwayMode', 'Away Mode', $payload);
                }
                if (array_key_exists('away_preset_days', $payload)) {
                    $this->SetValue('Z2M_AwayPresetDays', $payload['away_preset_days']);
                }
                if (array_key_exists('away_preset_temperature', $payload)) {
                    $this->SetValue('Z2M_AwayPresetTemperature', $payload['away_preset_temperature']);
                }
                if (array_key_exists('boost_time', $payload)) {
                    $this->SetValue('Z2M_BoostTime', $payload['boost_time']);
                }
                if (array_key_exists('comfort_temperature', $payload)) {
                    $this->SetValue('Z2M_ComfortTemperature', $payload['comfort_temperature']);
                }
                if (array_key_exists('eco_temperature', $payload)) {
                    $this->SetValue('Z2M_EcoTemperature', $payload['eco_temperature']);
                }
                if (array_key_exists('current_heating_setpoint', $payload)) {
                    $this->SetValue('Z2M_CurrentHeatingSetpoint', $payload['current_heating_setpoint']);
                }
                if (array_key_exists('current_heating_setpoint_auto', $payload)) {
                    $this->SetValue('Z2M_CurrentHeatingSetpoint', $payload['current_heating_setpoint_auto']);
                }
                if (array_key_exists('occupied_heating_setpoint', $payload)) {
                    $this->SetValue('Z2M_OccupiedHeatingSetpoint', $payload['occupied_heating_setpoint']);
                }
                if (array_key_exists('pi_heating_demand', $payload)) {
                    $this->SetValue('Z2M_PiHeatingDemand', $payload['pi_heating_demand']);
                }
                if (array_key_exists('system_mode', $payload)) {
                    $this->SetValue('Z2M_SystemMode', $payload['system_mode']);
                }
                if (array_key_exists('running_state', $payload)) {
                    $this->SetValue('Z2M_RunningState', $payload['running_state']);
                }
                if (array_key_exists('sensor', $payload)) {
                    $this->SetValue('Z2M_Sensor', $payload['sensor']);
                }
                if (array_key_exists('linkquality', $payload)) {
                    $this->SetValue('Z2M_Linkquality', $payload['linkquality']);
                }
                if (array_key_exists('valve_position', $payload)) {
                    $this->SetValue('Z2M_ValvePosition', $payload['valve_position']);
                }
                if (array_key_exists('humidity', $payload)) {
                    $this->SetValue('Z2M_Humidity', $payload['humidity']);
                }
                if (array_key_exists('pressure', $payload)) {
                    $this->SetValue('Z2M_Pressure', $payload['pressure']);
                }
                if (array_key_exists('battery', $payload)) {
                    $this->SetValue('Z2M_Battery', $payload['battery']);
                }
                //Da Millivolt und Volt mit dem selben Topic verschickt wird
                if (array_key_exists('voltage', $payload)) {
                    if ($payload['voltage'] > 400) { //Es gibt wahrscheinlich keine Zigbee Geräte mit über 400 Volt
                        $this->SetValue('Z2M_Voltage', $payload['voltage'] / 1000);
                    } else {
                        $this->SetValue('Z2M_Voltage', $payload['voltage']);
                    }
                }
                if (array_key_exists('current', $payload)) {
                    $this->SetValue('Z2M_Current', $payload['current']);
                }
                if (array_key_exists('action', $payload)) {
                    $this->SetValue('Z2M_Action', $payload['action']);
                }
                if (array_key_exists('min_brightness_l1', $payload)) {
                    $this->SetValue('Z2M_MinBrightnessL1', $payload['min_brightness_l1']);
                }
                if (array_key_exists('Maxbrightness_l1', $payload)) {
                    $this->SetValue('Z2M_MaxBrightnessL1', $payload['max_brightness_l1']);
                }
                if (array_key_exists('min_brightness_l2', $payload)) {
                    $this->SetValue('Z2M_MinBrightnessL2', $payload['min_brightness_l2']);
                }
                if (array_key_exists('Maxbrightness_l2', $payload)) {
                    $this->SetValue('Z2M_MaxBrightnessL2', $payload['max_brightness_l2']);
                }
                if (array_key_exists('brightness', $payload)) {
                    $this->SetValue('Z2M_Brightness', $payload['brightness']);
                }
                if (array_key_exists('brightness_l1', $payload)) {
                    $this->SetValue('Z2M_BrightnessL1', $payload['brightness_l1']);
                }
                if (array_key_exists('brightness_l2', $payload)) {
                    $this->SetValue('Z2M_BrightnessL2', $payload['brightness_l2']);
                }
                if (array_key_exists('brightness_rgb', $payload)) {
                    $this->EnableAction('Z2M_BrightnessRGB');
                    $this->SetValue('Z2M_BrightnessRGB', $payload['brightness_rgb']);
                }
                if (array_key_exists('brightness_cct', $payload)) {
                    $this->EnableAction('Z2M_BrightnessCCT');
                    $this->SetValue('Z2M_BrightnessCCT', $payload['brightness_CCT']);
                }
                if (array_key_exists('brightness_white', $payload)) {
                    $this->SetValue('Z2M_BrightnessWhite', $payload['brightness_white']);
                }
                if (array_key_exists('position', $payload)) {
                    $this->SetValue('Z2M_Position', $payload['position']);
                }
                if (array_key_exists('position_left', $payload)) {
                    $this->SetValue('Z2M_PositionLeft', $payload['position_left']);
                }
                if (array_key_exists('position_right', $payload)) {
                    $this->SetValue('Z2M_PositionRight', $payload['position_right']);
                }
                if (array_key_exists('motor_speed', $payload)) {
                    $this->SetValue('Z2M_MotorSpeed', $payload['motor_speed']);
                }
                if (array_key_exists('region_id', $payload)) {
                    $this->SetValue('Z2M_RegionID', $payload['region_id']);
                }
                if (array_key_exists('occupancy', $payload)) {
                    $this->SetValue('Z2M_Occupancy', $payload['occupancy']);
                }
                if (array_key_exists('occupancy_timeout', $payload)) {
                    $this->SetValue('Z2M_OccupancyTimeout', $payload['occupancy_timeout']);
                }
                if (array_key_exists('motion_sensitivity', $payload)) {
                    $this->SetValue('Z2M_MotionSensitivity', $payload['motion_sensitivity']);
                }
                if (array_key_exists('presence', $payload)) {
                    $this->SetValue('Z2M_Presence', $payload['presence']);
                }
                if (array_key_exists('motion', $payload)) {
                    $this->SetValue('Z2M_Motion', $payload['motion']);
                }
                if (array_key_exists('motion_direction', $payload)) {
                    $this->SetValue('Z2M_MotionDirection', $payload['motion_direction']);
                }
                if (array_key_exists('motor_direction', $payload)) {
                    $this->SetValue('Z2M_MotorDirection', $payload['motor_direction']);
                }
                if (array_key_exists('scene', $payload)) {
                    $this->LogMessage('Please contact module developer. Undefined variable: scene', KL_WARNING);
                    //$this->RegisterVariableString('Z2M_Scene', $this->Translate('Scene'), '');
                    //$this->SetValue('Z2M_Scene', $payload['scene']);
                }
                if (array_key_exists('motion_speed', $payload)) {
                    $this->SetValue('Z2M_MotionSpeed', $payload['motion_speed']);
                }
                if (array_key_exists('led_enable', $payload)) {
                    $this->SetValue('Z2M_LEDEnable', $payload['led_enable']);
                }
                if (array_key_exists('replace_filter', $payload)) {
                    $this->SetValue('Z2M_ReplaceFilter', $payload['replace_filter']);
                }
                if (array_key_exists('filter_age', $payload)) {
                    $this->SetValue('Z2M_FilterAge', $payload['filter_age']);
                }
                if (array_key_exists('fan_speed', $payload)) {
                    $this->SetValue('Z2M_FanSpeed', $payload['fan_speed']);
                }
                if (array_key_exists('air_quality', $payload)) {
                    $this->SetValue('Z2M_AirQuality', $payload['air_quality']);
                }
                if (array_key_exists('radar_sensitivity', $payload)) {
                    $this->SetValue('Z2M_RadarSensitivity', $payload['radar_sensitivity']);
                }
                if (array_key_exists('radar_scene', $payload)) {
                    $this->SetValue('Z2M_RadarScene', $payload['radar_scene']);
                }
                if (array_key_exists('motor_working_mode', $payload)) {
                    $this->SetValue('Z2M_MotorWorkingMode', $payload['motor_working_mode']);
                }
                if (array_key_exists('detection_interval', $payload)) {
                    $this->SetValue('Z2M_DetectionInterval', $payload['detection_interval']);
                }
                if (array_key_exists('control', $payload)) {
                    $this->SetValue('Z2M_Control', $payload['control']);
                }
                if (array_key_exists('mode', $payload)) {
                    $this->SetValue('Z2M_Mode', $payload['mode']);
                }
                if (array_key_exists('week', $payload)) {
                    $this->SetValue('Z2M_Week', $payload['week']);
                }
                if (array_key_exists('control_back_mode', $payload)) {
                    $this->SetValue('Z2M_ControlBackMode', $payload['control_back_mode']);
                }
                if (array_key_exists('border', $payload)) {
                    $this->SetValue('Z2M_Border', $payload['border']);
                }
                if (array_key_exists('illuminance', $payload)) {
                    $this->SetValue('Z2M_Illuminance', $payload['illuminance']);
                }
                if (array_key_exists('illuminance_lux', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux', $payload['illuminance_lux']);
                    }
                }
                if (array_key_exists('illuminance_lux_l1', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l1') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l1', $payload['illuminance_lux_l1']);
                    }
                }
                if (array_key_exists('illuminance_lux_l2', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l2') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l2', $payload['illuminance_lux_l2']);
                    }
                }
                if (array_key_exists('illuminance_lux_l3', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l3') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l3', $payload['illuminance_lux_l3']);
                    }
                }
                if (array_key_exists('illuminance_lux_l4', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l4') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l4', $payload['illuminance_lux_l4']);
                    }
                }
                if (array_key_exists('illuminance_lux_l5', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l5') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l5', $payload['illuminance_lux_l5']);
                    }
                }
                if (array_key_exists('illuminance_lux_l6', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l6') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l6', $payload['illuminance_lux_l6']);
                    }
                }
                if (array_key_exists('illuminance_lux_l7', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l7') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l7', $payload['illuminance_lux_l7']);
                    }
                }
                if (array_key_exists('illuminance_lux_l8', $payload)) {
                    if (@$this->GetIDForIdent('Z2M_Illuminance_Lux_l8') > 0) {
                        $this->SetValue('Z2M_Illuminance_Lux_l8', $payload['illuminance_lux_l8']);
                    }
                }
                if (array_key_exists('strength', $payload)) {
                    $this->SetValue('Z2M_Strength', $payload['strength']);
                }
                if (array_key_exists('water_leak', $payload)) {
                    $this->SetValue('Z2M_WaterLeak', $payload['water_leak']);
                }
                if (array_key_exists('contact', $payload)) {
                    $this->SetValue('Z2M_Contact', $payload['contact']);
                }
                if (array_key_exists('carbon_monoxide', $payload)) {
                    $this->SetValue('Z2M_CarbonMonoxide', $payload['carbon_monoxide']);
                }
                if (array_key_exists('smoke', $payload)) {
                    $this->SetValue('Z2M_Smoke', $payload['smoke']);
                }
                if (array_key_exists('smoke_density', $payload)) {
                    $this->SetValue('Z2M_SmokeDensity', $payload['smoke_density']);
                }
                if (array_key_exists('tamper', $payload)) {
                    $this->SetValue('Z2M_Tamper', $payload['tamper']);
                }
                if (array_key_exists('battery_low', $payload)) {
                    $this->SetValue('Z2M_Battery_Low', $payload['battery_low']);
                }
                if (array_key_exists('action_angle', $payload)) {
                    $this->SetValue('Z2M_ActionAngle', $payload['action_angle']);
                }
                if (array_key_exists('angle_x', $payload)) {
                    $this->SetValue('Z2M_Angle_X', $payload['angle_x']);
                }
                if (array_key_exists('angle_y', $payload)) {
                    $this->SetValue('Z2M_Angle_Y', $payload['angle_y']);
                }
                if (array_key_exists('angle_x_absolute', $payload)) {
                    //$this->LogMessage('Please Contact Module Developer. Undefined Variable angle_x_absolute', KL_WARNING);
                    //$this->RegisterVariableFloat('Z2M_Angle_X_Absolute', $this->Translate('Angle_X_Absolute'), '');
                    $this->SetValue('Z2M_AngleXAbsolute', $payload['angle_x_absolute']);
                }
                if (array_key_exists('angle_y_absolute', $payload)) {
                    //$this->LogMessage('Please contact module developer. Undefined variable: angle_y_absolute', KL_WARNING);
                    //$this->RegisterVariableFloat('Z2M_Angle_Y_Absolute', $this->Translate('Angle_Y_Absolute'), '');
                    $this->SetValue('Z2M_AngleYAbsolute', $payload['angle_y_absolute']);
                }
                if (array_key_exists('angle_z', $payload)) {
                    $this->SetValue('Z2M_Angle_Z', $payload['angle_z']);
                }
                if (array_key_exists('action_from_side', $payload)) {
                    $this->SetValue('Z2M_ActionFromSide', $payload['action_from_side']);
                }
                if (array_key_exists('action_side', $payload)) {
                    $this->SetValue('Z2M_ActionSide', $payload['action_side']);
                }
                if (array_key_exists('action_to_side', $payload)) {
                    $this->SetValue('Z2M_ActionToSide', $payload['action_to_side']);
                }
                if (array_key_exists('power', $payload)) {
                    $this->SetValue('Z2M_Power', $payload['power']);
                }
                if (array_key_exists('consumer_connected', $payload)) {
                    $this->SetValue('Z2M_Consumer_Connected', $payload['consumer_connected']);
                }
                if (array_key_exists('energy', $payload)) {
                    $this->SetValue('Z2M_Energy', $payload['energy']);
                }
                if (array_key_exists('overload_protection', $payload)) {
                    $this->SetValue('Z2M_OverloadProtection', $payload['overload_protection']);
                }
                if (array_key_exists('duration', $payload)) {
                    $this->SetValue('Z2M_Duration', $payload['duration']);
                }
                if (array_key_exists('gas_value', $payload)) {
                    $this->SetValue('Z2M_GasValue', $payload['gas_value']);
                }
                if (array_key_exists('gas', $payload)) {
                    $this->SetValue('Z2M_Gas', $payload['gas']);
                }
                if (array_key_exists('action_duration', $payload)) {
                    $this->SetValue('Z2M_ActionDuration', $payload['action_duration']);
                }
                if (array_key_exists('percent_state', $payload)) {
                    $this->SetValue('Z2M_PercentState', $payload['percent_state']);
                }
                if (array_key_exists('color', $payload)) {
                    if (array_key_exists('x', $payload['color'])) {
                        $this->SendDebug(__FUNCTION__ . ' Color', $payload['color']['x'], 0);
                        if (array_key_exists('brightness', $payload)) {
                            $RGBColor = ltrim($this->xyToHEX($payload['color']['x'], $payload['color']['y'], $payload['brightness']), '#');
                        } else {
                            $RGBColor = ltrim($this->xyToHEX($payload['color']['x'], $payload['color']['y'], 255), '#');
                        }
                        $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                        $this->SetValue('Z2M_Color', hexdec(($RGBColor)));
                    } elseif (array_key_exists('hue', $payload['color']) && array_key_exists('saturation', $payload['color'])) {
                        $RGBColor = ltrim($this->HSToRGB($payload['color']['hue'], $payload['color']['saturation'], 255), '#');
                        $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                        $this->SetValue('Z2M_ColorHS', hexdec($RGBColor));
                    }
                }
                if (array_key_exists('color_rgb', $payload)) {
                    $this->SendDebug(__FUNCTION__ . ':: Color X', $payload['color_rgb']['x'], 0);
                    $this->SendDebug(__FUNCTION__ . ':: Color Y', $payload['color_rgb']['y'], 0);
                    if (array_key_exists('brightness_rgb', $payload)) {
                        $RGBColor = ltrim($this->xyToHEX($payload['color_rgb']['x'], $payload['color_rgb']['y'], $payload['brightness_rgb']), '#');
                    } else {
                        $RGBColor = ltrim($this->xyToHEX($payload['color_rgb']['x'], $payload['color_rgb']['y']), '#');
                    }
                    $this->SendDebug(__FUNCTION__ . ' Color :: RGB HEX', $RGBColor, 0);
                    $this->SetValue('Z2M_ColorRGB', hexdec(($RGBColor)));
                }
                if (array_key_exists('sensitivity', $payload)) {
                    $this->SetValue('Z2M_Sensitivity', $payload['sensitivity']);
                    $this->EnableAction('Z2M_Sensitivity');
                }
                if (array_key_exists('color_temp', $payload)) {
                    $this->SetValue('Z2M_ColorTemp', $payload['color_temp']);
                    //Color Temperature in Kelvin
                    if ($payload['color_temp'] > 0) {
                        $this->SetValue('Z2M_ColorTempKelvin', 1000000 / $payload['color_temp']); //Convert to Kelvin
                    }
                }
                if (array_key_exists('color_temp_rgb', $payload)) {
                    $this->SetValue('Z2M_ColorTempRGB', $payload['color_temp_rgb']);
                    if ($payload['color_temp_rgb'] > 0) {
                        $this->SetValue('Z2M_ColorTempRGBKelvin', 1000000 / $payload['color_temp_rgb']); //Convert to Kelvin
                    }
                }
                if (array_key_exists('color_temp_cct', $payload)) {
                    $this->SetValue('Z2M_ColorTempCCT', $payload['color_temp_cct']);
                    if ($payload['color_temp_cct'] > 0) {
                        $this->SetValue('Z2M_ColorTempCCTKelvin', 1000000 / $payload['color_temp_cct']); //Convert to Kelvin
                    }
                }
                if (array_key_exists('color_temp_startup_rgb', $payload)) {
                    $this->SetValue('Z2M_ColorTempStartupRGB', $payload['color_temp_startup_rgb']);
                    $this->EnableAction('Z2M_ColorTempStartupRGB');
                }
                if (array_key_exists('color_temp_startup_cct', $payload)) {
                    $this->SetValue('Z2M_ColorTempStartupCCT', $payload['color_temp_startup_cct']);
                    $this->EnableAction('Z2M_ColorTempStartupCCT');
                }
                if (array_key_exists('color_temp_startup', $payload)) {
                    $this->SetValue('Z2M_ColorTempStartup', $payload['color_temp_startup']);
                    $this->EnableAction('Z2M_ColorTempStartup');
                }
                if (array_key_exists('state', $payload)) {
                    if (in_array($payload['state'], ['ON', 'OFF'])) {
                        $this->handleStateChange('state', 'Z2M_State', 'State', $payload, );
                    } elseif (in_array($payload['state'], ['OPEN', 'CLOSE', 'STOP', 'move', 'presence', 'none'])) {
                        $this->SetValue('Z2M_State', $payload['state']);
                    } else {
                        $this->SendDebug('State', 'Undefined State: ' . $payload['state'], 0);
                    }
                }
                if (array_key_exists('led_disabled_night', $payload)) {
                    $this->SetValue('Z2M_LEDDisabledNight', $payload['led_disabled_night']);
                }
                if (array_key_exists('state_rgb', $payload)) {
                    $this->handleStateChange('state_rgb', 'Z2M_StateRGB', 'State_rgb', $payload, );
                    $this->EnableAction('Z2M_StateRGB');
                }
                if (array_key_exists('state_cct', $payload)) {
                    $this->handleStateChange('state_cct', 'Z2M_StateCCT', 'State_cct', $payload, );
                    $this->EnableAction('Z2M_StateCCT');
                }
                if (array_key_exists('state_white', $payload)) {
                    $this->handleStateChange('state_white', 'Z2M_StateWhite', 'State White', $payload);
                }
                if (array_key_exists('power_outage_memory', $payload)) {
                    $this->SetValue('Z2M_PowerOutageMemory', $payload['power_outage_memory']);
                }
                if (array_key_exists('power_on_behavior', $payload)) {
                    $this->SetValue('Z2M_PowerOnBehavior', $payload['power_on_behavior']);
                }
                if (array_key_exists('power_on_behavior_l1', $payload)) {
                    $this->SetValue('Z2M_PowerOnBehaviorL1', $payload['power_on_behavior_l1']);
                }
                if (array_key_exists('power_on_behavior_l2', $payload)) {
                    $this->SetValue('Z2M_PowerOnBehaviorL2', $payload['power_on_behavior_l2']);
                }
                if (array_key_exists('power_on_behavior_l3', $payload)) {
                    $this->SetValue('Z2M_PowerOnBehaviorL3', $payload['power_on_behavior_l3']);
                }
                if (array_key_exists('power_on_behavior_l4', $payload)) {
                    $this->SetValue('Z2M_PowerOnBehaviorL4', $payload['power_on_behavior_l4']);
                }
                if (array_key_exists('state_l1', $payload)) {
                    $this->handleStateChange('state_l1', 'Z2M_Statel1', 'State 1', $payload);
                }
                if (array_key_exists('state_l2', $payload)) {
                    $this->handleStateChange('state_l2', 'Z2M_Statel2', 'State 2', $payload);
                }
                if (array_key_exists('state_l3', $payload)) {
                    $this->handleStateChange('state_l3', 'Z2M_Statel3', 'State 3', $payload);
                }
                if (array_key_exists('state_l4', $payload)) {
                    $this->handleStateChange('state_l4', 'Z2M_Statel4', 'State 4', $payload);
                }
                if (array_key_exists('state_l5', $payload)) {
                    $this->handleStateChange('state_l5', 'Z2M_Statel5', 'State 5', $payload);
                }
                if (array_key_exists('state_l6', $payload)) {
                    $this->handleStateChange('state_l6', 'Z2M_Statel6', 'State 6', $payload);
                }
                if (array_key_exists('state_l7', $payload)) {
                    $this->handleStateChange('state_l7', 'Z2M_Statel7', 'State 7', $payload);
                }
                if (array_key_exists('state_l8', $payload)) {
                    $this->handleStateChange('state_l8', 'Z2M_Statel8', 'State 8', $payload);
                }
                if (array_key_exists('state_left', $payload)) {
                    if (in_array($payload['state_left'], ['ON', 'OFF'])) {
                        $this->handleStateChange('state_left', 'Z2M_state_left', 'State left', $payload);
                    } elseif (in_array($payload['state_left'], ['OPEN', 'CLOSE', 'STOP'])) {
                        $this->SetValue('Z2M_state_left', $payload['state_left']);
                    } else {
                        $this->SendDebug('State left', 'Undefined State: ' . $payload['state_left'], 0);
                    }
                }
                if (array_key_exists('state_right', $payload)) {
                    if (in_array($payload['state_right'], ['ON', 'OFF'])) {
                        $this->handleStateChange('state_right', 'Z2M_state_right', 'State Right', $payload);
                    } elseif (in_array($payload['state_right'], ['OPEN', 'CLOSE', 'STOP'])) {
                        $this->SetValue('Z2M_state_right', $payload['state_right']);
                    } else {
                        $this->SendDebug('State right', 'Undefined State: ' . $payload['state_right'], 0);
                    }
                }
                if (array_key_exists('window_detection', $payload)) {
                    $this->handleStateChange('window_detection', 'Z2M_WindowDetection', 'Window_Detection', $payload);
                }
                if (array_key_exists('open_window', $payload)) {
                    $this->handleStateChange('open_window', 'Z2M_OpenWindow', 'Open Window', $payload);
                }
                if (array_key_exists('window_open', $payload)) {
                    $this->handleStateChange('window_open', 'Z2M_WindowOpen', 'Window Open', $payload);
                }
                if (array_key_exists('button_lock', $payload)) {
                    $this->handleStateChange('button_lock', 'Z2M_ButtonLock', 'Button Lock', $payload);
                }
                if (array_key_exists('open_window_temperature', $payload)) {
                    $this->SetValue('Z2M_OpenWindowTemperature', $payload['open_window_temperature']);
                }
                if (array_key_exists('holiday_temperature', $payload)) {
                    $this->SetValue('Z2M_HolidayTemperature', $payload['holiday_temperature']);
                }
                if (array_key_exists('boost_timeset_countdown', $payload)) {
                    $this->SetValue('Z2M_BoostTimesetCountdown', $payload['boost_timeset_countdown']);
                }
                if (array_key_exists('frost_protection', $payload)) {
                    $this->handleStateChange('open_window', 'Z2M_OpenWindow', 'Open Window', $payload);
                }
                if (array_key_exists('heating_stop', $payload)) {
                    $this->handleStateChange('heating_stop', 'Z2M_HeatingStop', 'Heating Stop', $payload);
                }
                if (array_key_exists('test', $payload)) {
                    $this->SetValue('Z2M_Test', $payload['test']);
                }
                if (array_key_exists('valve_detection', $payload)) {
                    $this->handleStateChange('valve_detection', 'Z2M_ValveDetection', 'Valve Detection', $payload);
                }
                if (array_key_exists('auto_lock', $payload)) {
                    $this->handleStateChange('auto_lock', 'Z2M_AutoLock', 'Auto Lock', $payload);
                }
                if (array_key_exists('child_lock', $payload)) {
                    $this->handleStateChange('child_lock', 'Z2M_ChildLock', 'Child Lock', $payload, ['LOCK' => true, 'UNLOCK' => false]);
                }
                if (array_key_exists('update_available', $payload)) {
                    //Bleibt hier. gibt es nicht als Expose
                    $this->RegisterVariableBoolean('Z2M_Update', $this->Translate('Update'), '');
                    $this->SetValue('Z2M_Update', $payload['update_available']);
                }
                if (array_key_exists('voc', $payload)) {
                    $this->SetValue('Z2M_VOC', $payload['voc']);
                }
                if (array_key_exists('pm25', $payload)) {
                    $this->SetValue('Z2M_PM25', $payload['pm25']);
                }
                if (array_key_exists('co2', $payload)) {
                    $this->SetValue('Z2M_CO2', $payload['co2']);
                }
                if (array_key_exists('formaldehyd', $payload)) {
                    $this->SetValue('Z2M_Formaldehyd', $payload['formaldehyd']);
                }
                if (array_key_exists('force', $payload)) {
                    $this->SetValue('Z2M_Force', $payload['force']);
                }
                if (array_key_exists('moving', $payload)) {
                    $this->SetValue('Z2M_Moving', $payload['moving']);
                }
                if (array_key_exists('moving_left', $payload)) {
                    $this->SetValue('Z2M_MovingLeft', $payload['moving_left']);
                }
                if (array_key_exists('moving_right', $payload)) {
                    $this->SetValue('Z2M_MovingRight', $payload['moving_right']);
                }
                if (array_key_exists('trv_mode', $payload)) {
                    $this->SetValue('Z2M_TRVMode', $payload['trv_mode']);
                }
                if (array_key_exists('calibration', $payload)) {
                    $this->handleStateChange('calibration', 'Z2M_Calibration', 'Calibration', $payload);
                }
                if (array_key_exists('calibration_left', $payload)) {
                    $this->handleStateChange('calibration_left', 'Z2M_CalibrationLeft', 'Calibration Left', $payload);
                }
                if (array_key_exists('calibration_right', $payload)) {
                    $this->handleStateChange('calibration_right', 'Z2M_CalibrationRight', 'Calibration Right', $payload);
                }
                if (array_key_exists('motor_reversal', $payload)) {
                    $this->handleStateChange('motor_reversal', 'Z2M_MotorReversal', 'Motor Reversal', $payload);
                }
                if (array_key_exists('motor_reversal_left', $payload)) {
                    $this->handleStateChange('motor_reversal_left', 'Z2M_MotorReversalLeft', 'Motor Reversal Left', $payload);
                }
                if (array_key_exists('motor_reversal_right', $payload)) {
                    $this->handleStateChange('motor_reversal_right', 'Z2M_MotorReversalRight', 'Motor Reversal Right', $payload);
                }
                if (array_key_exists('calibration_time', $payload)) {
                    $this->SetValue('Z2M_CalibrationTime', $payload['calibration_time']);
                }
                if (array_key_exists('calibration_time_left', $payload)) {
                    $this->SetValue('Z2M_CalibrationTimeLeft', $payload['calibration_time_left']);
                }
                if (array_key_exists('calibration_time_right', $payload)) {
                    $this->SetValue('Z2M_CalibrationTimeRight', $payload['calibration_time_right']);
                }
                if (array_key_exists('target_distance', $payload)) {
                    $this->SetValue('Z2M_TargetDistance', $payload['target_distance']);
                }
                if (array_key_exists('minimum_range', $payload)) {
                    $this->SetValue('Z2M_MinimumRange', $payload['minimum_range']);
                }
                if (array_key_exists('maximum_range', $payload)) {
                    $this->SetValue('Z2M_MaximumRange', $payload['maximum_range']);
                }
                if (array_key_exists('deadzone_temperature', $payload)) {
                    $this->SetValue('Z2M_DeadzoneTemperature', $payload['deadzone_temperature']);
                }
                if (array_key_exists('max_temperature_limit', $payload)) {
                    $this->SetValue('Z2M_MaxTemperatureLimit', $payload['max_temperature_limit']);
                }
                if (array_key_exists('detection_delay', $payload)) {
                    $this->SetValue('Z2M_DetectionDelay', $payload['detection_delay']);
                }
                if (array_key_exists('fading_time', $payload)) {
                    $this->SetValue('Z2M_FadingTime', $payload['fading_time']);
                }
                if (array_key_exists('trigger', $payload)) {
                    $this->handleStateChange('trigger', 'Z2M_Trigger', 'Trigger', $payload);
                }
                if (array_key_exists('garage_door_contact', $payload)) {
                    $this->SetValue('Z2M_GarageDoorContact', $payload['garage_door_contact']);
                }
                if (array_key_exists('brightness_level', $payload)) {
                    $this->SetValue('Z2M_BrightnessLevel', $payload['brightness_level']);
                }
                if (array_key_exists('trigger_indicator', $payload)) {
                    $this->SetValue('Z2M_TriggerIndicator', $payload['trigger_indicator']);
                }
                if (array_key_exists('factory_reset', $payload)) {
                    $this->SetValue('Z2M_FactoryReset', $payload['factory_reset']);
                }
                if (array_key_exists('action_code', $payload)) {
                    $this->SetValue('Z2M_ActionCode', $payload['action_code']);
                }
                if (array_key_exists('action_transaction', $payload)) {
                    $this->SetValue('Z2M_ActionTransaction', $payload['action_transaction']);
                }
                if (array_key_exists('vibration', $payload)) {
                    $this->SetValue('Z2M_Vibration', $payload['vibration']);
                }
            }
        }
    }

    public function setColorExt($color, string $mode, array $params = [], string $Z2MMode = 'color')
    {
        switch ($mode) {
            case 'cie':
                $this->SendDebug(__FUNCTION__, $color, 0);
                $this->SendDebug(__FUNCTION__, $mode, 0);
                $this->SendDebug(__FUNCTION__, json_encode($params, JSON_UNESCAPED_SLASHES), 0);
                $this->SendDebug(__FUNCTION__, $Z2MMode, 0);
                if (preg_match('/^#[a-f0-9]{6}$/i', strval($color))) {
                    $color = ltrim($color, '#');
                    $color = hexdec($color);
                }
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToXy($RGB);
                if ($Z2MMode = 'color') {
                    $payload['color'] = $cie;
                    $payload['brightness'] = $cie['bri'];
                } elseif ($Z2MMode == 'color_rgb') {
                    $payload['color_rgb'] = $cie;
                } else {
                    return;
                }

                foreach ($params as $key => $value) {
                    $payload[$key] = $value;
                }

                $payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
                $this->SendDebug(__FUNCTION__, $payloadJSON, 0);
                $this->Z2MSet($payloadJSON);
                break;
            default:
                $this->SendDebug('setColor', 'Invalid Mode ' . $mode, 0);
                break;
        }
    }

    public function Z2MSet($payload)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/set';
        $Data['Payload'] = $payload;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
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

        /**
         * if (!IPS_VariableProfileExists('Z2M.RadarScene')) {
         * $this->RegisterProfileStringEx('Z2M.RadarScene', 'Menu', '', '', [
         * ['default', $this->Translate('Default'), '', 0xFFFFFF],
         * ['area', $this->Translate('Area'), '', 0x0000FF],
         * ['toilet', $this->Translate('Toilet'), '', 0x0000FF],
         * ['bedroom', $this->Translate('Bedroom'), '', 0x0000FF],
         * ['parlour', $this->Translate('Parlour'), '', 0x0000FF],
         * ['office', $this->Translate('Office'), '', 0x0000FF],
         * ['hotel', $this->Translate('Hotel'), '', 0x0000FF]
         * ]);
         * }
         */
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
        if (!IPS_VariableProfileExists('Z2M.ChargeState')) {
            $this->RegisterProfileBooleanEx('Z2M.ChargeState', 'Battery', '', '', [
                [false, 'Kein laden',  '', 0xFF0000],
                [true, 'wird geladen',  '', 0x00FF00]
            ]);
        }
        if (!IPS_VariableProfileExists('Z2M.AutoLock')) {
            $this->RegisterProfileBooleanEx('Z2M.AutoLock', 'Key', '', '', [
                [false, $this->Translate('Manual'),  '', 0xFF0000],
                [true, $this->Translate('Auto'),  '', 0x00FF00]
            ]);
        }
        if (!IPS_VariableProfileExists('Z2M.ValveState')) {
            $this->RegisterProfileBooleanEx('Z2M.ValveState', 'Radiator', '', '', [
                [false, $this->Translate('Valve Closed'),  '', 0xFF0000],
                [true, $this->Translate('Valve Open'),  '', 0x00FF00]
            ]);
        }
        if (!IPS_VariableProfileExists('Z2M.WindowOpenInternal')) {
            $Associations = [];
            $Associations[] = [0, $this->Translate('Quarantine'), '', -1];
            $Associations[] = [1, $this->Translate('Windows are closed'), '', -1];
            $Associations[] = [2, $this->Translate('Hold'), '', -1];
            $Associations[] = [3, $this->Translate('Open window detected'), '', -1];
            $Associations[] = [4, $this->Translate('In window open state from external but detected closed locally'), '', -1];
            $this->RegisterProfileIntegerEx('Z2M.WindowOpenInternal', '', '', '', $Associations);
        }
    }

    protected function SetValue($ident, $value)
    {
        if (@$this->GetIDForIdent($ident)) {
            $this->SendDebug('Info :: SetValue for ' . $ident, 'Value: ' . $value, 0);
            parent::SetValue($ident, $value);
        } else {
            $this->SendDebug('Error :: No Expose for Value', 'Ident: ' . $ident, 0);
        }
    }
    private function convertIdentToPayloadKey($ident)
    {
        // Gehört zu RequestAction
        $identWithoutPrefix = str_replace('Z2M_', '', $ident);
        $payloadKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $identWithoutPrefix));
        return $payloadKey;
    }

    private function convertStateBasedOnMapping($key, $value, $variableType)
    {
        // Gehört zu RequestAction
        // Überprüfe zuerst das spezielle Mapping für den Schlüssel
        if (array_key_exists($key, $this->stateTypeMapping)) {
            $mapping = $this->stateTypeMapping[$key];
            $dataType = $mapping['dataType'] ?? 'string'; // Standard auf 'string', falls nicht definiert
            // Spezielle Konvertierung basierend auf dem Typ im Mapping
            if (isset($mapping['type'])) {
                return $this->convertState($value, $mapping['type']);
            }
            // Formatierung des Wertes basierend auf dem definierten Datentyp
            switch ($dataType) {
                case 'string':
                    return strval($value);
                case 'float':
                    $format = $mapping['format'] ?? '%f';
                    return sprintf($format, $value);
                case 'numeric':
                    return $value; // Keine Umwandlung notwendig
                default:
                    return strval($value); // Standardfall: Konvertiere zu String
            }
        }
        // Direkte Behandlung für boolesche Werte, wenn kein spezielles Mapping vorhanden ist
        if ($variableType === 0) { // Boolean
            return $value ? 'ON' : 'OFF';
        }
        // Standardbehandlung für Werte ohne spezifisches Mapping
        return is_numeric($value) ? $value : strval($value);
    }

    private function convertState($value, $type)
    {
        // Gehört zu RequestAction
        // Erweiterte Zustandsmappings
        $stateMappings = [
            'onoff'      => ['ON', 'OFF'],
            'openclose'  => ['OPEN', 'CLOSE'],
            'lockunlock' => ['LOCK', 'UNLOCK'],
            'automanual' => ['AUTO', 'MANUAL'],
            'valve'      => ['OPEN', 'CLOSED'],
        ];
        // Prüfe, ob der Zustandstyp in den Mappings vorhanden ist
        if (array_key_exists($type, $stateMappings)) {
            // Wähle den korrekten Wert basierend auf dem booleschen $value
            return $value ? $stateMappings[$type][0] : $stateMappings[$type][1];
        } else {
            // Fallback für nicht definierte Zustandstypen
            return $value ? 'true' : 'false';
        }
    }
    private function handleStateChange($payloadKey, $valueId, $debugTitle, $payload, $stateMapping = null)
    {
        // Gehört zu ReceiveData
        if (array_key_exists($payloadKey, $payload)) {
            $state = $payload[$payloadKey];
            if ($stateMapping === null) {
                $stateMapping = ['ON' => true, 'OFF' => false];
            }
            if (array_key_exists($state, $stateMapping)) {
                $this->SetValue($valueId, $stateMapping[$state]);
            } else {
                $this->SendDebug($debugTitle, 'Undefined State: ' . $state, 0);
            }
        }
    }

    private function setColor(int $color, string $mode, string $Z2MMode = 'color')
    {
        switch ($mode) {
            case 'cie':
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToXy($RGB);
                if ($Z2MMode = 'color') {
                    $payload['color'] = $cie;
                    $payload['brightness'] = $cie['bri'];
                } elseif ($Z2MMode == 'color_rgb') {
                    $payload['color_rgb'] = $cie;
                } else {
                    return;
                }
                $payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
                $this->Z2MSet($payloadJSON);
                break;
            case 'hs':
                $this->SendDebug('setColor - Input Color', json_encode($color), 0);
                if (!is_array($color)) {
                    $RGB = $this->HexToRGB($color);
                    $HSB = $this->RGBToHSB($RGB[0], $RGB[1], $RGB[2]);
                } else {
                    $RGB = $color;
                    $HSB = $this->RGBToHSB($RGB[0], $RGB[1], $RGB[2]);
                }
                $this->SendDebug('setColor - RGB Values for HSB Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);
                $HSB = $this->RGBToHSB($RGB[0], $RGB[1], $RGB[2]);
                if ($Z2MMode == 'color') {
                    $payload = [
                        'color' => [
                            'hue'        => $HSB['hue'],
                            'saturation' => $HSB['saturation'],
                        ]
                    ];
                } else {
                    return;
                }
                $payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
                $this->Z2MSet($payloadJSON);
                break;
            default:
                $this->SendDebug('setColor', 'Invalid Mode ' . $mode, 0);
                break;
        }
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
                        case 'Z2M.occupancy_sensitivity.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0x00FF00],
                                    ['high', $this->Translate('High'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.illumination.f4cbb805':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Illumination', '', '', [
                                    ['dim', $this->Translate('Dim'), '', 0x00FF00],
                                    ['bright', $this->Translate('Bright'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.Calibrate.':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Calibrate', '', '', [
                                    ['calibrate', $this->Translate('Calibrate'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.temperature_alarm.15475477':
                        case 'Z2M.humidity_alarm.15475477':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alarm', '', '', [
                                    ['lower_alarm', $this->Translate('Lower Alarm'), '', 0x00FF00],
                                    ['upper_alarm', $this->Translate('Upper Alarm'), '', 0x00FF00],
                                    ['cancel', $this->Translate('Cancel'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.alarm_ringtone.d5606273':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['melody_1', $this->Translate('Melody') . ' 1', '', 0x00FF00],
                                    ['melody_2', $this->Translate('Melody') . ' 2', '', 0x00FF00],
                                    ['melody_3', $this->Translate('Melody') . ' 3', '', 0x00FF00],
                                    ['melody_4', $this->Translate('Melody') . ' 4', '', 0x00FF00],
                                    ['melody_5', $this->Translate('Melody') . ' 5', '', 0x00FF00],

                                ]);
                            }
                            break;
                        case 'Z2M.opening_mode.724b010b':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['tilt', $this->Translate('Tilt'), '', 0x00FF00],
                                    ['lift', $this->Translate('Lift'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.set_upper_limit.70b36756':
                        case 'Z2M.set_bottom_limit.70b36756':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['SET', $this->Translate('Set'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.working_day.19c5c139':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['mon_sun', $this->Translate('Mon - Sun'), '', 0x00FF00],
                                    ['mon_fri+sat+sun', $this->Translate('Mon - Fri & Sat & Sun'), '', 0x00FF00],
                                    ['separate', $this->Translate('Seperate'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.week_day.1d251e55':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['monday', $this->Translate('Monday'), '', 0x00FF00],
                                    ['tuesday', $this->Translate('Tuesday'), '', 0x00FF00],
                                    ['wednesday', $this->Translate('Wednesday'), '', 0x00FF00],
                                    ['thursday', $this->Translate('Thursday'), '', 0x00FF00],
                                    ['friday', $this->Translate('Friday'), '', 0x00FF00],
                                    ['saturday', $this->Translate('Saturday'), '', 0x00FF00],
                                    ['sunday', $this->Translate('Sunday'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.valve_adapt_status.81ca7d32':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['ready_to_calibrate', $this->Translate('Ready To Calibrate'), '', 0x00FF00],
                                    ['calibration_in_progress', $this->Translate('Calibration in Progress'), '', 0x00FF00],
                                    ['error', $this->Translate('Error'), '', 0x00FF00],
                                    ['success', $this->Translate('Success'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.detection_distance.cae0fad1':
                        case 'Z2M.motion_state.0f5b1d2d':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['10 cm', $this->Translate('10 cm'), '', 0x00FF00],
                                    ['20 cm', $this->Translate('20 cm'), '', 0x00FF00],
                                    ['30 cm', $this->Translate('30 cm'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.motion_state.5874d5f5':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['large', $this->Translate('Large'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0x00FF00],
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['small', $this->Translate('Small'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.detection_distance.cae0fad1':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['small', $this->Translate('Small'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0x00FF00],
                                    ['large', $this->Translate('Large'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.presence_state.ffd9a501':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['none', $this->Translate('None'), '', 0xFF0000],
                                    ['present', $this->Translate('Present'), '', 0x00FF00],
                                    ['moving', $this->Translate('Moving'), '', 0x00FF00],
                                    ['presence', $this->Translate('Presence'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.self_test_result.c088393d':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['checking', $this->Translate('Checking'), '', 0xFF0000],
                                    ['success', $this->Translate('Success'), '', 0x00FF00],
                                    ['failure', $this->Translate('failure'), '', 0xFF0000],
                                    ['others', $this->Translate('others'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.presence_event.ef1acb4c':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['enter', $this->Translate('Enter'), '', 0xFF0000],
                                    ['leave', $this->Translate('Leave'), '', 0x00FF00],
                                    ['left_enter', $this->Translate('Left Enter'), '', 0x00FF00],
                                    ['right_leave', $this->Translate('Right Leave'), '', 0x00FF00],
                                    ['right_enter', $this->Translate('Right Enter'), '', 0x00FF00],
                                    ['left_leave', $this->Translate('Left Leave'), '', 0x00FF00],
                                    ['approach', $this->Translate('Approach'), '', 0x00FF00],
                                    ['Sway', $this->Translate('Left Enter'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.monitoring_mode.45923aef':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['undirected', $this->Translate('Undirected'), '', 0xFF0000],
                                    ['left_right', $this->Translate('Left/Right'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.approach_distance.a1fc888b':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['far', $this->Translate('Far'), '', 0xFF0000],
                                    ['medium', $this->Translate('Medium'), '', 0x00FF00],
                                    ['near', $this->Translate('Near'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.reset_nopresence_status':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['', $this->Translate('Reset'), '', 0xFF0000]
                                ]);
                            }
                            break;
                            case 'Z2M.device_mode.e8eb408':
                            case 'Z2M.device_mode.887c8dff':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['single_rocker', $this->Translate('Single Rocker'), '', 0xFF0000],
                                    ['single_push_button', $this->Translate('Single Push Button'), '', 0x00FF00],
                                    ['dual_rocker', $this->Translate('Dual Rocker'), '', 0x00FF00],
                                    ['dual_push_button', $this->Translate('Dual Push Button'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.alarm_mode.b39b85ae':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['alarm_sound', $this->Translate('Alarm Sound'), '', 0xFF0000],
                                    ['alarm_light', $this->Translate('Alarm Light'), '', 0x00FF00],
                                    ['alarm_sound_light', $this->Translate('Alarm Sound & Light'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.alarm_melody.65680ce3':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['melody1', $this->Translate('Melody 1'), '', 0x00FF00],
                                    ['melody2', $this->Translate('Melody 2'), '', 0x00FF00],
                                    ['melody3', $this->Translate('Melody 3'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.alarm_state.d6cc0174':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['alarm_sound', $this->Translate('Alarm Sound'), '', 0x00FF00],
                                    ['alarm_light', $this->Translate('Alarm Light'), '', 0x00FF00],
                                    ['alarm_sound_light', $this->Translate('Alarm Sound & Light'), '', 0x00FF00],
                                    ['no_alarm', $this->Translate('No Alarm'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.motor_direction.cf88002f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Shuffle', '', '', [
                                    ['back', $this->Translate('Back'), '', 0x00FF00],
                                    ['forward', $this->Translate('Forward'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.color_power_on_behavior.ae76ffdc':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['initial', $this->Translate('Initial'), '', 0x00FF00],
                                    ['previous', $this->Translate('Medium'), '', 0x00FF00],
                                    ['cutomized', $this->Translate('Customized'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.air_quality.ea904784':
                        case 'Z2M.air_quality.64f2d4b3':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['excellent', $this->Translate('Excellent'), '', 0x00FF00],
                                    ['good', $this->Translate('Good'), '', 0x00CD00],
                                    ['hazardous', $this->Translate('Hazardous'), '', 0xFF4500],
                                    ['moderate', $this->Translate('Moderate'), '', 0xEE4000],
                                    ['out_of_range', $this->Translate('Out of range'), '', 0xCD3700],
                                    ['poor', $this->Translate('poor'), '', 0xFF3030],
                                    ['unhealthy', $this->Translate('Unhealthy'), '', 0xFF0000],
                                    ['unknown', $this->Translate('Unknown'), '', 0x000000],
                                ]);
                            }
                            break;
                        case 'Z2M.displayed_temperature.f31d1694':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['target', $this->Translate('Target'), '', 0x00FF00],
                                    ['measured', $this->Translate('Medium'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.battery_state.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Battery', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0x00FF00],
                                    ['high', $this->Translate('High'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.temperature_unit.abf8ba6a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['celsius', $this->Translate('Celsius'), '', 0x00FF00],
                                    ['fahrenheit', $this->Translate('Fahrenheit'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.selftest.e0cc684':
                        case 'Z2M.selftest.784dd132':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['Test', $this->Translate('Test'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.mute_buzzer.6c8bdc62':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['Mute', $this->Translate('Mute'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.adaptation_run_control.e596b9f2':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['initiate_adaptation', $this->Translate('Initiate Adaptation'), '', 0x00FF00],
                                    ['cancel_adaptation', $this->Translate('Cancel Adaptation'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.adaptation_run_status.cc98878f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['in_progress', $this->Translate('In Progress'), '', 0x00FF00],
                                    ['found', $this->Translate('Found'), '', 0x00FF00],
                                    ['lost', $this->Translate('Lost'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.day_of_week.87770221':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['sunday', $this->Translate('Sunday'), '', 0x00FF00],
                                    ['monday', $this->Translate('Monday'), '', 0x00FF00],
                                    ['tuesday', $this->Translate('Tuesday'), '', 0x00FF00],
                                    ['wednesday', $this->Translate('Wednesday'), '', 0x00FF00],
                                    ['thursday', $this->Translate('Thursday'), '', 0x00FF00],
                                    ['Friday', $this->Translate('Friday'), '', 0x00FF00],
                                    ['saturday', $this->Translate('Saturday'), '', 0x00FF00],
                                    ['away_or_vacation', $this->Translate('Away Or Vacation'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.setpoint_change_source.2b697f02':
                        case 'Z2M.setpoint_change_source.bc4ed50':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['manual', $this->Translate('manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x00FF00],
                                    ['externally', $this->Translate('Externally'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.programming_operation_mode.5dfa482f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['setpoint', $this->Translate('Setpoint'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x00FF00],
                                    ['eco', $this->Translate('Eco'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.keypad_lockout.84f3d9b9':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['unlock', $this->Translate('Unlock'), '', 0x00FF00],
                                    ['lock1', $this->Translate('Lock 1'), '', 0x00FF00],
                                    ['lock2', $this->Translate('Lock 2'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.buzzer.cd21c09a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Alert', '', '', [
                                    ['mute', $this->Translate('Mute'), '', 0x00FF00],
                                    ['alarm', $this->Translate('Alarm'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.display_orientation.d6fc8316':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['normal', $this->Translate('Normal'), '', 0x00FF00],
                                    ['flipped', $this->Translate('Flipped'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.2b653bba':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['press_1', $this->Translate('Press 1'), '', 0x00FF00],
                                    ['press_1_and_2', $this->Translate('Press 1 and 2'), '', 0x00FF00],
                                    ['press_1_and_2_and_3', $this->Translate('Press 1 and 2 and 3'), '', 0x00FF00],
                                    ['press_1_and_2_and_4', $this->Translate('Press 1 and 2 and 4'), '', 0x00FF00],
                                    ['press_1_and_3', $this->Translate('Press 1 and 3'), '', 0x00FF00],
                                    ['press_1_and_3_and_4', $this->Translate('Press 1 and 3 and 4'), '', 0x00FF00],
                                    ['press_1_and_4', $this->Translate('Press 1 and 4'), '', 0x00FF00],
                                    ['press_2', $this->Translate('Press 2'), '', 0x00FF00],
                                    ['press_2_and_3_and_4', $this->Translate('Press 2 and 3 and 4'), '', 0x00FF00],
                                    ['press_2_and_4', $this->Translate('Press 2 and 4'), '', 0x00FF00],
                                    ['press_3', $this->Translate('Press 3'), '', 0x00FF00],
                                    ['press_3_and_4', $this->Translate('Press 3 and 4'), '', 0x00FF00],
                                    ['press_4', $this->Translate('Press 4'), '', 0x00FF00],
                                    ['press_all', $this->Translate('Press all'), '', 0x00FF00],
                                    ['press_energy_bar', $this->Translate('Press energy bar'), '', 0x00FF00],
                                    ['release', $this->Translate('Release'), '', 0x00FF00],
                                    ['short_press_2_of_2', $this->Translate('Short press 2 of 2'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.7985b4e3':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['brightness_move_down', $this->Translate('Brightness move down'), '', 0x00FF00],
                                    ['brightness_move_to_level', $this->Translate('Brightness Move To Level'), '', 0x00FF00],
                                    ['brightness_move_up', $this->Translate('Brightness move up'), '', 0x00FF00],
                                    ['brightness_stop', $this->Translate('Brightness Stop'), '', 0x00FF00],
                                    ['color_temperature_move', $this->Translate('Color Temperature Move'), '', 0x00FF00],
                                    ['hue_move', $this->Translate('Hue Move'), '', 0x00FF00],
                                    ['hue_stop', $this->Translate('Hue Stop'), '', 0x00FF00],
                                    ['move_to_saturation', $this->Translate('Move To Saturation'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.177702c6':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['press', $this->Translate('Press'), '', 0x00FF00],
                                    ['release', $this->Translate('Release'), '', 0x00FF00],
                                    ['skip_backward', $this->Translate('Skip Backward'), '', 0x00FF00],
                                    ['skip_forward', $this->Translate('Skip Forward'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.2593a6d2':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['arm_all_zones', $this->Translate('Arm All Zones'), '', 0x00FF00],
                                    ['arm_day_zones', $this->Translate('Arm Day Zones'), '', 0x00FF00],
                                    ['arm_night_zones', $this->Translate('Arm Night Zones'), '', 0x00FF00],
                                    ['disarm', $this->Translate('Disarm'), '', 0x00FF00],
                                    ['emergency', $this->Translate('Emergency'), '', 0x00FF00],
                                    ['exit_delay', $this->Translate('Exit Delay'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.d9f7f4ac':
                        case 'Z2M.action.a3d14936':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['single', $this->Translate('Single'), '', 0x00FF00],
                                    ['double', $this->Translate('Double'), '', 0x00FF00],
                                    ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                    ['long', $this->Translate('Long'), '', 0x00FF00],

                                ]);
                            }
                            break;
                        case 'Z2M.action.faa13699':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['down', $this->Translate('Down'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00],
                                    ['select_0', $this->Translate('Select 0'), '', 0x00FF00],
                                    ['select_1', $this->Translate('Select 1'), '', 0x00FF00],
                                    ['select_2', $this->Translate('Select 2'), '', 0x00FF00],
                                    ['select_3', $this->Translate('Select 3'), '', 0x00FF00],
                                    ['select_4', $this->Translate('Select 4'), '', 0x00FF00],
                                    ['select_5', $this->Translate('Select 5'), '', 0x00FF00],
                                    ['stop', $this->Translate('Stop'), '', 0x00FF00],
                                    ['up', $this->Translate('Up'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.815b927a':
                        case 'Z2M.action.b918bcb2':
                        case 'Z2M.action.555bdfc4':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['1_single', $this->Translate('1 Single'), '', 0x00FF00],
                                    ['1_double', $this->Translate('1 Double'), '', 0x00FF00],
                                    ['1_hold', $this->Translate('1 Hold'), '', 0x00FF00],
                                    ['2_single', $this->Translate('2 Single'), '', 0x00FF00],
                                    ['2_double', $this->Translate('2 Double'), '', 0x00FF00],
                                    ['2_hold', $this->Translate('2 Hold'), '', 0x00FF00],
                                    ['3_single', $this->Translate('3 Single'), '', 0x00FF00],
                                    ['3_double', $this->Translate('3 Double'), '', 0x00FF00],
                                    ['3_hold', $this->Translate('3 Hold'), '', 0x00FF00],
                                    ['4_single', $this->Translate('4 Single'), '', 0x00FF00],
                                    ['4_double', $this->Translate('4 Double'), '', 0x00FF00],
                                    ['4_hold', $this->Translate('4 Hold'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.ccd55656':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['double', $this->Translate('Double'), '', 0x00FF00],
                                    ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                    ['quadruple', $this->Translate('Quadruple'), '', 0x00FF00],
                                    ['release', $this->Translate('Release'), '', 0x00FF00],
                                    ['single', $this->Translate('Single'), '', 0x00FF00],
                                    ['triple', $this->Translate('Triple'), '', 0x00FF00]]);
                            }
                            break;
                        case 'Z2M.action.be89cdac':
                        case 'Z2M.action.c1cb007d':
                        case 'Z2M.action.be89cdac':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['brightness_move_down', $this->Translate('Brightness move down'), '', 0x00FF00],
                                    ['brightness_move_up', $this->Translate('Brightness move up'), '', 0x00FF00],
                                    ['brightness_step_down', $this->Translate('Brightness Step Down'), '', 0x00FF00],
                                    ['brightness_step_up', $this->Translate('Brightness Step Up'), '', 0x00FF00],
                                    ['brightness_stop', $this->Translate('Brightness Stop'), '', 0x00FF00],
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00],
                                    ['rotate_right', $this->Translate('Rotate Right'), '', 0x00FF00],
                                    ['rotate_left', $this->Translate('Rotate Left'), '', 0x00FF00],
                                    ['rotate_stop', $this->Translate('Rotate Stop'), '', 0x00FF00],
                                    ['skip_backward', $this->Translate('Skip Backward'), '', 0x00FF00],
                                    ['skip_forward', $this->Translate('Skip Forward'), '', 0x00FF00],
                                    ['play_pause', $this->Translate('Play Pause'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.b4ce018d':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['brightness_step_down', $this->Translate('Brightness Step Down'), '', 0x00FF00],
                                    ['brightness_step_up', $this->Translate('Brightness Step Up'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['recall_*', $this->Translate('Unknown'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.dc7fd161':
                        case 'Z2M.action.dc7fd161':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['press_1', $this->Translate('Press 1'), '', 0x00FF00],
                                    ['press_2', $this->Translate('Press 2'), '', 0x00FF00],
                                    ['press_3', $this->Translate('Press 3'), '', 0x00FF00],
                                    ['press_4', $this->Translate('Press 4'), '', 0x00FF00],
                                    ['release_1', $this->Translate('Release 1'), '', 0x00FF00],
                                    ['release_2', $this->Translate('Release 2'), '', 0x00FF00],
                                    ['release_3', $this->Translate('Release 3'), '', 0x00FF00],
                                    ['release_4', $this->Translate('Release 4'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.350f117':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['press_1', $this->Translate('Press 1'), '', 0x00FF00],
                                    ['press_1_and_3', $this->Translate('Press 1 and 3'), '', 0x00FF00],
                                    ['press_2', $this->Translate('Press 2'), '', 0x00FF00],
                                    ['press_2_and_4', $this->Translate('Press 2 and 4'), '', 0x00FF00],
                                    ['press_3', $this->Translate('Press 3'), '', 0x00FF00],
                                    ['press_4', $this->Translate('Press 4'), '', 0x00FF00],
                                    ['press_energy_bar', $this->Translate('Press Energy Bar'), '', 0x00FF00],
                                    ['release_1', $this->Translate('Release 1'), '', 0x00FF00],
                                    ['release_1_and_3', $this->Translate('Release 1 and 3'), '', 0x00FF00],
                                    ['release_2', $this->Translate('Release 2'), '', 0x00FF00],
                                    ['release_2_and_4', $this->Translate('Release 2 and 4'), '', 0x00FF00],
                                    ['release_3', $this->Translate('Release 3'), '', 0x00FF00],
                                    ['release_4', $this->Translate('Release 4'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.869d1272':
                        case 'Z2M.action.ec8cf04f':
                        case 'Z2M.action.a084bd4e':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on_press', $this->Translate('On Press'), '', 0x00FF00],
                                    ['on_press_release', $this->Translate('On Press Release'), '', 0x00FF00],
                                    ['on_hold', $this->Translate('On Hold'), '', 0x00FF00],
                                    ['on_hold_release', $this->Translate('On Hold Release'), '', 0x00FF00],
                                    ['up_press', $this->Translate('Up Press'), '', 0x00FF00],
                                    ['up_press_release', $this->Translate('Up Press Release'), '', 0x00FF00],
                                    ['up_hold', $this->Translate('Up Hold'), '', 0x00FF00],
                                    ['up_hold_release', $this->Translate('Up Hold Release'), '', 0x00FF00],
                                    ['down_press', $this->Translate('Down Press'), '', 0x00FF00],
                                    ['down_press_release', $this->Translate('Down Press Release'), '', 0x00FF00],
                                    ['down_hold', $this->Translate('Down Hold'), '', 0x00FF00],
                                    ['down_hold_release', $this->Translate('Down Hold Release'), '', 0x00FF00],
                                    ['off_press', $this->Translate('Off Press'), '', 0x00FF00],
                                    ['off_press_release', $this->Translate('Off Press Release'), '', 0x00FF00],
                                    ['off_hold', $this->Translate('Off Hold'), '', 0x00FF00],
                                    ['off_hold_release', $this->Translate('Off Hold Release'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.712e126b':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['dots_1_double_press', $this->Translate('Dots 1 Double Press'), '', 0x00FF00],
                                    ['dots_1_initial_press', $this->Translate('Dots 1 Initial Press'), '', 0x00FF00],
                                    ['dots_1_long_press', $this->Translate('Dots 1 Long Press'), '', 0x00FF00],
                                    ['dots_1_long_release', $this->Translate('Dots 1 Long Release'), '', 0x00FF00],
                                    ['dots_1_short_release', $this->Translate('Dots 1 Short Release'), '', 0x00FF00],
                                    ['dots_2_double_press', $this->Translate('Dots 2 Double Press'), '', 0x00FF00],
                                    ['dots_2_initial_press', $this->Translate('Dots 2 Initial Press'), '', 0x00FF00],
                                    ['dots_2_long_press', $this->Translate('Dots 2 Long Press'), '', 0x00FF00],
                                    ['dots_2_long_release', $this->Translate('Dots 2 Long Release'), '', 0x00FF00],
                                    ['dots_2_short_release', $this->Translate('Dots 2 Short Release'), '', 0x00FF00],
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00],
                                    ['track_next', $this->Translate('Next Track'), '', 0x00FF00],
                                    ['track_previous', $this->Translate('Previous Track'), '', 0x00FF00],
                                    ['volume_down', $this->Translate('Volume Down'), '', 0x00FF00],
                                    ['volume_down_hold', $this->Translate('Volume Down Hold'), '', 0x00FF00],
                                    ['volume_up', $this->Translate('Volume Up'), '', 0x00FF00],
                                    ['volume_up_hold', $this->Translate('Volume Up Hold'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.817f2757':
                        case 'Z2M.action.bdac7927':
                        case 'Z2M.action.301a3bd1':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['arrow_left_click', $this->Translate('Arrow Left Click'), '', 0x00FF00],
                                    ['arrow_left_hold', $this->Translate('Arrow Left Hold'), '', 0x00FF00],
                                    ['arrow_left_release', $this->Translate('Arrow Left Release'), '', 0x00FF00],
                                    ['arrow_right_click', $this->Translate('Arrow Right click'), '', 0x00FF00],
                                    ['arrow_right_hold', $this->Translate('Arrow Right Hold'), '', 0x00FF00],
                                    ['arrow_right_release', $this->Translate('Arrow Right Release'), '', 0x00FF00],
                                    ['brightness_down_hold', $this->Translate('Brightness Down Hold'), '', 0x00FF00],
                                    ['brightness_down_release', $this->Translate('Brightness Down Release'), '', 0x00FF00],
                                    ['brightness_down_click', $this->Translate('Brightness Down click'), '', 0x00FF00],
                                    ['brightness_up_click', $this->Translate('Brightness Up click'), '', 0x00FF00],
                                    ['brightness_up_hold', $this->Translate('Brightness Up Hold'), '', 0x00FF00],
                                    ['brightness_up_release', $this->Translate('Brightness Up Release'), '', 0x00FF00],
                                    ['brightness_move_down', $this->Translate('Brightness Move Down'), '', 0x00FF00],
                                    ['brightness_move_up', $this->Translate('Brightness Move Up'), '', 0x00FF00],
                                    ['brightness_stop', $this->Translate('Brightness Stop'), '', 0x00FF00],
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00]
                                ]);
                            }
                            break;
                            case 'Z2M.action.f200af18':
                                if (!IPS_VariableProfileExists($ProfileName)) {
                                    $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                        ['double', $this->Translate('Double'), '', 0x00FF00],
                                        ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                        ['release', $this->Translate('Release'), '', 0x00FF00],
                                        ['shake', $this->Translate('Shake'), '', 0x00FF00],
                                        ['single', $this->Translate('Single'), '', 0x00FF00]
                                    ]);
                                }
                            break;
                            case 'Z2M.action.bdac7927':
                                if (!IPS_VariableProfileExists($ProfileName)) {
                                    $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                        ['arrow_left_click', $this->Translate('Arrow Left Click'), '', 0x00FF00],
                                        ['arrow_left_hold', $this->Translate('Arrow Left Hold'), '', 0x00FF00],
                                        ['arrow_left_release', $this->Translate('Arrow Left Release'), '', 0x00FF00],
                                        ['arrow_right_click', $this->Translate('Arrow Right click'), '', 0x00FF00],
                                        ['arrow_right_hold', $this->Translate('Arrow Right Hold'), '', 0x00FF00],
                                        ['arrow_right_release', $this->Translate('Arrow Right Release'), '', 0x00FF00],
                                        ['brightness_down_hold', $this->Translate('Brightness Down Hold'), '', 0x00FF00],
                                        ['brightness_down_release', $this->Translate('Brightness Down Release'), '', 0x00FF00],
                                        ['brightness_down_click', $this->Translate('Brightness Down click'), '', 0x00FF00],
                                        ['brightness_up_click', $this->Translate('Brightness Up click'), '', 0x00FF00],
                                        ['brightness_up_hold', $this->Translate('Brightness Up Hold'), '', 0x00FF00],
                                        ['brightness_up_release', $this->Translate('Brightness Up Release'), '', 0x00FF00],
                                        ['toggle', $this->Translate('Toggle'), '', 0x00FF00]
                                    ]);
                                }
                            break;
                        case 'Z2M.action.29611a11':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['fall', $this->Translate('Fall'), '', 0x00FF00],
                                    ['flip180', $this->Translate('Flip 180'), '', 0x00FF00],
                                    ['flip90', $this->Translate('Flip 90'), '', 0x00FF00],
                                    ['rotate_left', $this->Translate('Rotate Left'), '', 0x00FF00],
                                    ['rotate_right', $this->Translate('Rotate Right'), '', 0x00FF00],
                                    ['shake', $this->Translate('Shake'), '', 0x00FF00],
                                    ['slide', $this->Translate('Slide'), '', 0x00FF00],
                                    ['tap', $this->Translate('Tap'), '', 0x00FF00],
                                    ['throw', $this->Translate('Throw'), '', 0x00FF00],
                                    ['wakeup', $this->Translate('Wakeup'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.47d59fde':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['1_min_inactivity', $this->Translate('1 minute inactivity'), '', 0x00FF00],
                                    ['flip180', $this->Translate('Flip 180'), '', 0x00FF00],
                                    ['flip90', $this->Translate('Flip 90'), '', 0x00FF00],
                                    ['flip_to_side', $this->Translate('Flip to side'), '', 0x00FF00],
                                    ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                    ['rotate_left', $this->Translate('Rotate Left'), '', 0x00FF00],
                                    ['rotate_right', $this->Translate('Rotate Right'), '', 0x00FF00],
                                    ['shake', $this->Translate('Shake'), '', 0x00FF00],
                                    ['side_up', $this->Translate('Side up'), '', 0x00FF00],
                                    ['slide', $this->Translate('Slide'), '', 0x00FF00],
                                    ['tap', $this->Translate('Tap'), '', 0x00FF00],
                                    ['throw', $this->Translate('Throw'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.e87c79ad':
                        case 'Z2M.action.64797105':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['left_hold', $this->Translate('Left Hold'), '', 0x00FF00],
                                    ['left_hold_release', $this->Translate('Left Hold Release'), '', 0x00FF00],
                                    ['left_press', $this->Translate('Left Press'), '', 0x00FF00],
                                    ['left_press_release', $this->Translate('Left Press Release'), '', 0x00FF00],
                                    ['right_hold', $this->Translate('Right Hold'), '', 0x00FF00],
                                    ['right_hold_release', $this->Translate('Right Hold Release'), '', 0x00FF00],
                                    ['right_press', $this->Translate('Right Press'), '', 0x00FF00],
                                    ['right_press_release', $this->Translate('Right Press Release'), '', 0x00FF00],
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.85b816e8':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['single', $this->Translate('Single'), '', 0x00FF00],
                                    ['double', $this->Translate('Double'), '', 0x00FF00],
                                    ['hold', $this->Translate('Hold'), '', 0x00FF00],
                                    ['many', $this->Translate('Many'), '', 0x00FF00],
                                    ['quadruple', $this->Translate('Quadruple'), '', 0x00FF00],
                                    ['release', $this->Translate('Release'), '', 0x00FF00],
                                    ['triple', $this->Translate('Triple'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.33dbe026':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['double_both', $this->Translate('Double Both'), '', 0x00FF00],
                                    ['double_left', $this->Translate('Double Left'), '', 0x00FF00],
                                    ['double_right', $this->Translate('Double Right'), '', 0x00FF00],
                                    ['hold_both', $this->Translate('Hold Both'), '', 0x00FF00],
                                    ['hold_left', $this->Translate('Hold Left'), '', 0x00FF00],
                                    ['hold_right', $this->Translate('Hold Right'), '', 0x00FF00],
                                    ['single_both', $this->Translate('Single Both'), '', 0x00FF00],
                                    ['single_left', $this->Translate('Single Left'), '', 0x00FF00],
                                    ['single_right', $this->Translate('Single Right'), '', 0x00FF00],
                                    ['triple_both', $this->Translate('Triple Both'), '', 0x00FF00],
                                    ['triple_left', $this->Translate('Triple Left'), '', 0x00FF00],
                                    ['triple_right', $this->Translate('Triple Right'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.14fac83':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['brightness_move_down', $this->Translate('Brightness move down'), '', 0x00FF00],
                                    ['brightness_move_up', $this->Translate('Brightness move up'), '', 0x00FF00],
                                    ['brightness_stop', $this->Translate('Brightness Stop'), '', 0x00FF00],
                                    ['brightness_move_to_level', $this->Translate('Brightness Move To Level'), '', 0x00FF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.bdac7927':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['arrow_left_click', $this->Translate('Arrow Left Click'), '', 0x00FF00],
                                    ['arrow_left_hold', $this->Translate('Arrow Left Hold'), '', 0x00FF00],
                                    ['arrow_left_release', $this->Translate('Arrow Left Release'), '', 0x00FF00],
                                    ['arrow_right_click', $this->Translate('Arrow Right Click'), '', 0x00FF00],
                                    ['arrow_right_hold', $this->Translate('Arrow Right Hold'), '', 0x00FF00],
                                    ['arrow_right_release', $this->Translate('Arrow Right Release'), '', 0x00FF00],
                                    ['brightness_down_click', $this->Translate('Brightness Down Click'), '', 0x00FF00],
                                    ['brightness_down_hold', $this->Translate('Brightness DownHold'), '', 0x00FF00],
                                    ['brightness_down_release', $this->Translate('Brightness Down Release'), '', 0x00FF00],
                                    ['brightness_up_click', $this->Translate('Brightness Up Click'), '', 0x00FF00],
                                    ['brightness_up_hold', $this->Translate('Brightness Up Hold'), '', 0x00FF00],
                                    ['brightness_up_release', $this->Translate('Brightness Up Release'), '', 0x00FF00],
                                    ['toggle', $this->Translate('toggle'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.91e7a350':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['region_1_enter', $this->Translate('Region 1 enter'), '', 0x00FF00],
                                    ['region_1_leave', $this->Translate('Region 1 leave'), '', 0x00FF00],
                                    ['region_1_occupied', $this->Translate('Region 1 occupied'), '', 0x00FF00],
                                    ['region_1_unoccupied', $this->Translate('Region 1 unoccupied'), '', 0x00FF00],
                                    ['region_2_enter', $this->Translate('Region 2 enter'), '', 0x00FF00],
                                    ['region_2_leave', $this->Translate('Region 2 leave'), '', 0x00FF00],
                                    ['region_2_occupied', $this->Translate('Region 2 occupied'), '', 0x00FF00],
                                    ['region_2_unoccupied', $this->Translate('Region 2 unoccupied'), '', 0x00FF00],
                                    ['region_3_enter', $this->Translate('Region 3 enter'), '', 0x00FF00],
                                    ['region_3_leave', $this->Translate('Region 3 leave'), '', 0x00FF00],
                                    ['region_3_occupied', $this->Translate('Region 3 occupied'), '', 0x00FF00],
                                    ['region_3_unoccupied', $this->Translate('Region 3 unoccupied'), '', 0x00FF00],
                                    ['region_4_enter', $this->Translate('Region 4 enter'), '', 0x00FF00],
                                    ['region_4_leave', $this->Translate('Region 4 leave'), '', 0x00FF00],
                                    ['region_4_occupied', $this->Translate('Region 4 occupied'), '', 0x00FF00],
                                    ['region_4_unoccupied', $this->Translate('Region 4 unoccupied'), '', 0x00FF00],
                                    ['region_5_enter', $this->Translate('Region 5 enter'), '', 0x00FF00],
                                    ['region_5_leave', $this->Translate('Region 5 leave'), '', 0x00FF00],
                                    ['region_5_occupied', $this->Translate('Region 5 occupied'), '', 0x00FF00],
                                    ['region_5_unoccupied', $this->Translate('Region 5 unoccupied'), '', 0x00FF00],
                                    ['region_6_enter', $this->Translate('Region 6 enter'), '', 0x00FF00],
                                    ['region_6_leave', $this->Translate('Region 6 leave'), '', 0x00FF00],
                                    ['region_6_occupied', $this->Translate('Region 6 occupied'), '', 0x00FF00],
                                    ['region_6_unoccupied', $this->Translate('Region 6 unoccupied'), '', 0x00FF00],
                                    ['region_7_enter', $this->Translate('Region 7 enter'), '', 0x00FF00],
                                    ['region_7_leave', $this->Translate('Region 7 leave'), '', 0x00FF00],
                                    ['region_7_occupied', $this->Translate('Region 7 occupied'), '', 0x00FF00],
                                    ['region_7_unoccupied', $this->Translate('Region 7 unoccupied'), '', 0x00FF00],
                                    ['region_8_enter', $this->Translate('Region 8 enter'), '', 0x00FF00],
                                    ['region_8_leave', $this->Translate('Region 8 leave'), '', 0x00FF00],
                                    ['region_8_occupied', $this->Translate('Region 8 occupied'), '', 0x00FF00],
                                    ['region_8_unoccupied', $this->Translate('Region 8 unoccupied'), '', 0x00FF00],
                                    ['region_9_enter', $this->Translate('Region 9 enter'), '', 0x00FF00],
                                    ['region_9_leave', $this->Translate('Region 9 leave'), '', 0x00FF00],
                                    ['region_9_occupied', $this->Translate('Region 9 occupied'), '', 0x00FF00],
                                    ['region_9_unoccupied', $this->Translate('Region 9 unoccupied'), '', 0x00FF00],
                                    ['region_10_enter', $this->Translate('Region 10 enter'), '', 0x00FF00],
                                    ['region_10_leave', $this->Translate('Region 10 leave'), '', 0x00FF00],
                                    ['region_10_occupied', $this->Translate('Region 10 occupied'), '', 0x00FF00],
                                    ['region_10_unoccupied', $this->Translate('Region 10 unoccupied'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.5a39b546':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['open', $this->Translate('Open'), '', 0x00FF00],
                                    ['stop', $this->Translate('Stop'), '', 0xFF0000],
                                    ['close', $this->Translate('Close'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.action.d779c595':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['single_both', $this->Translate('Single Both'), '', 0x00FF00],
                                    ['single_left', $this->Translate('Single Left'), '', 0xFF0000],
                                    ['single_right', $this->Translate('Single Right'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.action.c1844f92':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['button_1_hold', $this->Translate('Button 1 Hold'), '', 0x00FF00],
                                    ['button_1_release', $this->Translate('Button 1 Release'), '', 0x00FF00],
                                    ['button_1_single', $this->Translate('Button 1 Single'), '', 0x00FF00],
                                    ['button_1_double', $this->Translate('Button 1 Double'), '', 0x00FF00],
                                    ['button_1_triple', $this->Translate('Button 1 Tripple'), '', 0x00FF00],
                                    ['button_2_hold', $this->Translate('Button 2 Hold'), '', 0x00FF00],
                                    ['button_2_release', $this->Translate('Button 2 Release'), '', 0x00FF00],
                                    ['button_2_single', $this->Translate('Button 2 Single'), '', 0x00FF00],
                                    ['button_2_double', $this->Translate('Button 2 Double'), '', 0x00FF00],
                                    ['button_2_triple', $this->Translate('Button 2 Tripple'), '', 0x00FF00],
                                    ['button_3_hold', $this->Translate('Button 3 Hold'), '', 0x00FF00],
                                    ['button_3_release', $this->Translate('Button 3 Release'), '', 0x00FF00],
                                    ['button_3_single', $this->Translate('Button 3 Single'), '', 0x00FF00],
                                    ['button_3_double', $this->Translate('Button 3 Double'), '', 0x00FF00],
                                    ['button_3_triple', $this->Translate('Button 3 Tripple'), '', 0x00FF00],
                                    ['button_4_hold', $this->Translate('Button 4 Hold'), '', 0x00FF00],
                                    ['button_4_release', $this->Translate('Button 4 Release'), '', 0x00FF00],
                                    ['button_4_single', $this->Translate('Button 4 Single'), '', 0x00FF00],
                                    ['button_4_double', $this->Translate('Button 4 Double'), '', 0x00FF00],
                                    ['button_4_triple', $this->Translate('Button 4 Tripple'), '', 0x00FF00],
                                    ['button_5_hold', $this->Translate('Button 5 Hold'), '', 0x00FF00],
                                    ['button_5_release', $this->Translate('Button 5 Release'), '', 0x00FF00],
                                    ['button_5_single', $this->Translate('Button 5 Single'), '', 0x00FF00],
                                    ['button_5_double', $this->Translate('Button 5 Double'), '', 0x00FF00],
                                    ['button_5_triple', $this->Translate('Button 5 Tripple'), '', 0x00FF00],
                                    ['button_6_hold', $this->Translate('Button 6 Hold'), '', 0x00FF00],
                                    ['button_6_release', $this->Translate('Button 6 Release'), '', 0x00FF00],
                                    ['button_6_single', $this->Translate('Button 6 Single'), '', 0x00FF00],
                                    ['button_6_double', $this->Translate('Button 6 Double'), '', 0x00FF00],
                                    ['button_6_triple', $this->Translate('Button 6 Tripple'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.action.e7f91f00':
                        case 'Z2M.action.94c054ec':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['brightness_step_down', $this->Translate('Brightness Step Down'), '', 0x00FF00],
                                    ['brightness_step_up', $this->Translate('Brightness Step Up'), '', 0x00FF00],
                                    ['button_1_hold', $this->Translate('Button 1 Hold'), '', 0x00FF00],
                                    ['button_1_hold_release', $this->Translate('Button 1 Hold Release'), '', 0x00FF00],
                                    ['button_1_press', $this->Translate('Button 1 Press'), '', 0x00FF00],
                                    ['button_1_press_release', $this->Translate('Button 1 Press Release'), '', 0x00FF00],
                                    ['button_2_hold', $this->Translate('Button 2 Hold'), '', 0x00FF00],
                                    ['button_2_hold_release', $this->Translate('Button 2 Hold Release'), '', 0x00FF00],
                                    ['button_2_press', $this->Translate('Button 2 Press'), '', 0x00FF00],
                                    ['button_2_press_release', $this->Translate('Button 2 Press Release'), '', 0x00FF00],
                                    ['button_3_hold', $this->Translate('Button 3 Hold'), '', 0x00FF00],
                                    ['button_3_hold_release', $this->Translate('Button 3 Hold Release'), '', 0x00FF00],
                                    ['button_3_press', $this->Translate('Button 3 Press'), '', 0x00FF00],
                                    ['button_3_press_release', $this->Translate('Button 3 Press Release'), '', 0x00FF00],
                                    ['button_4_hold', $this->Translate('Button 4 Hold'), '', 0x00FF00],
                                    ['button_4_hold_release', $this->Translate('Button 4 Hold Release'), '', 0x00FF00],
                                    ['button_4_press', $this->Translate('Button 4 Press'), '', 0x00FF00],
                                    ['button_4_press_release', $this->Translate('Button 4 Press Release'), '', 0x00FF00],
                                    ['dial_rotate_left_fast', $this->Translate('Dial Rotate Left Fast'), '', 0x00FF00],
                                    ['dial_rotate_left_slow', $this->Translate('Dial Rotate Left Slow'), '', 0x00FF00],
                                    ['dial_rotate_left_step', $this->Translate('Dial Rotate Left Step'), '', 0x00FF00],
                                    ['dial_rotate_right_fast', $this->Translate('Dial Rotate Right Fast'), '', 0x00FF00],
                                    ['dial_rotate_right_slow', $this->Translate('Dial Rotate Right Slow'), '', 0x00FF00],
                                    ['dial_rotate_right_step', $this->Translate('Dial Rotate Right Step'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.action.5e7f11cc':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['vibration', $this->Translate('Vibration'), '', 0x00FF00],
                                    ['tilt', $this->Translate('Tilt'), '', 0xFFFF00],
                                    ['drop', $this->Translate('Drop'), '', 0xFF9900]
                                ]);
                            }
                            break;
                        case 'Z2M.gradient_scene.da30b2e':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Light', '', '', [
                                    ['blossom', $this->Translate('Blossom'), '', 0x00FF00],
                                    ['crocus', $this->Translate('Crocus'), '', 0x00FF00],
                                    ['precious', $this->Translate('Precious'), '', 0x00FF00],
                                    ['narcissa', $this->Translate('Narcissa'), '', 0x00FF00],
                                    ['beginnings', $this->Translate('Beginnings'), '', 0x00FF00],
                                    ['first_light', $this->Translate('First Light'), '', 0x00FF00],
                                    ['horizon', $this->Translate('Horizon'), '', 0x00FF00],
                                    ['valley_dawn', $this->Translate('Valley Down'), '', 0x00FF00],
                                    ['sunflare', $this->Translate('Sunflare'), '', 0x00FF00],
                                    ['emerald_flutter', $this->Translate('Emerald Flutter'), '', 0x00FF00],
                                    ['memento', $this->Translate('Memento'), '', 0x00FF00],
                                    ['resplendent', $this->Translate('Resplendent'), '', 0x00FF00],
                                    ['scarlet_dream', $this->Translate('Scarlet Dream'), '', 0x00FF00],
                                    ['lovebirds', $this->Translate('Lovebirds'), '', 0x00FF00],
                                    ['smitten', $this->Translate('Smitten'), '', 0x00FF00],
                                    ['glitz_and_glam', $this->Translate('Glitz and Glam'), '', 0x00FF00],
                                    ['promise', $this->Translate('Promise'), '', 0x00FF00],
                                    ['ruby_romance', $this->Translate('Ruby Romance'), '', 0x00FF00],
                                    ['city_of_love', $this->Translate('City of Love'), '', 0x00FF00],
                                    ['honolulu', $this->Translate('Honolulu'), '', 0x00FF00],
                                    ['savanna_sunset', $this->Translate('Savanna Sunset'), '', 0x00FF00],
                                    ['golden_pond', $this->Translate('Golden Pond'), '', 0x00FF00],
                                    ['runy_glow', $this->Translate('Runny Glow'), '', 0x00FF00],
                                    ['tropical_twilight', $this->Translate('Tropical Twilight'), '', 0x00FF00],
                                    ['miami', $this->Translate('Miami'), '', 0x00FF00],
                                    ['cancun', $this->Translate('Cancun'), '', 0x00FF00],
                                    ['rio', $this->Translate('Rio'), '', 0x00FF00],
                                    ['chinatown', $this->Translate('Chinatown'), '', 0x00FF00],
                                    ['ibiza', $this->Translate('Ibiza'), '', 0x00FF00],
                                    ['osaka', $this->Translate('Osaka'), '', 0x00FF00],
                                    ['tokyo', $this->Translate('Tokyo'), '', 0x00FF00],
                                    ['motown', $this->Translate('Motown'), '', 0x00FF00],
                                    ['fairfax', $this->Translate('Fairfax'), '', 0x00FF00],
                                    ['galaxy', $this->Translate('Galaxy'), '', 0x00FF00],
                                    ['starlight', $this->Translate('Starlight'), '', 0x00FF00],
                                    ['blood moon', $this->Translate('Blood Moon'), '', 0x00FF00],
                                    ['artic_aurora', $this->Translate('Artic Aurora'), '', 0x00FF00],
                                    ['moonlight', $this->Translate('Moonlight'), '', 0x00FF00],
                                    ['nebula', $this->Translate('Nebula'), '', 0x00FF00],
                                    ['sundown', $this->Translate('Sundown'), '', 0x00FF00],
                                    ['blue_lagoon', $this->Translate('Blue Lagoon'), '', 0x00FF00],
                                    ['palm_beach', $this->Translate('Palm Beach'), '', 0x00FF00],
                                    ['lake_placid', $this->Translate('Lake Placid'), '', 0x00FF00],
                                    ['mountain_breeze', $this->Translate('Mountain Breeze'), '', 0x00FF00],
                                    ['lake_mist', $this->Translate('Lake Mist'), '', 0x00FF00],
                                    ['ocean_dawn', $this->Translate('Ocean Dawn'), '', 0x00FF00],
                                    ['frosty_dawn', $this->Translate('Frosty Dawn'), '', 0x00FF00],
                                    ['sunday_morning', $this->Translate('Sunday Morning'), '', 0x00FF00],
                                    ['emerald_isle', $this->Translate('Emerald Isle'), '', 0x00FF00],
                                    ['spring_blossom', $this->Translate('Spring Blossom'), '', 0x00FF00],
                                    ['midsummer_sun', $this->Translate('Midsummer Sun'), '', 0x00FF00],
                                    ['autumn_gold', $this->Translate('Autumn Gold'), '', 0x00FF00],
                                    ['spring_lake', $this->Translate('Spring Lake'), '', 0x00FF00],
                                    ['winter_mountain', $this->Translate('Winter Mountain'), '', 0x00FF00],
                                    ['midwinter', $this->Translate('Midwinter'), '', 0x00FF00],
                                    ['amber_bloom', $this->Translate('Amber Bloom'), '', 0x00FF00],
                                    ['lily', $this->Translate('Lily'), '', 0x00FF00],
                                    ['painted_sky', $this->Translate('Painted Sky'), '', 0x00FF00],
                                    ['winter_beauty', $this->Translate('Winter Beauty'), '', 0x00FF00],
                                    ['orange_fields', $this->Translate('Orange Fields'), '', 0x00FF00],
                                    ['forest_adventure', $this->Translate('Forest Adventure'), '', 0x00FF00],
                                    ['blue_planet', $this->Translate('Blue Planet'), '', 0x00FF00],
                                    ['soho', $this->Translate('Soho'), '', 0x00FF00],
                                    ['vapor_wave', $this->Translate('Vapor Wave'), '', 0x00FF00],
                                    ['magneto', $this->Translate('Magneto'), '', 0x00FF00],
                                    ['tyrell', $this->Translate('Tyrell'), '', 0x00FF00],
                                    ['disturbia', $this->Translate('Disturbia'), '', 0x00FF00],
                                    ['hal', $this->Translate('Hal'), '', 0x00FF00],
                                    ['golden_star', $this->Translate('Golden Star'), '', 0x00FF00],
                                    ['under_the_tree', $this->Translate('Under the Tree'), '', 0x00FF00],
                                    ['silent_night', $this->Translate('Silent Night'), '', 0x00FF00],
                                    ['rosy_sparkle', $this->Translate('Rosy Sparkle'), '', 0x00FF00],
                                    ['festive_fun', $this->Translate('Festive Fun'), '', 0x00FF00],
                                    ['colour_burst', $this->Translate('Colour Burst'), '', 0x00FF00],
                                    ['crystalline', $this->Translate('Crystalline'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.system_mode.ba44e6f8':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['heat', $this->Translate('Heat'), '', 0x00FF00],
                                ]);
                            }
                            break;
                        case 'Z2M.switch_type.7c047117':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['toggle', $this->Translate('Toggle'), '', 0x00FF00],
                                    ['state', $this->Translate('State'), '', 0xFFFF00],
                                    ['momentary', $this->Translate('Momentary'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.indicator_mode.c2a87bbe':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['off_on', $this->Translate('Off/On'), '', 0xFFFF00],
                                    ['on_off', $this->Translate('On/Off'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.indicator_mode.593418f7':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['off/on', $this->Translate('Off/On'), '', 0xFFFF00],
                                    ['on/off', $this->Translate('On/Off'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.indicator_mode.45cba34f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0xFF00],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['off/on', $this->Translate('Off/On'), '', 0xFFFF00],
                                    ['on/off', $this->Translate('On/Off'), '', 0xFF9900],
                                ]);
                            }
                            break;
                        case 'Z2M.melody.a0adcd38':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Speaker', '', '', [
                                    ['0', $this->Translate('0'), '', 0x000000],
                                    ['1', $this->Translate('1'), '', 0x000000],
                                    ['2', $this->Translate('2'), '', 0x000000],
                                    ['3', $this->Translate('3'), '', 0x000000],
                                    ['4', $this->Translate('4'), '', 0x000000],
                                    ['5', $this->Translate('5'), '', 0x000000],
                                    ['6', $this->Translate('6'), '', 0x000000],
                                    ['7', $this->Translate('7'), '', 0x000000],
                                    ['8', $this->Translate('8'), '', 0x000000],
                                    ['9', $this->Translate('9'), '', 0x000000],
                                    ['10', $this->Translate('10'), '', 0x000000],
                                    ['11', $this->Translate('11'), '', 0x000000],
                                    ['12', $this->Translate('12'), '', 0x000000],
                                    ['13', $this->Translate('13'), '', 0x000000],
                                    ['14', $this->Translate('14'), '', 0x000000],
                                    ['15', $this->Translate('15'), '', 0x000000],
                                    ['16', $this->Translate('16'), '', 0x000000],
                                    ['17', $this->Translate('17'), '', 0x000000],
                                    ['18', $this->Translate('18'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.power_type.6557c94':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Plug', '', '', [
                                    ['battery_full', $this->Translate('Battery Full'), '', 0x00FF00],
                                    ['battery_high', $this->Translate('Battery High'), '', 0xFFFF00],
                                    ['battery_medium', $this->Translate('Battery Medium'), '', 0xFF9900],
                                    ['battery_low', $this->Translate('Battery Low'), '', 0xFF0000],
                                    ['usb', $this->Translate('USB'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.volume.b8421401':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Speaker', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFFFF00],
                                    ['high', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.backlight_mode.9e0e16e4':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Light', '', '', [
                                    ['LOW', $this->Translate('Low'), '', 0xFFA500],
                                    ['MEDIUM', $this->Translate('Medium'), '', 0xFF0000],
                                    ['HIGH', $this->Translate('High'), '', 0x000000]
                                ]);
                            }
                            // No break. Add additional comment above this line if intentional
                        case 'Z2M.backlight_mode.b964fcdc':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Light', '', '', [
                                    ['inverted', $this->Translate('Inverted'), '', 0xFFA500],
                                    ['normal', $this->Translate('Normal'), '', 0xFF0000],
                                    ['off', $this->Translate('Off'), '', 0x000000]
                                ]);
                            }
                        break;
                        case 'Z2M.system_mode.3aabe70a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['auto', $this->Translate('Auto'), '', 0xFFA500],
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['off', $this->Translate('Off'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.system_mode.e9feae72':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['off', $this->Translate('Off'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.9fca219c':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x8800FF],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['boost', $this->Translate('Boost'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.879ced8a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['programming', $this->Translate('Programming'), '', 0x8800FF],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['temporary_manual', $this->Translate('Temporary Manual'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.72d7acf2':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['auto', $this->Translate('Auto'), '', 0xFFA500],
                                    ['holiday', $this->Translate('Holiday'), '', 0xFFa500],
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.400bed67':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['hold', $this->Translate('Hold'), '', 0xFFA500],
                                    ['programm', $this->Translate('Program'), '', 0xFFa500],

                                ]);
                            }
                            break;
                        case 'Z2M.preset.1d99b46a':
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
                        case 'Z2M.preset.e1df23ef':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['comfort', $this->Translate('Comfort'), '', 0xFFFF00],
                                    ['complex', $this->Translate('Complex'), '', 0x0000FF],
                                    ['eco', $this->Translate('Eco'), '', 0x00FF00],
                                    ['manual', $this->Translate('Manual'), '', 0x00FF00],
                                    ['schedule', $this->Translate('Schedule'), '', 0x8800FF],
                                    ['boost', $this->Translate('Boost'), '', 0xFF0000],
                                    ['away', $this->Translate('Away'), '', 0xFFa500]
                                ]);
                            }
                            break;
                        case 'Z2M.preset.e4c8988a':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['auto', $this->Translate('Auto'), '', 0xFFFF00],
                                    ['manual', $this->Translate('Manual'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x00FF00],
                                    ['on', $this->Translate('On'), '', 0x00FF00]
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
                        case 'Z2M.running_state.95941f91':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['cool', $this->Translate('Cool'), '', 0x0000FF],
                                    ['heat', $this->Translate('Heat'), '', 0xFF0000],
                                    ['idle', $this->Translate('Idle'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.sensor.183d8cee':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['AL', $this->Translate('AL'), '', 0xFF0000],
                                    ['IN', $this->Translate('IN'), '', 0x00FF00],
                                    ['OU', $this->Translate('OU'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.sensor.':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['external', $this->Translate('External'), '', 0xFF0000],
                                    ['internal', $this->Translate('Internal'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.effect.988c295e':
                        case 'Z2M.effect.fe70ca86':
                        case 'Z2M.effect.efbfc77e':
                        case 'Z2M.effect.dd503500':
                        case 'Z2M.effect.5b9efbea':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['blink', $this->Translate('Blink'), '', 0x0000FF],
                                    ['breathe', $this->Translate('Breathe'), '', 0x0000FF],
                                    ['okay', $this->Translate('Okay'), '', 0x0000FF],
                                    ['channel_change', $this->Translate('Channel Change'), '', 0x0000FF],
                                    ['candle', $this->Translate('Candle'), '', 0x0000FF],
                                    ['fireplace', $this->Translate('Fireplace'), '', 0x0000FF],
                                    ['colorloop', $this->Translate('Colorloop'), '', 0x0000FF],
                                    ['sunrise', $this->Translate('Sunrise'), '', 0x0000FF],
                                    ['stop_hue_effect', $this->Translate('Stop Hue Effect'), '', 0x0000FF],
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
                        case 'Z2M.power_on_behavior.8a599b04':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['previous', $this->Translate('Previous'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.power_on_behavior.420a27e2':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Information', '', '', [
                                    ['on', $this->Translate('On'), '', 0x0000FF],
                                    ['off', $this->Translate('Off'), '', 0x0000FF],
                                    ['previous', $this->Translate('Previous'), '', 0x0000FF],
                                    ['toggle', $this->Translate('Toggle'), '', 0x0000FF]
                                ]);
                            }
                            break;
                        case 'Z2M.backlight_mode':
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
                        case 'Z2M.motion_direction.1440af33':
                        case 'Z2M.motion_direction.c4d8a6f1':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Move', '', '', [
                                    ['moving_backward', $this->Translate('moving backward'), '', 0x00FF00],
                                    ['moving_forward', $this->Translate('moving forward'), '', 0xFF0000],
                                    ['standing_still', $this->Translate('standing still'), '', 0xFFFF00]
                                ]);
                            }
                            break;
                        case 'Z2M.force.85dac8d5':
                        case 'Z2M.force.2bd28f19':
                        case 'Z2M.force.a420d592':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['normal', $this->Translate('Normal'), '', 0x00FF00],
                                    ['open', $this->Translate('Open'), '', 0xFF8800],
                                    ['close', $this->Translate('Close'), '', 0xFF0000],
                                    ['high', $this->Translate('High'), '', 0xFF0000],
                                    ['standard', $this->Translate('Standard'), '', 0xFF0000],
                                    ['very_high', $this->Translate('Very High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.moving.fe5886c':
                        case 'Z2M.moving.7ac27aed':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Move', '', '', [
                                    ['UP', $this->Translate('Up'), '', 0x00FF00],
                                    ['STOP', $this->Translate('Stop'), '', 0xFF8800],
                                    ['DOWN', $this->Translate('Down'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.moving_left':
                        case 'Z2M.moving_right':
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
                        case 'Z2M.state.7c75b7a3':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Shutter', '', '', [
                                    ['OPEN', $this->Translate('Open'), '', 0x00FF00],
                                    ['STOP', $this->Translate('Stop'), '', 0xFF0000],
                                    ['CLOSE', $this->Translate('Close'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.state.1cb7a647':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Shutter', '', '', [
                                    ['none', $this->Translate('None'), '', 0x00FF00],
                                    ['presence', $this->Translate('Presence'), '', 0xFF0000],
                                    ['move', $this->Translate('Move'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.mode.a774535':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Mode', '', '', [
                                    ['duration', $this->Translate('Duration'), '', 0x00FF00],
                                    ['capacity', $this->Translate('Capacity'), '', 0xFF8800]
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
                        case 'Z2M.mode.be3d8da4':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['morning', $this->Translate('Morning'), '', 0xFFC0CB],
                                    ['night', $this->Translate('Night'), '', 0xFFFF00]
                                ]);
                            }
                            break;
                        case 'Z2M.mode.8cfb6fd7':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['click', $this->Translate('Click'), '', 0xFFC0CB],
                                    ['program', $this->Translate('Program'), '', 0xFFFF00],
                                    ['switch', $this->Translate('Switch'), '', 0x2ACFE8]
                                ]);
                            }
                            break;
                        case 'Z2M.fan_mode.c348e40f':
                        case 'Z2M.mode.c348e40f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Intensity', '', '', [
                                    ['off', $this->Translate('Off'), '', 0xFF0000],
                                    ['auto', $this->Translate('Auto'), '', 0x00FF00],
                                    ['1', $this->Translate('1'), '', 0x00FF00],
                                    ['2', $this->Translate('2'), '', 0x00FF00],
                                    ['3', $this->Translate('3'), '', 0x000000],
                                    ['4', $this->Translate('4'), '', 0x000000],
                                    ['5', $this->Translate('5'), '', 0x000000],
                                    ['6', $this->Translate('6'), '', 0x000000],
                                    ['7', $this->Translate('7'), '', 0x000000],
                                    ['8', $this->Translate('8'), '', 0x000000],
                                    ['9', $this->Translate('9'), '', 0x000000]
                                ]);
                            }
                            break;
                        case 'Z2M.week.4e05e759':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Calendar', '', '', [
                                    ['5+2', $this->Translate('5+2'), '', 0x00FF00],
                                    ['6+1', $this->Translate('6+1'), '', 0xFF8800],
                                    ['7', $this->Translate('7'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.level.ae420ac':
                        case 'Z2M.strobe_level.ae420ac':
                        case 'Z2M.motion_sensitivity.f607dc1f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Gear', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['medium', $this->Translate('Medium'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000],
                                    ['very_high', $this->Translate('Very High'), '', 0xFF8800],
                                ]);
                            }
                            break;
                        case 'Z2M.radar_scene.b071d907':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['area', $this->Translate('Area'), '', 0xFF0000],
                                    ['bedroom', $this->Translate('Bedroom'), '', 0x8800FF],
                                    ['default', $this->Translate('Default'), '', 0xFFFFFF],
                                    ['hotel', $this->Translate('Hotel'), '', 0xFFFF00],
                                    ['office', $this->Translate('Office'), '', 0x008800],
                                    ['parlour', $this->Translate('Parlour'), '', 0x0000FF],
                                    ['toilet', $this->Translate('Toilet'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.motor_working_mode.12bc841d':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['continuous', $this->Translate('Continuous'), '', 0xFF0000],
                                    ['intermittently', $this->Translate('Intermittently'), '', 0x8800FF]
                                ]);
                            }
                            break;
                        case 'Z2M.control.a0c4f29e':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['close', $this->Translate('Close'), '', 0xFF8800],
                                    ['continue', $this->Translate('Continue'), '', 0xFFFF00],
                                    ['open', $this->Translate('Open'), '', 0x00FF00],
                                    ['stop', $this->Translate('Stop'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.control_back_mode.cf88002f':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['back', $this->Translate('Back'), '', 0xFF8800],
                                    ['forward', $this->Translate('Forward'), '', 0xFFFF00]
                                ]);
                            }
                            break;
                        case 'Z2M.border.8e25e2eb':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['down', $this->Translate('Down'), '', 0xFF8800],
                                    ['down_delete', $this->Translate('Down Delete'), '', 0xFFFF00],
                                    ['up', $this->Translate('Up'), '', 0x00FF00]
                                ]);
                            }
                            break;
                        case 'Z2M.brightness_state.95110215':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['low', $this->Translate('Low'), '', 0x00FF00],
                                    ['middle', $this->Translate('Middle'), '', 0xFF8800],
                                    ['high', $this->Translate('High'), '', 0xFF0000],
                                    ['strong', $this->Translate('Strong'), '', 0xFF8800]
                                ]);
                            }
                            break;
                        case 'Z2M.self_test.f4bae49d':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['checking', $this->Translate('Checking'), '', 0xFFFF00],
                                    ['check_success', $this->Translate('Check Success'), '', 0x00FF00],
                                    ['check_failure', $this->Translate('Check Failure'), '', 0xFF0000],
                                    ['others', $this->Translate('Others'), '', 0xFFFF00],
                                    ['comm_fault', $this->Translate('Comm Fault'), '', 0xFF0000],
                                    ['radar_fault', $this->Translate('Radar Fault'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        case 'Z2M.brightness_level.9e0e16e4':
                            if (!IPS_VariableProfileExists($ProfileName)) {
                                $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', [
                                    ['LOW', $this->Translate('Low'), '', 0x00FF00],
                                    ['MEDIUM', $this->Translate('Medium'), '', 0xFF8800],
                                    ['HIGH', $this->Translate('High'), '', 0xFF0000]
                                ]);
                            }
                            break;
                        default:
                            $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                            $this->SendDebug(__FUNCTION__ . ':: ProfileName Values', json_encode($expose['values']), 0);
                            return false;
                    }
                }
                break;
            case 'numeric':
                switch ($expose['property']) {
                    case 'voc_index':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Information', '', ' ', 0, 0, 0, 2);
                        }
                        break;
                    case 'voltage_a':
                    case 'voltage_b':
                    case 'voltage_c':
                    case 'voltage_x':
                    case 'voltage_X':
                    case 'voltage_y':
                    case 'voltage_Y':
                    case 'voltage_z':
                    case 'voltage_Z':
                    case 'current_a':
                    case 'current_b':
                    case 'current_c':
                    case 'current_X':
                    case 'current_x':
                    case 'current_y':
                    case 'current_Y':
                    case 'current_Z':
                    case 'current_z':
                    case 'power_a':
                    case 'power_b':
                    case 'power_c':
                    case 'power_x':
                    case 'power_X':
                    case 'power_y':
                    case 'power_Y':
                    case 'power_z':
                    case 'power_Z':
                    case 'produced_energy':
                    case 'power_reactive':
                    case 'power_factor':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Electricity', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'identify':
                        $ProfileName = $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' seconds', $expose['value_min'], $expose['value_max'], 1, 0);
                        }
                        break;
                    case 'gas_value':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Gas', '', ' LEL', 0, 0, 1, 0);
                        }
                        break;
                    case 'motor_speed':
                        $ProfileName = $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Speedo', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1, 0);
                        }
                        break;
                    case 'temperature_periodic_report':
                    case 'humidity_periodic_report':
                    case 'temperature_sensitivity':
                    case 'humidity_sensitivity':
                        $ProfileName = $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Report', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1, 0);
                        }
                        break;
                    case 'min_temperature_alarm':
                    case 'max_humidity_alarm':
                    case 'min_humidity_alarm':
                    case 'max_temperature_alarm':
                        $ProfileName = $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Alert', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1, 0);
                        }
                        break;
                    case 'error_status':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Alert', '', '', 0, 100, 1, 0);
                        }
                        break;
                    case 'cycle_irrigation_num_times':
                        $ProfileName = $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', '', $expose['value_min'], $expose['value_max'], 1, 0);
                        }
                        break;
                    case 'irrigation_end_time':
                    case 'last_irrigation_duration':
                    case 'irrigation_start_time':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ', ' ', 0, 0, 2);
                        }
                      break;
                    case 'water_consumed':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Drops', '', ' L', ' ', 0, 0, 2);
                        }
                        break;
                    case 'irrigation_target':
                    case 'cycle_irrigation_interval':
                        $ProfileName = $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Graph', '', ' ', ' ', $expose['value_min'], $expose['value_max'], 2);
                        }
                        break;
                    case 'countdown_l2':
                    case 'countdown_l1':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'presence_timeout':
                    case 'radar_range':
                    case 'move_sensitivity':
                    case 'small_detection_sensitivity':
                    case 'small_detection_distance':
                    case 'medium_motion_detection_distance':
                    case 'medium_motion_detection_sensitivity':
                    case 'large_motion_detection_distance':
                    case 'large_motion_detection_sensitivity':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Motion', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'distance':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Move', '', ' ', ' ', 0, 0, 2);
                        }
                      break;
                    case 'requested_brightness_percent':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' %', $expose['value_min'], $expose['value_max'], 0, 0);
                        }
                      break;
                    case 'requested_brightness_level':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], 0, 0);
                        }
                      break;
                    case 'z_axis':
                    case 'y_axis':
                    case 'x_axis':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Shuffle', '', ' ', 0, 0, 0, 2);
                        }
                    break;
                    case 'ac_frequency':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Information', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'presence_sensitivity':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' min', $expose['value_min'], $expose['value_max'], $expose['value_step'], 0);
                        }
                      break;
                    case 'sensitivity':
                    $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                    $ProfileName = str_replace(',', '.', $ProfileName);
                    if (!IPS_VariableProfileExists($ProfileName)) {
                        $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' min', $expose['value_min'], $expose['value_max'], 1, 0);
                    }
                    break;
                    case 'detection_distance_min':
                    case 'detection_distance_max':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Distance', '', ' m', $expose['value_min'], $expose['value_max'], $expose['value_step'], 0);
                        }
                      break;
                    case 'transmit_power':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' dBm', $expose['value_min'], $expose['value_max'], $expose['value_step'], 0);
                        }
                        break;
                    case 'motion_sensitivity':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Motion', '', ' min', $expose['value_min'], $expose['value_max'], $expose['value_step'], 0);
                        }
                        break;
                    case 'action_transaction':
                    case 'power_outage_count':
                    case 'action_rate':
                    case 'action_level':
                    case 'action_transition_time':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Information', '', ' ', 0, 0, 0);
                        }
                        break;
                    case 'error':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Alert', '', ' ', 0, 0, 0);
                        }
                        break;
                    case 'alarm_time':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' min', $expose['value_min'], $expose['value_max'], $expose['value_step'], 0);
                        }
                        break;
                    case 'soil_moisture':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Drops', '', ' ' . $expose['unit'], 0, 0, 0);
                        }
                        break;
                    case 'co':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Gas', '', ' ' . $expose['unit'], 0, 0, 0);
                        }
                        break;
                    case 'regulation_setpoint_offset':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Temperature', '', ' °C', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'load_estimate':
                    case 'fan_speed':
                    case 'load_room_mean':
                    case 'algorithm_scale_factor':
                    case 'display_brightness':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'trigger_time':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' Minutes', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'external_measured_room_sensor':
                    case 'external_temperature_input':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' °', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'smoke_density_dbm':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Factory', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'display_ontime':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Sleep', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'side':
                    case 'angle_x':
                    case 'angle_y':
                    case 'angle_z':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Shuffle', '', ' °', $expose['value_min'], $expose['value_max'], 1);
                        }
                            break;
                    case 'boost_heating_countdown_time_set':
                    case 'duration':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' S', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'min_temperature':
                    case 'max_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'humidity_max':
                    case 'humidity_min':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Gauge', '', ' %', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'temperature_max':
                    case 'temperature_min':
                    case 'eco_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Temperature', '', ' °C', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'duration_of_absence':
                    case 'duration_of_attendance':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ' . $expose['unit'], 0, 0, 0);
                        }
                        break;
                    case 'brightness':
                    case 'brightness_l1':
                    case 'brightness_l2':
                    case 'min_brightness_l1':
                    case 'max_brightness_l1':
                    case 'min_brightness_l2':
                    case 'max_brightness_l2':
                    case 'brightness_rgb':
                    case 'brightness_cct':
                    case 'brightness_white':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', '%', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'color_temp':
                    case 'color_temp_rgb':
                    case 'color_temp_cct':
                    case 'color_temp_startup':
                    case 'color_temp_startup_rgb':
                    case 'color_temp_startup_cct':
                    case 'action_color_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Bulb', '', ' mired', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'valve_position':
                    case 'percent_state':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' %', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'remote_temperature':
                    case 'current_heating_setpoint_auto':
                    case 'current_heating_setpoint':
                    case 'occupied_heating_setpoint':
                    case 'occupied_heating_setpoint_scheduled':
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
                    case 'pm25':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Leaf', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step']);
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
                    case 'boost_timeset_countdown':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ', $expose['value_min'], $expose['value_max'], 1);
                        }
                        break;
                    case 'overload_protection':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Electricity', '', ' ' . $this->Translate('Watt'), $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'strobe_duty_cycle':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Clock', '', ' ', $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'region_id':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'IPS', '', ' ', $expose['value_min'], $expose['value_max'], 0);
                        }
                        break;
                    case 'action_duration':
                    case 'action_transition_time':
                    case 'calibration_time':
                    case 'calibration_time_left':
                    case 'calibration_time_right':
                        $ProfileName .= '_' . $expose['unit'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'radar_sensitivity':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileInteger($ProfileName, 'Intensity', '', ' ', $expose['value_min'], $expose['value_max'], $expose['value_step']);
                        }
                        break;
                    case 'target_distance':
                        $ProfileName .= '_' . $expose['unit'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Move', '', ' ' . $expose['unit'], 0, 0, 0, 2);
                        }
                        break;
                    case 'minimum_range':
                    case 'maximum_range':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Intensity', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'deadzone_temperature':
                    case 'max_temperature_limit':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Temperature', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'detection_delay':
                    case 'fading_time':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 2);
                        }
                        break;
                    case 'detection_interval':
                    case 'detfading_timeection_delay':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'Clock', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], $expose['value_step'], 1);
                        }
                        break;
                    case 'max_temperature':
                        $ProfileName .= $expose['value_min'] . '_' . $expose['value_max'];
                        $ProfileName = str_replace(',', '.', $ProfileName);
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileFloat($ProfileName, 'intensity', '', ' ' . $expose['unit'], $expose['value_min'], $expose['value_max'], 1);
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
        $missedVariables['composite'] = [];
        $missedVariables['enum'] = [];
        $missedVariables['numeric'] = [];
        $missedVariables['binary'] = [];
        $missedVariables['text'] = [];
        $missedVariables['light'] = [];
        $missedVariables['switch'] = [];
        $missedVariables['climate'] = [];
        $missedVariables['lock'] = [];
        $missedVariables['fan'] = [];

        $this->SendDebug(__FUNCTION__ . ':: All Exposes', json_encode($exposes), 0);

        foreach ($exposes as $key => $expose) {
            switch ($expose['type']) {
                case 'text':
                    switch ($expose['property']) {
                        case 'schedule_settings':
                            $this->RegisterVariableString('Z2M_ScheduleSettings', $this->Translate('Schedule Settings'), '');
                            $this->EnableAction('Z2M_ScheduleSettings');
                            break;

                        case 'action_zone':
                            $this->RegisterVariableString('Z2M_ActionZone', $this->Translate('Action Zone'), '');
                            break;
                        case 'action_code':
                            $this->RegisterVariableString('Z2M_ActionCode', $this->Translate('Action Code'), '');
                            break;
                        case 'learned_ir_code':
                            $this->RegisterVariableString('Z2M_LearnedIRCode', $this->Translate('Learned IR Code'), '');
                            break;
                        case 'ir_code_to_send':
                            $this->RegisterVariableString('Z2M_IRCodeToSend', $this->Translate('IR Code to send'), '');
                            $this->EnableAction('Z2M_IRCodeToSend');
                            break;
                        case 'programming_mode':
                            $this->RegisterVariableString('Z2M_ProgrammingMode', $this->Translate('Programming Mode'), '');
                            $this->EnableAction('Z2M_ProgrammingMode');
                            break;
                        default:
                            $missedVariables['text'][] = $expose;
                            break;
                    }
                    break; //break text
                case 'switch':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'learn_ir_code':
                                            $this->RegisterVariableBoolean('Z2M_LearnIRCode', $this->Translate('Learn IR Code'), '~Switch');
                                            $this->EnableAction('Z2M_LearnIRCode');
                                            break;
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
                                        case 'state_l6':
                                            $this->RegisterVariableBoolean('Z2M_Statel6', $this->Translate('State 6'), '~Switch');
                                            $this->EnableAction('Z2M_Statel6');
                                            break;
                                        case 'state_l7':
                                            $this->RegisterVariableBoolean('Z2M_Statel7', $this->Translate('State 7'), '~Switch');
                                            $this->EnableAction('Z2M_Statel7');
                                            break;
                                        case 'state_l8':
                                            $this->RegisterVariableBoolean('Z2M_Statel8', $this->Translate('State 8'), '~Switch');
                                            $this->EnableAction('Z2M_Statel8');
                                            break;
                                        case 'window_detection':
                                            $this->RegisterVariableBoolean('Z2M_WindowDetection', $this->Translate('Window Detection'), '~Switch');
                                            $this->EnableAction('Z2M_WindowDetection');
                                            break;
                                        case 'valve_detection':
                                            $this->RegisterVariableBoolean('Z2M_ValveDetection', $this->Translate('Valve Detection'), '~Switch');
                                            $this->EnableAction('Z2M_ValveDetection');
                                            break;
                                        case 'auto_lock':
                                            $this->RegisterVariableBoolean('Z2M_AutoLock', $this->Translate('Auto Lock'), 'Z2M.AutoLock');
                                            $this->EnableAction('Z2M_AutoLock');
                                            break;
                                        case 'away_mode':
                                            $this->RegisterVariableBoolean('Z2M_AwayMode', $this->Translate('Away Mode'), '~Switch');
                                            $this->EnableAction('Z2M_AwayMode');
                                            break;
                                        case 'state_left':
                                            $this->RegisterVariableBoolean('Z2M_state_left', $this->Translate('State Left'), '~Switch');
                                            $this->EnableAction('Z2M_state_left');
                                            break;
                                        case 'state_right':
                                            $this->RegisterVariableBoolean('Z2M_state_right', $this->Translate('State Right'), '~Switch');
                                            $this->EnableAction('Z2M_state_right');
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
                                        case 'state_l1':
                                            $this->RegisterVariableBoolean('Z2M_Statel1', $this->Translate('State 1'), '~Switch');
                                            $this->EnableAction('Z2M_Statel1');
                                            break;
                                        case 'state_l2':
                                            $this->RegisterVariableBoolean('Z2M_Statel2', $this->Translate('State 2'), '~Switch');
                                            $this->EnableAction('Z2M_Statel2');
                                            break;
                                        case 'state':
                                            //Variable with Profile ~Switch
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                                                $this->EnableAction('Z2M_State');
                                            }
                                            break;
                                        case 'state_rgb':
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('Z2M_StateRGB', $this->Translate('State RGB'), '~Switch');
                                                $this->EnableAction('Z2M_StateRGB');
                                            }
                                            break;
                                        case 'state_cct':
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('Z2M_StateCCT', $this->Translate('State CCT'), '~Switch');
                                                $this->EnableAction('Z2M_StateCCT');
                                            }
                                            break;
                                        case 'state_white':
                                            if (($feature['value_on'] == 'ON') && ($feature['value_off'] = 'OFF')) {
                                                $this->RegisterVariableBoolean('Z2M_StateWhite', $this->Translate('State White'), '~Switch');
                                                $this->EnableAction('Z2M_StateWhite');
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
                                        case 'max_brightness_l1':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_MaxBrightnessL1', $this->Translate('Max Brightness L1'), $ProfileName);
                                                $this->EnableAction('Z2M_MaxBrightnessL1');
                                            }
                                            break;
                                        case 'min_brightness_l1':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_MinBrightnessL1', $this->Translate('Min Brightness L1'), $ProfileName);
                                                $this->EnableAction('Z2M_MinBrightnessL1');
                                            }
                                            break;
                                        case 'max_brightness_l2':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_MaxBrightnessL2', $this->Translate('Max Brightness L2'), $ProfileName);
                                                $this->EnableAction('Z2M_MaxBrightnessL2');
                                            }
                                            break;
                                        case 'min_brightness_l2':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_MinBrightnessL2', $this->Translate('Min Brightness L2'), $ProfileName);
                                                $this->EnableAction('Z2M_MinBrightnessL2');
                                            }
                                            break;
                                        case 'brightness_l1':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_BrightnessL1', $this->Translate('Brightness L1'), $ProfileName);
                                                $this->EnableAction('Z2M_BrightnessL1');
                                            }
                                            break;
                                        case 'brightness_l2':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_BrightnessL2', $this->Translate('Brightness L1'), $ProfileName);
                                                $this->EnableAction('Z2M_BrightnessL2');
                                            }
                                            break;
                                        case 'brightness':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_Brightness', $this->Translate('Brightness'), $ProfileName);
                                                $this->EnableAction('Z2M_Brightness');
                                            }
                                            break;
                                        case 'brightness_rgb':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_BrightnessRGB', $this->Translate('Brightness RGB'), $ProfileName);
                                                $this->EnableAction('Z2M_BrightnessRGB');
                                            }
                                            break;
                                        case 'brightness_cct':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_BrightnessCCT', $this->Translate('Brightness CCT'), $ProfileName);
                                                $this->EnableAction('Z2M_BrightnessCCT');
                                            }
                                            break;
                                        case 'brightness_white':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_BrightnessWhite', $this->Translate('Brightness White'), $ProfileName);
                                                $this->EnableAction('Z2M_BrightnessWhite');
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
                                        case 'color_temp_rgb':
                                            //Color Temperature Mired
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_ColorTempRGB', $this->Translate('Color Temperature RGB'), $ProfileName);
                                                $this->EnableAction('Z2M_ColorTempRGB');
                                            }
                                            //TODO: Color Temp Presets
                                            // Color Temperature in Kelvin nicht automatisiert, deswegen nicht über die Funktion registerVariableProfile
                                            if (!IPS_VariableProfileExists('Z2M.ColorTemperatureKelvin')) {
                                                $this->RegisterProfileInteger('Z2M.ColorTemperatureKelvin', 'Intensity', '', '', 2000, 6535, 1);
                                            }
                                            $this->RegisterVariableInteger('Z2M_ColorTempRGBKelvin', $this->Translate('Color Temperature RGB Kelvin'), 'Z2M.ColorTemperatureKelvin');
                                            $this->EnableAction('Z2M_ColorTempRGBKelvin');
                                            break;
                                        case 'color_temp_cct':
                                            //Color Temperature Mired
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_ColorTempCCT', $this->Translate('Color Temperature CCT'), $ProfileName);
                                                $this->EnableAction('Z2M_ColorTempCCT');
                                            }
                                            //TODO: Color Temp Presets
                                            // Color Temperature in Kelvin nicht automatisiert, deswegen nicht über die Funktion registerVariableProfile
                                            if (!IPS_VariableProfileExists('Z2M.ColorTemperatureKelvin')) {
                                                $this->RegisterProfileInteger('Z2M.ColorTemperatureKelvin', 'Intensity', '', '', 2000, 6535, 1);
                                            }
                                            $this->RegisterVariableInteger('Z2M_ColorTempCCTKelvin', $this->Translate('Color Temperature CCT Kelvin'), 'Z2M.ColorTemperatureKelvin');
                                            $this->EnableAction('Z2M_ColorTempCCTKelvin');
                                            break;
                                        case 'color_temp_startup_rgb':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_ColorTempStartupRGB', $this->Translate('Color Temperature Startup RGB'), $ProfileName);
                                                $this->EnableAction('Z2M_ColorTempStartupRGB');
                                            }
                                            break;
                                         case 'color_temp_startup_cct':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_ColorTempStartupCCT', $this->Translate('Color Temperature Startup CCT'), $ProfileName);
                                                $this->EnableAction('Z2M_ColorTempStartupCCT');
                                            }
                                            break;
                                        case 'color_temp_startup':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_ColorTempStartup', $this->Translate('Color Temperature Startup RGB'), $ProfileName);
                                                $this->EnableAction('Z2M_ColorTempStartup');
                                            }
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
                                                $this->SendDebug(__FUNCTION__, 'Erkannter Modus: color_xy', 0);
                                                $this->RegisterVariableInteger('Z2M_Color', $this->Translate('Color'), 'HexColor');
                                                $this->EnableAction('Z2M_Color');
                                            } elseif ($feature['name'] == 'color_hs') {
                                                $this->SendDebug(__FUNCTION__, 'Erkannter Modus: color_hs', 0); // Hier fügen wir den SendDebug ein
                                                $this->RegisterVariableInteger('Z2M_ColorHS', $this->Translate('Color HS'), 'HexColor');
                                                $this->EnableAction('Z2M_ColorHS');
                                            }
                                            break;
                                        case 'color_rgb':
                                            if ($feature['name'] == 'color_xy') {
                                                $this->RegisterVariableInteger('Z2M_ColorRGB', $this->Translate('Color'), 'HexColor');
                                                $this->EnableAction('Z2M_ColorRGB');
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
                                        case 'away_mode':
                                            $this->RegisterVariableBoolean('Z2M_AwayMode', $this->Translate('Away Mode'), '~Switch');
                                            $this->EnableAction('Z2M_AwayMode');
                                            break;
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
                                            $this->RegisterVariableInteger('Z2M_PiHeatingDemand', $this->Translate('Valve Position (Heating Demand)'), '~Intensity.100');
                                            $this->EnableAction('Z2M_PiHeatingDemand');
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
                                        case 'sensor':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_Sensor', $this->Translate('Sensor'), $ProfileName);
                                                $this->EnableAction('Z2M_Sensor');
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
                case 'fan':
                        if (array_key_exists('features', $expose)) {
                            foreach ($expose['features'] as $key => $feature) {
                                switch ($feature['type']) {
                                    case 'binary':
                                        switch ($feature['property']) {
                                            case 'fan_state':
                                                $this->RegisterVariableBoolean('Z2M_FanState', $this->Translate('Fan State'), '~Switch');
                                                $this->EnableAction('Z2M_FanState');
                                                break;
                                            default:
                                                // Default lock binary
                                                $missedVariables['fan'][] = $feature;
                                                break;
                                        }
                                        break; //Lock binaray break;
                                    case 'numeric':
                                        switch ($feature['property']) {
                                            default:
                                                // Default lock binary
                                                $missedVariables['fan'][] = $feature;
                                                break;
                                        }
                                        break; //Lock numeric break;
                                    case 'enum':
                                        switch ($feature['property']) {
                                            case 'fan_mode':
                                                $ProfileName = $this->registerVariableProfile($feature);
                                                if ($ProfileName != false) {
                                                    $this->RegisterVariableString('Z2M_FanMode', $this->Translate('Fan Mode'), $ProfileName);
                                                    $this->EnableAction('Z2M_FanMode');
                                                }
                                                break;
                                            default:
                                                // Default lock enum
                                                $missedVariables['fan'][] = $feature;
                                                break;
                                        }
                                        break; //Lock enum break;
                                }
                            }
                        }
                        break;
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
                        case 'smoke_alarm_state':
                            $this->RegisterVariableBoolean('Z2M_SmokeAlarmState', $this->Translate('Smoke Alarm State'), '~Alert');
                            $this->EnableAction('Z2M_SmokeAlarmState');
                            break;
                        case 'intruder_alarm_state':
                            $this->RegisterVariableBoolean('Z2M_IntruderAlarmState', $this->Translate('Intruder Alarm State'), '~Alert');
                            $this->EnableAction('Z2M_IntruderAlarmState');
                            break;
                        case 'schedule':
                            $this->RegisterVariableBoolean('Z2M_Schedule', $this->Translate('Schedule'), '~Switch');
                            $this->EnableAction('Z2M_Schedule');
                            break;
                        case 'valve_alarm':
                            $this->RegisterVariableBoolean('Z2M_ValveAlarm', $this->Translate('Valve Alarm'), '~Alert');
                            break;
                        case 'setup':
                            $this->RegisterVariableBoolean('Z2M_Setup', $this->Translate('Setup'), '~Switch');
                            break;
                        case 'backlight_mode':
                            $this->RegisterVariableBoolean('Z2M_BacklightMode', $this->Translate('Backlight Mode'), '~Switch');
                            $this->EnableAction('Z2M_BacklightMode');
                            break;
                        case 'gas':
                            $this->RegisterVariableBoolean('Z2M_Gas', $this->Translate('Gas'), '~Alert');
                            break;
                        case 'self_test':
                            $this->RegisterVariableBoolean('Z2M_SelfTest', $this->Translate('Self Test'), '~Switch');
                            $this->EnableAction('Z2M_SelfTest');
                            break;
                        case 'preheat':
                            $this->RegisterVariableBoolean('Z2M_Preheat', $this->Translate('Preheat'), '~Switch');
                            break;
                        case 'online':
                            $this->RegisterVariableBoolean('Z2M_Online', $this->Translate('Online'), '~Switch');
                            $this->EnableAction('Z2M_Online');
                            break;
                        case 'window_detection':
                            $this->RegisterVariableBoolean('Z2M_WindowDetection', $this->Translate('Window Detection'), '~Switch');
                            $this->EnableAction('Z2M_WindowDetection');
                            break;
                        case 'illuminance_above_threshold':
                            $this->RegisterVariableBoolean('Z2M_IlluminanceAboveThreshold', $this->Translate('Illuminance Above Threshold'), '~Switch');
                            break;
                        case 'valve_adapt_process':
                            $this->RegisterVariableBoolean('Z2M_ValveAdaptProcess', $this->Translate('Valve Adapt Process'), '~Switch');
                            $this->EnableAction('Z2M_ValveAdaptProcess');
                            break;
                        case 'indicator':
                            $this->RegisterVariableBoolean('Z2M_Indicator', $this->Translate('Indicator'), '~Switch');
                            $this->EnableAction('Z2M_Indicator');
                            break;
                        case 'led_indication':
                            $this->RegisterVariableBoolean('Z2M_LedIndication', $this->Translate('Led Indication'), '~Switch');
                            $this->EnableAction('Z2M_LedIndication');
                            break;
                        case 'silence':
                            $this->RegisterVariableBoolean('Z2M_Silence', $this->Translate('Silence'), '~Switch');
                            $this->EnableAction('Z2M_Silence');
                            break;
                        case 'scale_protection':
                            $this->RegisterVariableBoolean('Z2M_ScaleProtection', $this->Translate('Scale Protection'), '~Switch');
                            $this->EnableAction('Z2M_ScaleProtection');
                            break;
                        case 'charge_state':
                            $this->RegisterVariableBoolean('Z2M_ChargeState', $this->Translate('Charge State'), 'Z2M.ChargeState');
                            break;
                        case 'tamper_alarm':
                            $this->RegisterVariableBoolean('Z2M_TamperAlarm', $this->Translate('Tamper Alarm'), '~Switch');
                            $this->EnableAction('Z2M_TamperAlarm');
                            break;
                        case 'tamper_alarm_switch':
                            $this->RegisterVariableBoolean('Z2M_TamperAlarmSwitch', $this->Translate('Tamper Alarm Switch'), '~Switch');
                            $this->EnableAction('Z2M_TamperAlarmSwitch');
                            break;
                        case 'alarm_switch':
                            $this->RegisterVariableBoolean('Z2M_AlarmSwitch', $this->Translate('Alarm Switch'), '~Switch');
                            $this->EnableAction('Z2M_AlarmSwitch');
                            break;
                        case 'do_not_disturb':
                            $this->RegisterVariableBoolean('Z2M_DoNotDisturb', $this->Translate('Do Not Disturb'), '~Switch');
                            $this->EnableAction('Z2M_DoNotDisturb');
                            break;
                        case 'led_enable':
                            $this->RegisterVariableBoolean('Z2M_LEDEnable', $this->Translate('LED Enable'), '~Switch');
                            $this->EnableAction('Z2M_LEDEnable');
                            break;
                        case 'button_lock':
                            $this->RegisterVariableBoolean('Z2M_ButtonLock', $this->Translate('Button Lock'), '~Switch');
                            break;
                        case 'child_lock':
                            $this->RegisterVariableBoolean('Z2M_ChildLock', $this->Translate('Child Lock'), '~Switch');
                            $this->EnableAction('Z2M_ChildLock');
                            break;
                        case 'replace_filter':
                            $this->RegisterVariableBoolean('Z2M_ReplaceFilter', $this->Translate('Replace Filter'), '~Switch');
                            break;
                        case 'mute':
                            $this->RegisterVariableBoolean('Z2M_Mute', $this->Translate('Mute'), '~Switch');
                            break;
                        case 'adaptation_run_settings':
                            $this->RegisterVariableBoolean('Z2M_AdaptationRunSettings', $this->Translate('Adaptation Run Settings'), '~Switch');
                            $this->EnableAction('Z2M_AdaptationRunSettings');
                            break;
                        case 'preheat_status':
                            $this->RegisterVariableBoolean('Z2M_PreheatStatus', $this->Translate('Preheat Status'), '~Switch');
                            $this->EnableAction('Z2M_PreheatStatus');
                            break;
                        case 'load_balancing_enable':
                            $this->RegisterVariableBoolean('Z2M_LoadBalancingEnable', $this->Translate('Load Balancing Enable'), '~Switch');
                            $this->EnableAction('Z2M_LoadBalancingEnable');
                            break;
                        case 'window_open_external':
                            $this->RegisterVariableBoolean('Z2M_WindowOpenExternal', $this->Translate('Window Open External'), '~Switch');
                            $this->EnableAction('Z2M_WindowOpenExternal');
                            break;
                        case 'window_open_feature':
                            $this->RegisterVariableBoolean('Z2M_Window_OpenFeature', $this->Translate('Window Open Feature'), '~Switch');
                            $this->EnableAction('Z2M_Window_OpenFeature');
                            break;
                        case 'radiator_covered':
                            $this->RegisterVariableBoolean('Z2M_RadiatorCovered', $this->Translate('Radiator Covered'), '~Switch');
                            $this->EnableAction('Z2M_RadiatorCovered');
                            break;
                        case 'heat_required':
                            $this->RegisterVariableBoolean('Z2M_HeatRequired', $this->Translate('Heat Required'), '~Switch');
                            break;
                        case 'heat_available':
                            $this->RegisterVariableBoolean('Z2M_HeatAvailable', $this->Translate('Heat Available'), '~Switch');
                            $this->EnableAction('Z2M_HeatAvailable');
                            break;
                        case 'viewing_direction':
                            $this->RegisterVariableBoolean('Z2M_ViewingDirection', $this->Translate('Viewing Direction'), '~Switch');
                            $this->EnableAction('Z2M_ViewingDirection');
                            break;
                        case 'thermostat_vertical_orientation':
                            $this->RegisterVariableBoolean('Z2M_ThermostatVerticalOrientation', $this->Translate('Thermostat VerticalOrientation'), '~Switch');
                            $this->EnableAction('Z2M_ThermostatVerticalOrientation');
                            break;
                        case 'mounted_mode_control':
                            $this->RegisterVariableBoolean('Z2M_MountedModeControl', $this->Translate('Mounted Mode Control'), '~Switch');
                            $this->EnableAction('Z2M_MountedModeControl');
                            break;
                        case 'mounted_mode_active':
                            $this->RegisterVariableBoolean('Z2M_MountedModeActive', $this->Translate('Mounted Mode Active'), '~Switch');
                            break;
                        case 'linkage_alarm_state':
                            $this->RegisterVariableBoolean('Z2M_LinkageAlarmState', $this->Translate('Linkage Alarm State'), '~Switch');
                            break;
                        case 'linkage_alarm':
                            $this->RegisterVariableBoolean('Z2M_LinkageAlarm', $this->Translate('Linkage Alarm'), '~Switch');
                            $this->EnableAction('Z2M_LinkageAlarm');
                            break;
                        case 'heartbeat_indicator':
                            $this->RegisterVariableBoolean('Z2M_HeartbeatIndicator', $this->Translate('Heartbeat Indicator'), '~Switch');
                            $this->EnableAction('Z2M_HeartbeatIndicator');
                            break;
                        case 'buzzer_manual_mute':
                            $this->RegisterVariableBoolean('Z2M_BuzzerManualMute', $this->Translate('Buzzer Manual Mute'), '~Switch');
                            break;
                        case 'buzzer_manual_alarm':
                            $this->RegisterVariableBoolean('Z2M_BuzzerManualAlarm', $this->Translate('Buzzer Manual Alarm'), '~Switch');
                            break;
                        case 'boost':
                            $this->RegisterVariableBoolean('Z2M_Boost', $this->Translate('Boost'), '~Switch');
                            $this->EnableAction('Z2M_Boost');
                            break;
                        case 'valve_state':
                            $this->RegisterVariableBoolean('Z2M_ValveState', $this->Translate('Valve State'), 'Z2M.ValveState');
                            break;
                        case 'eco_mode':
                            $this->RegisterVariableBoolean('Z2M_EcoMode', $this->Translate('Eco Mode'), '~Switch');
                            $this->EnableAction('Z2M_EcoMode');
                            break;
                        case 'temperature_alarm':
                            $this->RegisterVariableBoolean('Z2M_TemperatureAlarm', $this->Translate('Temperature Alarm'), '~Switch');
                            $this->EnableAction('Z2M_TemperatureAlarm');
                            break;
                        case 'humidity_alarm':
                            $this->RegisterVariableBoolean('Z2M_HumidityAlarm', $this->Translate('Humidity Alarm'), '~Switch');
                            $this->EnableAction('Z2M_HumidityAlarm');
                            break;
                        case 'alarm':
                            $this->RegisterVariableBoolean('Z2M_Alarm', $this->Translate('Alarm'), '~Switch');
                            $this->EnableAction('Z2M_Alarm');
                            break;
                        case 'state':
                              $this->RegisterVariableBoolean('Z2M_State', $this->Translate('State'), '~Switch');
                              $this->EnableAction('Z2M_State');
                            break;
                        case 'led_state':
                            $this->RegisterVariableBoolean('Z2M_LedState', $this->Translate('LED State'), '~Switch');
                            $this->EnableAction('Z2M_LedState');
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
                            $this->RegisterVariableBoolean('Z2M_CarbonMonoxide', $this->Translate('Alarm'), '~Alert');
                            break;
                        case 'heating':
                            $this->RegisterVariableBoolean('Z2M_Heating', $this->Translate('Heating'), '~Switch');
                            break;
                        case 'boost_heating':
                            $this->RegisterVariableBoolean('Z2M_BoostHeating', $this->Translate('Boost Heating'), '~Switch');
                            $this->EnableAction('Z2M_BoostHeating');
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
                        case 'calibrated':
                            $this->RegisterVariableBoolean('Z2M_Calibrated', $this->Translate('Calibrated'), '~Switch');
                            break;
                        case 'calibration_left':
                            $this->RegisterVariableBoolean('Z2M_CalibrationLeft', $this->Translate('Calibration Left'), '~Switch');
                            $this->EnableAction('Z2M_CalibrationLeft');
                            break;
                        case 'calibration_right':
                            $this->RegisterVariableBoolean('Z2M_CalibrationRight', $this->Translate('Calibration Right'), '~Switch');
                            $this->EnableAction('Z2M_CalibrationRight');
                            break;
                        case 'motor_reversal':
                            $this->RegisterVariableBoolean('Z2M_MotorReversal', $this->Translate('Motor Reversal'), '~Switch');
                            $this->EnableAction('Z2M_MotorReversal');
                            break;
                        case 'motor_reversal_left':
                            $this->RegisterVariableBoolean('Z2M_MotorReversalLeft', $this->Translate('Motor Reversal Left'), '~Switch');
                            $this->EnableAction('Z2M_MotorReversalLeft');
                            break;
                        case 'motor_reversal_right':
                            $this->RegisterVariableBoolean('Z2M_MotorReversalRight', $this->Translate('Motor Reversal Right'), '~Switch');
                            $this->EnableAction('Z2M_MotorReversalRight');
                            break;
                        case 'open_window':
                            $this->RegisterVariableBoolean('Z2M_OpenWindow', $this->Translate('Open Window'), '~Window');
                            $this->EnableAction('Z2M_OpenWindow');
                            break;
                        case 'window_open':
                            $this->RegisterVariableBoolean('Z2M_WindowOpen', $this->Translate('Open Window'), '~Window');
                            $this->EnableAction('Z2M_WindowOpen');
                            break;
                        case 'frost_protection':
                            $this->RegisterVariableBoolean('Z2M_FrostProtection', $this->Translate('Frost Protection'), '~Switch');
                            $this->EnableAction('Z2M_FrostProtection');
                            break;
                        case 'heating_stop':
                            $this->RegisterVariableBoolean('Z2M_HeatingStop', $this->Translate('Heating Stop'), '~Switch');
                            $this->EnableAction('Z2M_HeatingStop');
                            break;
                        case 'test':
                            $this->RegisterVariableBoolean('Z2M_Test', $this->Translate('Test'), '~Switch');
                            break;
                        case 'trigger':
                            $this->RegisterVariableBoolean('Z2M_GarageTrigger', $this->Translate('Garage Trigger'), '~Switch');
                            $this->EnableAction('Z2M_GarageTrigger');
                            break;
                        case 'garage_door_contact':
                            $this->RegisterVariableBoolean('Z2M_GarageDoorContact', $this->Translate('Garage Door Contact'), '~Window.Reversed');
                            break;
                        case 'trigger_indicator':
                            $this->RegisterVariableBoolean('Z2M_TriggerIndicator', $this->Translate('Trigger Indicator'), '~Switch');
                            $this->EnableAction('Z2M_TriggerIndicator');
                            break;
                        case 'factory_reset':
                            $this->RegisterVariableBoolean('Z2M_FactoryReset', $this->Translate('Factory Reset'), '~Switch');
                            $this->EnableAction('Z2M_FactoryReset');
                            break;
                        default:
                            $missedVariables['binary'][] = $expose;
                        break;
                    }
                    break; //binary break
                case 'enum':
                    switch ($expose['property']) {
                        case 'occupancy_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_OccupancySensitivity', $this->Translate('Occupancy Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_OccupancySensitivity');
                            }
                            break;
                        case 'illumination':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Illumination', $this->Translate('Illumination'), $ProfileName);
                            }
                            break;
                        case 'calibrate':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Calibrate', $this->Translate('Calibrate'), $ProfileName);
                                $this->EnableAction('Z2M_Calibrate');
                            }
                            break;
                        case 'humidity_alarm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_HumidityAlarm', $this->Translate('Humidity Alarm'), $ProfileName);
                                $this->EnableAction('Z2M_HumidityAlarm');
                            }
                            break;
                        case 'alarm_ringtone':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_AlarmRingtone', $this->Translate('Alarm Ringtone'), $ProfileName);
                                $this->EnableAction('Z2M_AlarmRingtone');
                            }
                            break;
                        case 'opening_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_OpeningMode', $this->Translate('Opening Mode'), $ProfileName);
                                $this->EnableAction('Z2M_OpeningMode');
                            }
                            break;
                        case 'set_upper_limit':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_SetUpperLimit', $this->Translate('Set Upper Limit'), $ProfileName);
                                $this->EnableAction('Z2M_SetUpperLimit');
                            }
                            break;
                        case 'set_bottom_limit':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_SetBottomLimit', $this->Translate('Set Bottom Limit'), $ProfileName);
                                $this->EnableAction('Z2M_SetBottomLimit');
                            }
                            break;

                        case 'temperature_alarm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_TemperatureAlarm', $this->Translate('Temperature Alarm'), $ProfileName);
                                $this->EnableAction('Z2M_TemperatureAlarm');
                            }
                            break;
                        case 'working_day':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_WorkingDay', $this->Translate('Working Day'), $ProfileName);
                                $this->EnableAction('Z2M_WorkingDay');
                            }
                            break;
                        case 'week_day':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_WeekDay', $this->Translate('Week Day'), $ProfileName);
                                $this->EnableAction('Z2M_WeekDay');
                            }
                            break;
                        case 'state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_State', $this->Translate('State'), $ProfileName);
                            }
                            break;
                        case 'valve_adapt_status':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_ValveAdaptStatus', $this->Translate('Valve Adapt Status'), $ProfileName);
                            }
                            break;
                        case 'motion_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MotionState', $this->Translate('Motion State'), $ProfileName);
                            }
                            break;
                        case 'detection_distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_DetectionDistance', $this->Translate('Detection Distance'), $ProfileName);
                                $this->EnableAction('Z2M_DetectionDistance');
                            }
                            break;
                        case 'presence_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PresenceState', $this->Translate('Presence State'), $ProfileName);
                            }
                            break;
                        case 'self_test_result':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_SelfTestResult', $this->Translate('Self Test Result'), $ProfileName);
                            }
                            break;
                        case 'presence_event':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PresenceEvent', $this->Translate('Presence Event'), $ProfileName);
                            }
                            break;
                        case 'monitoring_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MonitoringMode', $this->Translate('Monitoring Mode'), $ProfileName);
                                $this->EnableAction('Z2M_MonitoringMode');
                            }
                            break;
                        case 'approach_distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_ApproachDistance', $this->Translate('Approach Distance'), $ProfileName);
                                $this->EnableAction('Z2M_ApproachDistance');
                            }
                            break;
                        case 'reset_nopresence_status':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_ResetNopresenceStatus', $this->Translate('Reset Nopresence Status'), $ProfileName);
                                $this->EnableAction('Z2M_ResetNopresenceStatus');
                            }
                            break;
                        case 'device_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_DeviceMode', $this->Translate('Device Mode'), $ProfileName);
                                $this->EnableAction('Z2M_DeviceMode');
                            }
                            break;
                        case 'alarm_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_AlarmMode', $this->Translate('Alarm Mode'), $ProfileName);
                                $this->EnableAction('Z2M_AlarmMode');
                            }
                            break;
                        case 'alarm_melody':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_AlarmMelody', $this->Translate('Alarm Melody'), $ProfileName);
                                $this->EnableAction('Z2M_AlarmMelody');
                            }
                            break;
                        case 'alarm_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_AlarmState', $this->Translate('Alarm State'), $ProfileName);
                            }
                            break;
                        case 'air_quality':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_AirQuality', $this->Translate('Air Quality'), $ProfileName);
                            }
                            break;
                        case 'do_not_disturb':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_DoNotDisturb', $this->Translate('Do not Disturb'), $ProfileName);
                                $this->EnableAction('Z2M_DoNotDisturb');
                            }
                            break;
                        case 'color_power_on_behavior':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_ColorPowerOnBehavior', $this->Translate('Color Power On Behavior'), $ProfileName);
                                $this->EnableAction('Z2M_ColorPowerOnBehavior');
                            }
                            break;
                        case 'displayed_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_DisplayedTemperature', $this->Translate('Displayed Temperature'), $ProfileName);
                            }
                            break;
                        case 'battery_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_BatteryState', $this->Translate('Battery State'), $ProfileName);
                            }
                            break;
                        case 'temperature_unit':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_TemperatureUnit', $this->Translate('Temperature Unit'), $ProfileName);
                                $this->EnableAction('Z2M_TemperatureUnit');
                            }
                            break;
                        case 'mute_buzzer':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MuteBuzzer', $this->Translate('Mute Buzzer'), $ProfileName);
                                $this->EnableAction('Z2M_MuteBuzzer');
                            }
                            break;
                        case 'adaptation_run_control':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_AdaptationRunControl', $this->Translate('Adaptation Run Control'), $ProfileName);
                                $this->EnableAction('Z2M_AdaptationRunControl');
                            }
                            break;
                        case 'adaptation_run_status':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_AdaptationRunStatus', $this->Translate('Adaptation Run Status'), $ProfileName);
                            }
                            break;
                        case 'day_of_week':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_DayOfWeek', $this->Translate('Day Of Week'), $ProfileName);
                                $this->EnableAction('Z2M_DayOfWeek');
                            }
                            break;
                        case 'setpoint_change_source':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_SetpointChangeSource', $this->Translate('Setpoint Change Source'), $ProfileName);
                            }
                            break;
                        case 'programming_operation_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_ProgrammingOperationMode', $this->Translate('Programming Operation Mode'), $ProfileName);
                                $this->EnableAction('Z2M_ProgrammingOperationMode');
                            }
                            break;
                        case 'keypad_lockout':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_KeypadLockout', $this->Translate('Keypad Lockout'), $ProfileName);
                                $this->EnableAction('Z2M_Keypad_Lockout');
                            }
                            break;
                        case 'buzzer':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Buzzer', $this->Translate('Buzzer'), $ProfileName);
                                $this->EnableAction('Z2M_Buzzer');
                            }
                            break;
                        case 'display_orientation':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_DisplayOrientation', $this->Translate('Display Orientation'), $ProfileName);
                                $this->EnableAction('Z2M_DisplayOrientation');
                            }
                            break;
                        case 'gradient_scene':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_GradientScene', $this->Translate('Gradient Scene'), $ProfileName);
                                $this->EnableAction('Z2M_GradientScene');
                            }
                            break;
                        case 'switch_type':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_SwitchType', $this->Translate('Switch Type'), $ProfileName);
                                $this->EnableAction('Z2M_SwitchType');
                            }
                            break;
                        case 'indicator_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_IndicatorMode', $this->Translate('Indicator Mode'), $ProfileName);
                                $this->EnableAction('Z2M_IndicatorMode');
                            }
                            break;
                        case 'melody':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Melody', $this->Translate('Melody'), $ProfileName);
                                $this->EnableAction('Z2M_Melody');
                            }
                            break;
                        case 'power_type':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PowerType', $this->Translate('Power Type'), $ProfileName);
                            }
                            break;
                        case 'volume':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Volume', $this->Translate('Volume'), $ProfileName);
                                $this->EnableAction('Z2M_Volume');
                            }
                            break;
                        case 'backlight_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_BacklightMode', $this->Translate('Backlight Mode'), $ProfileName);
                                $this->EnableAction('Z2M_BacklightMode');
                            }
                            break;
                        case 'effect':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Effect', $this->Translate('Effect'), $ProfileName);
                                $this->EnableAction('Z2M_Effect');
                            }
                            break;
                        case 'action':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Action', $this->Translate('Action'), $ProfileName);
                            }
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
                        case 'power_on_behavior_l1':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PowerOnBehaviorL1', $this->Translate('Power on behavior L1'), $ProfileName);
                                $this->EnableAction('Z2M_PowerOnBehaviorL1');
                            }
                            break;
                        case 'power_on_behavior_l2':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PowerOnBehaviorL2', $this->Translate('Power on behavior L2'), $ProfileName);
                                $this->EnableAction('Z2M_PowerOnBehaviorL2');
                            }
                            break;
                        case 'power_on_behavior_l3':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PowerOnBehaviorL3', $this->Translate('Power on behavior L3'), $ProfileName);
                                $this->EnableAction('Z2M_PowerOnBehaviorL3');
                            }
                            break;
                        case 'power_on_behavior_l4':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_PowerOnBehaviorL4', $this->Translate('Power on behavior L4'), $ProfileName);
                                $this->EnableAction('Z2M_PowerOnBehaviorL4');
                            }
                            break;
                        case 'motor_direction':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MotorDirection', $this->Translate('Motor Direction'), $ProfileName);
                                $this->EnableAction('Z2M_MotorDirection');
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
                                $this->RegisterVariableString('Z2M_Moving', $this->Translate('Current Action'), $ProfileName);
                            }
                            break;
                        case 'moving_left':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MovingLeft', $this->Translate('Current Action Left'), $ProfileName);
                            }
                            break;
                        case 'moving_right':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MovingRight', $this->Translate('Current Action Right'), $ProfileName);
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
                        case 'radar_scene':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_RadarScene', $this->Translate('Radar Scene'), $ProfileName);
                                $this->EnableAction('Z2M_RadarScene');
                            }
                            break;
                        case 'motor_working_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_MotorWorkingMode', $this->Translate('Motor Working Mode'), $ProfileName);
                                $this->EnableAction('Z2M_MotorWorkingMode');
                            }
                            break;
                        case 'control':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Control', $this->Translate('Control'), $ProfileName);
                                $this->EnableAction('Z2M_Control');
                            }
                            break;
                        case 'mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Mode', $this->Translate('Mode'), $ProfileName);
                            }
                            $this->EnableAction('Z2M_Mode');
                            break;
                        case 'control_back_mode':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_ControlBackMode', $this->Translate('Control back Mode'), $ProfileName);
                            }
                            $this->EnableAction('Z2M_ControlBackMode');
                            break;
                        case 'border':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_Border', $this->Translate('Border'), $ProfileName);
                            }
                            $this->EnableAction('Z2M_Border');
                            break;
                        case 'brightness_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_BrightnessSate', $this->Translate('Brightness State'), $ProfileName);
                            }
                            break;
                        case 'self_test':
                        case 'selftest':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_SelfTest', $this->Translate('Self Test'), $ProfileName);
                                if ($expose['access'] == 1) {
                                    $this->EnableAction('Z2M_SelfTest');
                                }
                            }
                            break;
                        case 'brightness_level':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableString('Z2M_BrightnessLevel', $this->Translate('Brightness Level'), $ProfileName);
                                if ($expose['access'] == 1) {
                                    $this->EnableAction('Z2M_BrightnessLevel');
                                }
                            }
                            break;
                        default:
                            $missedVariables['enum'][] = $expose;
                            break;
                    }
                    break; //enum break
                case 'numeric':
                    switch ($expose['property']) {
                        case 'voc_index':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_VOCIndex', $this->Translate('VOC Index'), $ProfileName);
                            }
                            break;
                        case 'external_temperature_input':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_ExternalTemperatureInput', $this->Translate('External Temperature Input'), $ProfileName);
                            }
                            break;
                        case 'voltage_a':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_VoltageA', $this->Translate('Voltage A'), $ProfileName);
                            }
                            break;
                        case 'voltage_b':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_VoltageB', $this->Translate('Voltage B'), $ProfileName);
                            }
                            break;
                        case 'voltage_c':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_VoltageC', $this->Translate('Voltage C'), $ProfileName);
                            }
                            break;
                        case 'voltage_x':
                        case 'voltage_X':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_VoltageX', $this->Translate('Voltage X'), $ProfileName);
                            }
                            break;
                        case 'voltage_y':
                        case 'voltage_Y':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_VoltageY', $this->Translate('Voltage Y'), $ProfileName);
                            }
                            break;
                        case 'voltage_z':
                        case 'voltage_Z':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_VoltageZ', $this->Translate('Voltage Z'), $ProfileName);
                            }
                            break;
                        case 'current_a':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CurrentA', $this->Translate('Current A'), $ProfileName);
                            }
                            break;
                        case 'current_b':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CurrentB', $this->Translate('Current B'), $ProfileName);
                            }
                            break;
                        case 'current_c':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CurrentC', $this->Translate('Current C'), $ProfileName);
                            }
                            break;
                        case 'current_x':
                        case 'current_X':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CurrentX', $this->Translate('Current X'), $ProfileName);
                            }
                            break;
                        case 'current_y':
                        case 'current_Y':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CurrentY', $this->Translate('Current Y'), $ProfileName);
                            }
                            break;
                        case 'current_z':
                        case 'current_Z':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CurrentZ', $this->Translate('Current Z'), $ProfileName);
                            }
                            break;
                        case 'power_a':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerA', $this->Translate('Power A'), $ProfileName);
                            }
                            break;
                        case 'power_b':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerB', $this->Translate('Power B'), $ProfileName);
                            }
                            break;
                        case 'power_c':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerC', $this->Translate('Power C'), $ProfileName);
                            }
                            break;
                        case 'power_x':
                        case 'power_X':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerX', $this->Translate('Power X'), $ProfileName);
                            }
                            break;
                        case 'power_y':
                        case 'power_Y':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerY', $this->Translate('Power Y'), $ProfileName);
                            }
                            break;
                        case 'power_z':
                        case 'power_Z':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerZ', $this->Translate('Power Z'), $ProfileName);
                            }
                            break;
                        case 'produced_energy':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_ProducedEnergy', $this->Translate('Produced Energy'), $ProfileName);
                            }
                            break;
                        case 'identify':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Identify', $this->Translate('Identify'), $ProfileName);
                                $this->EnableAction('Z2M_Identify');
                            }
                            break;
                        case 'humidity_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_HumiditySensitivity', $this->Translate('Humidity Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_HumiditySensitivity');
                            }
                            break;
                        case 'temperature_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_TemperatureSensitivity', $this->Translate('Temperature Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_TemperatureSensitivity');
                            }
                            break;
                        case 'humidity_periodic_report':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_HumidityPeriodicReport', $this->Translate('Humidity Periodic Report'), $ProfileName);
                                $this->EnableAction('Z2M_HumidityPeriodicReport');
                            }
                            break;
                        case 'temperature_periodic_report':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_TemperaturePeriodicReport', $this->Translate('Temperature Periodic Report'), $ProfileName);
                                $this->EnableAction('Z2M_TemperaturePeriodicReport');
                            }
                            break;
                        case 'gas_value':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_GasValue', $this->Translate('Gas Value'), $ProfileName);
                            }
                            break;
                        case 'max_temperature_alarm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_MaxTemperatureAlarm', $this->Translate('Max Temperature Alarm'), $ProfileName);
                                $this->EnableAction('Z2M_MaxTemperatureAlarm');
                            }
                            break;
                        case 'min_temperature_alarm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_MinTemperatureAlarm', $this->Translate('Min Temperature Alarm'), $ProfileName);
                                $this->EnableAction('Z2M_MinTemperatureAlarm');
                            }
                            break;
                        case 'max_humidity_alarm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_MaxHumidityAlarm', $this->Translate('Max Humidity Alarm'), $ProfileName);
                                $this->EnableAction('Z2M_MaxHumidityAlarm');
                            }
                            break;
                        case 'min_humidity_alarm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_MinHumidityAlarm', $this->Translate('Min Humidity Alarm'), $ProfileName);
                                $this->EnableAction('Z2M_MinHumidityAlarm');
                            }
                            break;
                        case 'error_status':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_ErrorStatus', $this->Translate('Error Status'), $ProfileName);
                            }
                            break;
                        case 'cycle_irrigation_num_times':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_CycleIrrigationNumTimes', $this->Translate('Cycle Irrigation Num Times'), $ProfileName);
                                $this->EnableAction('Z2M_CycleIrrigationNumTimes');
                            }
                            break;
                        case 'irrigation_start_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_IrrigationStartTime', $this->Translate('Irrigation Start Time'), $ProfileName);
                            }
                            break;
                        case 'irrigation_end_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_IrrigationEndTime', $this->Translate('Irrigation End Time'), $ProfileName);
                            }
                            break;
                        case 'last_irrigation_duration':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_LastIrrigationDuration', $this->Translate('Last Irrigation Duration'), $ProfileName);
                            }
                            break;
                        case 'water_consumed':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_WaterConsumed', $this->Translate('Water Consumed'), $ProfileName);
                            }
                            break;
                        case 'irrigation_target':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_IrrigationTarget', $this->Translate('Irrigation Target'), $ProfileName);
                                $this->EnableAction('Z2M_IrrigationTarget');
                            }
                            break;
                        case'cycle_irrigation_interval':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_CycleIrrigationInterval', $this->Translate('Cycle Irrigation Interval'), $ProfileName);
                                $this->EnableAction('Z2M_CycleIrrigationInterval');
                            }
                            break;
                        case 'countdown_l1':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_CountdownL1', $this->Translate('Countdown L1'), $ProfileName);
                                $this->EnableAction('Z2M_CountdownL1');
                            }
                            break;
                        case 'countdown_l2':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_CountdownL2', $this->Translate('Countdown L1'), $ProfileName);
                                $this->EnableAction('Z2M_CountdownL2');
                            }
                            break;
                        case 'presence_timeout':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_Presence_Timeout', $this->Translate('Presence Timeout'), $ProfileName);
                                $this->EnableAction('Z2M_Presence_Timeout');
                            }
                          break;
                        case 'radar_range':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_RadarRange', $this->Translate('Radar Range'), $ProfileName);
                                $this->EnableAction('Z2M_RadarRange');
                            }
                          break;
                        case 'move_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_MoveSensitivity', $this->Translate('Move Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_MoveSensitivity');
                            }
                          break;
                        case 'distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_Distance', $this->Translate('Distance'), $ProfileName);
                            }
                        break;
                        case 'power_reactive':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerReactive', $this->Translate('Power Reactive'), $ProfileName);
                            }
                        break;
                        case 'requested_brightness_level':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_RequestedBrightnessLevel', $this->Translate('Requested Brightness Level'), $ProfileName);
                            }
                            break;
                        case 'requested_brightness_percent':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_RequestedBrightnessPercent', $this->Translate('Requested Brightness Percent'), $ProfileName);
                            }
                            break;
                        case 'z_axis':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_ZAxis', $this->Translate('Z Axis'), $ProfileName);
                            }
                            break;
                        case 'y_axis':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_YAxis', $this->Translate('Y Axis'), $ProfileName);
                            }
                            break;
                        case 'x_axis':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_XAxis', $this->Translate('X Axis'), $ProfileName);
                            }
                            break;
                        case 'power_factor':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PowerFactor', $this->Translate('Power Factor'), $ProfileName);
                            }
                            break;
                        case 'ac_frequency':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_AcFrequency', $this->Translate('AC Frequency'), $ProfileName);
                            }
                            break;
                        case 'small_detection_distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_SmallDetectionDistance', $this->Translate('Small Detection Distance'), $ProfileName);
                                $this->EnableAction('Z2M_SmallDetectionDistance');
                            }
                          break;
                        case 'small_detection_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_SmallDetectionSensitivity', $this->Translate('Small Detection Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_SmallDetectionSensitivity');
                            }
                          break;
                        case 'medium_motion_detection_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_MediumMotionDetectionSensitivity', $this->Translate('Medium Motion Detection Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_MediumMotionDetectionSensitivity');
                            }
                          break;
                        case 'medium_motion_detection_distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_MediumMotionDetectionDistance', $this->Translate('Medium Motion Detection Distance'), $ProfileName);
                                $this->EnableAction('Z2M_MediumMotionDetectionDistance');
                            }
                          break;
                        case 'large_motion_detection_distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_LargeMotionDetectionDistance', $this->Translate('Large Motion Detection Distance'), $ProfileName);
                                $this->EnableAction('Z2M_LargeMotionDetectionDistance');
                            }
                          break;
                        case 'large_motion_detection_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_LargeMotionDetectionSensitivity', $this->Translate('Large Motion Detection Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_LargeMotionDetectionSensitivity');
                            }
                          break;
                        case 'presence_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PresenceSensitivity', $this->Translate('Presence Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_PresenceSensitivity');
                            }
                          break;
                        case 'sensitivity':
                        $ProfileName = $this->registerVariableProfile($expose);
                        if ($ProfileName != false) {
                            $this->RegisterVariableFloat('Z2M_TransmitPower', $this->Translate('Transmit Power'), $ProfileName);
                        }
                        break;
                        if ($ProfileName != false) {
                            $this->RegisterVariableFloat('Z2M_Sensitivity', $this->Translate('Sensitivity'), $ProfileName);
                            $this->EnableAction('Z2M_Sensitivity');
                        }
                        break;
                        case 'detection_distance_min':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_DetectionDistanceMin', $this->Translate('Detection Distance Min'), $ProfileName);
                                $this->EnableAction('Z2M_DetectionDistanceMin');
                            }
                          break;
                        case 'detection_distance_max':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_DetectionDistanceMax', $this->Translate('Detection Distance Max'), $ProfileName);
                                $this->EnableAction('Z2M_DetectionDistanceMax');
                            }
                            break;
                        case 'error':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_TRVError', $this->Translate('Error'), $ProfileName);
                            }
                            break;
                        case 'motion_sensitivity':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_MotionSensitivity', $this->Translate('Motion Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_MotionSensitivity');
                            }
                            break;
                        case 'alarm_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_AlarmTime', $this->Translate('Alarm Time'), $ProfileName);
                                $this->EnableAction('Z2M_AlarmTime');
                            }
                            break;
                        case 'remote_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_RemoteTemperature', $this->Translate('Remote Temperature'), $ProfileName);
                                $this->EnableAction('Z2M_RemoteTemperature');
                            }
                            break;
                        case 'co':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_CO', $this->Translate('Carbon Monoxide'), $ProfileName);
                            }
                            break;
                        case 'filter_age':
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_FilterAge', $this->Translate('Filter Age'), '');
                            }
                            break;
                        case 'occupied_heating_setpoint_scheduled':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_OccupiedHeatingSetpointScheduled', $this->Translate('Occupied Heating Setpoint Scheduled'), $ProfileName);
                                $this->EnableAction('Z2M_OccupiedHeatingSetpointScheduled');
                            }
                            break;
                        case 'regulation_setpoint_offset':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_RegulationSetpointOffset', $this->Translate('Regulation Setpoint Offset'), $ProfileName);
                                $this->EnableAction('Z2M_RegulationSetpointOffset');
                            }
                            break;
                        case 'load_estimate':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_LoadEstimate', $this->Translate('Load Estimate'), $ProfileName);
                                $this->EnableAction('Z2M_LoadEstimate');
                            }
                            break;
                        case 'load_room_mean':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_LoadRoomMean', $this->Translate('Load Room Mean'), $ProfileName);
                                $this->EnableAction('Z2M_LoadRoomMean');
                            }
                            break;
                        case 'algorithm_scale_factor':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_AlgorithmScaleFactor', $this->Translate('Algorithm Scale Factor'), $ProfileName);
                                $this->EnableAction('Z2M_AlgorithmScaleFactor');
                            }
                            break;
                        case 'trigger_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_TriggerTime', $this->Translate('Trigger Time'), $ProfileName);
                                $this->EnableAction('Z2M_TriggerTime');
                            }
                            break;
                        case 'window_open_internal':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_WindowOpenInternal', $this->Translate('Window Open Internal'), 'Z2M.WindowOpenInternal');
                            }
                            break;
                        case 'external_measured_room_sensor':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_ExternalMeasuredRoomSensor', $this->Translate('External Measured Room Sensor'), $ProfileName);
                                $this->EnableAction('Z2M_ExternalMeasuredRoomSensor');
                            }
                            break;
                        case 'smoke_density_dbm':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_SmokeDensityDBM', $this->Translate('Smoke Density db/m'), $ProfileName);
                            }
                            break;
                        case 'display_brightness':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_DisplayBrightness', $this->Translate('Display Brightness'), $ProfileName);
                                $this->EnableAction('Z2M_DisplayBrightness');
                            }
                            break;
                        case 'display_ontime':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_DisplayOntime', $this->Translate('Display Ontime'), $ProfileName);
                                $this->EnableAction('Z2M_DisplayOntime');
                            }
                            break;
                        case 'side':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Side', $this->Translate('Side'), $ProfileName);
                            }
                            break;
                        case 'angle_x':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Angle_X', $this->Translate('Angle X'), $ProfileName);
                            }
                            break;
                        case 'angle_y':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Angle_Y', $this->Translate('Angle Y'), $ProfileName);
                            }
                            break;
                        case 'angle_z':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Angle_Z', $this->Translate('Angle Z'), $ProfileName);
                            }
                            break;
                        case 'boost_heating_countdown_time_set':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_BoostHeatingCountdownTimeSet', $this->Translate('Boost Heating Countdown Time Set'), $ProfileName);
                                $this->EnableAction('Z2M_BoostHeatingCountdownTimeSet');
                            }
                            break;
                        case 'power_outage_count':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_PowerOutageCount', $this->Translate('Power Outage Count'), $ProfileName);
                            }
                            break;
                        case 'duration':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Duration', $this->Translate('Alarm Duration'), $ProfileName);
                                $this->EnableAction('Z2M_Duration');
                            }
                            break;
                        case 'motor_speed':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_MotorSpeed', $this->Translate('Motor Speed'), $ProfileName);
                                $this->EnableAction('Z2M_MotorSpeed');
                            }
                            break;
                        case 'humidity_max':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_HumidityMax', $this->Translate('Humidity Max'), $ProfileName);
                                $this->EnableAction('Z2M_HumidityMax');
                            }
                            break;
                        case 'humidity_min':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_HumidityMin', $this->Translate('Humidity Min'), $ProfileName);
                                $this->EnableAction('Z2M_HumidityMin');
                            }
                            break;
                        case 'temperature_max':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_TemperatureMax', $this->Translate('Temperature Max'), $ProfileName);
                                $this->EnableAction('Z2M_TemperatureMax');
                            }
                            break;
                        case 'temperature_min':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_TemperatureMin', $this->Translate('Temperature Min'), $ProfileName);
                                $this->EnableAction('Z2M_TemperatureMin');
                            }
                            break;
                        case 'action_rate':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('Z2M_ActionRate', $this->Translate('Action Rate'), $ProfileName);
                            }
                            break;
                        case 'action_step_size':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('Z2M_ActionStepSize', $this->Translate('Action Step Size'), $ProfileName);
                            }
                            break;
                        case 'action_transition_time':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('Z2M_ActionTransTime', $this->Translate('Action Transition Time'), $ProfileName);
                            }
                            break;
                        case 'action_group':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('Z2M_ActionGroup', $this->Translate('Action Group'), $ProfileName);
                            }
                            break;
                        case 'action_color_temperature':
                            $Profilename = $this->registerVariableProfile($expose);
                            if ($Profilename != false) {
                                $this->RegisterVariableInteger('Z2M_ActionColorTemp', $this->Translate('Action Color Temperature'), $ProfileName);
                            }
                            break;
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
                        case 'duration_of_attendance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Attendance', $this->Translate('Duration of Attendance'), $ProfileName);
                            }
                            break;
                        case 'duration_of_absence':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_Absence', $this->Translate('Duration of Absence'), $ProfileName);
                            }
                            break;
                        case 'battery':
                            $this->RegisterVariableInteger('Z2M_Battery', $this->Translate('Battery'), '~Battery.100');
                            break;
                        case 'temperature':
                            $this->RegisterVariableFloat('Z2M_Temperature', $this->Translate('Temperature'), '~Temperature');
                            break;
                        case 'temperature_l1':
                            $this->RegisterVariableFloat('Z2M_TemperatureL1', $this->Translate('Temperature L1'), '~Temperature');
                            break;
                        case 'temperature_l2':
                            $this->RegisterVariableFloat('Z2M_TemperatureL2', $this->Translate('Temperature L2'), '~Temperature');
                            break;
                        case 'temperature_l3':
                            $this->RegisterVariableFloat('Z2M_TemperatureL3', $this->Translate('Temperature L3'), '~Temperature');
                            break;
                        case 'temperature_l4':
                            $this->RegisterVariableFloat('Z2M_TemperatureL4', $this->Translate('Temperature L4'), '~Temperature');
                            break;
                        case 'temperature_l5':
                            $this->RegisterVariableFloat('Z2M_TemperatureL5', $this->Translate('Temperature L5'), '~Temperature');
                            break;
                        case 'temperature_l6':
                            $this->RegisterVariableFloat('Z2M_TemperatureL6', $this->Translate('Temperature L6'), '~Temperature');
                            break;
                        case 'temperature_l7':
                            $this->RegisterVariableFloat('Z2M_TemperatureL7', $this->Translate('Temperature L7'), '~Temperature');
                            break;
                        case 'temperature_l8':
                            $this->RegisterVariableFloat('Z2M_TemperatureL8', $this->Translate('Temperature L8'), '~Temperature');
                            break;
                        case 'device_temperature':
                            $this->RegisterVariableFloat('Z2M_DeviceTemperature', $this->Translate('Device Temperature'), '~Temperature');
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
                        case 'pm25':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_PM25', $this->Translate('PM25'), $ProfileName);
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
                        case 'illuminance_lux_l1':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l1', $this->Translate('Illuminance Lux l1'), '~Illumination');
                            break;
                        case 'illuminance_lux_l2':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l2', $this->Translate('Illuminance Lux l2'), '~Illumination');
                            break;
                        case 'illuminance_lux_l3':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l3', $this->Translate('Illuminance Lux l3'), '~Illumination');
                            break;
                        case 'illuminance_lux_l4':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l4', $this->Translate('Illuminance Lux l4'), '~Illumination');
                            break;
                        case 'illuminance_lux_l5':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l5', $this->Translate('Illuminance Lux l5'), '~Illumination');
                            break;
                        case 'illuminance_lux_l6':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l6', $this->Translate('Illuminance Lux l6'), '~Illumination');
                            break;
                        case 'illuminance_lux_l7':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l7', $this->Translate('Illuminance Lux l7'), '~Illumination');
                            break;
                        case 'illuminance_lux_l8':
                            $this->RegisterVariableInteger('Z2M_Illuminance_Lux_l8', $this->Translate('Illuminance Lux l8'), '~Illumination');
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
                        case 'angle_x_absolute':
                            $this->RegisterVariableFloat('Z2M_AngleXAbsolute', $this->Translate('Angle X Absolute'), '');
                            break;
                        case 'angle_y':
                            $this->RegisterVariableFloat('Z2M_Angle_Y', $this->Translate('Angle Y'), '');
                            break;
                        case 'angle_y_absolute':
                            $this->RegisterVariableFloat('Z2M_AngleYAbsolute', $this->Translate('Angle Y Absolute'), '');
                            break;
                        case 'angle_z':
                            $this->RegisterVariableFloat('Z2M_Angle_Z', $this->Translate('Angle Z'), '');
                            break;
                        case 'smoke_density':
                            $this->RegisterVariableFloat('Z2M_SmokeDensity', $this->Translate('Smoke Density'), '');
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
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_MaxTemperature', $this->Translate('Max Temperature'), $ProfileName);
                                $this->EnableAction('Z2M_MaxTemperature');
                            }
                            break;
                        case 'min_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_MinTemperature', $this->Translate('Min Temperature'), $ProfileName);
                                $this->EnableAction('Z2M_MinTemperature');
                            }
                            break;
                        case 'eco_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_EcoTemperature', $this->Translate('Eco Temperature'), $ProfileName);
                                $this->EnableAction('Z2M_EcoTemperature');
                            }
                            break;
                        case 'open_window_temperature':
                            $this->RegisterVariableFloat('Z2M_OpenWindowTemperature', $this->Translate('Open Window Temperature'), '~Temperature');
                            $this->EnableAction('Z2M_OpenWindowTemperature');
                            break;
                        case 'holiday_temperature':
                            $this->RegisterVariableFloat('Z2M_HolidayTemperature', $this->Translate('Holiday Temperature'), '~Temperature');
                            $this->EnableAction('Z2M_HolidayTemperature');
                            break;
                        case 'position':
                            $this->RegisterVariableInteger('Z2M_Position', $this->Translate('Position'), '~Shutter');
                            break;
                        case 'position_left':
                            $this->RegisterVariableInteger('Z2M_PositionLeft', $this->Translate('Position Left'), '~Shutter');
                            break;
                        case 'position_right':
                            $this->RegisterVariableInteger('Z2M_PositionRight', $this->Translate('Position Right'), '~Shutter');
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
                        case 'boost_timeset_countdown':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_BoostTimesetCountdown', $this->Translate('Boost Time'), $ProfileName);
                                $this->EnableAction('Z2M_BoostTimesetCountdown');
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
                                $this->RegisterVariableFloat('Z2M_CalibrationTime', $this->Translate('Calibration Time'), $ProfileName);
                            }
                            break;
                        case 'calibration_time_left':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CalibrationTimeLeft', $this->Translate('Calibration Time Left'), $ProfileName);
                            }
                            break;
                        case 'calibration_time_right':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_CalibrationTimeRight', $this->Translate('Calibration Time Right'), $ProfileName);
                            }
                            break;
                        case 'soil_moisture':
                                $this->RegisterVariableInteger('Z2M_SoilMoisture', $this->Translate('Soil Moisture'), '~Intensity.100');
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
                                $this->RegisterVariableInteger('Z2M_RadarSensitivity', $this->Translate('Radar Sensitivity'), $ProfileName);
                                $this->EnableAction('Z2M_RadarSensitivity');
                            }
                            break;
                        case 'fan_speed':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_FanSpeed', $this->Translate('Fan Speed'), $ProfileName);
                            }
                            break;
                        case 'action_duration':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_ActionDuration', $this->Translate('Action Duration'), $ProfileName);
                            }
                            break;
                        case 'percent_state':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_PercentState', $this->Translate('PercentState'), $ProfileName);
                                $this->EnableAction('Z2M_PercentState');
                            }
                            break;
                        case 'target_distance':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_TargetDistance', $this->Translate('Target Distance'), $ProfileName);
                            }
                            break;
                        case 'minimum_range':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_MinimumRange', $this->Translate('Minimum Range'), $ProfileName);
                                $this->EnableAction('Z2M_MinimumRange');
                            }
                            break;
                        case 'maximum_range':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_MaximumRange', $this->Translate('Maximum Range'), $ProfileName);
                                $this->EnableAction('Z2M_MaximumRange');
                            }
                            break;
                        case 'deadzone_temperature':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_DeadzoneTemperature', $this->Translate('Deadzone Temperature'), $ProfileName);
                                $this->EnableAction('Z2M_DeadzoneTemperature');
                            }
                            break;
                        case 'max_temperature_limit':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_MaxTemperatureLimit', $this->Translate('Max Temperature Limit'), $ProfileName);
                                $this->EnableAction('Z2M_MaxTemperatureLimit');
                            }
                            break;
                        case 'detection_delay':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_DetectionDelay', $this->Translate('Detection Delay'), $ProfileName);
                                $this->EnableAction('Z2M_DetectionDelay');
                            }
                            break;
                        case 'fading_time':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableFloat('Z2M_FadingTime', $this->Translate('Fading Time'), $ProfileName);
                                $this->EnableAction('Z2M_FadingTime');
                            }
                            break;
                        case 'detection_interval':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->registerVariableFloat('Z2M_DetectionInterval', $this->Translate('Detection Interval'), $ProfileName);
                                $this->EnableAction('Z2M_DetectionInterval');
                            }
                            break;
                        case 'action_code':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->registerVariableFloat('Z2M_ActionCode', $this->Translate('Action Code'), $ProfileName);
                            }
                            break;
                        case 'action_transaction':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->registerVariableFloat('Z2M_ActionTransaction', $this->Translate('Action Transaction'), $ProfileName);
                            }
                            break;
                        case 'brightness_white':
                            $ProfileName = $this->registerVariableProfile($expose);
                            if ($ProfileName != false) {
                                $this->RegisterVariableInteger('Z2M_BrightnessWhite', $this->Translate('Brightness White'), $ProfileName);
                                $this->EnableAction('Z2M_BrightnessWhite');
                            }
                            break;
                        default:
                            $missedVariables['numeric'][] = $expose;
                            break;
                    }
                    break; //numeric break
                case 'composite':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        case 'execute_if_off':
                                            $this->RegisterVariableBoolean('Z2M_ExecuteIfOff', $this->Translate('Execute If Off'), '~Switch');
                                            $this->EnableAction('Z2M_ExecuteIfOff');
                                            break;
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
                                                $this->RegisterVariableInteger('Z2M_StrobeDutyCycle', $this->Translate('Strobe Duty Cycle'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_StrobeDutyCycle');
                                            break;
                                        case 'duration':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableFloat('Z2M_Duration', $this->Translate('Duration'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_Duration');
                                            break;
                                        case 'motor_speed':
                                            $this->RegisterVariableInteger('Z2M_MotorSpeed', $this->Translate('Motor Speed'), '~Intensity.255');
                                            $this->EnableAction('Z2M_MotorSpeed');
                                            break;
                                        case 'region_id':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableInteger('Z2M_RegionID', $this->Translate('Region id'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_RegionID');
                                            break;
                                        default:
                                            // Default composite binary
                                            $missedVariables['composite'][] = $feature;
                                            break;
                                    }
                                    break; //Composite numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        case 'week_day':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_WeekDay', $this->Translate('Week Day'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_WeekDay');
                                            break;
                                        case 'mode':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_Mode', $this->Translate('Mode'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_Mode');
                                            break;
                                        case 'week':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_Week', $this->Translate('Woche'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_Week');
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
                    break; //Composite break
                case 'cover':
                    if (array_key_exists('features', $expose)) {
                        foreach ($expose['features'] as $key => $feature) {
                            switch ($feature['type']) {
                                case 'binary':
                                    switch ($feature['property']) {
                                        default:
                                            // Default cover binary
                                            $missedVariables['cover'][] = $feature;
                                            break;
                                    }
                                    break; //Cover binaray break;
                                case 'numeric':
                                    switch ($feature['property']) {
                                        case 'position':
                                            $this->RegisterVariableInteger('Z2M_Position', $this->Translate('Position'), '~Intensity.100');
                                            $this->EnableAction('Z2M_Position');
                                            break;
                                        case 'position_left':
                                            $this->RegisterVariableInteger('Z2M_PositionLeft', $this->Translate('Position Left'), '~Intensity.100');
                                            $this->EnableAction('Z2M_PositionLeft');
                                            break;
                                        case 'position_right':
                                            $this->RegisterVariableInteger('Z2M_PositionRight', $this->Translate('Position Right'), '~Intensity.100');
                                            $this->EnableAction('Z2M_PositionRight');
                                            break;
                                        default:
                                            // Default cover binary
                                            $missedVariables['cover'][] = $feature;
                                            break;
                                    }
                                    break; //Cover numeric break;
                                case 'enum':
                                    switch ($feature['property']) {
                                        case 'state':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_State', $this->Translate('State'), $ProfileName);
                                            }
                                            if ($ProfileName != 'Z2M.State.12345678') {
                                                $this->EnableAction('Z2M_State');
                                            }
                                            break;
                                        case 'state_left':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_state_left', $this->Translate('State Left'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_state_left');
                                            break;
                                        case 'state_right':
                                            $ProfileName = $this->registerVariableProfile($feature);
                                            if ($ProfileName != false) {
                                                $this->RegisterVariableString('Z2M_state_right', $this->Translate('State Right'), $ProfileName);
                                            }
                                            $this->EnableAction('Z2M_state_right');
                                            break;
                                        default:
                                            // Default cover enum
                                            $missedVariables['cover'][] = $feature;
                                            break;
                                    }
                                    break; //Cover enum break;
                            }
                        }
                    }
                    break; //Cover break
                default: // Expose Type default
                    break;
            }
        }
        $this->SendDebug(__FUNCTION__ . ':: Missed Exposes', json_encode($missedVariables), 0);
    }
}
