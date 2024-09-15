<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';

require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/Zigbee2MQTTHelper.php';

class Zigbee2MQTTDevice extends IPSModule
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\ColorHelper;
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
        $this->RegisterPropertyString('IEEE', '');
        $this->RegisterAttributeString('Icon', '');
        // createVariableProfiles existiert nicht
        // Wurden hier nicht die Basis Profile erstellt?
        //$this->createVariableProfiles();
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
        $Filter2 = preg_quote('"Topic":"' . $BaseTopic . '/SymconExtension/response/getDeviceInfo/' . $MQTTTopic);
        $this->SendDebug('Filter', '.*(' . $Filter1 . '|' . $Filter2 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . ').*');
        $this->SetSummary($this->ReadPropertyString('IEEE'));
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            $this->UpdateDeviceInfo();
        }
        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Form['elements'][0]['items'][1]['image'] = $this->ReadAttributeString('Icon');
        return json_encode($Form);
    }

    private function UpdateDeviceInfo()
    {
        $Result = $this->SendData('/SymconExtension/request/getDeviceInfo/' . $this->ReadPropertyString('MQTTTopic'));
        $this->SendDebug('result', json_encode($Result), 0);
        if ($Result === false) {
            return false;
        }
        if (array_key_exists('ieeeAddr', $Result)) {
            if (empty($this->ReadPropertyString('IEEE')) && ($this->ReadPropertyString('IEEE') != $Result['ieeeAddr'])) {
                // Einmalig die leere IEEE Adresse in der Konfig setzen.
                IPS_SetProperty($this->InstanceID, 'IEEE', $Result['ieeeAddr']);
                IPS_ApplyChanges($this->InstanceID);
                return true;
            }
            if (array_key_exists('model', $Result)) {
                $Model = $Result['model'];
                if ($Model != 'Unknown Model') { // nur wenn Z2M ein Model liefert
                    if (!$this->ReadAttributeString('Icon')) { // und wir noch kein Bild haben
                        $Url = 'https://raw.githubusercontent.com/Koenkk/zigbee2mqtt.io/master/public/images/devices/' . $Model . '.png';
                        $this->SendDebug('loadImage', $Url, 0);
                        $ImageRaw = @file_get_contents($Url);
                        if ($ImageRaw) {
                            $Icon = 'data:image/png;base64,' . base64_encode($ImageRaw);
                            $this->WriteAttributeString('Icon', $Icon);
                        }
                    }
                }
            }
            $this->mapExposesToVariables($Result['exposes']);
            return true;
        }
        trigger_error($this->Translate('Device not found. Check topic'), E_USER_NOTICE);
        return false;
    }
}
