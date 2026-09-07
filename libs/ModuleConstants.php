<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * Definition Konstanten
 */
trait Constants
{
    /** @var string Verzeichnisname für die Exposes JSON Dateien */
    protected const EXPOSES_DIRECTORY = 'Zigbee2MQTTExposes';
    /** @var string Basispfad für MQTT-Nachrichten */
    protected const MQTT_BASE_TOPIC = 'MQTTBaseTopic';
    /** @var string Spezifisches MQTT-Topic für dieses Gerät */
    protected const MQTT_TOPIC = 'MQTTTopic';
    /** @var string Topic für Verfügbarkeit */
    protected const AVAILABILITY_TOPIC = 'availability';
    /** @var string Topic für die Extension-Anfragen */
    protected const SYMCON_EXTENSION_REQUEST = '/SymconExtension/request/';
    /** @var string Topic für die Extension-Antworten */
    protected const SYMCON_EXTENSION_RESPONSE = '/SymconExtension/response/';
    /** @var string Topic für Extension Listen-Anfragen */
    protected const SYMCON_EXTENSION_LIST_REQUEST = '/SymconExtension/lists/request/';
    /** @var string Topic für Extension Listen-Anfragen */
    protected const SYMCON_EXTENSION_LIST_RESPONSE = '/SymconExtension/lists/response/';
    /** @var string GUID des MQTT Servers */
    protected const GUID_MQTT_SERVER = '{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}';
    /** @var string GUID des MQTT Client */
    protected const GUID_MQTT_CLIENT = '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}';
    /** @var string GUID des Client Socket */
    protected const GUID_CLIENT_SOCKET = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
    /** @var string GUID des Datenfluss zu einen MQTT Splitter */
    protected const GUID_MQTT_SEND = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
    /** @var string Name des Attribut welches die Modul-Version enthält */
    protected const ATTRIBUTE_MODUL_VERSION = 'Version';
    /** @var string GUID des Module Zigbee2MQTT Bridge */
    protected const GUID_MODULE_BRIDGE = '{00160D82-9E2F-D1BD-6D0B-952F945332C5}';
    /** @var string GUID des Module Zigbee2MQTT Konfigurator */
    protected const GUID_MODULE_CONFIGURATOR = '{D30BADA8-F261-4D9F-89A9-2E9961AF021F}';
    /** @var string GUID des Module Zigbee2MQTT Gerät */
    protected const GUID_MODULE_DEVICE = '{E5BB36C6-A70B-EB23-3716-9151A09AC8A2}';
    /** @var string GUID des Module Zigbee2MQTT Discovery */
    protected const GUID_MODULE_DISCOVERY = '{7D2AD94C-6CD2-4B32-8B23-3F21EFC30DAC}';
    /** @var string GUID des Module Zigbee2MQTT Gruppe */
    protected const GUID_MODULE_GROUP = '{11BF3773-E940-469B-9DD7-FB9ACD7199A2}';
    /** @var string summary of ATTRIBUTE_EXPOSES */
    protected const ATTRIBUTE_EXPOSES = 'Exposes';
    /** @var string Attribut fuer geraetespezifische filtered_attributes aus Z2M */
    protected const ATTRIBUTE_FILTERED = 'FilteredAttributes';
}
