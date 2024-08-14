<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';

require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';
require_once __DIR__ . '/../libs/Zigbee2MQTTHelper.php';

/**
 * @property array $TransactionData
 */
class Zigbee2MQTTDevice extends IPSModule
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
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
        $this->RegisterPropertyString('IEEE', '');
        $this->createVariableProfiles();
        $this->TransactionData = [];
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');

        if (empty($BaseTopic) || empty($MQTTTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
            $this->SetStatus(201);
            return;
        }
        //Setze Filter für ReceiveData
        $Filter1 = preg_quote('"Topic":"' . $BaseTopic . '/' . $MQTTTopic . '"');
        $Filter2 = preg_quote('"Topic":"' . $BaseTopic . '/SymconExtension/response/getDeviceInfo/' . $MQTTTopic);
        $this->SendDebug('Filter', '.*(' . $Filter1 . '|' . $Filter2 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . ').*');

        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            $this->UpdateDeviceInfo();
        }
        $this->SetStatus(102);
    }

    public function UpdateDeviceInfo()
    {
        $Result = $this->SendData('/SymconExtension/request/getDeviceInfo/' . $this->ReadPropertyString('MQTTTopic'));
        if ($Result) {
            $this->mapExposesToVariables($Result['exposes']);
            return true;
        }
        return false;
    }
}
