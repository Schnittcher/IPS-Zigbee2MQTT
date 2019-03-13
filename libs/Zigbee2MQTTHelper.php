<?php

declare(strict_types=1);
define('MQTT_GROUP_TOPIC', 'zigbee2mqtt');

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
            default:
                $this->SendDebug('Request Action', 'No Action defined: ' . $Ident, 0);
                break;
        }
    }

    public function setDimmer(int $value)
    {
        $Payload['brightness'] = strval($value);
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->publish($PayloadJSON);
    }

    public function SwitchMode(bool $value)
    {
        switch ($value) {
            case true:
                $state = 'ON';
                break;
            case false:
                $state = 'OFF';
                break;
        }
        $Payload['state'] = $state;
        $PayloadJSON = json_encode($Payload, JSON_UNESCAPED_SLASHES);
        $this->publish($PayloadJSON);
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
