<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';

require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/Zigbee2MQTTHelper.php';

class Zigbee2MQTTGroup extends IPSModule
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\ColorHelper;
    use \Zigbee2MQTT\MQTTHelper;
    use \Zigbee2MQTT\SendData;
    use \Zigbee2MQTT\VariableProfileHelper;
    use \Zigbee2MQTT\Zigbee2MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', '');
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyInteger('GroupId', 0);
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
        $this->TransactionData = [];
        if (empty($BaseTopic) || empty($MQTTTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
            return;
        }
        //Setze Filter für ReceiveData
        $Filter1 = preg_quote('"Topic":"' . $BaseTopic . '/' . $MQTTTopic . '"');
        $Filter2 = preg_quote('"Topic":"' . $BaseTopic . '/SymconExtension/response/getGroupInfo/' . $MQTTTopic);
        $this->SendDebug('Filter ', '.*(' . $Filter1 . '|' . $Filter2 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . ').*');
        $GroupId=$this->ReadPropertyInteger('GroupId');
        $GroupId = $GroupId ? 'Group Id: '. $GroupId : '';
        $this->SetSummary($GroupId);
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            $this->UpdateGroupInfo();
        }
        $this->SetStatus(IS_ACTIVE);
    }

    public function UpdateGroupInfo()
    {
        $Result = $this->SendData('/SymconExtension/request/getGroupInfo/' . $this->ReadPropertyString('MQTTTopic'));

        if ($Result) {
            if ($Result['foundGroup']) {
                unset($Result['foundGroup']);
                $this->mapExposesToVariables($Result);
                return true;
            }
            trigger_error($this->Translate('Group not found. Check topic'), E_USER_NOTICE);
        }
        return false;
    }
}
