<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';
require_once dirname(__DIR__) . '/libs/VariableProfileHelper.php';
require_once dirname(__DIR__) . '/libs/Zigbee2MQTTBridgeHelper.php';
require_once dirname(__DIR__) . '/libs/Zigbee2MQTTHelper.php';

class Zigbee2MQTTBridge extends IPSModule
{
    use \Zigbee2MQTT\Zigbee2MQTTBridgeHelper;
    use \Zigbee2MQTT\VariableProfileHelper;
    use \Zigbee2MQTT\MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', 'zigbee2mqtt');
        $Version = 'unknown';
        $File = file(dirname(__DIR__) . '/libs/IPSymconExtension.js');
        $Start = strpos($File[2], 'Version: ');
        if ($Start) {
            $Version = trim(substr($File[2], $Start + strlen('Version: ')));
        }
        $this->SetBuffer('actualExtensionVersion', $Version);
        $this->SetBuffer('ExtensionFilename', '');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        if (empty($BaseTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
        } else {
            $this->SetStatus(IS_ACTIVE);
            //Setze Filter für ReceiveData
            $this->SetReceiveDataFilter('.*"Topic":"' . $this->ReadPropertyString('MQTTBaseTopic') . '/bridge/.*');
        }
        $this->RegisterProfileIntegerEx('Z2M.bridge.restart', '', '', '', [
            [0, $this->Translate('Restart'), '', 0xFF0000],
        ]);
        $this->RegisterProfileStringEx('Z2M.brigde.loglevel', '', '', '', [
            ['error', $this->Translate('Error'), '', 0x00FF00],
            ['warning', $this->Translate('Warning'), '', 0x00FF00],
            ['info', $this->Translate('Information'), '', 0x00FF00],
            ['debug', $this->Translate('Debug'), '', 0x00FF00],

        ]);
    }

    public function ReceiveData($JSONString)
    {
        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        if (empty($BaseTopic)) {
            return '';
        }
        $this->SendDebug('JSON', $JSONString, 0);

        $Buffer = json_decode($JSONString, true);
        if (!isset($Buffer['Topic'])) {
            return '';
        }
        $ReceiveTopic = $Buffer['Topic'];
        $this->SendDebug('MQTT FullTopic', $ReceiveTopic, 0);
        $Topic = substr($ReceiveTopic, strlen($BaseTopic . '/bridge/'));
        $this->SendDebug('MQTT Topic', $Topic, 0);
        $this->SendDebug('MQTT Payload', $Buffer['Payload'], 0);
        $Payload = json_decode($Buffer['Payload'], true);
        switch ($Topic) {
            case 'state':
                $this->RegisterVariableBoolean('state', $this->Translate('State'));
                $this->SetValue('state', $Payload['state'] == 'online');
                break;
            case 'info':
                if (isset($Payload['log_level'])) {
                    $this->RegisterVariableString('log_level', $this->Translate('Log Level'), 'Z2M.brigde.loglevel');
                    $this->EnableAction('log_level');
                    $this->SetValue('log_level', $Payload['log_level']);
                }
                if (isset($Payload['permit_join'])) {
                    $this->RegisterVariableBoolean('permit_join', $this->Translate('Allow joining the network'), '~Switch');
                    $this->EnableAction('permit_join');
                    $this->SetValue('permit_join', $Payload['permit_join']);
                }
                if (isset($Payload['restart_required'])) {
                    $this->RegisterVariableBoolean('restart_required', $this->Translate('Restart Required'));
                    $this->SetValue('restart_required', $Payload['restart_required']);
                    $this->RegisterVariableInteger('restart_request', $this->Translate('Perform a restart'), 'Z2M.bridge.restart');
                    $this->EnableAction('restart_request');
                }
                if (isset($Payload['version'])) {
                    $this->RegisterVariableString('version', $this->Translate('Version'));
                    $this->SetValue('version', $Payload['version']);
                }
                if (isset($Payload['network'])) {
                    $this->RegisterVariableInteger('network_channel', $this->Translate('Network Channel'));
                    $this->SetValue('network_channel', $Payload['network']['channel']);
                }
                break;
            case 'extensions':
                $foundExtension = false;
                foreach ($Payload as $Extension) {
                    if (strpos($Extension['code'], 'class IPSymconExtension')) {
                        $foundExtension = true;
                        $this->SetBuffer('ExtensionName', $Extension['name']);
                        $Version = 'unknown';
                        $Lines = explode("\n", $Extension['code']);
                        $Start = strpos($Lines[2], 'Version: ');
                        if ($Start) {
                            $Version = trim(substr($Lines[2], $Start + strlen('Version: ')));
                        }
                        $this->RegisterVariableString('extension_version', $this->Translate('Extension Version'));
                        $this->SetValue('extension_version', $Version);
                        $this->RegisterVariableBoolean('extension_is_current', $this->Translate('Extension is up to date'));
                        $this->SetValue('extension_is_current', $this->GetBuffer('actualExtensionVersion') == $Version);
                        break;
                    }
                }
                $this->RegisterVariableBoolean('extension_loaded', $this->Translate('Extension Loaded'));
                $this->SetValue('extension_loaded', $foundExtension);
                break;
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'permit_join':
                $this->SetPermitJoin((bool) $Value);
                break;
            case 'log_level':
                $this->SetLogLevel((string) $Value);
                break;
            case'restart_request':
                $this->Restart();
                break;
        }
    }

    public function InstallSymconExtension()
    {
        $ExtensionName = $this->GetBuffer('ExtensionName');
        if (empty($ExtensionName)) {
            $ExtensionName = 'IPSymconExtension.js';
        }
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/bridge/request/extension/save';
        $Data['Payload'] = json_encode(['name'=>$ExtensionName, 'code'=>file_get_contents(dirname(__DIR__) . '/libs/IPSymconExtension.js')]);
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Restart', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }
}
