<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModuleConstants.php';
require_once dirname(__DIR__) . '/libs/BufferHelper.php';
require_once dirname(__DIR__) . '/libs/phpMQTT.php';

/**
 * Zigbee2MQTTDiscovery
 *
 * @property array $ManuelTopics
 * @property array $ManuelBrokerConfig
 */
class Zigbee2MQTTDiscovery extends IPSModule
{
    use Zigbee2MQTT\Constants;
    use Zigbee2MQTT\BufferHelper;

    /**
     * Create
     *
     * @return void
     *
     * @uses IPSModule::Create()
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Init Buffers
        $this->ManuelBrokerConfig = [];
        $this->ManuelTopics = [];
    }

    /**
     * ApplyChanges
     *
     * @return void
     *
     * @uses IPSModule::ApplyChanges()
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        // Buffer leeren
        $this->ManuelTopics = [];
        $this->ManuelBrokerConfig = [];
    }

    /**
     * RequestAction
     *
     * @param  string $ident
     * @param  mixed $value
     *
     * @return void
     *
     * @uses Zigbee2MQTTDiscovery::SearchBridges()
     * @uses IPSModule::SendDebug()
     * @uses IPSModule::ReloadForm()
     * @uses IPSModule::UpdateFormField()
     * @uses IPSModule::Translate()
     * @uses IPSModule::ReloadForm()
     * @uses json_decode()
     * @uses json_encode()
     * @uses parse_url()
     * @uses empty()
     * @uses isset()
     */
    public function RequestAction($ident, $value)
    {
        switch ($ident) {
            case 'CheckMQTTBroker':
                $Config = json_decode($value, true);
                $this->SendDebug('Manuel CheckMQTTBroker', $value, 0);
                $Url = parse_url($Config['Url']);
                if (empty($Url['host'])) {
                    $this->ManuelTopics = [];
                    $this->ManuelBrokerConfig = [];
                    $this->ReloadForm();
                } else {
                    $this->UpdateFormField('CheckMQTTBroker', 'caption', $this->Translate('Please wait'));
                    $this->UpdateFormField('CheckMQTTBroker', 'enabled', false);

                    $Config['Host'] = $Url['host'];
                    if ($Url['scheme'] === 'mqtts') {
                        $Config['Port'] = isset($Url['port']) ? $Url['port'] : 8883;
                        $Config['UseSSL'] = true;
                    } else {
                        $Config['Port'] = isset($Url['port']) ? $Url['port'] : 1883;
                        $Config['UseSSL'] = false;
                    }
                    $Topics = $this->SearchBridges($Config);
                    if ($Topics == null) {
                        $this->UpdateFormField('CheckMQTTBroker', 'caption', $this->Translate('Save'));
                        $this->UpdateFormField('CheckMQTTBroker', 'enabled', true);
                        $this->UpdateFormField('ErrorPopup', 'visible', true);
                    } else {
                        $this->SendDebug('Found Zigbee2MQTT', json_encode($Topics), 0);
                        $this->ManuelTopics = $Topics;
                        $this->ManuelBrokerConfig = $Config;
                        $this->ReloadForm();
                    }
                }
                break;
            case 'EditMQTTBroker':
                $this->UpdateFormField('BrokerTitle', 'caption', $this->Translate('Edit configuration'));
                $Config = $this->ManuelBrokerConfig;
                if (count($Config)) {
                    $this->UpdateFormField('Url', 'value', $Config['Url']);
                    $this->UpdateFormField('UserName', 'value', $Config['UserName']);
                    $this->UpdateFormField('Password', 'value', $Config['Password']);
                }
                $this->UpdateFormField('BrokerPopup', 'visible', true);
                break;
        }
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     *
     * @uses Zigbee2MQTTDiscovery::checkAllMqttServers()
     * @uses Zigbee2MQTTDiscovery::GetConfigurators()
     * @uses Zigbee2MQTTDiscovery::SearchBridges()
     * @uses IPSModule::SendDebug()
     * @uses IPS_GetInstance()
     * @uses IPS_GetConfiguration()
     * @uses IPS_GetProperty()
     * @uses IPS_GetName()
     * @uses json_decode()
     * @uses json_encode()
     * @uses file_get_contents()
     * @uses count()
     * @uses in_array()
     * @uses array_search()
     * @uses array_column()
     * @uses array_intersect_key()
     * @uses array_merge()
     * @uses unset()
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        if (!count($this->ManuelBrokerConfig)) {
            $Form['actions'][0]['caption'] = 'Add external broker';
        }

        $FoundZ2mBySplitterId = $this->checkAllMqttServers();
        $IPSConfigurators = $this->GetConfigurators();
        $this->SendDebug('Known Configurators', json_encode($IPSConfigurators), 0);
        $Values = [];

        if ($FoundZ2mBySplitterId === null) {
            if (!count($this->ManuelTopics)) {
                $Form['actions'][2]['visible'] = true;
            }
        } else {
            foreach ($FoundZ2mBySplitterId as $SplitterId => $Topics) {
                foreach ($Topics as $key => $Topic) {
                    $instanceID = array_search($Topic, $IPSConfigurators);
                    if ($instanceID) {
                        unset($IPSConfigurators[$instanceID]);
                    }
                    // Konfigeintrag mit Kette zu SplitterId
                    $value = []; //Array leeren
                    if ($instanceID) {
                        $value['name'] = IPS_GetName($instanceID);
                        $value['instanceID'] = $instanceID;

                    } else {
                        $value['name'] = $Topic . ' ' . $this->Translate('Configurator');
                        $value['instanceID'] = 0;
                    }
                    $value['topic'] = $Topic;
                    $value['create'] = [
                        [
                            'moduleID'      => self::GUID_MODULE_CONFIGURATOR,
                            'configuration' => [
                                self::MQTT_BASE_TOPIC    => $Topic
                            ]
                        ],
                        [
                            'moduleID'      => IPS_GetInstance($SplitterId)['ModuleInfo']['ModuleID'],
                            'configuration' => json_decode(IPS_GetConfiguration($SplitterId), true)
                        ],
                        [
                            'moduleID'      => IPS_GetInstance(IPS_GetInstance($SplitterId)['ConnectionID'])['ModuleInfo']['ModuleID'],
                            'configuration' => json_decode(IPS_GetConfiguration(IPS_GetInstance($SplitterId)['ConnectionID']), true)
                        ]
                    ];
                    $Values[] = $value;
                }
            }
        }
        $KnownTopics = array_column($Values, 'topic');
        foreach ($this->ManuelTopics as $Topic) {
            if (in_array($Topic, $KnownTopics)) {
                //skip found topics
                continue;
            }
            $instanceID = array_search($Topic, $IPSConfigurators);
            if ($instanceID) {
                unset($IPSConfigurators[$instanceID]);
            }
            // Konfigeintrag mit Kette für neuen MQTT-Client an externe Broker
            $value = []; //Array leeren
            if ($instanceID) {
                $value['name'] = IPS_GetName($instanceID);
                $value['instanceID'] = $instanceID;

            } else {
                $value['name'] = $Topic;
                $value['instanceID'] = 0;
            }
            $value['topic'] = $Topic;
            $value['create'] = [
                [
                    'moduleID'      => self::GUID_MODULE_CONFIGURATOR,
                    'configuration' => [
                        self::MQTT_BASE_TOPIC    => $Topic
                    ]
                ],
                [
                    'moduleID'      => self::GUID_MQTT_CLIENT,
                    'configuration' => array_intersect_key($this->ManuelBrokerConfig, array_flip(['UserName', 'Password']))
                ],
                [
                    'moduleID'      => self::GUID_CLIENT_SOCKET,
                    'configuration' => array_merge(
                        array_intersect_key($this->ManuelBrokerConfig, array_flip(['Host', 'Port', 'UseSSL'])),
                        ['Open' => true]
                    )

                ]
            ];
            $Values[] = $value;

        }
        foreach ($IPSConfigurators as $instanceID => $Topic) {
            // nicht gefundene Konfiguratoren werden hier in rot auflisten
            $value = []; //Array leeren
            $value['name'] = IPS_GetName($instanceID);
            $value['instanceID'] = $instanceID;
            $value['topic'] = IPS_GetProperty($instanceID, self::MQTT_BASE_TOPIC);
            $Values[] = $value;
        }

        $Form['actions'][1]['values'] = $Values;
        $this->SendDebug('Form', json_encode($Form), 0);
        return json_encode($Form);
    }

    /**
     * GetConfigurators
     *
     * @return array
     *
     * @uses IPS_GetInstanceListByModuleID()
     * @uses IPS_GetProperty()
     * @uses array_filter()
     */
    private function GetConfigurators(): array
    {
        $ConfiguratorList = [];
        $InstanceIDList = IPS_GetInstanceListByModuleID(self::GUID_MODULE_CONFIGURATOR);
        foreach ($InstanceIDList as $InstanceID) {
            $ConfiguratorList[$InstanceID] = @IPS_GetProperty($InstanceID, self::MQTT_BASE_TOPIC);
        }
        return $ConfiguratorList;
    }

    /**
     * SearchBridges
     * @param array $Config
     * @return ?array
     *
     * @uses Zigbee2MQTT\phpMQTT::setSslContextOptions()
     * @uses Zigbee2MQTT\phpMQTT::connect()
     * @uses Zigbee2MQTT\phpMQTT::subscribe()
     * @uses Zigbee2MQTT\phpMQTT::proc()
     * @uses Zigbee2MQTT\phpMQTT::close()
     * @uses IPSModule::SendDebug()
     * @uses IPS_GetName()
     * @uses IPS_GetLicensee()
     * @uses array_filter()
     * @uses strstr()
     */
    private function SearchBridges(array $Config): ?array
    {
        $ClientId = IPS_GetName(0) . IPS_GetLicensee();
        $mqtt = new \Zigbee2MQTT\phpMQTT($Config['Host'], $Config['Port'], $ClientId);
        if ($Config['UseSSL']) {
            $mqtt->setSslContextOptions(
                [
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false
                    ]
                ]
            );
        }
        if (!$mqtt->connect(true, null, $Config['UserName'], $Config['Password'])) {
            return null;
        }

        $mqtt->subscribe(['+/bridge/state' => 0]);

        $i = 0;
        $Topics = [];
        while ($i < 5) {
            $ret = $mqtt->proc();
            if (is_array($ret)) {
                $this->SendDebug('Receive ' . $ret[0], $ret[1], 0);
                if ($ret[1] === '{"state":"online"}') {
                    $Topics[] = strstr($ret[0], '/', true);
                }
                $ret = false;
            }
            $i++;
        }
        $mqtt->close();
        return count($Topics) ? array_unique($Topics) : null;
    }

    /**
     * checkAllMqttServers
     *
     * @return null|array
     *
     * @uses Zigbee2MQTTDiscovery::getAllMqTTSplitterInstances()
     * @uses Zigbee2MQTTDiscovery::SearchBridges()
     * @uses IPSModule::SendDebug()
     * @uses MQTT_GetRetainedMessageTopicList()
     * @uses MQTT_GetRetainedMessage()
     * @uses count()
     * @uses isset()
     * @uses array_filter()
     * @uses array_unique()
     * @uses strstr()
     * @uses json_encode()
     */
    private function checkAllMqttServers(): ?array
    {
        $Topics = [];
        $MqttSplitters = $this->getAllMqTTSplitterInstances();
        if (!count($MqttSplitters)) {
            $this->SendDebug('No MQTT Splitters found', '', 0);
            return null;
        }
        foreach ($MqttSplitters as $SplitterId => $Config) {
            // client
            if (IPS_GetInstance($SplitterId)['ModuleInfo']['ModuleID'] == self::GUID_MQTT_CLIENT) {
                if (isset($Config['Host'])) {
                    $FoundTopics = $this->SearchBridges($Config);
                    if ($FoundTopics !== null) {
                        $Topics[$SplitterId] = $FoundTopics;
                    }
                }
            }
            if (IPS_GetInstance($SplitterId)['ModuleInfo']['ModuleID'] == self::GUID_MQTT_SERVER) {
                foreach (array_filter(MQTT_GetRetainedMessageTopicList($SplitterId), [$this, 'FilterTopics']) as $Topic) {
                    $Found = [];
                    if (MQTT_GetRetainedMessage($SplitterId, $Topic)['Payload'] == '{"state":"online"}') {
                        $Found[] = strstr($Topic, '/', true);
                    }
                    $Topics[$SplitterId] = array_unique($Found);
                }
            }
        }
        $this->SendDebug('Found Zigbee2MQTT', json_encode($Topics), 0);
        return $Topics;
    }

    /**
     * FilterTopics
     *
     * @param string $Topic
     *
     * @return bool
     *
     * @uses explode()
     * @uses array_shift()
     * @uses implode()
     */
    private function FilterTopics(string $Topic): bool
    {
        $Topics = explode('/', $Topic);
        array_shift($Topics);
        return implode('/', $Topics) === 'bridge/state';
    }

    /**
     * getAllMqTTSplitterInstances
     *
     * @return array
     *
     * @uses IPSModule::SendDebug()
     * @uses IPS_GetInstanceListByModuleID()
     * @uses IPS_GetInstance()
     * @uses IPS_GetConfiguration()
     * @uses array_intersect_key()
     * @uses json_decode()
     * @uses array_merge()
     * @uses array_flip()
     */
    private function getAllMqTTSplitterInstances(): array
    {
        $MqttSplitter = [];
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MQTT_SERVER) as $mqttInstanceId) {
            $MqttSplitter[$mqttInstanceId] = array_intersect_key(json_decode(IPS_GetConfiguration($mqttInstanceId), true), array_flip(['UserName', 'Password']));
        }
        foreach (IPS_GetInstanceListByModuleID(self::GUID_MQTT_CLIENT) as $mqttInstanceId) {
            $ioInstance = IPS_GetInstance($mqttInstanceId)['ConnectionID'];
            if (IPS_InstanceExists($ioInstance) && IPS_GetInstance($ioInstance)['ModuleInfo']['ModuleID'] == self::GUID_CLIENT_SOCKET) {
                $MqttSplitter[$mqttInstanceId] =
                    array_merge(
                        array_intersect_key(json_decode(IPS_GetConfiguration($mqttInstanceId), true), array_flip(['UserName', 'Password'])),
                        array_intersect_key(json_decode(IPS_GetConfiguration($ioInstance), true), array_flip(['Host', 'Port', 'UseSSL']))
                    );
            }
        }
        $this->SendDebug('MQTTSplitter', json_encode($MqttSplitter), 0);
        return $MqttSplitter;
    }

}
