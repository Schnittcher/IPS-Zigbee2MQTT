<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

trait Zigbee2MQTTHelper
{

    private $stateTypeMapping = [
        'Z2M_ChildLock'                         => ['type' => 'lockunlock', 'dataType' =>'string'],
        'Z2M_StateWindow'                       => ['type' => 'openclose', 'dataType' =>'string'],
        'Z2M_AutoLock'                          => ['type' => 'automode', 'dataType' => 'string'],
        'Z2M_ValveState'                        => ['type' => 'valve', 'dataType' => 'string'],
        'Z2M_EcoTemperature'                    => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_MaxTemperature'                    => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_MinTemperature'                    => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_TemperatureMax'                    => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_TemperatureMin'                    => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_OccupiedHeatingSetpointScheduled'  => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_ComfortTemperature'                => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_LocalTemperatureCalibration'       => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_OpenWindowTemperature'             => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f'],
        'Z2M_HolidayTemperature'                => ['type' => 'numeric', 'dataType' => 'float', 'format' => '%.2f']
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
        case 'Z2M_ColorTempKelvin':
            $convertedValue = strval(intval(round(1000000 / $value, 0)));
            $payloadKey = $this->convertIdentToPayloadKey($ident);
            $payload = [$payloadKey => $convertedValue];
            $payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $this->Z2MSet($payloadJSON);
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

            $Payload = json_decode($Buffer['Payload'], true);
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/deviceInfo', $Buffer['Topic'])) {
                if (is_array($Payload['exposes'])) {
                    $this->mapExposesToVariables($Payload['exposes']);
                }
            }
            if (fnmatch('symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/groupInfo', $Buffer['Topic'])) {
                if (is_array($Payload)) {
                    $this->mapExposesToVariables($Payload);
                }
            }

            $payload = json_decode($Buffer['Payload'], true);
            foreach ($payload as $key => $value) {
                $ident = 'Z2M_' . implode('', array_map('ucfirst', explode('_', $key)));
                $variableID = @$this->GetIDForIdent($ident);

                if ($variableID !== false) {
                    $variableInfo = IPS_GetVariable($variableID);
                    $variableType = $variableInfo['VariableType'];
                    $translate = $this->convertKeyToReadableFormat($key);
                    // Prüfen, ob der aktuelle Schlüssel spezielle Behandlung erfordert
                    // Spezielle Behandlungen unabhängig vom Typ durchführen
                    $handled = false; // Flag, um zu markieren, ob eine spezielle Behandlung durchgeführt wurde
                    switch ($key) {
                        case 'update_available':
                            $this->RegisterVariableBoolean('Z2M_Update', $this->Translate('Update'), '');
                            $this->SetValue('Z2M_Update', $payload['update_available']);
                            $handled = true;
                            break;
                        case 'scene':
                            $this->LogMessage('Please contact module developer. Undefined variable: scene', KL_WARNING);
                            //$this->RegisterVariableString('Z2M_Scene', $this->Translate('Scene'), '');
                            //$this->SetValue('Z2M_Scene', $payload['scene']);
                            $handled = true;
                            break;

                        case 'voltage':
                            if ($payload['voltage'] > 400) { //Es gibt wahrscheinlich keine Zigbee Geräte mit über 400 Volt
                                $this->SetValue('Z2M_Voltage', $payload['voltage'] / 1000);
                            } else {
                                $this->SetValue('Z2M_Voltage', $payload['voltage']);
                            }
                            $handled = true;
                            break;
                        case 'action_rate':
                            $this->RegisterVariableInteger('Z2M_ActionRate', $this->Translate('Action Rate'), $ProfileName);
                            $this->EnableAction('Z2M_ActionRate');
                            $this->SetValue('Z2M_ActionRate', $payload['action_rate']);
                            $handled = true;
                            break;
                        case 'action_level':
                            $this->RegisterVariableInteger('Z2M_ActionLevel', $this->Translate('Action Level'), $ProfileName);
                            $this->EnableAction('Z2M_ActionLevel');
                            $this->SetValue('Z2M_ActionLevel', $payload['action_level']);
                            $handled = true;
                            break;
                        case 'action_transition_time':
                            $this->RegisterVariableInteger('Z2M_ActionTransitionTime', $this->Translate('Action Transition Time'), $ProfileName);
                            $this->EnableAction('Z2M_ActionTransitionTime');
                            $this->SetValue('Z2M_ActionTransitionTime', $payload['action_transition_time']);
                            $handled = true;
                            break;
                        case 'child_lock':
                            $this->handleStateChange('child_lock', 'Z2M_ChildLock', 'Child Lock', $payload, ['LOCK' => true, 'UNLOCK' => false]);
                            $handled = true;
                            break;
                        case 'color':
                            if (is_array($value)) {
                                if (isset($value['x']) && isset($value['y'])) {
                                    $this->SendDebug(__FUNCTION__ . ' Color', $value['x'], 0);
                                    $brightness = isset($value['brightness']) ? $value['brightness'] : 255;
                                    $RGBColor = ltrim($this->xyToHEX($value['x'], $value['y'], $brightness), '#');
                                    $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                                    $this->SetValue($ident, hexdec($RGBColor));
                                } elseif (isset($value['hue']) && isset($value['saturation'])) {
                                    $RGBColor = ltrim($this->HSToRGB($value['hue'], $value['saturation'], 255), '#');
                                    $this->SendDebug(__FUNCTION__ . ' Color RGB HEX', $RGBColor, 0);
                                    $this->SetValue($ident, hexdec($RGBColor));
                                }
                            }
                            $handled = true;
                            break;
                        case 'color_rgb':
                            if (isset($payload['color_rgb']) && is_array($payload['color_rgb'])) {
                                $colorRgb = $payload['color_rgb'];
                                $this->SendDebug(__FUNCTION__ . ':: Color X', $colorRgb['x'], 0);
                                $this->SendDebug(__FUNCTION__ . ':: Color Y', $colorRgb['y'], 0);
                                // Bestimmen der Helligkeit, falls vorhanden
                                $brightnessRgb = isset($payload['brightness_rgb']) ? $payload['brightness_rgb'] : 255;
                                $RGBColor = ltrim($this->xyToHEX($colorRgb['x'], $colorRgb['y'], $brightnessRgb), '#');
                                $this->SendDebug(__FUNCTION__ . ' Color :: RGB HEX', $RGBColor, 0);
                                $this->SetValue('Z2M_ColorRGB', hexdec($RGBColor));
                            }
                            $handled = true;
                            break;
                        case 'color_temp_cct':
                            $this->SetValue('Z2M_ColorTempCCT', $payload['color_temp_cct']);
                            if ($payload['color_temp_cct'] > 0) {
                                $this->SetValue('Z2M_ColorTempCCTKelvin', 1000000 / $payload['color_temp_cct']); //Convert to Kelvin
                            }
                            $handled = true;
                            break;
                        case 'color_temp_rgb':
                            $this->SetValue('Z2M_ColorTempRGB', $payload['color_temp_rgb']);
                            if ($payload['color_temp_rgb'] > 0) {
                                $this->SetValue('Z2M_ColorTempRGBKelvin', 1000000 / $payload['color_temp_rgb']); //Convert to Kelvin
                            }
                            $handled = true;
                            break;
                        case 'color_temp':
                            $this->SetValue('Z2M_ColorTemp', $payload['color_temp']);
                            if ($payload['color_temp'] > 0) {
                                $this->SetValue('Z2M_ColorTempKelvin', 1000000 / $payload['color_temp']); //Convert to Kelvin
                            }
                            $handled = true;
                            break;
                        case 'brightness_rgb':
                            $this->EnableAction('Z2M_BrightnessRGB');
                            $this->SetValue('Z2M_BrightnessRGB', $payload['brightness_rgb']);
                            $handled = true;
                            break;
                        case 'color_temp_startup_rgb':
                            $this->SetValue('Z2M_ColorTempStartupRGB', $payload['color_temp_startup_rgb']);
                            $this->EnableAction('Z2M_ColorTempStartupRGB');
                            $handled = true;
                            break;
                        case 'color_temp_startup_cct':
                            $this->SetValue('Z2M_ColorTempStartupCCT', $payload['color_temp_startup_cct']);
                            $this->EnableAction('Z2M_ColorTempStartupCCT');
                            $handled = true;
                            break;
                        case 'color_temp_startup':
                            $this->SetValue('Z2M_ColorTempStartup', $payload['color_temp_startup']);
                            $this->EnableAction('Z2M_ColorTempStartup');
                            $handled = true;
                            break;
                        case 'state_rgb':
                            $this->handleStateChange('state_rgb', 'Z2M_StateRGB', 'State_rgb', $payload, );
                            $this->EnableAction('Z2M_StateRGB');
                            $handled = true;
                            break;
                        case 'state_cct':
                            $this->handleStateChange('state_cct', 'Z2M_StateCCT', 'State_cct', $payload);
                            $this->EnableAction('Z2M_StateCCT');
                            $handled = true;
                            break;
                        case 'last_seen':
                            $translate = $this->convertKeyToReadableFormat($key);
                            $this->RegisterVariableInteger('Z2M_LastSeen', $this->Translate($translate), '~UnixTimestamp');
                            $this->SetValue($ident, $value / 1000);
                            $handled = true;
                            break;
                        case 'smoke_alarm_state':
                            $translate = $this->convertKeyToReadableFormat($key);
                            $this->handleStateChange($key, $ident, $translate, $payload);
                            $handled = true;
                            break;
                    }

                    if (!$handled) {
                        // Allgemeine Typbehandlung, wenn keine spezielle Behandlung durchgeführt wurde
                        switch ($variableType) {
                            case 0: // Boolean
                                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                                $this->SendDebug(__FUNCTION__, "Ident: $ident, Wert: $value, Typ: Boolean", 0);
                                break;
                            case 1: // Integer
                                $value = intval($value);
                                $this->SendDebug(__FUNCTION__, "Ident: $ident, Wert: $value, Typ: Integer", 0);
                                break;
                            case 2: // Float
                                $value = floatval($value);
                                $this->SendDebug(__FUNCTION__, "Ident: $ident, Wert: $value, Typ: Float", 0);
                                break;
                            case 3: // String
                                $this->SendDebug(__FUNCTION__, "Ident: $ident, Wert: " . json_encode($value) . ", Typ: String", 0);
                                if (is_array($value)) {
                                // Konvertiert das Array zu einem String
                                // $value = json_encode($value); // Für eine JSON-Darstellung
                                    $value = implode(', ', $value); // Für eine kommagetrennte Liste
                                } else {
                                    // Stellt sicher, dass der Wert ein String ist
                                    $value = strval($value);
                                }
                                break;
                        }

                        $this->SetValue($ident, $value);
                    }
                } else {
                    // Die Variable existiert nicht; hier könnte Logik zum Erstellen der Variable stehen
                    $this->SendDebug(__FUNCTION__, "Ident $ident nicht gefunden", 0);
                }
            }
        }
    }
    private function convertKeyToReadableFormat($key)
    {
        $this->SendDebug(__FUNCTION__, "Schlüssel: $key", 0);
        $translateParts = explode('_', $key); // Teilt den Schlüssel in Teile
        $translatedParts = array_map('ucfirst', $translateParts); // Kapitalisiert jeden Teil
        $translate = implode(' ', $translatedParts); // Fügt die Teile mit einem Leerzeichen zusammen
        return $translate;
    }

    private function convertKeyToIdent($key)
    {
        $identParts = explode('_', $key); // Teilt den Schlüssel an Unterstrichen
        $capitalizedParts = array_map('ucfirst', $identParts); // Kapitalisiert jeden Teil
        $ident = 'Z2M_' . implode('', $capitalizedParts); // Fügt die Teile mit einem Präfix zusammen
        $this->SendDebug(__FUNCTION__, "Ident: $ident", 0);
        return $ident;
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
                    $Payload['color'] = $cie;
                    $Payload['brightness'] = $cie['bri'];
                } elseif ($Z2MMode == 'color_rgb') {
                    $Payload['color_rgb'] = $cie;
                } else {
                    return;
                }

                foreach ($params as $key => $value) {
                    $Payload[$key] = $value;
                }

                $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
                $this->SendDebug(__FUNCTION__, $PayloadJSON, 0);
                $this->Z2MSet($PayloadJSON);
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
        $this->SendDebug('Info :: convertIdentToPayloadKey', 'Ident: '. $ident.'-> IdentWithoutPrefix: '. $identWithoutPrefix, 0);
        $payloadKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $identWithoutPrefix));
        $this->SendDebug('Info :: convertIdentToPayloadKey', 'Ident: '. $ident.'-> PayloadKey: '. $payloadKey, 0);
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
    private function handleStateChange($payloadKey, $valueId, $debugTitle, $Payload, $stateMapping = null)
    {
        if (array_key_exists($payloadKey, $Payload)) {
            $state = $Payload[$payloadKey];
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
                    $Payload['color'] = $cie;
                    $Payload['brightness'] = $cie['bri'];
                } elseif ($Z2MMode == 'color_rgb') {
                    $Payload['color_rgb'] = $cie;
                } else {
                    return;
                }
                $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
                $this->Z2MSet($PayloadJSON);
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
                    $Payload = [
                        'color' => [
                            'hue'        => $HSB['hue'],
                            'saturation' => $HSB['saturation'],
                        ]
                    ];
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

    private function registerVariableProfile($expose)
    {
        $ProfileName = 'Z2M.' . $expose['name'];
        $unit = isset($expose['unit']) ? ' ' . $expose['unit'] : '';

        switch ($expose['type']) {
            case 'binary':
                switch ($expose['property']) {
                    case 'consumer_connected':
                        if (!IPS_VariableProfileExists($ProfileName)) {
                            $this->RegisterProfileBooleanEx($ProfileName, 'Plug', '', '', [
                                [false, $this->Translate('not connected'), '', 0xFF0000],
                                [true, $this->Translate('connected'), '', 0x00FF00]
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
                    sort($expose['values']); // Sortieren, um Konsistenz beim Hashing zu gewährleisten
                    $tmpProfileName = implode('', $expose['values']);
                    $ProfileName .= '.' . dechex(crc32($tmpProfileName));

                    if (!IPS_VariableProfileExists($ProfileName)) {
                        $profileValues = [];
                        foreach ($expose['values'] as $value) {
                            $readableValue = ucwords(str_replace('_', ' ', $value));
                            $translatedValue = $this->Translate($readableValue);
                            if ($translatedValue === $readableValue) {
                                $this->SendDebug(__FUNCTION__ . ':: Missing Translation', "Keine Übersetzung für Wert: $readableValue", 0);
                            }
                            $profileValues[] = [$value, $translatedValue, '', 0x00FF00]; // Beispiel für eine Standardfarbe
                        }
                        $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', $profileValues);
                    }
                } else {
                    $this->SendDebug(__FUNCTION__ . ':: Variableprofile missing', $ProfileName, 0);
                    $this->SendDebug(__FUNCTION__ . ':: ProfileName Values', json_encode($expose['values']), 0);
                    return false;
                }
                break;

            case 'numeric':
                // Auslagern der numeric Logik in eine spezialisierte Funktion
                return $this->registerNumericProfile($expose);

            default:
                $this->SendDebug(__FUNCTION__ . ':: Type not handled', $ProfileName, 0);
                return false;
        }
    }
    private function registerNumericProfile($expose) {
        $ProfileName = 'Z2M.' . $expose['name'];
        $min = $expose['value_min'] ?? 0;
        $max = $expose['value_max'] ?? 0;
        $fullRangeProfileName = $ProfileName . $min . '_' . $max;
        $presetProfileName = $fullRangeProfileName . '_Presets';
        $unit = isset($expose['unit']) ? ' ' . $expose['unit'] : '';

        $this->SendDebug("registerNumericProfile", "ProfileName: $fullRangeProfileName, min: $min, max: $max, unit: $unit", 0);

        if (!IPS_VariableProfileExists($fullRangeProfileName)) {
            $this->RegisterProfileInteger($fullRangeProfileName, 'Bulb', '', $unit, $min, $max, 1);
        }

        if (isset($expose['presets']) && !empty($expose['presets'])) {
            if (IPS_VariableProfileExists($presetProfileName)) {
                IPS_DeleteVariableProfile($presetProfileName);
            }
            $this->RegisterProfileInteger($presetProfileName, 'Bulb', '', '', 0, 0, 0);
            foreach ($expose['presets'] as $preset) {
                $presetValue = $preset['value'];
                $presetName = $this->Translate(ucwords(str_replace('_', ' ', $preset['name'])));

                $this->SendDebug("Preset Info", "presetValue: $presetValue, presetName: $presetName", 0);

                IPS_SetVariableProfileAssociation($presetProfileName, $presetValue, $presetName, '', 0xFFFFFF);
            }
        }

        return ['mainProfile' => $fullRangeProfileName, 'presetProfile' => $presetProfileName];
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
                                                $profileData = $this->registerNumericProfile($feature);
                                                $mainProfileName = $profileData['mainProfile'];
                                                $presetProfileName = $profileData['presetProfile'];

                                                $this->SendDebug("Variable Registration", "Main Profile: $mainProfileName, Preset Profile: $presetProfileName", 0);

                                                if ($mainProfileName) {
                                                    $this->RegisterVariableInteger('Z2M_ColorTemp', $this->Translate('Color Temperature'), $mainProfileName);
                                                    $this->EnableAction('Z2M_ColorTemp');
                                                    $this->SendDebug("Variable Registration", "Main Color Temp Variable Created with ID: $variableID", 0);

                                                }

                                                if ($presetProfileName) {
                                                    $this->RegisterVariableInteger('Z2M_ColorTempPresets', $this->Translate('Color Temperature Presets'), $presetProfileName);
                                                    $this->EnableAction('Z2M_ColorTempPresets');
                                                    $this->SendDebug("Variable Registration", "Preset Color Temp Variable Created with ID: $presetVariableID", 0);
                                                }

                                            // Anlegen weiterer nicht-automatisierter Kelvin Temperaturvariablen
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
