<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once dirname(__DIR__) . '/libs/VariableProfileHelper.php';
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';

/**
 * @property string $actualExtensionVersion Enthält die Aktuelle Version der Extension in einem InstanzBuffer
 * @property string $ExtensionFilename Enthält den Dateinamen der Extension in einem InstanzBuffer
 * @property string $ConfigLastSeen Enthält die Z2M Konfiguration der LastSeen Option in einem InstanzBuffer
 * @property bool $ConfigPermitJoin Enthält die Z2M Konfiguration der PermitJoin Option in einem InstanzBuffer
 */
class Zigbee2MQTTBridge extends IPSModule
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\VariableProfileHelper;
    use \Zigbee2MQTT\SendData;

    /**
     * Create
     *
     * @return void
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', '');
        $Version = 'unknown';
        $File = file(dirname(__DIR__) . '/libs/IPSymconExtension.js');
        $Start = strpos($File[2], 'Version: ');
        if ($Start) {
            $Version = trim(substr($File[2], $Start + strlen('Version: ')));
        }
        $this->actualExtensionVersion = $Version;
        $this->ExtensionFilename = '';
        $this->ConfigLastSeen = 'epoch';
        $this->TransactionData = [];
        $this->ConfigPermitJoin = false;
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges()
    {
        $this->TransactionData = [];
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
            $this->SetReceiveDataFilter('.*"Topic":"' . $BaseTopic . '/bridge/.*');
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
        $this->RegisterProfileInteger('Z2M.seconds', '', '', ' s', 0, 0, 1, 0);
        $this->RegisterVariableBoolean('state', $this->Translate('State'));
        $this->RegisterVariableBoolean('extension_loaded', $this->Translate('Extension Loaded'));
        $this->RegisterVariableString('extension_version', $this->Translate('Extension Version'));
        $this->RegisterVariableBoolean('extension_is_current', $this->Translate('Extension is up to date'));
        $this->RegisterVariableString('log_level', $this->Translate('Log Level'), 'Z2M.brigde.loglevel');
        $this->EnableAction('log_level');
        $this->RegisterVariableBoolean('permit_join', $this->Translate('Allow joining the network'), '~Switch');
        $this->EnableAction('permit_join');
        $this->RegisterVariableInteger('permit_join_timeout', $this->Translate('Permit Join Timeout'), 'Z2M.seconds');
        $this->RegisterVariableBoolean('restart_required', $this->Translate('Restart Required'));
        $this->RegisterVariableInteger('restart_request', $this->Translate('Perform a restart'), 'Z2M.bridge.restart');
        $this->EnableAction('restart_request');
        $this->RegisterVariableString('version', $this->Translate('Version'));
        $this->RegisterVariableString('zigbee_herdsman_converters', $this->Translate('Zigbee Herdsman Converters Version'));
        $this->RegisterVariableString('zigbee_herdsman', $this->Translate('Zigbee Herdsman Version'));
        $this->RegisterVariableInteger('network_channel', $this->Translate('Network Channel'));

        if (!empty($BaseTopic)) {
            if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                @$this->RequestOptions();
            }
        }

        $ExtVersion = $this->GetValue('extension_version');
        if (!empty($ExtVersion) && ($ExtVersion != 'unknown')) {
            $this->SetValue('extension_is_current', $this->actualExtensionVersion == $ExtVersion);
            if ($this->actualExtensionVersion == $ExtVersion) {
                $this->UpdateFormField('InstallExtension', 'enabled', false);
            } else {
                //$this->LogMessage($this->Translate('Symcon Extension in Zigbee2MQTT is outdated. Please update the extension.'), KL_ERROR);
                @$this->InstallSymconExtension();
            }
        }

    }

    /**
     * ReceiveData
     *
     * @param  string $JSONString
     * @return string
     */
    public function ReceiveData($JSONString)
    {
        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        if (empty($BaseTopic)) {
            return '';
        }
        $this->SendDebug('ReceiveData', $JSONString, 0);

        $Buffer = json_decode($JSONString, true);
        if (!isset($Buffer['Topic'])) {
            return '';
        }
        $ReceiveTopic = $Buffer['Topic'];
        $this->SendDebug('MQTT FullTopic', $ReceiveTopic, 0);
        $Topic = substr($ReceiveTopic, strlen($BaseTopic . '/bridge/'));
        $Topics = explode('/', $Topic);
        $Topic = array_shift($Topics);
        $this->SendDebug('MQTT Topic', $Topic, 0);
        $this->SendDebug('MQTT Payload', utf8_decode($Buffer['Payload']), 0);
        $Payload = json_decode(utf8_decode($Buffer['Payload']), true);
        switch ($Topic) {
            case 'request': //nothing todo
                break;
            case 'response': //response from request
                if (isset($Payload['transaction'])) {
                    $this->UpdateTransaction($Payload);
                    break;
                }
                if (count($Topics)) {
                    if ($Topics[0] == 'networkmap') {
                        if ($Payload['status'] == 'ok') {
                            $this->RegisterVariableString($Payload['data']['type'], $this->Translate('Network Map'));
                            $this->SetValue($Payload['data']['type'], $Payload['data']['value']);
                        }
                    }
                }
                break;
            case 'state':
                $this->SetValue('state', $Payload['state'] == 'online');
                break;
            case 'info':
                if (isset($Payload['log_level'])) {
                    $this->SetValue('log_level', $Payload['log_level']);
                }
                if (isset($Payload['permit_join'])) {
                    $this->SetValue('permit_join', $Payload['permit_join']);
                    if ($Payload['permit_join'] === false) {
                        $this->SetValue('permit_join_timeout', 0);
                    }
                }
                if (isset($Payload['permit_join_timeout'])) {
                    $this->SetValue('permit_join_timeout', $Payload['permit_join_timeout']);
                }
                if (isset($Payload['restart_required'])) {
                    $this->SetValue('restart_required', $Payload['restart_required']);
                }
                if (isset($Payload['version'])) {
                    $this->SetValue('version', $Payload['version']);
                }
                if (isset($Payload['config']['permit_join'])) {
                    $this->ConfigPermitJoin = $Payload['config']['permit_join'];
                    $this->UpdateFormField('PermitJoinOption', 'visible', $Payload['config']['permit_join']);
                    if ($Payload['config']['permit_join']) {
                        $this->LogMessage($this->Translate("Danger! In the Zigbee2MQTT configuration permit_join is activated.\r\nThis leads to a possible security risk!"), KL_ERROR);
                    }
                }
                if (isset($Payload['zigbee_herdsman_converters']['version'])) {
                    $this->SetValue('zigbee_herdsman_converters', $Payload['zigbee_herdsman_converters']['version']);
                }
                if (isset($Payload['zigbee_herdsman']['version'])) {
                    $this->SetValue('zigbee_herdsman', $Payload['zigbee_herdsman']['version']);
                }
                if (isset($Payload['config']['advanced']['last_seen'])) {
                    $this->ConfigLastSeen = $Payload['config']['advanced']['last_seen'];
                    if ($Payload['config']['advanced']['last_seen'] == 'epoch') {
                        $this->UpdateFormField('SetLastSeen', 'enabled', false);
                    } else {
                        $this->LogMessage($this->Translate('Wrong last_seen setting in Zigbee2MQTT. Please set last_seen to epoch.'), KL_ERROR);
                    }
                }
                if (isset($Payload['network'])) {
                    $this->SetValue('network_channel', $Payload['network']['channel']);
                }
                break;
            case 'extensions':
                $foundExtension = false;
                $Version = 'unknown';
                foreach ($Payload as $Extension) {
                    if (strpos($Extension['code'], 'class IPSymconExtension') !== false) {
                        if ($foundExtension) {
                            $this->LogMessage($this->Translate("Danger! Several extensions for Symcon have been found.\r\nPlease delete outdated versions manually to avoid malfunctions."), KL_ERROR);
                            continue;
                        }
                        $foundExtension = true;
                        $this->ExtensionName = $Extension['name'];
                        $Lines = explode("\n", $Extension['code']);
                        $Start = strpos($Lines[2], 'Version: ');
                        if ($Start) {
                            $Version = trim(substr($Lines[2], $Start + strlen('Version: ')));
                        }
                        if ($this->actualExtensionVersion == $Version) {
                            $this->UpdateFormField('InstallExtension', 'enabled', false);
                        } else {
                            $this->LogMessage($this->Translate('Symcon Extension in Zigbee2MQTT is outdated. Please update the extension.'), KL_ERROR);
                        }
                    }
                }
                $this->SetValue('extension_loaded', $foundExtension);
                $this->SetValue('extension_version', $Version);
                $this->SetValue('extension_is_current', $this->actualExtensionVersion == $Version);
                if (!$foundExtension) {
                    $this->LogMessage($this->Translate('No Symcon Extension in Zigbee2MQTT installed. Please install the extension.'), KL_ERROR);
                }
                break;
        }
        return '';
    }

    /**
     * RequestAction
     *
     * @param  string $Ident
     * @param  mixed $Value
     * @return void
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'permit_join':
                $this->SetPermitJoin((bool) $Value);
                break;
            case 'log_level':
                $this->SetLogLevel((string) $Value);
                break;
            case 'restart_request':
                $this->Restart();
                break;
        }
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetValue('extension_loaded') && $this->GetValue('extension_is_current')) {
            $Form['actions'][0]['enabled'] = false;
            $Form['actions'][0]['label'] = $this->Translate('Symcon-Extension is up-to-date');
        }
        if ($this->ConfigLastSeen == 'epoch') {
            $Form['actions'][1]['enabled'] = false;
            $Form['actions'][1]['label'] = $this->Translate('last_seen setting is correct');
        }
        if ($this->ConfigPermitJoin) {
            $Form['actions'][2]['visible'] = true;
        }
        return json_encode($Form);
    }

    /**
     * InstallSymconExtension
     *
     * @todo todo check the Response
     * @return bool
     */
    public function InstallSymconExtension()
    {
        if (empty($this->ExtensionName)) {
            $ExtensionName = 'IPSymconExtension.js';
        }
        $Topic = '/bridge/request/extension/save';
        $Payload = ['name'=>$ExtensionName, 'code'=>file_get_contents(dirname(__DIR__) . '/libs/IPSymconExtension.js')];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * RequestOptions
     *
     * @todo todo check the Response
     * @return bool
     */
    public function RequestOptions()
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> []
        ];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * SetLastSeen
     *
     * @todo todo check the Response
     * @return bool
     */
    public function SetLastSeen()
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> [
                'advanced'=> [
                    'last_seen'=> 'epoch'
                ]
            ]
        ];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * SetPermitJoinOption
     *
     * @todo todo check the Response
     * @param  bool $PermitJoin
     * @return bool
     */
    public function SetPermitJoinOption(bool $PermitJoin)
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options'=> ['permit_join' => $PermitJoin]];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * SetPermitJoin
     *
     * @todo todo check the Response
     * @param  bool $PermitJoin
     * @return bool
     */
    public function SetPermitJoin(bool $PermitJoin)
    {
        $Topic = '/bridge/request/permit_join';
        $Payload = ['value'=>$PermitJoin, 'time'=> 254];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * SetLogLevel
     *
     * @todo todo check the Response
     * @param  string $LogLevel
     * @return bool
     */
    public function SetLogLevel(string $LogLevel)
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options' =>['advanced' => ['log_level'=> $LogLevel]]];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * Restart
     *
     * @todo todo check the Response
     * @return bool
     */
    public function Restart()
    {
        $Topic = '/bridge/request/restart';
        $Result = $this->SendData($Topic);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * CreateGroup
     *
     * @todo todo check the Response
     * @param  string $GroupName
     * @return bool
     */
    public function CreateGroup(string $GroupName)
    {
        $Topic = '/bridge/request/group/add';
        $Payload = ['friendly_name' => $GroupName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * DeleteGroup
     *
     * @todo todo check the Response
     * @param  string $GroupName
     * @return bool
     */
    public function DeleteGroup(string $GroupName)
    {
        $Topic = '/bridge/request/group/remove';
        $Payload = ['id' => $GroupName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * RenameGroup
     *
     * @todo todo check the Response
     * @param  string $OldName
     * @param  string $NewName
     * @return bool
     */
    public function RenameGroup(string $OldName, string $NewName)
    {
        $Topic = '/bridge/request/group/rename';
        $Payload = ['from' => $OldName, 'to' => $NewName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * AddDeviceToGroup
     *
     * @todo todo check the Response
     * @param  string $GroupName
     * @param  string $DeviceName
     * @return bool
     */
    public function AddDeviceToGroup(string $GroupName, string $DeviceName)
    {
        $Topic = '/bridge/request/group/members/add';
        $Payload = ['group'=>$GroupName, 'device' => $DeviceName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * RemoveDeviceFromGroup
     *
     * @todo todo check the Response
     * @param  string $GroupName
     * @param  string $DeviceName
     * @return bool
     */
    public function RemoveDeviceFromGroup(string $GroupName, string $DeviceName)
    {
        $Topic = '/bridge/request/group/members/remove';
        $Payload = ['group'=>$GroupName, 'device' => $DeviceName, 'skip_disable_reporting'=>true];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * RemoveAllDevicesFromGroup
     *
     * @todo todo check the Response
     * @param  string $GroupName
     * @return bool
     */
    public function RemoveAllDevicesFromGroup(string $GroupName)
    {
        $Topic = '/bridge/request/group/members/remove_all';
        $Payload = ['group'=>$GroupName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * Bind
     *
     * @todo todo check the Response
     * @param  string $SourceDevice
     * @param  string $TargetDevice
     * @return bool
     */
    public function Bind(string $SourceDevice, string $TargetDevice)
    {
        $Topic = '/bridge/request/device/bind';
        $Payload = ['from' => $SourceDevice, 'to' => $TargetDevice];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * Unbind
     *
     * @todo todo check the Response
     * @param  string $SourceDevice
     * @param  string $TargetDevice
     * @return bool
     */
    public function Unbind(string $SourceDevice, string $TargetDevice)
    {
        $Topic = '/bridge/request/device/unbind';
        $Payload = ['from' => $SourceDevice, 'to' => $TargetDevice];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * RequestNetworkmap
     *
     * @todo todo check the Response
     * @return bool
     */
    public function RequestNetworkmap()
    {
        $Topic = '/bridge/request/networkmap';
        $Payload = ['type' => 'graphviz', 'routes' => true];
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * RenameDevice
     *
     * @todo todo check the Response
     * @param  string $OldDeviceName
     * @param  string $NewDeviceName
     * @return bool
     */
    public function RenameDevice(string $OldDeviceName, string $NewDeviceName)
    {
        $Topic = '/bridge/request/device/rename';
        $Payload = ['from' => $OldDeviceName, 'to' => $NewDeviceName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * RemoveDevice
     *
     * @todo todo check the Response
     * @param  string $DeviceName
     * @return bool
     */
    public function RemoveDevice(string $DeviceName)
    {
        $Topic = '/bridge/request/device/remove';
        $Payload = ['id'=>$DeviceName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * CheckOTAUpdate
     *
     * @todo todo check the Response
     * @param  string $DeviceName
     * @return bool
     */
    public function CheckOTAUpdate(string $DeviceName)
    {
        $Topic = '/bridge/request/device/ota_update/check';
        $Payload = ['id'=>$DeviceName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

    /**
     * PerformOTAUpdate
     *
     * @todo todo check the Response
     * @param  string $DeviceName
     * @return bool
     */
    public function PerformOTAUpdate(string $DeviceName)
    {
        $Topic = '/bridge/request/device/ota_update/update';
        $Payload = ['id'=>$DeviceName];
        $Result = $this->SendData($Topic, $Payload);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

}
