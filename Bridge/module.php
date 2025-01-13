<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once dirname(__DIR__) . '/libs/VariableProfileHelper.php';

/**
 * @property array $TransactionData
 * @property string $actualExtensionVersion
 * @property string $ExtensionFilename
 * @property string $ConfigLastSeen
 * @property bool $ConfigPermitJoin
 */
class Zigbee2MQTTBridge extends IPSModule
{
    use \Zigbee2MQTT\BufferHelper;
    use \Zigbee2MQTT\Semaphore;
    use \Zigbee2MQTT\VariableProfileHelper;

    /** @var array ZH Version zu Erweiterung  */
    private const EXTENSION_ZH_VERSION = [
        2 => 'IPSymconExtension.js',
        //3 => 'IPSymconExtension2.js'
    ];

    private static $MQTTDataArray = [
        'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
        'PacketType'       => 3,
        'QualityOfService' => 0,
        'Retain'           => false,
        'Topic'            => '',
        'Payload'          => ''
    ];

    /**
     * Create
     *
     * @return void
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('MQTTBaseTopic', 'zigbee2mqtt');
        /*
        $Version = 'unknown';
        $File = file(dirname(__DIR__) . '/libs/IPSymconExtension.js');
        $Start = strpos($File[2], 'Version: ');
        if ($Start) {
            $Version = trim(substr($File[2], $Start + strlen('Version: ')));
        }
         */
        $this->actualExtensionVersion = 0;
        $this->installedZhVersion = 0;
        $this->ExtensionFilename = '';
        $this->ConfigLastSeen = 'epoch';
        $this->TransactionData = [];
        $this->ConfigPermitJoin = false;
    }

    public function ApplyChanges()
    {
        $this->TransactionData = [];
        //Never delete this line!
        parent::ApplyChanges();
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

        $online = false;
        if (!empty($BaseTopic)) {
            if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                $online = @$this->RequestOptions();
            }
        }
        $this->SendDebug('Online', $online ? 'true' : 'false', 0);
        $installedExtVersion = (float) $this->GetValue('extension_version');
        $this->SetValue('extension_is_current', $this->actualExtensionVersion <= $installedExtVersion);
        if ($this->actualExtensionVersion <= $installedExtVersion) {
            $this->UpdateFormField('InstallExtension', 'label', $this->Translate('Symcon-Extension is up-to-date'));
            $this->UpdateFormField('InstallExtension', 'enabled', false);
        } else {
            $this->UpdateFormField('InstallExtension', 'label', $this->Translate('Install or upgrade Symcon-Extension'));
            $this->UpdateFormField('InstallExtension', 'enabled', true);
            if (!empty($BaseTopic)) {
                if ($online) {
                    @$this->InstallSymconExtension();
                }
            }
        }
    }

    public function ReceiveData($JSONString)
    {
        if ($this->GetStatus() == IS_CREATING) {
            return '';
        }
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
                    $this->installedZhVersion = $Payload['zigbee_herdsman']['version'];
                    if (isset(self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])) {
                        $Extension = file_get_contents(dirname(__DIR__) . '/libs/' . self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion]);
                        preg_match('/Version: (.*)/', $Extension, $matches);
                        if (isset($matches[1])) {
                            $this->actualExtensionVersion = (float) $matches[1];
                        }
                    } else {
                        $this->actualExtensionVersion = 0;
                    }
                    $this->SetValue('zigbee_herdsman', $Payload['zigbee_herdsman']['version']);
                }
                if (isset($Payload['config']['advanced']['last_seen'])) {
                    $this->ConfigLastSeen = $Payload['config']['advanced']['last_seen'];
                    if ($Payload['config']['advanced']['last_seen'] == 'epoch') {
                        $this->UpdateFormField('SetLastSeen', 'label', $this->Translate('last_seen setting is correct'));
                        $this->UpdateFormField('SetLastSeen', 'enabled', false);
                    } else {
                        $this->UpdateFormField('SetLastSeen', 'label', $this->Translate('Set last_seen setting to epoch'));
                        $this->UpdateFormField('SetLastSeen', 'enabled', true);
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
                        $this->SendDebug('Found Extension', $this->ExtensionName, 0);
                        preg_match('/Version: (.*)/', $Extension['code'], $matches);
                        if (isset($matches[1])) {
                            $Version = $matches[1];
                        }
                        if ($this->actualExtensionVersion <= (float) $Version) {
                            $this->UpdateFormField('InstallExtension', 'label', $this->Translate('Symcon-Extension is up-to-date'));
                            $this->UpdateFormField('InstallExtension', 'enabled', false);
                        } else {
                            $this->UpdateFormField('InstallExtension', 'label', $this->Translate('Install or upgrade Symcon-Extension'));
                            $this->UpdateFormField('InstallExtension', 'enabled', true);
                            $this->LogMessage($this->Translate('Symcon Extension in Zigbee2MQTT is outdated. Please update the extension.'), KL_ERROR);
                        }
                    }
                }
                $this->SetValue('extension_loaded', $foundExtension);
                $this->SetValue('extension_version', $Version);
                $this->SetValue('extension_is_current', $this->actualExtensionVersion == (float) $Version);
                if (!$foundExtension) {
                    $this->LogMessage($this->Translate('No Symcon Extension in Zigbee2MQTT installed. Please install the extension.'), KL_ERROR);
                }
                break;
        }
        return '';
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
            case 'restart_request':
                $this->Restart();
                break;
        }
    }

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

    public function InstallSymconExtension()
    {
        if ($this->installedZhVersion == 0) {
            $this->LogMessage($this->Translate('Cannot determine ZH Version. No Extension installed.'), KL_WARNING);
            return;
        }
        if (!isset(self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])) {
            return;

        }
        $ExtensionName = $this->ExtensionName == '' ? 'IPSymconExtension.js' : $this->ExtensionName;
        $Topic = '/bridge/request/extension/save';
        $Payload = ['name'=>$ExtensionName, 'code'=>file_get_contents(dirname(__DIR__) . '/libs/' . self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])];
        $Result = $this->SendData($Topic, $Payload);
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
        }
        if (isset($Result['status'])) {
            return $Result['status'] == 'ok';
        }
        return false;
    }

    /**
     * RequestOptions
     *
     * @return bool
     */
    public function RequestOptions()
    {
        $Topic = '/bridge/request/options';
        $Payload = [
            'options'=> []
        ];
        $Result = $this->SendData($Topic, $Payload);
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
        }
        if (isset($Result['status'])) {
            return $Result['status'] == 'ok';
        }
        return false;
    }

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

    public function Restart()
    {
        $Topic = '/bridge/request/restart';
        $Result = $this->SendData($Topic);
        if ($Result) { //todo check the Response
            return true;
        }
        return false;
    }

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

    public function RequestNetworkmap()
    {
        $Topic = '/bridge/request/networkmap';
        $Payload = ['type' => 'graphviz', 'routes' => true];
        return $this->SendData($Topic, $Payload, 0);
    }

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

    private function SendData(string $Topic, array $Payload = [], int $Timeout = 5000)
    {
        if ($Timeout) {
            $TransactionId = $this->AddTransaction($Payload);
        }
        $this->SendDebug(__FUNCTION__ . ':Topic', $Topic, 0);
        $this->SendDebug(__FUNCTION__ . ':Payload', json_encode($Payload), 0);
        $DataJSON = self::BuildRequest($this->ReadPropertyString('MQTTBaseTopic') . $Topic, $Payload);
        $this->SendDataToParent($DataJSON);
        if ($Timeout) {
            $Result = $this->WaitForTransactionEnd($TransactionId, $Timeout);
            if ($Result === false) {
                trigger_error($this->Translate('Zigbee2MQTT did not response.'), E_USER_NOTICE);
                return false;
            }
            return $Result;
        }
        return true;
    }

    private function WaitForTransactionEnd(int $TransactionId, int $Timeout)
    {
        $Sleep = intdiv($Timeout, 1000);
        for ($i = 0; $i < 1000; $i++) {
            $Buffer = $this->TransactionData;
            if (!array_key_exists($TransactionId, $Buffer)) {
                return false;
            }
            if (count($Buffer[$TransactionId])) {
                $this->RemoveTransaction($TransactionId);
                return $Buffer[$TransactionId];
            }
            IPS_Sleep($Sleep);
        }
        $this->RemoveTransaction($TransactionId);
        return false;
    }
    //################# SENDQUEUE

    private function AddTransaction(array &$Payload)
    {
        if (!$this->lock('TransactionData')) {
            throw new Exception($this->Translate('TransactionData is locked'), E_USER_NOTICE);
        }
        $TransactionId = mt_rand(1, 10000);
        $Payload['transaction'] = $TransactionId;
        $TransactionData = $this->TransactionData;
        $TransactionData[$TransactionId] = [];
        $this->TransactionData = $TransactionData;
        $this->unlock('TransactionData');
        return $TransactionId;
    }

    private function UpdateTransaction(array $Data)
    {
        if (!$this->lock('TransactionData')) {
            throw new Exception($this->Translate('TransactionData is locked'), E_USER_NOTICE);
        }
        $TransactionData = $this->TransactionData;
        if (array_key_exists($Data['transaction'], $TransactionData)) {
            $TransactionData[$Data['transaction']] = $Data;
            $this->TransactionData = $TransactionData;
            $this->unlock('TransactionData');
            return;
        }
        $this->unlock('TransactionData');
        return;
    }

    private function RemoveTransaction(int $TransactionId)
    {
        if (!$this->lock('TransactionData')) {
            throw new Exception($this->Translate('TransactionData is locked'), E_USER_NOTICE);
        }
        $TransactionData = $this->TransactionData;
        unset($TransactionData[$TransactionId]);
        $this->TransactionData = $TransactionData;
        $this->unlock('TransactionData');
    }

    private static function BuildRequest(string $Topic, array $Payload)
    {
        return json_encode(
            array_merge(
                self::$MQTTDataArray,
                [
                    'Topic'  => $Topic,
                    'Payload'=> json_encode($Payload)
                ]
            ),
            JSON_UNESCAPED_SLASHES
        );
    }
}
