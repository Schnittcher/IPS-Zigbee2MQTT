<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/SemaphoreHelper.php';
require_once dirname(__DIR__) . '/libs/VariableProfileHelper.php';
require_once dirname(__DIR__) . '/libs/MQTTHelper.php';

/**
 * Zigbee2MQTTBridge
 *
 * @property float $actualExtensionVersion Enthält die benötigte Version der Extension passend zu Z2M in einem InstanzBuffer
 * @property float $installedZhVersion Enthält die installierte Version des zigbee-herdsman Moduls
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

    /** @var array ZH Version zu Erweiterung  */
    private const EXTENSION_ZH_VERSION = [
        2  => 'IPSymconExtension.js',
        3  => 'IPSymconExtension2.js',
        4  => 'IPSymconExtension2.js',
        5  => 'IPSymconExtension2.js',
        6  => 'IPSymconExtension2.js',
        7  => 'IPSymconExtension2.js',
        8  => 'IPSymconExtension2.js',
        9  => 'IPSymconExtension2.js',
        10 => 'IPSymconExtension2.js',
        11 => 'IPSymconExtension2.js',
        12 => 'IPSymconExtension2.js',
        13 => 'IPSymconExtension2.js',
        14 => 'IPSymconExtension2.js',
        15 => 'IPSymconExtension2.js',
        16 => 'IPSymconExtension2.js',
        17 => 'IPSymconExtension2.js',
        18 => 'IPSymconExtension2.js'
    ];
    /**
     * Create
     *
     * @uses IPSModule::Create()
     * @uses IPSModule::RegisterPropertyString()
     *
     * @return void
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString(self::MQTT_BASE_TOPIC, '');

        // Init Buffers
        $this->actualExtensionVersion = 0;
        $this->installedZhVersion = 0;
        $this->ExtensionFilename = '';
        $this->ConfigLastSeen = 'epoch';
        $this->TransactionData = [];
        $this->ConfigPermitJoin = false;
    }

    /**
     * ApplyChanges
     *
     * @return void
     *
     * @uses IPSModule::ApplyChanges()
     * @uses IPSModule::ReadPropertyString()
     * @uses IPSModule::SetStatus()
     * @uses IPSModule::SetReceiveDataFilter()
     * @uses IPSModule::SetSummary()
     * @uses IPSModule::UnregisterVariable()
     * @uses IPSModule::RegisterVariableBoolean()
     * @uses IPSModule::RegisterVariableString()
     * @uses IPSModule::RegisterVariableInteger()
     * @uses IPSModule::EnableAction()
     * @uses IPSModule::HasActiveParent()
     * @uses IPSModule::UpdateFormField()
     * @uses IPSModule::GetValue()
     * @uses IPSModule::SetValue()
     * @uses IPSModule::Translate()
     * @uses Zigbee2MQTTBridge::RegisterProfileIntegerEx()
     * @uses Zigbee2MQTTBridge::RegisterProfileStringEx()
     * @uses Zigbee2MQTTBridge::RequestOptions()
     * @uses Zigbee2MQTTBridge::InstallSymconExtension()
     * @uses IPS_GetKernelRunlevel()
     */
    public function ApplyChanges()
    {
        // Empty TransactionQueue
        $this->TransactionData = [];

        //Never delete this line!
        parent::ApplyChanges();

        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        if (empty($BaseTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
        } else {
            $this->SetStatus(IS_ACTIVE);
            //Setze Filter für ReceiveData
            $this->SetReceiveDataFilter('.*"Topic":"' . $BaseTopic . '/bridge/.*');
        }

        $this->SetSummary($BaseTopic);

        $this->RegisterProfileIntegerEx('Z2M.bridge.restart', '', '', '', [
            [0, $this->Translate('Restart'), '', 0xFF0000],
        ]);
        $this->RegisterProfileStringEx('Z2M.brigde.loglevel', '', '', '', [
            ['error', $this->Translate('Error'), '', 0x00FF00],
            ['warning', $this->Translate('Warning'), '', 0x00FF00],
            ['info', $this->Translate('Information'), '', 0x00FF00],
            ['debug', $this->Translate('Debug'), '', 0x00FF00],
        ]);
        $this->RegisterVariableBoolean('state', $this->Translate('State'), '~Alert.Reversed');
        $this->RegisterVariableBoolean('extension_loaded', $this->Translate('Extension Loaded'));
        $this->RegisterVariableString('extension_version', $this->Translate('Extension Version'));
        $this->RegisterVariableBoolean('extension_is_current', $this->Translate('Extension is up to date'));
        $this->RegisterVariableString('log_level', $this->Translate('Log Level'), 'Z2M.brigde.loglevel');
        $this->EnableAction('log_level');
        $this->RegisterVariableBoolean('permit_join', $this->Translate('Allow joining the network'), '~Switch');
        $this->EnableAction('permit_join');
        $this->RegisterVariableBoolean('restart_required', $this->Translate('Restart Required'));
        $this->RegisterVariableInteger('restart_request', $this->Translate('Perform a restart'), 'Z2M.bridge.restart');
        $this->EnableAction('restart_request');
        $this->RegisterVariableString('version', $this->Translate('Version'));
        $this->RegisterVariableString('zigbee_herdsman_converters', $this->Translate('Zigbee Herdsman Converters Version'));
        $this->RegisterVariableString('zigbee_herdsman', $this->Translate('Zigbee Herdsman Version'));
        $this->RegisterVariableInteger('network_channel', $this->Translate('Network Channel'));

        $this->UnregisterVariable('permit_join_timeout');

        $online = false;
        if (!empty($BaseTopic)) {
            if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                $online = @$this->RequestOptions();
            }
        }
        $this->SendDebug('Online', $online ? 'true' : 'false', 0);
        $installedExtVersion = (empty($this->GetValue('extension_version')) ? -1 : (float) $this->GetValue('extension_version'));
        $this->SetValue('extension_is_current', $this->actualExtensionVersion <= $installedExtVersion);
        if ($this->actualExtensionVersion <= $installedExtVersion) {
            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
            $this->UpdateFormField('InstallExtension', 'enabled', false);
        } else {
            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Install or upgrade Symcon-Extension'));
            $this->UpdateFormField('InstallExtension', 'enabled', true);
            if (!empty($BaseTopic)) {
                if ($online) {
                    @$this->InstallSymconExtension();
                }
            }
        }
    }

    /**
     * ReceiveData
     *
     * @param  string $JSONString
     *
     * @return string
     *
     * @uses IPSModule::GetStatus()
     * @uses IPSModule::ReadPropertyString()
     * @uses IPSModule::RegisterVariableString()
     * @uses IPSModule::SendDebug()
     * @uses IPSModule::SetValue()
     * @uses IPSModule::Translate()
     * @uses IPSModule::UpdateFormField()
     * @uses IPSModule::LogMessage()
     * @uses Zigbee2MQTTBridge::UpdateTransaction()
     * @uses json_decode()
     * @uses strpos()
     * @uses substr()
     * @uses strlen()
     * @uses explode()
     * @uses array_shift()
     * @uses utf8_decode()
     * @uses file_get_contents()
     * @uses preg_match()
     * @uses isset()
     */
    public function ReceiveData($JSONString)
    {
        if ($this->GetStatus() == IS_CREATING) {
            return '';
        }
        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
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
            case 'request': //nothing
                break;
            case 'response': //response from request
                if (isset($Payload['transaction'])) {
                    $this->UpdateTransaction($Payload);
                    break;
                }
                if (is_array($Topics)) {
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
                        $this->UpdateFormField('SetLastSeen', 'caption', $this->Translate('last_seen setting is correct'));
                        $this->UpdateFormField('SetLastSeen', 'enabled', false);
                    } else {
                        $this->UpdateFormField('SetLastSeen', 'caption', $this->Translate('Set last_seen setting to epoch'));
                        $this->UpdateFormField('SetLastSeen', 'enabled', true);
                        $this->LogMessage($this->Translate('Wrong last_seen setting in Zigbee2MQTT. Please set last_seen to epoch.'), KL_ERROR);
                    }
                }
                if (isset($Payload['network'])) {
                    $this->SetValue('network_channel', $Payload['network']['channel']);
                }
                break;
            case 'extensions':
                if (!is_array($Payload)) {
                    break;
                }
                $foundExtension = false;
                $Version = 'unknown';
                foreach ($Payload as $Extension) {
                    if (strpos($Extension['code'], 'class IPSymconExtension') !== false) {
                        if ($foundExtension) {
                            $this->LogMessage($this->Translate("Danger! Several extensions for Symcon have been found.\r\nPlease delete outdated versions manually to avoid malfunctions."), KL_ERROR);
                            continue;
                        }
                        $foundExtension = true;
                        $this->ExtensionFilename = $Extension['name'];
                        $this->SendDebug('Found Extension', $this->ExtensionFilename, 0);
                        preg_match('/Version: (.*)/', $Extension['code'], $matches);
                        if (isset($matches[1])) {
                            $Version = $matches[1];
                        }
                        if ($this->actualExtensionVersion <= (float) $Version) {
                            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Symcon-Extension is up-to-date'));
                            $this->UpdateFormField('InstallExtension', 'enabled', false);
                        } else {
                            $this->UpdateFormField('InstallExtension', 'caption', $this->Translate('Install or upgrade Symcon-Extension'));
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

    /**
     * RequestAction
     *
     * @param  string $ident
     * @param  mixed $value
     * @return void
     *
     * @uses Zigbee2MQTTBridge::SetPermitJoin()
     * @uses Zigbee2MQTTBridge::SetLogLevel()
     * @uses Zigbee2MQTTBridge::Restart()
     */
    public function RequestAction($ident, $value)
    {
        switch ($ident) {
            case 'permit_join':
                $this->SetPermitJoin((bool) $value);
                break;
            case 'log_level':
                $this->SetLogLevel((string) $value);
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
     *
     * @uses IPSModule::GetValue()
     * @uses IPSModule::Translate()
     * @uses json_decode()
     * @uses json_encode()
     * @uses file_get_contents()
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetValue('extension_loaded') && $this->GetValue('extension_is_current')) {
            $Form['actions'][0]['enabled'] = false;
            $Form['actions'][0]['caption'] = $this->Translate('Symcon-Extension is up-to-date');
        }
        if ($this->ConfigLastSeen == 'epoch') {
            $Form['actions'][1]['enabled'] = false;
            $Form['actions'][1]['caption'] = $this->Translate('last_seen setting is correct');
        }
        if ($this->ConfigPermitJoin) {
            $Form['actions'][2]['visible'] = true;
        }
        return json_encode($Form);
    }

    /**
     * InstallSymconExtension
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses IPSModule::LogMessage()
     * @uses IPSModule::Translate()
     * @uses file_get_contents()
     * @uses dirname()
     * @uses trigger_error()
     * @uses isset()
     */
    public function InstallSymconExtension()
    {
        if ($this->installedZhVersion == 0) {
            $this->LogMessage($this->Translate('Cannot determine ZH Version. No Extension installed.'), KL_WARNING);
            return false;
        }
        if (!isset(self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])) {
            return false;

        }
        $ExtensionFilename = $this->ExtensionFilename == '' ? 'IPSymconExtension.js' : $this->ExtensionFilename;
        $Topic = '/bridge/request/extension/save';
        $Payload = ['name'=>$ExtensionFilename, 'code'=>file_get_contents(dirname(__DIR__) . '/libs/' . self::EXTENSION_ZH_VERSION[(int) $this->installedZhVersion])];
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
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
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

    /**
     * SetLastSeen
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
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
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
        }
        if (isset($Result['status'])) {
            return $Result['status'] == 'ok';
        }
        return false;
    }

    /**
     * SetPermitJoinOption
     *
     * @param  bool $PermitJoin
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function SetPermitJoinOption(bool $PermitJoin)
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options'=> ['permit_join' => $PermitJoin]];
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
     * SetPermitJoin
     *
     * @param  bool $PermitJoin
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function SetPermitJoin(bool $PermitJoin)
    {
        $Topic = '/bridge/request/permit_join';
        $Payload = ['time'=> ($PermitJoin ? 254 : 0)];
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
     * SetLogLevel
     *
     * @param  string $LogLevel
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function SetLogLevel(string $LogLevel)
    {
        $Topic = '/bridge/request/options';
        $Payload = ['options' =>['advanced' => ['log_level'=> $LogLevel]]];
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
     * Restart
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function Restart()
    {
        $Topic = '/bridge/request/restart';
        $Result = $this->SendData($Topic);
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
        }
        if (isset($Result['status'])) {
            return $Result['status'] == 'ok';
        }
        return false;
    }

    /**
     * CreateGroup
     *
     * @todo todo check the Response
     * @param  string $GroupName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     *
     * @uses Zigbee2MQTTBridge::SendData()
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
     * @param  string $OldDeviceName
     * @param  string $NewDeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RenameDevice(string $OldDeviceName, string $NewDeviceName)
    {
        $Topic = '/bridge/request/device/rename';
        $Payload = ['from' => $OldDeviceName, 'to' => $NewDeviceName];
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
     * RemoveDevice
     *
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function RemoveDevice(string $DeviceName)
    {
        $Topic = '/bridge/request/device/remove';
        $Payload = ['id'=>$DeviceName];
        $Result = $this->SendData($Topic, $Payload, 11000);
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
        }
        if (isset($Result['status'])) {
            return $Result['status'] == 'ok';
        }
        return false;
    }

    /**
     * CheckOTAUpdate
     *
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function CheckOTAUpdate(string $DeviceName)
    {
        $Topic = '/bridge/request/device/ota_update/check';
        $Payload = ['id'=>$DeviceName];
        $Result = $this->SendData($Topic, $Payload, 10000);
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
            return false;
        }
        if (!isset($Result['status']) && ($Result['status'] != 'ok')) {
            trigger_error('unknown error', E_USER_NOTICE);
            return false;
        }
        return $Result['data']['updateAvailable'];
    }

    /**
     * PerformOTAUpdate
     *
     * @todo todo check the Response
     * @param  string $DeviceName
     *
     * @return bool
     *
     * @uses Zigbee2MQTTBridge::SendData()
     * @uses trigger_error()
     * @uses isset()
     */
    public function PerformOTAUpdate(string $DeviceName)
    {
        $Topic = '/bridge/request/device/ota_update/update';
        $Payload = ['id'=>$DeviceName];
        $Result = $this->SendData($Topic, $Payload);
        if (isset($Result['error'])) {
            trigger_error($Result['error'], E_USER_NOTICE);
        }
        if (isset($Result['status'])) {
            return $Result['status'] == 'ok';
        }
        return false;
    }

}
