<?php

declare(strict_types=1);
define('MQTT_GROUP_TOPIC', 'zigbee2mqtt');

if (!function_exists('fnmatch')) {
    function fnmatch($pattern, $string)
    {
        return preg_match('#^' . strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.']) . '$#i', $string);
    }
}

trait Zigbee2MQTTHelper
{
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Z2M_Brightness':
                $this->setDimmer($Value);
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

    public function Command(string $topic, string $value)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topic;
        $Data['Payload'] = $value;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function setDimmer(int $value)
    {
        $Payload['brightness'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->publish($PayloadJSON);
    }

    public function setPosition(int $value)
    {
        $Payload['position'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->publish($PayloadJSON);
    }

    public function SwitchMode(bool $value)
    {
        $this->OnOff($Value);
        $Payload['state'] = $state;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->publish($PayloadJSON);
    }

    public function setColorMode(int $mode)
    {
        $Payload['color_mode'] = strval($mode);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->publish($PayloadJSON);
    }

    public function setColor(int $color, string $mode)
    {
        switch ($mode) {
            case 'cie':
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToCIE($RGB[0], $RGB[1], $RGB[2]);

                $Payload['color'] = $cie;
                $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
                $this->publish($PayloadJSON);
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
        $this->publish($PayloadJSON);
    }

    protected function HexToRGB($value)
    {
        $RGB = [];
        $RGB[0] = (($value >> 16) & 0xFF);
        $RGB[1] = (($value >> 8) & 0xFF);
        $RGB[2] = ($value & 0xFF);
        $this->SendDebug('HexToRGB', 'R: ' . $RGB[0] . ' G: ' . $RGB[1] . ' B: ' . $RGB[2], 0);
        return $RGB;
    }

    protected function RGBToCIE($red, $green, $blue)
    {
        $red = ($red > 0.04045) ? pow(($red + 0.055) / (1.0 + 0.055), 2.4) : ($red / 12.92);
        $green = ($green > 0.04045) ? pow(($green + 0.055) / (1.0 + 0.055), 2.4) : ($green / 12.92);
        $blue = ($blue > 0.04045) ? pow(($blue + 0.055) / (1.0 + 0.055), 2.4) : ($blue / 12.92);

        $X = $red * 0.664511 + $green * 0.154324 + $blue * 0.162028;
        $Y = $red * 0.283881 + $green * 0.668433 + $blue * 0.047685;
        $Z = $red * 0.000088 + $green * 0.072310 + $blue * 0.986039;
        $this->SendDebug('RGBToCIE', 'X: ' . $X . ' Y: ' . $Y . ' Z: ' . $Z, 0);

        $cie['x'] = round(($X / ($X + $Y + $Z)), 4);
        $cie['y'] = round(($Y / ($X + $Y + $Z)), 4);

        return $cie;
    }

    protected function CIEToRGB($x, $y, $brightness = 255)
    {
        $z = 1.0 - $x - $y;
        $Y = $brightness / 255;
        $X = ($Y / $y) * $x;
        $Z = ($Y / $y) * $z;

        $red = $X * 1.656492 - $Y * 0.354851 - $Z * 0.255038;
        $green = -$X * 0.707196 + $Y * 1.655397 + $Z * 0.036152;
        $blue = $X * 0.051713 - $Y * 0.121364 + $Z * 1.011530;

        if ($red > $blue && $red > $green && $red > 1.0) {
            $green = $green / $red;
            $blue = $blue / $red;
            $red = 1.0;
        } elseif ($green > $blue && $green > $red && $green > 1.0) {
            $red = $red / $green;
            $blue = $blue / $green;
            $green = 1.0;
        } elseif ($blue > $red && $blue > $green && $blue > 1.0) {
            $red = $red / $blue;
            $green = $green / $blue;
            $blue = 1.0;
        }
        $red = $red <= 0.0031308 ? 12.92 * $red : (1.0 + 0.055) * $red ** (1.0 / 2.4) - 0.055;
        $green = $green <= 0.0031308 ? 12.92 * $green : (1.0 + 0.055) * $green ** (1.0 / 2.4) - 0.055;
        $blue = $blue <= 0.0031308 ? 12.92 * $blue : (1.0 + 0.055) * $blue ** (1.0 / 2.4) - 0.055;

        $red = ceil($red * 255);
        $green = ceil($green * 255);
        $blue = ceil($blue * 255);

        $color = sprintf('#%02x%02x%02x', $red, $green, $blue);

        return $color;
    }

    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1) {
                throw new Exception('Variable profile type does not match for profile ' . $Name);
            }
        }
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }
        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 0);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 0) {
                throw new Exception('Variable profile type does not match for profile ' . $Name);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    private function OnOff(bool $Value)
    {
        switch ($value) {
            case true:
                $state = 'ON';
                break;
            case false:
                $state = 'OFF';
                break;
        }
        return $state;
    }

    private function publish($payload)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/set';
        $Data['Payload'] = $payload;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Publish Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }
}

trait Zigbee2MQTTBridgeHelper
{
    public function AddGroup(string $group_name, string $friendly_name)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/group/' . $group_name . '/add';
        $Data['Payload'] = $friendly_name;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Add Group Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function RemoveGroup(string $group_name, string $friendly_name)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/group/' . $group_name . '/remove';
        $Data['Payload'] = $friendly_name;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Remove Group Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function RemoveAllGroup(string $group_name, string $friendly_name)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/group/' . $group_name . '/remove_all';
        $Data['Payload'] = $friendly_name;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Publish Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function Bind(string $source_device, string $target_device)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/bind/' . $source_device;
        $Data['Payload'] = $target_device;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Bind Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function Unbind(string $source_device, string $target_device)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/unbind/' . $source_device;
        $Data['Payload'] = $target_device;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Unbind Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function getGroupMembership(string $friendly_name)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/device/' . $friendly_name . '/get_group_membership';
        $Data['Payload'] = '';
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'getGroupMembership Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function Networkmap()
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/networkmap';
        $Data['Payload'] = 'graphviz';
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Bind Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function RenameDevice(string $old_friendly_name, string $new_friendly_name)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/config/rename';

        $Payload['old'] = $old_friendly_name;
        $Payload['new'] = $new_friendly_name;
        $Data['Payload'] = json_encode($Payload);

        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'RenameDevice Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function BanDevice(string $friendly_name)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/config/ban';
        $Data['Payload'] = $friendly_name;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Bind Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function RemoveDevice(string $friendly_name)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = MQTT_GROUP_TOPIC . '/' . $this->ReadPropertyString('MQTTTopic') . '/config/remove';
        $Data['Payload'] = $friendly_name;
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Bind Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }
}
