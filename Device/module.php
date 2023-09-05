<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace Zigbee2MQTT {?>' . file_get_contents(__DIR__ . '/../libs/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace Zigbee2MQTT {?>' . file_get_contents(__DIR__ . '/../libs/ColorHelper.php') . '}');
eval('declare(strict_types=1);namespace Zigbee2MQTT {?>' . file_get_contents(__DIR__ . '/../libs/MQTTHelper.php') . '}');
eval('declare(strict_types=1);namespace Zigbee2MQTT {?>' . file_get_contents(__DIR__ . '/../libs/Zigbee2MQTTHelper.php') . '}');

class Zigbee2MQTTDevice extends IPSModule
{
    use \Zigbee2MQTT\ColorHelper;
    use \Zigbee2MQTT\MQTTHelper;
    use \Zigbee2MQTT\VariableProfileHelper;
    use \Zigbee2MQTT\Zigbee2MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zigbee2mqtt');
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->createVariableProfiles();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        $Filter1 = preg_quote('"Topic":"' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '"');
        $Filter2 = preg_quote('"Topic":"symcon/' . $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/');
        //$this->SendDebug('Filter ::', $MQTTTopic, 0);
        //$this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');

        $this->SendDebug('Filter ', '.*(' . $Filter1 . '|' . $Filter2 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . ').*');
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            $this->getDeviceInfo();
        }
        $this->SetStatus(102);
    }
}
