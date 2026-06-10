<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

require_once __DIR__ . '/AttributeArrayHelper.php';
require_once __DIR__ . '/BufferHelper.php';
require_once __DIR__ . '/SemaphoreHelper.php';
require_once __DIR__ . '/VariableProfileHelper.php';
require_once __DIR__ . '/MQTTHelper.php';
require_once __DIR__ . '/ColorHelper.php';

/**
 * ModulBase
 *
 * Basisklasse für Geräte (Devices module.php) und Gruppen (Groups module.php)
 *
 * Pseudo Variablen, welche über BufferHelper und die Magic-Functions __get und __set
 * direkt typsichere Werte, Arrays und Objekte in einem Instanz-Buffer schreiben und lesen können.
 * @property bool $BUFFER_MQTT_SUSPENDED Zugriff auf den Buffer für laufende Migration
 * @property bool $BUFFER_PROCESSING_MIGRATION Zugriff auf den Buffer für MQTT Nachrichten nicht verarbeiten
 * @property string $lastPayload Zugriff auf den Buffer welcher das Letzte Payload enthält (für Download-Button)
 * @property array $missingTranslations Zugriff auf den Buffer welcher ein array von fehlenden Übersetzungen enthält (für Download-Button)
 */
abstract class ModulBase extends \IPSModule
{
    use AttributeArrayHelper;
    use BufferHelper;
    use Semaphore;
    use ColorHelper;
    use VariableProfileHelper;
    use SendData;
    private const MINIMAL_MODUL_VERSION = 5.1;

    /**
     * @var array STATE_PATTERN
     * Definiert Nomenklatur für State-Variablen
     *      KEY:
     *      - BASE     'state' (Basisbezeichner)
     *      - SUFFIX:   Zusatzbezeichner
     *          - NUMERIC:   statel1, state_l1, StateL1, state_L1
     *          - DIRECTION: state_left, state_right, State_Left
     *          - COMBINED:  state_left_l1, State_Right_L1
     *      - MQTT:    Validiert MQTT-Payload (state, state_l1)
     *      - SYMCON:  Validiert Symcon-Variablen (state, State, statel1, state_l1, State_Left, state_right_l1)
     */
    private const STATE_PATTERN = [
        'PREFIX' => '',
        'BASE'   => 'state',
        'SUFFIX' => [
            'NUMERIC'   => '_[0-9]+',
            'DIRECTION' => '_(?:left|right)',
            'COMBINED'  => '_(?:left|right)_[0-9]+'
        ],
        'MQTT'   => '/^state(?:_[a-z0-9]+)?$/i',  // Für MQTT-Payload
        'SYMCON' => '/^[Ss]tate(?:_?(?:[Ll][0-9]+)|(?:[Ll]eft|[Rr]ight)(?:[Ll][0-9]+)?)?$/'
    ];

    /**
     * @var array FLOAT_UNITS
     * Entscheidet über Float oder Integer profile
     */
    private const FLOAT_UNITS = [
        '°C',
        '°F',
        'K',
        'mg/L',
        'g/m³',
        'mV',
        'V',
        'kV',
        'µV',
        'A',
        'mA',
        'µA',
        'W',
        'kW',
        'MW',
        'GW',
        'Wh',
        'kWh',
        'MWh',
        'GWh',
        'Hz',
        'kHz',
        'MHz',
        'GHz',
        'cd',
        'pH',
        'm',
        'cm',
        'mm',
        'µm',
        'nm',
        'l',
        'ml',
        'dl',
        'm³',
        'cm³',
        'mm³',
        'g',
        'kg',
        'mg',
        'µg',
        'ton',
        'lb',
        's',
        'ms',
        'µs',
        'ns',
        'min',
        'h',
        'd',
        'rad',
        'sr',
        'Bq',
        'Gy',
        'Sv',
        'kat',
        'mol',
        'mol/l',
        'N',
        'Pa',
        'kPa',
        'MPa',
        'GPa',
        'bar',
        'mbar',
        'atm',
        'torr',
        'psi',
        'ohm',
        'kohm',
        'mohm',
        'S',
        'mS',
        'µS',
        'F',
        'mF',
        'µF',
        'nF',
        'pF',
        'H',
        'mH',
        'µH',
        '%',
        'dB',
        'dBA',
        'dBC',
        'dB/m'
    ];

    /**
     * Liste bekannter Abkürzungen, die bei der Konvertierung von Identifikatoren
     * in snake_case beibehalten werden sollen.
     *
     * Diese Konstante wird im convertToSnakeCase() verwendet, um sicherzustellen,
     * dass gängige Abkürzungen (z.B. CO2, LED) korrekt formatiert werden.
     *
     * @var string[]
     */
    private const KNOWN_ABBREVIATIONS = [
        'VOC',
        'CO2',
        'PM25',
        'LED',
        'RGB',
        'HSV',
        'HSL',
        'XY',
        'MV',
        'KV',
        'MA',
        'KW',
        'MW',
        'GW',
        'kWH',
        'MWH',
        'GWH',
        'KHZ',
        'MHZ',
        'GHZ',
        'PH',
        'KPA',
        'MPA',
        'GPA',
        'MS',
        'MF',
        'NF',
        'PF',
        'MH',
        'DB',
        'DBA',
        'DBC'
    ];

    /**
     * @var string[]
     * Liste von alten Z2M Idents, welche bei der Konvertierung übersprungen werden müssen
     * damit sie erhalten bleiben.
     * Weil sich entweder der VariablenTyp ändert, oder der alte Name nicht konvertiert werden kann.
     * z.B. Z2M_ActionTransTime, was eigentlich action_transition_time ist.
     */
    private const SKIP_IDENTS = [
        'Z2M_ActionTransaction',
        'Z2M_ActionTransTime',
        'Z2M_XAxis',
        'Z2M_YAxis',
        'Z2M_ZAxis'
    ];

    /**
     * @var string[]
     * Liste von Composite-Keys, die beim Flattening übersprungen werden sollen.
     * Diese Composites werden nicht in einzelne Variablen aufgelöst.
     */
    private const SKIP_COMPOSITES = [
        'device',       // Geräteinformationen nicht als Einzelvariablen anlegen
        'endpoints',    // Endpoint-Informationen nicht als Einzelvariablen anlegen
        'options'       // Optionsstruktur nicht als Einzelvariablen anlegen
    ];

    /**
     * @var string $ExtensionTopic
     * Muss überschrieben werden.
     * - für den ReceiveFilter
     * - für LoadDeviceInfo
     * - überall wo das Topic der Extension genutzt wird
     *
     */
    protected static $ExtensionTopic = '';

    /**
     * @var array<array{type: string, feature: string, profile: string, variableType: string}
     * Ein Array, das Standardprofile für bestimmte Gerätetypen und Eigenschaften definiert.
     *
     * Jedes Element des Arrays enthält folgende Schlüssel:
     *
     * - 'group_type' (string): Der Gerätetyp, z. B. 'cover' oder 'light'. Ein leerer Wert ('') bedeutet, dass der Typ nicht relevant ist.
     * - 'feature' (string): Die spezifische Eigenschaft oder das Feature des Geräts, z. B. 'position', 'temperature'.
     * - 'profile' (string): Das Symcon-Profil, das für dieses Feature verwendet wird, z. B. '~Shutter.Reversed' oder '~Battery.100'.
     * - 'variableType' (string): Der Variablentyp, der für dieses Profil verwendet wird, z. B. VARIABLETYPE_INTEGER für Integer oder VARIABLETYPE_FLOAT für Gleitkommazahlen.
     *
     * Beispieleintrag:
     * @var array<string,array{
     *   'group_type' => 'cover',
     *   'feature' => 'position',
     *   'profile' => '~Shutter.Reversed',
     *   'variableType' => VARIABLETYPE_INTEGER
     * }>
     */
    protected static $VariableUseStandardProfile = [
        ['group_type' => 'cover', 'feature' => 'position', 'profile' => '~Shutter.Reversed', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => 'cover', 'feature' => 'position_left', 'profile' => '~Shutter.Reversed', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => 'cover', 'feature' => 'position_right', 'profile' => '~Shutter.Reversed', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'temperature', 'profile' => '~Temperature', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'humidity', 'profile' => '~Humidity.F', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'local_temperature', 'profile' => '~Temperature', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'battery', 'profile' => '~Battery.100', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'current', 'profile' => '~Ampere', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'energy', 'profile' => '~Electricity', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'power', 'profile' => '~Watt', 'variableType' => VARIABLETYPE_FLOAT],
        ['group_type' => '', 'feature' => 'battery', 'profile' => '~Battery.100', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'occupancy', 'profile' => '~Presence', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'pi_heating_demand', 'profile' => '~Valve', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'presence', 'profile' => '~Presence', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'illuminance_lux', 'profile' => '~Illumination', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'child_lock', 'profile' => '~Lock', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'window_open', 'profile' => '~Window', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'valve', 'profile' => '~Valve', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => '', 'feature' => 'window_detection', 'profile' => '~Window', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'contact', 'profile' => '~Window.Reversed', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'tamper', 'profile' => '~Alert', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => '', 'feature' => 'smoke', 'profile' => '~Alert', 'variableType' => VARIABLETYPE_BOOLEAN],
        ['group_type' => 'light', 'feature' => 'color', 'profile' => '~HexColor', 'variableType' => VARIABLETYPE_INTEGER],
        ['group_type' => 'climate', 'feature' => 'occupied_heating_setpoint', 'profile' => '~Temperature.Room', 'variableType' => VARIABLETYPE_FLOAT]
    ];

    /**
     * @var array<string,array{
     *   type: int,
     *   name: string,
     *   profile: string,
     *   ident?: string,
     *
     * Definiert spezielle Variablen mit vordefinierten Eigenschaften
     *
     * Schlüssel:
     *   - type: int Variablentyp
     *   - name: string Anzeigename der Variable -> @todo Wozu? Wird in registerSpecialVariable nicht genutzt
     *   - profile: string Profilname oder leer
     *   - ident?: string Optional: Benutzerdefinierter Identifier
     */
    protected static $specialVariables = [
        'last_seen'                  => ['type' => VARIABLETYPE_INTEGER, 'name' => 'Last Seen', 'profile' => '~UnixTimestamp'],
        'color_mode'                 => ['type' => VARIABLETYPE_STRING, 'name' => 'Color Mode', 'profile' => ''],
        'update'                     => ['type' => VARIABLETYPE_STRING, 'name' => 'Firmware Update Status', 'profile' => ''],
        'device_temperature'         => ['type' => VARIABLETYPE_FLOAT, 'name' => 'Device Temperature', 'profile' => '~Temperature'],
        'brightness'                 => ['type' => VARIABLETYPE_INTEGER, 'ident' => 'brightness', 'profile' => '~Intensity.100'],
        'brightness_l1'              => ['type' => VARIABLETYPE_INTEGER, 'name' => 'brightness_l1', 'profile' => '~Intensity.100'],
        'brightness_l2'              => ['type' => VARIABLETYPE_INTEGER, 'name' => 'brightness_l2', 'profile' => '~Intensity.100'],
        'voltage'                    => ['type' => VARIABLETYPE_FLOAT, 'ident' => 'voltage', 'profile' => '~Volt'],
        'calibration_time'           => ['type' => VARIABLETYPE_FLOAT, 'profile' => ''],
        'countdown'                  => ['type' => VARIABLETYPE_INTEGER, 'profile' => ''],
        'countdown_l1'               => ['type' => VARIABLETYPE_INTEGER, 'profile' => ''],
        'countdown_l2'               => ['type' => VARIABLETYPE_INTEGER, 'profile' => ''],
        'update__installed_version'  => ['type' => VARIABLETYPE_INTEGER, 'name' => 'Installed Version', 'profile' => ''],
        'update__latest_version'     => ['type' => VARIABLETYPE_INTEGER, 'name' => 'Latest Version', 'profile' => ''],
        'update__state'              => ['type' => VARIABLETYPE_STRING, 'name' => 'Update State', 'profile' => ''],
        'update__progress'           => ['type' => VARIABLETYPE_FLOAT, 'name' => 'Update Progress', 'profile' => '~Progress'],
        'update__remaining'          => ['type' => VARIABLETYPE_FLOAT, 'name' => 'Update Remaining', 'profile' => ''],
        'no_occupancy_since'         => ['type' => VARIABLETYPE_INTEGER, 'name' => 'No occupancy since', 'profile' => '~Duration']
    ];

    /**
     * @var array $stateDefinitions Array mit Status-Definitionen
     *
     * Definiert Status-Variablen mit festgelegten Wertebereichen
     * @todo die Struktur nutzt VariablenName als Index. Im Code wird aber sowohl ProfileName als auch Ident bzw. features->property benutzt.
     * Hier ist also irgendwas falsch.
     * Fix vom 24.10.2025 auf Ident / features->property, da der ProfileName innerhalb des Array vorhanden ist.
     *
     * Struktur:
     * [
     *   'VarIdent' => [
     *      'type'     => string,   // Typ der Variable (z.B. 'automode', 'valve')
     *      'dataType' => integer,  // IPS Variablentyp (VARIABLETYPE_*)
     *      'values'   => array,    // Erlaubte Werte für die Variable
     *      'ident'    => string,   // Identifier für die Variable
     *      'profile'  => string    // Profil für die Variable
     *   ]
     * ]
     */
    protected static $stateDefinitions = [
        'auto_lock'   => ['type' => 'automode', 'dataType' => VARIABLETYPE_STRING, 'values' => ['AUTO', 'MANUAL'], 'ident' => 'auto_lock', 'profile' => 'Z2M.AutoLock', 'enableAction' => true],
        'valve_state' => ['type' => 'valve', 'dataType' => VARIABLETYPE_STRING, 'values' => ['OPEN', 'CLOSED'], 'ident' => 'valve_state', 'profile' => 'Z2M.ValveState', 'enableAction' => true],
    ];

    /**
     *  @var array $stringVariablesNoResponse
     *
     * Erkennt String-Variablen ohne Rückmeldung seitens Z2M
     * Aktualisiert die in Symcon angelegte Variable direkt nach dem Senden des Set-Befehls
     * Zur einfacheren Wartung als table angelegt. Somit muss der Code bei späteren Ergänzungen nicht angepasst werden.
     *
     * Typische Anwendungsfälle:
     * - Effekt-Modi bei Leuchtmitteln (z.B. "EFFECT"), bei denen der zuletzt verwendete Effekt
     *   angezeigt werden soll.
     *
     * Beispiel:
     * - 'effect': Aktualisiert den zuletzt gesetzten Effekt.
     */
    protected static $stringVariablesNoResponse = [
        'effect',
    ];

    /**
     * @var array<string,array{values: array<int,string>}> $presetDefinitions
     *
     * Definiert vordefinierte Presets mit festen Wertzuordnungen
     *
     * Struktur:
     * [
     *   'PresetName' => [
     *     'values' => [
     *       Wert => 'Bezeichnung'
     *     ]
     *   ]
     * ]
     */
    protected static $presetDefinitions = [
        'level_config__current_level_startup' => [
            'values' => [
                0   => 'Minimum',    // Minimaler Wert
                255 => 'Previous'    // Vorheriger Wert
            ],
            'redirect' => true  // Zeigt an, dass diese Variable umgeleitet werden soll
        ]
    ];

    // Kernfunktionen

    /**
     * Create
     *
     * Wird einmalig beim Erstellen einer Instanz aufgerufen
     *
     * Führt folgende Aktionen aus:
     * - Verbindet mit der erstbesten MQTT-Server-Instanz
     * - Registriert Properties für MQTT-Basis-Topic und MQTT-Topic
     * - Initialisiert TransactionData Array
     * - Erstellt Zigbee2MQTTExposes Verzeichnis wenn nicht vorhanden
     * - Prüft und erstellt JSON-Datei für Geräteinfos
     *
     * @return void
     *
     * @throws \Exception Error on create Expose Directory
     *
     * @see \IPSModule::RegisterPropertyString()
     * @see \IPSModule::RegisterAttributeFloat()
     * @see \IPSModule::RegisterAttributeArray()
     * @see \Zigbee2MQTT\ModulBase::RegisterProfileBoolean()
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(self::MQTT_BASE_TOPIC, '');
        $this->RegisterPropertyString(self::MQTT_TOPIC, '');
        $this->RegisterAttributeArray(self::ATTRIBUTE_EXPOSES, []);
        $this->RegisterAttributeArray(self::ATTRIBUTE_FILTERED, []);
        $this->RegisterAttributeFloat(self::ATTRIBUTE_MODUL_VERSION, 5.0);

        /** Init Buffers */
        $this->BUFFER_MQTT_SUSPENDED = true;
        $this->BUFFER_PROCESSING_MIGRATION = false;
        $this->TransactionData = [];
        $this->lastPayload = [];
        $this->missingTranslations = [];

        /** @todo cleanup old directory
         * $this->createExposesDirectory();
         */

        // Statische Profile
        $this->RegisterProfileBooleanEx(
            'Z2M.DeviceStatus',
            'Network',
            '',
            '',
            [
                [false, 'Offline', '', 0xFF0000],
                [true, 'Online', '', 0x00FF00]
            ]
        );
        $this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
    }

    /**
     * ApplyChanges
     *
     * Wird aufgerufen bei übernehmen der Modulkonfiguration
     *
     * Führt folgende Aktionen aus:
     * - Verbindet mit MQTT-Parent
     * - Liest MQTT Basis- und Geräte-Topic
     * - Setzt Filter für eingehende MQTT-Nachrichten
     * - Aktualisiert Instanz-Status (aktiv/inaktiv)
     * - Prüft und aktualisiert Geräteinformationen (expose attribute)
     *
     * Bedingungen für Aktivierung:
     * - Basis-Topic und MQTT-Topic müssen gesetzt sein
     * - Parent muss aktiv sein
     * - System muss bereit sein (KR_READY)
     *
     * @return void
     *
     * @see \IPSModule::ApplyChanges()
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SetReceiveDataFilter()
     * @see \IPSModule::HasActiveParent()
     * @see \IPSModule::GetStatus()
     * @see \IPSModule::SetStatus()
     * @see IPS_GetKernelRunlevel()
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $BaseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $MQTTTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        $this->TransactionData = [];
        if (empty($BaseTopic) || empty($MQTTTopic)) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetReceiveDataFilter('NOTHING_TO_RECEIVE'); //block all
            return;
        }

        //Setze Filter für ReceiveData
        $Filter1 = preg_quote('"Topic":"' . $BaseTopic . '/' . $MQTTTopic . '/' . self::AVAILABILITY_TOPIC . '"');
        $Filter2 = preg_quote('"Topic":"' . $BaseTopic . '/' . $MQTTTopic . '"');
        $Filter3 = preg_quote('"Topic":"' . $BaseTopic . self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $MQTTTopic . '"');
        $this->SendDebug('Filter', '.*(' . $Filter1 . '|' . $Filter2 . '|' . $Filter3 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . '|' . $Filter3 . ').*');
        $this->SetStatus(IS_ACTIVE);
    }

    /**
     * MessageSink
     *
     * @param  mixed $Time
     * @param  mixed $SenderID
     * @param  mixed $Message
     * @param  mixed $Data
     * @return void
     */
    public function MessageSink($Time, $SenderID, $Message, $Data)
    {
        parent::MessageSink($Time, $SenderID, $Message, $Data);
        if ($SenderID != $this->InstanceID) {
            return;
        }
        switch ($Message) {
            case FM_CONNECT:
                if ($this->GetStatus() == IS_ACTIVE) {
                    $this->BUFFER_MQTT_SUSPENDED = false;
                }
                if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                    $this->checkExposeAttribute();
                }
                break;
            case IM_CHANGESTATUS:
                if ($Data[0] == IS_ACTIVE) {
                    $this->BUFFER_MQTT_SUSPENDED = false;
                    // Nur ein UpdateDeviceInfo wenn Parent aktiv und System bereit
                    if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
                        if ($this->checkExposeAttribute()) {
                            $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
                            $this->mapExposesToVariables($exposes);
                        }
                    }
                }
                return;
        }
    }

    /**
     * RequestAction
     *
     * Verarbeitet Aktionsanforderungen für Variablen
     *
     * Diese Methode wird automatisch aufgerufen, wenn eine Aktion einer Variable
     * oder IPS_RequestAction ausgeführt wird.
     *
     * Sie verarbeitet verschiedene Arten von Aktionstypen:
     *
     * - UpdateInfo: Aktualisiert Geräteinformationen
     * - presets: Verarbeitet vordefinierte Einstellungen
     * - String-Variablen ohne Rückmeldung: Direkte Aktualisierung
     * - Farbvariablen: Spezielle Behandlung von RGB/HSV/etc.
     * - Status-Variablen: ON/OFF und andere Zustände
     * - Standard-Variablen: Allgemeine Werteänderungen
     *
     * @param string $ident Identifikator der Variable (z.B. 'state', 'UpdateInfo')
     * @param mixed $value Neuer Wert für die Variable
     *
     * @return void
     *
     * @see \IPSModule::RequestAction()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\ModulBase::UpdateDeviceInfo()
     * @see \Zigbee2MQTT\ModulBase::handlePresetVariable()
     * @see \Zigbee2MQTT\ModulBase::handleStringVariableNoResponse()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::handleStateVariable()
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable()
     * @see json_encode()
     */
    public function RequestAction($ident, $value)
    {
        $this->SendDebug(__FUNCTION__, 'Aufgerufen für Ident: ' . $ident . ' mit Wert: ' . json_encode($value), 0);

        $handled = match (true) {
            // Behandelt UpdateInfo
            $ident == 'UpdateInfo' => function ()
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite UpdateInfo', 0);
                return $this->UpdateDeviceInfo();
            },
            // Behandelt ShowMissingTranslations
            $ident == 'ShowMissingTranslations' => function ()
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite ShowMissingTranslations', 0);
                return $this->ShowMissingTranslations();
            },
            // Behandelt Presets - WICHTIG: Vor dem Composite Key Check!
            strpos($ident, 'presets') !== false => function () use ($ident, $value)
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite Preset: ' . $ident, 0);
                return $this->handlePresetVariable($ident, $value);
            },
            // Behandelt _and_ Keys - WICHTIG: Vor dem Composite Key Check!
            strpos($ident, '_and_') !== false => function () use ($ident, $value)
            {
                $ident = str_replace('_and_', '&', $ident);
                $this->SendDebug(__FUNCTION__, 'recall action: ' . $ident, 0);
                return $this->RequestAction($ident, $value);
            },
            // Behandelt Composite Keys (z.B. color_options__execute_if_off)
            strpos($ident, '__') !== false => function () use ($ident, $value)
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite Composite Key: ' . $ident, 0);
                $payload = $this->buildNestedPayload($ident, $value);
                return $this->SendSetCommand($payload);
            },
            // Behandelt String-Variablen ohne Rückmeldung
            \in_array($ident, self::$stringVariablesNoResponse) => function () use ($ident, $value)
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite String ohne Rückmeldung: ' . $ident, 0);
                return $this->handleStringVariableNoResponse($ident, (string) $value);
            },
            // Behandelt Farbvariablen (exakte Namen prüfen)
            \in_array($ident, ['color', 'color_hs', 'color_rgb', 'color_temp_kelvin']) => function () use ($ident, $value)
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite Farbvariable: ' . $ident, 0);
                return $this->handleColorVariable($ident, $value);
            },
            // Behandelt Status-Variablen
            preg_match(self::STATE_PATTERN['SYMCON'], $ident) => function () use ($ident, $value)
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite Status-Variable: ' . $ident, 0);
                return $this->handleStateVariable($ident, $value);
            },
            // Behandelt Standard-Variablen
            default => function () use ($ident, $value)
            {
                $this->SendDebug(__FUNCTION__, 'Verarbeite Standard-Variable: ' . $ident, 0);
                return $this->handleStandardVariable($ident, $value);
            },
        };

        $result = $handled();

        if ($result === false) {
            //hier eine exception werfen?
            $this->SendDebug(__FUNCTION__, 'Fehler beim Verarbeiten der Aktion: ' . $ident . ' (Rückgabewert false)', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'Aktion erfolgreich verarbeitet: ' . $ident, 0);
        }

    }
    /**
     * ReceiveData
     *
     * Verarbeitet eingehende MQTT-Nachrichten
     *
     * Diese Methode wird automatisch aufgerufen, wenn eine MQTT-Nachricht empfangen wird.
     * Der Verarbeitungsablauf ist wie folgt:
     * 1. Prüft ob die Instanz noch bei der Migration ist
     * 2. Prüft ob Instanz im CREATE-Status ist
     * 3. Lässt den JSONString prüfen und zerlegen
     * 4. Verarbeitet spezielle Nachrichtentypen:
     *    - Verfügbarkeitsstatus (availability)
     *    - Symcon Extension Antworten
     * 5. Wenn keine spezielle Nachricht, dann Payload verarbeiten lassen
     *
     * @param string $JSONString Die empfangene MQTT-Nachricht im JSON-Format
     *
     * @return string Leerer String als Rückgabewert
     *
     * @see \IPSModule::ReceiveData()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::GetStatus()
     * @see \Zigbee2MQTT\ModulBase::validateAndParseMessage()
     * @see \Zigbee2MQTT\ModulBase::handleAvailability()
     * @see \Zigbee2MQTT\ModulBase::handleSymconExtensionResponses()
     * @see \Zigbee2MQTT\ModulBase::processPayload()
     */
    public function ReceiveData($JSONString)
    {
        // Während Migration keine MQTT Nachrichten verarbeiten
        if ($this->BUFFER_MQTT_SUSPENDED) {
            return '';
        }
        // Instanz im CREATE-Status überspringen
        if ($this->GetStatus() == IS_CREATING) {
            return '';
        }
        // JSON-Nachricht dekodieren
        [$topics, $payload] = $this->validateAndParseMessage($JSONString);
        if (!$topics) {
            return '';
        }
        // Behandelt Verfügbarkeit-Status
        if ($this->handleAvailability($topics, $payload)) {
            return '';
        }
        // Leere Payloads brauchte nur handleAvailability
        if (\is_null($payload)) {
            return '';
        }

        // Behandelt Symcon Extension Antworten, auch wenn Instanz noch in IS_CREATING ist.
        if ($this->handleSymconExtensionResponses($topics, $payload)) {
            return '';
        }
        // Verarbeitet Payload
        $this->processPayload($payload);
        return '';
    }

    /**
     * Migrate
     *
     * Prüft über ein Attribute ob die Modul-Instanz ein Update benötigt.
     *
     * Führt anschließend eine Migration von Objekt-Idents durch, indem es Kinder-Objekte dieser Instanz durchsucht,
     * auf definierte Kriterien überprüft und bei Bedarf umbenennt.
     *
     * - Überprüfung, ob der Ident mit "Z2M_" beginnt
     * - Konvertierung des Ident ins snake_case
     * - Loggt sowohl Fehler als auch erfolgreiche Änderungen
     *
     * @param string $JSONData JSON-Daten mit allen Properties und Attributen
     * @return string JSON-Daten mit allen Properties und Attributen
     *
     * @see \IPSModule::Migrate()
     * @see \IPSModule::SetBuffer()
     * @see \IPSModule::LogMessage()
     * @see IPS_GetChildrenIDs()
     * @see IPS_GetObject()
     * @see IPS_SetIdent()
     * @see json_decode()
     * @see json_encode()
     */
    public function Migrate($JSONData)
    {
        // Prüfe Version diese Modul-Instanz
        $j = json_decode($JSONData);
        if (isset($j->attributes->{self::ATTRIBUTE_MODUL_VERSION})) {
            if ($j->attributes->{self::ATTRIBUTE_MODUL_VERSION} >= self::MINIMAL_MODUL_VERSION) {
                return $JSONData;
            }
        }
        $j->attributes->{self::ATTRIBUTE_MODUL_VERSION} = self::MINIMAL_MODUL_VERSION;

        // Flag für laufende Migration setzen
        $this->BUFFER_MQTT_SUSPENDED = true;
        $this->BUFFER_PROCESSING_MIGRATION = true;

        // Move Exposes from file to attribute
        $jsonFile = IPS_GetKernelDir() . self::EXPOSES_DIRECTORY . DIRECTORY_SEPARATOR . $this->InstanceID . '.json';
        if (file_exists($jsonFile)) {
            $exposeData = @file_get_contents($jsonFile);
            $data = json_decode($exposeData, true);
            if (isset($data['exposes'])) { //device
                $exposes = $data['exposes'];
            } else { //group
                $exposes = $data;
            }
            $this->LogMessage(__FUNCTION__ . ' : Convert ExposeFile to attribute', KL_NOTIFY);
            $j->attributes->{self::ATTRIBUTE_EXPOSES} = json_encode($exposes);
            @unlink($jsonFile);
        }

        // 1) Suche alle Kinder-Objekte dieser Instanz
        // 2) Prüfe, ob ihr Ident z. B. mit "Z2M_" beginnt
        // 3) Bilde den neuen Ident (snake_case) und setze ihn
        $childrenIDs = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($childrenIDs as $childID) {
            // Nur weitermachen, wenn es sich um eine Variable handelt
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectType'] !== OBJECTTYPE_VARIABLE) {
                continue;
            }

            if ($obj['ObjectIdent'] == '') {
                // Hat keinen Ident, also ignorieren
                continue;
            }

            // Nur solche Idents, die mit 'Z2M_' beginnen:
            if (substr($obj['ObjectIdent'], 0, 4) !== 'Z2M_') {
                // Überspringen
                continue;
            }
            if (\in_array($obj['ObjectIdent'], self::SKIP_IDENTS)) {
                // Überspringen
                continue;
            }
            // Neuen Ident bilden
            $newIdent = self::convertToSnakeCase($obj['ObjectIdent']);
            // Versuchen zu setzen
            $result = @IPS_SetIdent($childID, $newIdent);
            if ($result === false) {
                $this->LogMessage(__FUNCTION__ . ' : Fehler: Ident "' . $newIdent . '" konnte nicht für Variable #' . $childID . ' gesetzt werden!', KL_ERROR);
            } else {
                $this->LogMessage(__FUNCTION__ . ' : Variable #' . $childID . ': "' . $obj['ObjectIdent'] . '" wurde geändert zu "' . $newIdent . '"', KL_NOTIFY);
            }
        }

        // Brightness Profil Migration
        $varID = @$this->GetIDForIdent('brightness');
        if ($varID !== false) {
            $this->RegisterVariableInteger(
                'brightness',
                $this->Translate('Brightness'),
                '~Intensity.100',
                10
            );

            // Zentrale EnableAction-Prüfung für brightness Migration
            $this->checkAndEnableAction('brightness');
        }
        // Flag für beendete Migration wieder setzen
        $this->BUFFER_MQTT_SUSPENDED = false;
        $this->BUFFER_PROCESSING_MIGRATION = false;
        return json_encode($j);
    }

    /**
     * Z2M_WriteValueBoolean Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  bool $value
     * @return bool
     */
    public function WriteValueBoolean(string $ident, bool $value)
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_WriteValueInteger Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  int $value
     * @return bool
     */
    public function WriteValueInteger(string $ident, int $value)
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_WriteValueFloat Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  float $value
     * @return bool
     */
    public function WriteValueFloat(string $ident, float $value)
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_WriteValueString Instanz Funktion
     *
     * Damit auch ein Senden an einen Ident möglich ist, wenn die Standardaktion überschrieben wurde.
     *
     * @param  string $ident
     * @param  string $value
     * @return bool
     */
    public function WriteValueString(string $ident, string $value)
    {
        $this->RequestAction($ident, $value);
        return true;
    }

    /**
     * Z2M_ReadValue Instanz Funktion
     *
     * Leseanforderung für ein Value an das Gerät senden.
     *
     * @param  string $Property
     * @return bool
     *
     * @throws \Exception Bei Fehlern während des Sendens
     *
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see json_encode()
     */
    public function ReadValue(string $Property)
    {
        $Payload = [$Property => ''];

        // MQTT-Topic für den Get-Befehl generieren
        $Topic = '/' . $this->ReadPropertyString(self::MQTT_TOPIC) . '/get';

        // Debug-Ausgabe des zu sendenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);

        // Sende die Daten an das Gerät
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * SendSetCommand
     *
     * Sendet einen Set-Befehl an das Gerät über MQTT
     *
     * Diese Methode generiert das MQTT-Topic für den Set-Befehl basierend auf der Konfiguration
     * und sendet das übergebene Array über SendData an das Gerät.
     *
     * @param array $Payload Array mit Schlüssel-Wert-Paaren, das an das Gerät gesendet werden soll
     *
     * @return bool True wenn die Daten versendet werden konnten, sonst false
     *
     * @throws \Exception Bei Fehlern während des Sendens
     *
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see json_encode()
     */
    public function SendSetCommand(array $Payload): bool
    {
        // MQTT-Topic für den Set-Befehl generieren
        $Topic = '/' . $this->ReadPropertyString(self::MQTT_TOPIC) . '/set';

        // Debug-Ausgabe des zu sendenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);

        // Sende die Daten an das Gerät
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * SendGetCommand
     *
     * Sendet einen Get-Befehl an das Gerät über MQTT
     *
     * Diese Methode generiert das MQTT-Topic für den Get-Befehl basierend auf der Konfiguration
     * und sendet ein Array mit allen Features/Propertys über SendData an das Gerät.
     *
     * @return bool True wenn die Daten versendet werden konnten, sonst false
     *
     * @throws \Exception Bei Fehlern während des Sendens
     *
     * @see \IPSModule::ReadPropertyString()
     * @see Zigbee2MQTT\AttributeArrayHelper::ReadAttributeArray()
     * @see \IPSModule::SendDebug()
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see json_encode()
     * @see in_array()
     */
    public function SendGetCommand(): bool
    {
        // MQTT-Topic für den Get-Befehl generieren
        $Topic = '/' . $this->ReadPropertyString(self::MQTT_TOPIC) . '/get';

        // Geraetespezifische filtered_attributes aus Z2M laden
        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);

        // Payload bauen
        $Payload = [];
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        foreach ($exposes as $expose) {
            // Config Features werden nur über den Namen des Config property abgefragt und nicht einzeln.
            if (isset($expose['category']) && ($expose['category'] == 'config')) {
                // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                $sProperty = $expose['property'] ?? '';
                if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                    continue;
                }
                $Payload[$sProperty] = '';
                continue;
            }
            // Einzelne Features durchgehen (z.B. Endpoints state_1 usw...)
            if (isset($expose['features']) && \is_array($expose['features'])) {
                foreach ($expose['features'] as $feature) {
                    // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                    $sProperty = $feature['property'] ?? '';
                    if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                        $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                        continue;
                    }
                    $Payload[$sProperty] = '';
                }
                continue;
            }
            // Rest
            // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
            $sProperty = $expose['property'] ?? '';
            if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                continue;
            }
            $Payload[$sProperty] = '';
        }

        // Debug-Ausgabe des zu sendenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: zu sendendes Payload: ', json_encode($Payload), 0);

        // Sende die Daten an das Gerät
        return $this->SendData($Topic, $Payload, 0);
    }

    /**
     * SetColorExt
     *
     * Ermöglicht es eine Farbe (INT) mit Transition zu setzen.
     *
     * @param  int $color
     * @param  int $Transition
     * @return bool
     */
    public function SetColorExt(int $color, int $TransitionTime): bool
    {
        return $this->setColor($color, $this->getColorMode(), 'color', $TransitionTime);
    }

    /**
     * UIExportDebugData
     *
     * @return string
     */
    public function UIExportDebugData(): string
    {
        $DebugData = [];
        $DebugData['Instance'] = IPS_GetObject($this->InstanceID) + IPS_GetInstance($this->InstanceID);
        if (IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID'] == self::GUID_MODULE_DEVICE) {
            $DebugData['Model'] = $this->ReadAttributeString('Model');
            $ModelUrl = str_replace([' ', '/'], '_', $DebugData['Model']);
            $DebugData['ModelUrl'] = 'https://www.zigbee2mqtt.io/devices/' . rawurlencode($ModelUrl) . '.html';
        }
        $DebugData['Config'] = json_decode(IPS_GetConfiguration($this->InstanceID), true);
        $DebugData['Exposes'] = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        $DebugData['LastPayload'] = $this->lastPayload;
        $DebugData['Childs'] = [];
        $DebugData['Profile'] = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectType'] !== OBJECTTYPE_VARIABLE) {
                continue;
            }
            $var = IPS_GetVariable($childID);
            $DebugData['Childs'][$childID] = IPS_GetObject($childID) + $var;
            if ($var['VariableCustomProfile'] != '') {
                $DebugData['Profile'][$var['VariableCustomProfile']] = IPS_GetVariableProfile($var['VariableCustomProfile']);
            }
            if ($var['VariableProfile'] != '') {
                $DebugData['Profile'][$var['VariableProfile']] = IPS_GetVariableProfile($var['VariableProfile']);
            }
        }
        $DebugData['missingTranslations'] = $this->missingTranslations;
        return 'data:application/json;base64,' . base64_encode(json_encode($DebugData, JSON_PRETTY_PRINT));
    }

    /**
     * Translate
     *
     * Überschreibt Translate um die Übersetzung aus der globalen json zu nutzen.
     *
     * @param  string $Text
     * @return string
     */
    public function Translate($Text)
    {
        $translation = array_merge_recursive(
            json_decode(file_get_contents(__DIR__ . '/locale.json'), true),
            json_decode(file_get_contents(__DIR__ . '/locale_z2m.json'), true)
        );
        $language = IPS_GetSystemLanguage();
        $code = explode('_', $language)[0];
        if (isset($translation['translations'])) {
            if (isset($translation['translations'][$language])) {
                if (isset($translation['translations'][$language][$Text])) {
                    return $translation['translations'][$language][$Text];
                }
            } elseif (isset($translation['translations'][$code])) {
                if (isset($translation['translations'][$code][$Text])) {
                    return $translation['translations'][$code][$Text];
                }
            }
        }
        return $Text;
    }

    // Variablenmanagement

    /**
     * SetValue
     *
     * Setzt den Wert einer Variable unter Berücksichtigung verschiedener Typen und Formatierungen
     *
     * Verarbeitung:
     * 1. Prüft Existenz der Variable, Abbruch wenn Variable nicht vorhanden
     * 2. Konvertiert Wert entsprechend Variablentyp (adjustValueByType)
     * 3. Wendet Profilzuordnungen an
     * 4. Behandelt Spezialfälle (z.B. ColorTemp, Color)
     *
     * Unterstützte Variablentypen:
     * 1. State-Variablen:
     *    - state: ON/OFF -> true/false
     *    - stateL1: Nummerierte States
     *    - stateLeft: Richtungs-States
     *    - stateLeftL1: Kombinierte States
     *
     * 2. Spezielle Variablen:
     *    - color: RGB-Farbwerte oder XY-Farbwerte mit Brightness
     *      Format RGB: Integer (0xRRGGBB)
     *      Format XY: Array ['x' => float, 'y' => float, 'brightness' => int]
     *    - color_temp: Farbtemperatur mit Kelvin-Konvertierung
     *    - preset: Vordefinierte Werte
     *
     * 3. Standard-Variablen:
     *    - Boolean: Automatische ON/OFF Konvertierung
     *    - Integer/Float: Typkonvertierung mit Einheitenbehandlung
     *    - String: Direkte Wertzuweisung
     *
     * @param string $ident Identifier der Variable (z.B. "state", "color_temp", "color")
     * @param mixed $value Zu setzender Wert
     *                    Bool: true/false oder "ON"/"OFF"
     *                    Int/Float: Numerischer Wert
     *                    String: Textwert
     *                    Array: Spezielle Behandlung für Farben und Presets
     *                    Array: Rest Wird ignoriert (Todo: Warum? Was ist mit UpdateStatus?)
     *
     * @return void
     *
     * Beispiel:
     * ```php
     * // States
     * $this->SetValue("state", "ON");         // Setzt bool true
     * $this->SetValue("stateL1", false);      // Setzt "OFF"
     *
     * // Farben & Temperatur
     * $this->SetValue("color_temp", 4000);    // Setzt Farbtemp + Kelvin
     * $this->SetValue("color", 0xFF0000);     // Setzt Rot als RGB
     * $this->SetValue("color", [              // Setzt Farbe im XY Format
     *     'x' => 0.7006,
     *     'y' => 0.2993,
     *     'brightness' => 254
     * ]);
     *
     * // Profile
     * $this->SetValue("mode", "auto");        // Nutzt Profilzuordnung
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::adjustValueByType()
     * @see \Zigbee2MQTT\ModulBase::convertMiredToKelvin()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \IPSModule::SetValue()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see IPS_GetVariable()
     * @see IPS_VariableProfileExists()
     * @see IPS_GetVariableProfile()
     */
    protected function SetValue($ident, $value)
    {
        $variableID = @$this->GetIDForIdent($ident);
        if (!$variableID) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden: ' . $ident, 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'Verarbeite Variable: ' . $ident . ' mit Wert: ' . json_encode($value), 0);

        // Array Spezialbehandlung für
        if (\is_array($value)) {
            // Color-Arrays
            if (strtolower($ident) === 'color') {
                $this->handleColorVariable($ident, $value);
                return;
            }
            $this->SendDebug(__FUNCTION__, 'Wert ist ein Array, übersprungen: ' . $ident, 0);
            return;
        }
        $var = IPS_GetVariable($variableID);
        $varType = $var['VariableType'];
        $adjustedValue = $this->adjustValueByType($var, $value);

        // Profilverarbeitung nur für nicht-boolesche Werte
        if ($varType !== 0) {
            $profileName = ($var['VariableCustomProfile'] != '' ? $var['VariableCustomProfile'] : $var['VariableProfile']);
            if ($profileName && IPS_VariableProfileExists($profileName)) {
                $profileAssociations = IPS_GetVariableProfile($profileName)['Associations'];
                foreach ($profileAssociations as $association) {
                    if ($association['Name'] == $value) {
                        $adjustedValue = $association['Value'];
                        $this->SendDebug(__FUNCTION__, 'Profilwert gefunden: ' . $value . ' -> ' . $adjustedValue, 0);
                        parent::SetValue($ident, $adjustedValue);
                        return;
                    }
                }
            }
        }

        $this->SendDebug(__FUNCTION__, 'Setze Variable: ' . $ident . ' auf Wert: ' . json_encode($adjustedValue), 0);
        parent::SetValue($ident, $adjustedValue);

        // Spezialbehandlung für ColorTemp
        if ($ident === 'color_temp') {
            $kelvinIdent = 'color_temp_kelvin';
            $kelvinValue = $this->convertMiredToKelvin($value);
            $this->SetValueDirect($kelvinIdent, $kelvinValue);
        }
    }

    /**
     * SetValueDirect
     *
     * Setzt den Wert einer Variable direkt ohne weitere Verarbeitung.
     *
     * Diese Methode setzt den Wert einer Variable direkt mit minimaler Verarbeitung:
     * - Keine Profile-Verarbeitung
     * - Keine Spezialbehandlung von States
     * - Basale Konvertierung der Typen für grundlegende Datentypen
     *
     * Verarbeitung:
     * 1. Array-Werte werden zu JSON konvertiert
     * 2. Grundlegende Konvertierung des Typs (bool, int, float, string)
     * 3. Debug-Ausgaben für Fehleranalyse
     *
     * @param string $ident Der Identifikator der Variable, deren Wert gesetzt werden soll
     * @param mixed $value Der zu setzende Wert
     *                    - Array: Wird zu JSON konvertiert
     *                    - Bool: Wird zu bool konvertiert
     *                    - Int/Float: Wird zum entsprechenden Typ konvertiert
     *                    - String: Wird zu string konvertiert
     *
     * @return void
     *
     * Beispiel:
     * ```php
     * // Boolean setzen
     * $this->SetValueDirect("state", true);
     *
     * // Array als JSON
     * $this->SetValueDirect("data", ["temp" => 22]);
     * ```
     *
     * @internal Diese Methode wird hauptsächlich intern verwendet für:
     *          - Direkte Wertzuweisung ohne Profile
     *          - Array zu JSON Konvertierung
     *          - Debug-Werte setzen
     *
     * @see \IPSModule::SetValue()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see IPS_GetVariable()
     */
    protected function SetValueDirect(string $ident, mixed $value): void
    {
        $variableID = @$this->GetIDForIdent($ident);

        if (!$variableID) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden: ' . $ident, 0);
            return;
        }

        // Typ-Prüfung und Konvertierung
        if (\is_array($value)) {
            $this->SendDebug(__FUNCTION__, 'Array-Wert erkannt, konvertiere zu JSON', 0);
            $value = json_encode($value);
        }

        // Wert entsprechend Variablentyp konvertieren
        switch (IPS_GetVariable($variableID)['VariableType']) {
            case VARIABLETYPE_BOOLEAN:
                $value = (bool) $value;
                $debugVarType = 'bool';
                break;
            case VARIABLETYPE_INTEGER:
                $value = (int) $value;
                $debugVarType = 'integer';
                break;
            case VARIABLETYPE_FLOAT:
                $value = (float) $value;
                $debugVarType = 'float';
                break;
            case VARIABLETYPE_STRING:
                $value = (string) $value;
                $debugVarType = 'string';
                break;
        }

        $this->SendDebug(__FUNCTION__, \sprintf('Setze Variable: %s, Typ: %s, Wert: %s', $ident, $debugVarType, json_encode($value)), 0);
        // Setze den Wert der Variable
        parent::SetValue($ident, $value);
    }

    // Feature & Expose Handling

    /**
     * mapExposesToVariables
     *
     * Mappt die übergebenen Exposes auf Variablen und registriert diese.
     * Diese Funktion verarbeitet die übergebenen Exposes (z.B. Sensoreigenschaften) und registriert sie als Variablen.
     * Wenn ein Expose mehrere Features enthält, werden diese ebenfalls einzeln registriert.
     *
     * @param array $exposes Ein Array von Exposes, das die Geräteeigenschaften oder Sensoren beschreibt.
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::getVariableTypeFromProfile()
     * @see \Zigbee2MQTT\ModulBase::registerPresetVariables()
     * @see \IPSModule::SetBuffer()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     */
    protected function mapExposesToVariables(array $exposes): void
    {
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: All Exposes', json_encode($exposes), 0);

        // Geraetespezifische filtered_attributes aus Z2M laden
        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);

        // Durchlaufe alle Exposes
        foreach ($exposes as $expose) {
            // Prüfen, ob es sich um eine Gruppe handelt
            if (isset($expose['type']) && \in_array($expose['type'], ['light', 'switch', 'lock', 'cover', 'climate', 'fan', 'text'])) {
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Found group: ', $expose['type'], 0);

                // Features in der Gruppe verarbeiten
                if (isset($expose['features']) && \is_array($expose['features'])) {
                    foreach ($expose['features'] as $feature) {
                        // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                        $sProperty = $feature['property'] ?? '';
                        if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                            $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                            continue;
                        }

                        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Processing feature in group: ', json_encode($feature), 0);
                        // Setze den Gruppentyp als zusätzlichen Wert
                        $feature['group_type'] = $expose['type'];
                        // Variablen für die einzelnen Features registrieren
                        $this->registerVariable($feature);

                        // Wenn es sich um brightness handelt, speichere die Min/Max Werte
                        if ($feature['property'] === 'brightness') {
                            $brightnessConfig = [
                                'min' => $feature['value_min'] ?? 0,
                                'max' => $feature['value_max'] ?? 255
                            ];
                            $this->brightnessConfig = $brightnessConfig;
                            $this->SendDebug(__FUNCTION__, 'Brightness Config: ' . json_encode($brightnessConfig), 0);
                        }
                    }
                } else {
                    $this->registerVariable($expose);
                }
            } else {
                // Gefilterte Attribute gemaess Z2M-Konfiguration ueberspringen
                $sProperty = $expose['property'] ?? '';
                if ($sProperty !== '' && \in_array($sProperty, $aFiltered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered attribute: ' . $sProperty, 0);
                    continue;
                }

                $this->registerVariable($expose);
                if (isset($expose['presets'])) {
                    $variableType = $this->getVariableTypeFromProfile($expose['type'], $expose['property'], $expose['unit'] ?? '', $expose['value_step'] ?? 1.0, null);
                    $this->registerPresetVariables($expose['presets'], $expose['property'], $variableType, $expose);
                }
            }
        }
    }

    /**
     * LoadDeviceInfo
     *
     * Lädt die Geräte oder Gruppen Infos über die SymconExtension von Zigbee2MQTT
     *
     * @return array|false Enthält die Antwort als Array, oder false im Fehlerfall.
     *
     * @see \Zigbee2MQTT\SendData::SendData()
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::LogMessage()
     */
    protected function LoadDeviceInfo()
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        if (empty($mqttTopic)) {
            $this->LogMessage($this->Translate('MQTTTopic not configured.'), KL_WARNING);
            return false;
        }
        $Result = $this->SendData(self::SYMCON_EXTENSION_REQUEST . static::$ExtensionTopic . $mqttTopic, [], 2500);
        return $Result;
    }

    /**
     * UpdateDeviceInfo
     *
     * Muss überschrieben werden
     * Muss die Exposes per LoadDeviceInfo laden und verarbeiten.
     *
     * @return bool
     */
    abstract protected function UpdateDeviceInfo(): bool;

    protected function ShowMissingTranslations(): bool
    {
        $this->UpdateFormField('ShowMissingTranslations', 'visible', true);
        $Values = [];
        foreach ($this->missingTranslations as $KVP) {
            $Values[] = [
                'type'  => array_key_first($KVP),
                'value' => $KVP[array_key_first($KVP)]
            ];
        }
        $this->UpdateFormField('MissingTranslationsList', 'values', json_encode($Values));
        return true;
    }

    /**
     * Wandelt ein verschachteltes Array in ein eindimensionales Array mit zusammengesetzten Schlüsseln um
     *
     * @param array  $payload Das zu verarbeitende Array mit verschachtelter Struktur
     * @param string $prefix  Optional, Prefix für die zusammengesetzten Schlüssel
     *
     * @return array Ein eindimensionales Array mit Schlüsseln in der Form 'parent__child'
     *
     * Beispiele:
     * ```php
     * // Verschachteltes Array
     * $input = [
     *     'weekly_schedule' => [
     *         'monday' => '00:00/7'
     *     ]
     * ];
     * $result = $this->flattenPayload($input);
     * // Ergebnis: ['weekly_schedule__monday' => '00:00/7']
     * ```
     *
     * @internal Wird von processPayload verwendet um verschachtelte Strukturen zu verarbeiten
     *
     * @see \Zigbee2MQTT\ModulBase::processPayload()
     */
    protected function flattenPayload(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {

            // Composite-Keys überspringen, die in SKIP_COMPOSITES definiert sind und auf oberster Ebene gesetzt sind
            if ($prefix === '' && \in_array($key, self::SKIP_COMPOSITES) && \is_array($value)) {
                $this->SendDebug(__FUNCTION__, "Überspringe Composite-Key auf oberster Ebene: $key", 0);
                continue;
            }

            // Spezialbehandlung für color-Properties, da zur Farbberechnung nicht als flatten benötigt
            if ($key === 'color' && \is_array($value)) {
                // Übernehme die color-Properties direkt ins color-Array
                $result['color'] = $value;
                continue;
            }

            $newKey = $prefix ? $prefix . '__' . $key : $key;
            if (\is_array($value)) {
                $result = array_merge($result, $this->flattenPayload($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Wandelt einen zusammengesetzten Identifikator in eine verschachtelte Array-Struktur um
     *
     * @param string $ident Der zusammengesetzte Identifikator (z.B. 'weekly_schedule__friday')
     * @param mixed $value Der Wert, der gesetzt werden soll
     *
     * @return array Das verschachtelte Array
     *
     * Beispiel:
     * ```php
     * $ident = 'weekly_schedule__friday';
     * $value = '00:00/7';
     * $result = $this->buildNestedPayload($ident, $value);
     * // Ergebnis: ['weekly_schedule' => ['friday' => '00:00/7']]
     * ```
     *
     * @internal Diese Methode wird von handleStandardVariable, handlePresetVariable und RequestAction verwendet
     *
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable()
     * @see \Zigbee2MQTT\ModulBase::handlePresetVariable()
     * @see \Zigbee2MQTT\ModulBase::RequestAction()
     */
    protected function buildNestedPayload(string $ident, mixed $value): array
    {
        $parts = explode('__', $ident);
        $result = [];
        $current = &$result;

        // Alle Teile außer dem letzten durchgehen
        for ($i = 0; $i < \count($parts) - 1; $i++) {
            $current[$parts[$i]] = [];
            $current = &$current[$parts[$i]];
        }

        // Letzten Wert setzen
        $current[$parts[\count($parts) - 1]] = $value;

        return $result;
    }

    /**
     * convertToSnakeCase
     *
     * Diese Hilfsfunktion entfernt das Prefix "Z2M_" und
     * wandelt CamelCase in lower_snake_case um.
     *
     * Beispiele:
     * - "color_temp" -> "color_temp"
     * - "brightnessABC" -> "brightness_a_b_c"
     * @param  string $oldIdent
     * @return string
     *
     * @see preg_replace()
     * @see ltrim()
     * @see strtolower()
     */
    private static function convertToSnakeCase(string $oldIdent): string
    {
        // 1) Z2M_ Prefix entfernen
        $withoutPrefix = preg_replace('/^Z2M_/', '', $oldIdent);

        // 2) State Pattern Check
        foreach ([self::STATE_PATTERN['MQTT'], self::STATE_PATTERN['SYMCON']] as $pattern) {
            if (preg_match($pattern, $withoutPrefix)) {
                $result = preg_replace('/^(state)([LlRr][0-9]+)$/i', '$1_$2', $withoutPrefix);
                return strtolower($result);
            }
        }

        // 3) Bekannte Abkürzungen prüfen
        foreach (self::KNOWN_ABBREVIATIONS as $abbr) {
            $pattern = '/\b' . preg_quote($abbr, '/') . '\b/';
            if (preg_match($pattern, $withoutPrefix)) {
                $withoutPrefix = preg_replace($pattern, strtolower($abbr), $withoutPrefix);
            }
        }

        // 4) Großbuchstaben verarbeiten
        $result = $withoutPrefix;
        // a) Einzelner Großbuchstabe am Wortanfang bleibt erhalten
        // b) Großbuchstabe nach Kleinbuchstaben bekommt Unterstrich
        $result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $result);
        // c) Großbuchstabenblöcke im Wort
        $result = preg_replace('/([A-Z])([A-Z][a-z])/', '$1_$2', $result);

        // 5) Formatierung finalisieren
        $result = preg_replace('/_+/', '_', $result);
        $result = strtolower($result);

        return $result;
    }

    // MQTT Kommunikation

    /**
     * validateAndParseMessage
     *
     * Dekodiert und validiert eine MQTT-JSON-Nachricht
     *
     * Verarbeitung:
     * - Dekodiert JSON-String in Array
     * - Prüft auf JSON-Decodierung-Fehler
     * - Validiert Vorhandensein des Topic-Felds
     * - Zerlegt Topic in Array
     *
     * @param string $JSONString Die zu dekodierende MQTT-Nachricht
     *
     * @return array Decodiertes Topic und Payload-Array oder false,false Array bei Fehlern
     *
     * @see \IPSModule::ReadPropertyString()
     * @see \IPSModule::SendDebug()
     * @see json_decode()
     * @see json_last_error()
     * @see json_last_error_msg()
     * @see substr()
     * @see strlen()
     * @see utf8_decode()
     */
    private function validateAndParseMessage(string $JSONString): array
    {
        $baseTopic = $this->ReadPropertyString(self::MQTT_BASE_TOPIC);
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);

        if (empty($baseTopic) || empty($mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'BaseTopic oder MQTTTopic ist leer', 0);
            return [false, false];
        }

        $messageData = json_decode($JSONString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->SendDebug(__FUNCTION__, 'JSON Decodierung fehlgeschlagen: ' . json_last_error_msg(), 0);
            return [false, false];
        }

        if (!isset($messageData['Topic'])) {
            $this->SendDebug(__FUNCTION__, 'Topic nicht gefunden', 0);
            return [false, false];
        }

        $topic = substr($messageData['Topic'], \strlen($baseTopic) + 1);

        /**
         * @deprecated utf8_decode (deprecated sind in Symcon deaktivert)
         * @depends Symcon Module-SPK  Nutzung von utf8_decode bei IPSModule, und hex2bin ab IPSModuleStrict.
         */
        $payloadData = json_decode(utf8_decode($messageData['Payload']), true);
        return [
            explode('/', $topic),
            $payloadData
        ];
    }

    /**
     * handleAvailability
     *
     * Verarbeitet den Verfügbarkeitsstatus eines Zigbee-Geräts
     *
     * Funktionen:
     * - Prüft ob Topic ein Verfügbarkeits-Topic ist
     * - Erstellt/Aktualisiert Z2M.DeviceStatus Profil
     * - Registriert/Aktualisiert Verfügbarkeits-Variable
     *
     * @param array $topics Array mit Topic-Bestandteilen
     * @param array $payload Array mit MQTT-Nachrichtendaten
     *
     * @return bool True wenn Verfügbarkeit verarbeitet wurde, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::RegisterProfileBoolean()
     * @see \Zigbee2MQTT\ModulBase::RegisterVariableBoolean()
     * @see \IPSModule::Translate()
     * @see \IPSModule::SetValue()
     * @see end()
     */
    private function handleAvailability(array $topics, ?array $payload): bool
    {
        if (end($topics) !== self::AVAILABILITY_TOPIC) {
            return false;
        }
        $this->RegisterVariableBoolean('device_status', $this->Translate('Availability'), 'Z2M.DeviceStatus');
        if (isset($payload['state'])) {
            parent::SetValue('device_status', $payload['state'] == 'online');
        } else { // leeren Payload, wenn z.B. Gerät gelöscht oder umbenannt wurde
            parent::SetValue('device_status', false);
        }
        return true;
    }

    /**
     * handleSymconExtensionResponses
     *
     * Verarbeitet Antworten von Symcon Extension Anfragen
     *
     * Funktionalität:
     * - Prüft ob Topic eine Symcon Extension Antwort ist
     * - Verarbeitet Device/Group Info Antworten
     * - Aktualisiert Transaktionsdaten wenn vorhanden
     *
     * Antwort-Typen:
     * - getDeviceInfo: Informationen über ein einzelnes Gerät
     * - getGroupInfo: Informationen über eine Gerätegruppe
     *
     * @param array $topics Array mit Topic-Bestandteilen
     * @param array $payload Array mit MQTT-Nachrichtendaten
     *
     * @return bool True wenn eine Symcon-Antwort verarbeitet wurde, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::UpdateTransaction()
     * @see \IPSModule::ReadPropertyString()
     * @see implode()
     */
    private function handleSymconExtensionResponses(array $topics, array $payload): bool
    {
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);
        $fullTopic = '/' . implode('/', $topics);
        if ($fullTopic === self::SYMCON_EXTENSION_RESPONSE . static::$ExtensionTopic . $mqttTopic) {
            if (isset($payload['transaction'])) {
                $this->UpdateTransaction($payload);
            }
            return true;
        }
        return false;
    }

    /**
     * Verarbeitet die empfangenen MQTT-Payload-Daten
     *
     * @param array $payload Array mit den MQTT-Nachrichtendaten
     *                      Unterstützt sowohl Array [] als auch Object {} Payload-Formate
     *
     * @return void
     *
     * Beispiele:
     * ```php
     * // Array Payload
     * $payload = [0 => 'value', 'temperature' => 21.5];
     * $this->processPayload($payload);
     *
     * // Object Payload mit Composite-Struktur
     * $payload = [
     *     'weekly_schedule' => [
     *         'monday' => '00:00/7'
     *     ]
     * ];
     * $this->processPayload($payload);
     * ```
     *
     * @internal Diese Methode wird von ReceiveData aufgerufen
     *
     * @see \Zigbee2MQTT\ModulBase::ReceiveData()
     * @see \Zigbee2MQTT\ModulBase::mapExposesToVariables()
     * @see \Zigbee2MQTT\ModulBase::processSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::processVariable()
     * @see \IPSModule::SendDebug()
     * @see strpos()
     * @see is_array()
     * @see json_encode()
     */
    private function processPayload(array $payload): void
    {
        // Exposes verarbeiten wenn vorhanden
        if (isset($payload['exposes'])) {
            $this->mapExposesToVariables($payload['exposes']);
            unset($payload['exposes']);
        }

        $this->lastPayload = $this->lastPayload + $payload;

        // Verschachtelte Strukturen flach machen
        $flattenedPayload = $this->flattenPayload($payload);

        // Payload-Daten verarbeiten
        foreach ($flattenedPayload as $key => $value) {
            $key = (string) $key;
            if ($value === null) {
                $this->SendDebug(__FUNCTION__, \sprintf('Skip empty value for key=%s', $key), 0);
                continue;
            }
            $this->SendDebug(__FUNCTION__, \sprintf('Verarbeite: Key=%s, Value=%s', $key, \is_array($value) ? json_encode($value) : (\is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : (string) $value)), 0);

            if (!$this->processSpecialVariable($key, $value)) {
                $this->processVariable($key, $value);
            }
        }
    }

    /**
     * getOrRegisterVariable
     *
     * Holt oder registriert eine Variable basierend auf dem Identifikator.
     *
     * Diese Methode prüft, ob eine Variable mit dem angegebenen Identifikator existiert. Wenn nicht,
     * wird die Variable registriert und die ID der neu registrierten Variable zurückgegeben.
     *
     * @param string $ident Der Identifikator der Variable.
     * @param array|null $variableProps Die Eigenschaften der Variable, die registriert werden sollen, falls sie nicht existiert.
     * @param string|null $formattedLabel Das formatierte Label der Variable, falls vorhanden.
     *
     * @return ?int Die ID der Variable oder NULL, wenn die Registrierung fehlschlägt.
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::GetIDForIdent()
     * @see debug_backtrace()
     */
    private function getOrRegisterVariable(string $ident, ?array $variableProps = null, ?string $formattedLabel = null): ?int
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return null;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $this->SendDebug(__FUNCTION__, 'Aufruf von getOrRegisterVariable für Ident: ' . $ident . ' von Funktion: ' . $caller, 0);

        $variableID = @$this->GetIDForIdent($ident);
        if (!$variableID && $variableProps !== null) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht gefunden, Registrierung: ' . $ident, 0);
            $this->registerVariable($variableProps, $formattedLabel);
            $variableID = @$this->GetIDForIdent($ident);
            if (!$variableID) {
                $this->SendDebug(__FUNCTION__, 'Fehler beim Registrieren der Variable: ' . $ident, 0);
                return null;
            }
        }
        $this->SendDebug(__FUNCTION__, 'Variable gefunden: ' . $ident . ' (ID: ' . $variableID . ')', 0);
        return $variableID;
    }

    /**
     * processVariable
     *
     * Verarbeitet eine einzelne Variable mit ihrem Wert.
     *
     * Diese Methode wird aufgerufen, um eine einzelne Variable aus dem empfangenen Payload zu verarbeiten.
     * Sie prüft, ob die Variable bekannt ist, registriert sie gegebenenfalls und setzt den Wert.
     *
     * @param string $key Der Schlüssel im empfangenen Payload.
     * @param mixed $value Der Wert, der mit dem Schlüssel verbunden ist.
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::processSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::handleColorVariable()
     * @see \Zigbee2MQTT\ModulBase::convertMillivoltToVolt()
     * @see \Zigbee2MQTT\ModulBase::getKnownVariables()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::GetIDForIdent()
     * @see strtolower()
     * @see is_array()
     * @see strpos()
     */
    private function processVariable(string $key, mixed $value): void
    {
        // Neue Prüfung für composite keys
        if ($this->isCompositeKey($key)) {
            // Bestimme den Variablentyp basierend auf dem Wert
            $varType = match (true) {
                \is_bool($value) => [
                    'type'         => VARIABLETYPE_BOOLEAN,
                    'profile'      => '~Switch',
                    'registerFunc' => 'RegisterVariableBoolean'
                ],
                \is_int($value) => [
                    'type'         => VARIABLETYPE_INTEGER,
                    'profile'      => '', // Hier ggf. ein passendes Profil wählen
                    'registerFunc' => 'RegisterVariableInteger'
                ],
                \is_float($value) => [
                    'type'         => VARIABLETYPE_FLOAT,
                    'profile'      => '', // Hier ggf. ein passendes Profil wählen
                    'registerFunc' => 'RegisterVariableFloat'
                ],
                default => [
                    'type'         => VARIABLETYPE_STRING,
                    'profile'      => '',
                    'registerFunc' => 'RegisterVariableString'
                ]
            };

            // Wenn Variable noch nicht existiert, registrieren
            if (!@$this->GetIDForIdent($key)) {
                $registerFunc = $varType['registerFunc'];
                $this->$registerFunc(
                    $key,
                    $this->Translate($this->convertLabelToName($key)),
                    $varType['profile']
                );

                // Zentrale EnableAction-Prüfung für neue Variable
                $this->checkAndEnableAction($key);
            }

            $this->SetValue($key, $value);
            return;
        }

        // Wenn Value ein Array ist und color im Key vorkommt, spezielle Behandlung
        if (\is_array($value) && strpos($key, 'color') === 0) {
            $this->handleColorVariable($key, $value);
            return;
        }

        // Wenn Value ein Array ist und einen 'composite' Key enthält
        if (\is_array($value) && isset($value['composite'])) {
            foreach ($value['composite'] as $compositeKey => $compositeValue) {
                $this->processVariable($compositeKey, $compositeValue);
            }
            return;
        }

        // Wenn Value ein Array ist und list im Type vorkommt
        if (\is_array($value) && isset($value['type']) && $value['type'] === 'list') {
            // Speichere komplette Liste als JSON
            $this->SetValueDirect($key, json_encode($value));

            // Verarbeite einzelne Einträge wenn vorhanden
            if (isset($value['items'])) {
                foreach ($value['items'] as $index => $item) {
                    $itemKey = $key . '_item_' . $index;
                    $this->processVariable($itemKey, $item);
                }
            }
            return;
        }

        $lowerKey = strtolower($key);
        $ident = $key;

        // Prüfe existierende Variable
        $variableID = @$this->GetIDForIdent($ident);
        if ($variableID !== false) {
            $this->SendDebug(__FUNCTION__, 'Existierende Variable gefunden: ' . $ident, 0);
            $this->SetValue($ident, $value);
            // Allgemeine Aktualisierung von Preset-Variablen
            $this->updatePresetVariable($ident, $value);
            return;
        }

        // Bekannte Variablen laden und prüfen
        $knownVariables = $this->getKnownVariables();
        if (!isset($knownVariables[$lowerKey])) {
            $this->SendDebug(__FUNCTION__, 'Variable nicht bekannt: ' . $key, 0);
            return;
        }

        $variableProps = $knownVariables[$lowerKey];

        // Array-Werte verarbeiten
        if (\is_array($value)) {
            $this->processArrayValue($ident, $value);
            return;
        }

        // Spezialbehandlungen durchführen
        if ($this->processSpecialCases($key, $value, $lowerKey, $variableProps)) {
            return;
        }

        // Variable registrieren und Wert setzen
        $variableID = $this->getOrRegisterVariable($ident, $variableProps);
        if ($variableID) {
            // Zentrale EnableAction-Prüfung auch bei neu angelegten Variablen
            $this->checkAndEnableAction($ident, $variableProps);
            $this->SetValue($ident, $value);
        }

        // Zusätzlich: Preset-Variable aktualisieren wenn vorhanden
        $presetIdent = $ident . '_presets';
        if (@$this->GetIDForIdent($presetIdent) !== false) {
            $this->SetValue($presetIdent, $value);
        }
        // Liste verarbeiten
        if (\is_array($value) && isset($value['type']) && $value['type'] === 'list') {
            // Speichere komplette Liste als JSON
            $this->SetValueDirect($key, json_encode($value));

            // Verarbeite einzelne Einträge
            foreach ($value as $index => $item) {
                $itemKey = $key . '_item_' . $index;
                $this->processVariable($itemKey, $item);
            }
            return;
        }
    }

    private function processArrayValue(string $ident, array $value): void
    {
        if (strpos($ident, 'color') === 0) {
            $this->handleColorVariable($ident, $value);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'Array-Wert für: ' . $ident, 0);
        $this->SendDebug(__FUNCTION__, 'Inhalt: ' . json_encode($value), 0);
    }

    private function processSpecialCases(string $key, mixed &$value, string $lowerKey, array $variableProps): bool
    {
        // Brightness in Lichtgruppen
        foreach (self::$VariableUseStandardProfile as $profile) {
            if (
                $profile['feature'] === $lowerKey &&
                isset($profile['group_type'], $variableProps['group_type']) &&
                $profile['group_type'] === 'light' &&
                $variableProps['group_type'] === 'light'
            ) {

                $this->SendDebug(__FUNCTION__, 'Brightness in Lichtgruppe - StandardProfile', 0);
                return $this->processSpecialVariable($key, $value);
            }
        }

        // Voltage Behandlung
        if ($lowerKey === 'voltage') {
            $this->SendDebug(__FUNCTION__, 'Voltage vor Konvertierung: ' . $value, 0);
            if ($this->processSpecialVariable($key, $value)) {
                return true;
            }
            $value = self::convertMillivoltToVolt($value);
            $this->SendDebug(__FUNCTION__, 'Voltage nach Konvertierung: ' . $value, 0);
        }

        return false;
    }

    /**
     * handleStandardVariable
     *
     * Verarbeitet Standard-Variablenaktionen und sendet diese an das Zigbee-Gerät.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Standard-Variable angefordert wird.
     * Sie konvertiert den Wert bei Bedarf und sendet den entsprechenden Set-Befehl.
     *
     * Spezielle Wertkonvertierungen:
     * - child_lock: bool true/false wird zu 'LOCK'/'UNLOCK' konvertiert
     * - Boolesche Werte: true/false wird zu 'ON'/'OFF' konvertiert
     * - brightness: Prozentwert (0-100) wird in Gerätewert (0-254) konvertiert
     *
     * @param string $ident Der Identifikator der Standard-Variable (z.B. 'state', 'brightness', 'child_lock')
     * @param mixed $value Der zu setzende Wert:
     *                    - bool für ON/OFF oder LOCK/UNLOCK
     *                    - int für Helligkeitswerte (0-100)
     *                    - mixed für andere Werte
     *
     * @return bool True wenn der Set-Befehl erfolgreich gesendet wurde, False bei Fehlern
     *
     * @example
     * handleStandardVariable('state', true)      // Sendet: {"state": "ON"}
     * handleStandardVariable('child_lock', true) // Sendet: {"child_lock": "LOCK"}
     * handleStandardVariable('brightness', 50)   // Sendet: {"brightness": 127}
     *
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::normalizeValueToRange()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_bool()
     */
    private function handleStandardVariable(string $ident, mixed $value): bool
    {
        $variableID = $this->getOrRegisterVariable($ident);
        if (!$variableID) {
            return false;
        }

        // Bei Boolean-Werten prüfen, ob es ein spezielles Mapping gibt
        if (\is_bool($value)) {
            $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
            foreach ($exposes as $expose) {
                $features = isset($expose['features']) ? $expose['features'] : [$expose];
                foreach ($features as $feature) {
                    if (isset($feature['property']) && $feature['property'] === $ident &&
                        isset($feature['value_on']) && isset($feature['value_off']) &&
                        $feature['type'] === 'binary') {

                        // Benutzerdefinierte Werte verwenden
                        $value = $value ? $feature['value_on'] : $feature['value_off'];
                        $payload = [$ident => $value];
                        return $this->SendSetCommand($payload);
                    }
                }
            }

            // Fallback auf Standard ON/OFF
            $value = $value ? 'ON' : 'OFF';
        }

        // Prüfe auf composite key vor der brightness Prüfung
        if ($this->isCompositeKey($ident)) {
            $payload = $this->buildNestedPayload($ident, $value);
            $this->SendDebug(__FUNCTION__, 'Sende composite payload: ' . json_encode($payload), 0);
            return $this->SendSetCommand($payload);
        }

        // light-Brightness wird immer das Profil ~Intensity.100 haben
        if ($ident === 'brightness') {
            // Konvertiere Prozentwert (0-100) in Gerätewert
            $deviceValue = $this->normalizeValueToRange($value, true);
            $payload = ['brightness' => $deviceValue];
            $this->SendSetCommand($payload);
            return true;
        }

        // Erstelle das Standard-Payload
        $payload = [$ident => $value];

        $this->SendDebug(__FUNCTION__, 'Sende payload: ' . json_encode($payload), 0);

        return $this->SendSetCommand($payload);
    }

    /**
     * handleStateVariable
     *
     * Verarbeitet State-bezogene Aktionen und sendet entsprechende MQTT-Befehle.
     *
     * Diese Methode überprüft verschiedene State-Szenarien:
     * 1. Standard State-Pattern (ON/OFF)
     * 2. Vordefinierte States aus stateDefinitions
     * 3. States aus dem STATE_PATTERN
     *
     * @param string $ident Identifikator der State-Variable
     * @param mixed $value Zu setzender Wert (bool|string|int)
     *
     * @return bool True wenn State erfolgreich verarbeitet wurde, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::convertOnOffValue() Konvertiert Werte zwischen ON/OFF und bool
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand() Sendet MQTT Befehle
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect() Setzt Variablenwert direkt
     * @see \IPSModule::SendDebug() Debug Ausgaben
     * @see \IPSModule::GetValue() Aktuellen Wert abfragen
     * @see preg_match() Pattern Matching für State-Erkennung
     * @see strtoupper() String zu Großbuchstaben
     * @see json_encode() JSON Konvertierung für Debug
     * @see isset() Array Key Prüfung
     */
    private function handleStateVariable(string $ident, mixed $value): bool
    {
        $this->SendDebug(__FUNCTION__, 'State-Handler für: ' . $ident . ' mit Wert: ' . json_encode($value), 0);

        // State Pattern Prüfung
        if (preg_match(self::STATE_PATTERN['SYMCON'], $ident)) {
            $payload = [$ident => $this->convertOnOffValue($value, false)];
            $this->SendDebug(__FUNCTION__, 'State-Payload wird gesendet: ' . json_encode($payload), 0);

            if (!$this->SendSetCommand($payload)) {
                return false;
            }
            $this->SetValueDirect($ident, $this->convertOnOffValue($value, false));
            return true;
        }

        // Prüfe auf vordefinierte States
        if (isset(static::$stateDefinitions[$ident])) {
            $stateInfo = static::$stateDefinitions[$ident];
            if (isset($stateInfo['values'])) {
                $index = \is_bool($value) ? (int) $value : $value;
                if (isset($stateInfo['values'][$index])) {
                    $payload = [$ident => $stateInfo['values'][$index]];
                    $this->SendDebug(__FUNCTION__, 'Vordefinierter State-Payload wird gesendet: ' . json_encode($payload), 0);
                    if (!$this->SendSetCommand($payload)) {
                        return false;
                    }
                    $this->SetValueDirect($ident, $stateInfo['values'][$index]);
                    return true;
                }
            }
        }

        // Überprüfen, ob der Wert in STATE_PATTERN definiert ist
        if (isset(self::STATE_PATTERN[strtoupper($value)])) {
            $adjustedValue = self::STATE_PATTERN[strtoupper($value)];
            $this->SendDebug(__FUNCTION__, 'State-Wert gefunden: ' . $value . ' -> ' . json_encode($adjustedValue), 0);
            $this->SetValueDirect($ident, $adjustedValue);
            return true;
        }

        $this->SendDebug(__FUNCTION__, 'Kein passender State-Handler gefunden', 0);
        return false;
    }

    /**
     * handleColorVariable
     *
     * Verarbeitet Farbvariablenaktionen.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Farbvariable angefordert wird.
     * Sie verarbeitet verschiedene Arten von Farbvariablen basierend auf dem Identifikator der Variable.
     * Der Identifikator color kann verschiedene Modi abbilden.
     * Der aktuelle Modus wird aus der Variable color_mode über getColorMode ermittelt.
     *
     * @param string $ident Der Identifikator der Farbvariable.
     * @param mixed $value Der Wert, der mit der Farbvariablen-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück, wenn die Aktion erfolgreich verarbeitet wurde, andernfalls false.
     *
     * @see \Zigbee2MQTT\ColorHelper::getColorMode()
     * @see \Zigbee2MQTT\ColorHelper::xyToInt()
     * @see \Zigbee2MQTT\ColorHelper::convertKelvinToMired()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \Zigbee2MQTT\ModulBase::setColor()
     * @see \IPSModule::GetValue()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_int()
     * @see is_array()
     * @see sprintf()
     */
    private function handleColorVariable(string $ident, mixed $value): bool
    {
        $handled = match ($ident) {
            'color' => function () use ($value)
            {
                $this->SendDebug(__FUNCTION__, 'Color Value: ' . json_encode($value), 0);
                if (\is_int($value)) { //Schaltaktion aus Symcon
                    if ($this->GetValue('color') !== $value) {
                        $mode = $this->getColorMode();
                        return $this->setColor($value, $mode);
                    }
                    return false;
                } elseif (\is_array($value)) { //Datenempfang
                    // Prüfen auf x/y Werte im color Array
                    if (isset($value['color']) && isset($value['color']['x']) && isset($value['color']['y'])) {
                        //  x/y Werte innerhalb color
                        $brightness = $value['brightness'] ?? 254;
                        $this->SendDebug(__FUNCTION__, 'Processing color with brightness: ' . $brightness, 0);
                        // Umrechnung der x und y Werte in einen HEX-Wert mit Helligkeit
                        $hexValue = $this->xyToInt($value['color']['x'], $value['color']['y'], $brightness);
                        $this->SetValueDirect('color', $hexValue);
                    } elseif (isset($value['x']) && isset($value['y'])) {
                        // Direkte x/y Werte
                        $brightness = $value['brightness'] ?? 254;
                        $this->SendDebug(__FUNCTION__, 'Processing color with brightness: ' . $brightness, 0);
                        // Umrechnung der x und y Werte in einen HEX-Wert mit Helligkeit
                        $hexValue = $this->xyToInt($value['x'], $value['y'], $brightness);
                        $this->SetValueDirect('color', $hexValue);
                    } elseif (isset($value['hue']) && isset($value['saturation'])) {
                        $brightness = $value['brightness'] ?? 254;
                        $this->SendDebug(__FUNCTION__, 'Processing color with brightness: ' . $brightness, 0);
                        // Umrechnung der H und S Werte in einen HEX-Wert mit Helligkeit
                        $hexValue = $this->HSVToInt($value['hue'], $value['saturation'], $brightness);
                        $this->SetValueDirect('color', $hexValue);
                    }
                    return true;
                }
                $this->SendDebug(__FUNCTION__, 'Ungültiger Wert für color: ' . json_encode($value), 0);
                return false;
            },
            'color_hs' => function () use ($value)
            {
                $this->SendDebug(__FUNCTION__, 'Color HS', 0);
                return $this->setColor($value, 'hs');
            },
            'color_rgb' => function () use ($value)
            {
                $this->SendDebug(__FUNCTION__, 'Color RGB', 0);
                return $this->setColor($value, 'cie', 'color_rgb');
            },
            'color_temp_kelvin' => function () use ($value)
            {
                // Konvertiere Kelvin zu Mired
                $convertedValue = $this->convertKelvinToMired($value);
                $payloadKey = 'color_temp'; // Zigbee2MQTT erwartet immer color_temp als Key
                $payload = [$payloadKey => $convertedValue];

                // Debug Ausgabe
                $this->SendDebug(__FUNCTION__, \sprintf('Converting %dK to %d Mired', $value, $convertedValue), 0);

                // Sende Payload an Gerät
                if (!$this->SendSetCommand($payload)) {
                    return false;
                }

                // Aktualisiere auch die Mired-Variable
                $this->SetValueDirect('color_temp', $convertedValue);

                return true;
            },
            'color_temp' => function () use ($value)
            {
                $convertedValue = $this->convertKelvinToMired($value);
                $this->SendDebug(__FUNCTION__, 'Converted Color Temp: ' . $convertedValue, 0);
                $payload = ['color_temp' => $convertedValue];
                return $this->SendSetCommand($payload);
            },
            default => function ()
            {
                return false;
            },
        };

        return $handled();
    }

    /**
     * handlePresetVariable
     *
     * Verarbeitet Preset-Variablenaktionen.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine Preset-Variable angefordert wird.
     * Sie leitet die Aktion an die Hauptvariable weiter und sendet den entsprechenden Set-Befehl.
     *
     * @param string $ident Der Identifikator der Preset-Variable.
     * @param mixed $value Der Wert, der mit der Preset-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück, wenn die Aktion erfolgreich verarbeitet wurde, andernfalls false.
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \Zigbee2MQTT\ModulBase::convertMiredToKelvin()
     * @see \IPSModule::SendDebug()
     * @see str_replace()
     */
    private function handlePresetVariable(string $ident, mixed $value): bool
    {
        // Hauptvariable ohne _presets suffix
        $mainIdent = str_replace('_presets', '', $ident);

        // Prüfen ob die Variable in presetDefinitions definiert ist mit redirect=true
        if (isset(self::$presetDefinitions[$mainIdent]['redirect'])) {
            $this->SendDebug(__FUNCTION__, 'Preset-Variable wird direkt umgeleitet: ' . $mainIdent, 0);

            // Wichtig: Payload mit mainIdent erstellen (ohne _presets)
            if ($this->isCompositeKey($mainIdent)) {
                $payload = $this->buildNestedPayload($mainIdent, $value); // Verwendet mainIdent
            } else {
                $payload = [$mainIdent => $value]; // Verwendet mainIdent
            }

            // Sende den Wert und aktualisiere beide Variablen bei Erfolg
            if (!$this->SendSetCommand($payload)) {
                return false;
            }

            $this->SetValueDirect($ident, $value);
            $this->SetValueDirect($mainIdent, $value);

            return true;
        }

        // Standard-Verarbeitung für nicht umgeleitete Presets...
        $this->SendDebug(__FUNCTION__, 'Aktion über presets erfolgt, Weiterleitung zur eigentlichen Variable: ' . $mainIdent, 0);

        // Payload mit mainIdent erstellen (ohne _presets)
        if ($this->isCompositeKey($mainIdent)) {
            $payload = $this->buildNestedPayload($mainIdent, $value); // Verwendet mainIdent
        } else {
            $payload = [$mainIdent => $value]; // Verwendet mainIdent
        }

        // Sende Befehl und aktualisiere beide Variablen bei Erfolg
        if (!$this->SendSetCommand($payload)) {
            return false;
        }

        $this->SetValueDirect($ident, $value);
        $this->SetValueDirect($mainIdent, $value);

        return true;
    }

    /**
     * handleStringVariableNoResponse
     *
     * Verarbeitet String-Variablen, die keine Rückmeldung von Zigbee2MQTT erfordern.
     *
     * Diese Methode wird aufgerufen, wenn eine Aktion für eine String-Variable angefordert wird,
     * die keine Rückmeldung von Zigbee2MQTT erfordert. Sie sendet den entsprechenden Set-Befehl
     * und aktualisiert die Variable direkt, wenn der Set-Befehl erfolgreich gesendet wurde.
     *
     * @param string $ident Der Identifikator der String-Variablen.
     * @param string $value Der Wert, der mit der String-Variablen-Aktionsanforderung verbunden ist.
     *
     * @return bool Gibt true zurück wenn der Set-Befehl abgesetzt wurde, sonder false.
     *
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     */
    private function handleStringVariableNoResponse(string $ident, string $value): bool
    {
        $this->SendDebug(__FUNCTION__, 'Behandlung String ohne Rückmeldung: ' . $ident, 0);
        $payload = [$ident => $value];
        if ($this->SendSetCommand($payload)) {
            $this->SetValue($ident, $value);
            return true;
        }
        return false;
    }

    /**
     * adjustValueByType
     *
     * Passt den Wert basierend auf dem Variablentyp an.
     * Diese Methode konvertiert den übergebenen Wert in den entsprechenden Typ der Variable.
     *
     * Spezielle Behandlungen:
     * - Bei child_lock: 'LOCK' wird zu true, 'UNLOCK' zu false konvertiert
     * - Boolesche Werte: 'ON' wird zu true, 'OFF' zu false konvertiert
     *
     * @param array $variableObject Ein Array von IPS_GetVariable() mit folgenden Schlüsseln:
     *                             - 'VariableType': int - Der Typ der Variable (0=Bool, 1=Int, 2=Float, 3=String)
     *                             - 'VariableID': int - Die ID der Variable
     * @param mixed $value Der Wert, der angepasst werden soll
     *
     * @return mixed Der konvertierte Wert:
     *               - bool für VARIABLETYPE_BOOLEAN (0)
     *               - int für VARIABLETYPE_INTEGER (1)
     *               - float für VARIABLETYPE_FLOAT (2)
     *               - string für VARIABLETYPE_STRING (3)
     *               - original $value bei unbekanntem Typ
     *
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     * @see is_bool()
     * @see is_string()
     * @see strtoupper()
     * @see IPS_GetObject()
     * @see VARIABLETYPE_BOOLEAN
     */
    private function adjustValueByType(array $variableObject, mixed $value): mixed
    {
        $varType = $variableObject['VariableType'];
        $varID = $variableObject['VariableID'];
        $ident = IPS_GetObject($varID)['ObjectIdent'];

        $this->SendDebug(__FUNCTION__, 'Variable ID: ' . $varID . ', Typ: ' . $varType . ', Ursprünglicher Wert: ' . json_encode($value), 0);

        switch ($varType) {
            case 0:
                if (\is_bool($value)) {
                    $this->SendDebug(__FUNCTION__, 'Wert ist bereits bool: ' . json_encode($value), 0);
                    return $value;
                }
                if (\is_string($value)) {
                    $normalizedValue = strtoupper(trim($value, " \t\n\r\0\x0B\"'"));

                    // Exposes-Daten für diesen Identifier abrufen
                    $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
                    foreach ($exposes as $expose) {
                        // Features durchsuchen
                        $features = isset($expose['features']) ? $expose['features'] : [$expose];
                        foreach ($features as $feature) {
                            if (isset($feature['property']) && $feature['property'] === $ident &&
                                isset($feature['value_on']) && isset($feature['value_off']) &&
                                $feature['type'] === 'binary') {

                                // Prüfen ob der Wert dem value_on entspricht
                                if ($value === $feature['value_on']) {
                                    return true;
                                }
                                // Prüfen ob der Wert dem value_off entspricht
                                elseif ($value === $feature['value_off']) {
                                    return false;
                                }
                            }
                        }
                    }
                    // Standardprüfung für übliche Bool-Textwerte als Fallback
                    if (\in_array($normalizedValue, ['ON', 'TRUE', 'YES', '1', 'LOCK', 'OPEN'], true)) {
                        return true;
                    }
                    if (\in_array($normalizedValue, ['OFF', 'FALSE', 'NO', '0', 'UNLOCK', 'CLOSE', 'CLOSED'], true)) {
                        return false;
                    }

                    $this->SendDebug(__FUNCTION__, 'Unbekannter boolescher Stringwert für ' . $ident . ': ' . json_encode($value) . ' -> false', 0);
                    return false;
                }
                return (bool) $value;
            case 1:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu int: ' . (int) $value, 0);
                return (int) $value;
            case 2:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu float: ' . (float) $value, 0);
                return (float) $value;
            case 3:
                $this->SendDebug(__FUNCTION__, 'Konvertiere zu string: ' . (string) $value, 0);
                return (string) $value;
            default:
                $this->SendDebug(__FUNCTION__, 'Unbekannter Variablentyp für ID ' . $varID . ', Wert: ' . json_encode($value), 0);
                return $value;
        }
    }

    // Farbmanagement

    /**
     * setColor
     *
     * Setzt die Farbe des Geräts basierend auf dem angegebenen Farbmodus.
     *
     * Diese Methode unterstützt verschiedene Farbmodi und konvertiert die Farbe in das entsprechende Format,
     * bevor sie an das Gerät gesendet wird. Unterstützte Modi sind:
     * - **cie**: Konvertiert RGB in den XY-Farbraum (CIE 1931).
     * - **hs**: Verwendet den Hue-Saturation-Modus (HS), um die Farbe zu setzen.
     * - **hsl**: Nutzt den Farbton, Sättigung und Helligkeit (HSL), um die Farbe zu setzen.
     * - **hsv**: Nutzt den Farbton, Sättigung und den Wert (HSV), um die Farbe zu setzen.
     *
     * @param int $color Der Farbwert in Hexadezimal- oder RGB-Format.
     *                   Die Farbe wird intern in verschiedene Farbmodelle umgerechnet.
     * @param string $mode Der Farbmodus, der verwendet werden soll. Unterstützte Werte:
     *                     - 'cie': Konvertiert die RGB-Werte in den XY-Farbraum.
     *                     - 'hs': Verwendet den Hue-Saturation-Modus.
     *                     - 'hsl': Nutzt den HSL-Modus für die Umrechnung.
     *                     - 'hsv': Nutzt den HSV-Modus für die Umrechnung.
     * @param string $Z2MMode Der Zigbee2MQTT-Modus, standardmäßig 'color'. Kann auch 'color_rgb' sein.
     *                        - 'color': Setzt den Farbwert im XY-Farbraum.
     *                        - 'color_rgb': Setzt den Farbwert im RGB-Modus (nur für 'cie' relevant).
     *
     * @return bool
     *
     * @throws \InvalidArgumentException Wenn der Modus ungültig ist.
     *
     * Beispiel:
     * ```php
     * // Setze eine Farbe im HSL-Modus.
     * $this->setColor(0xFF5733, 'hsl', 'color');
     *
     * // Setze eine Farbe im HSV-Modus.
     * $this->setColor(0x4287f5, 'hsv', 'color');
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::IntToRGB()
     * @see \Zigbee2MQTT\ModulBase::RGBToXy()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSB()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSL()
     * @see \Zigbee2MQTT\ModulBase::RGBToHSV()
     * @see \Zigbee2MQTT\ModulBase::SendSetCommand()
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     */
    private function setColor(int $color, string $mode, string $Z2MMode = 'color', ?int $TransitionTime = null): bool
    {
        $Payload = match ($mode) {
            'cie' => function () use ($color, $Z2MMode)
            {
                $RGB = $this->IntToRGB($color);
                $cie = $this->RGBToXy($RGB);

                if ($Z2MMode === 'color') {
                    // Entferne 'bri' aus dem 'color'-Objekt und füge es separat als 'brightness' hinzu
                    $brightness = $cie['bri'];
                    unset($cie['bri']);
                    return ['color' => $cie, 'brightness' => $brightness];
                } elseif ($Z2MMode === 'color_rgb') {
                    return ['color_rgb' => $cie];
                }
            },
            'hs' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSB = $this->RGBToHSB($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSB Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSB['hue'],
                            'saturation' => $HSB['saturation'],
                        ],
                        'brightness' => $HSB['brightness']
                    ];
                } else {
                    return null;
                }
            },
            'hsl' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSL = $this->RGBToHSL($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSL Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSL['hue'],
                            'saturation' => $HSL['saturation'],
                            'lightness'  => $HSL['lightness']
                        ]
                    ];
                } else {
                    return null;
                }
            },
            'hsv' => function () use ($color, $Z2MMode)
            {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->IntToRGB($color);
                $HSV = $this->RGBToHSV($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSV Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    return [
                        'color' => [
                            'hue'        => $HSV['hue'],
                            'saturation' => $HSV['saturation'],
                        ],
                        'brightness' => $HSV['brightness']
                    ];
                } else {
                    return null;
                }
            },
            default => throw new \InvalidArgumentException('Invalid color mode: ' . $mode),
        };

        $result = $Payload();
        if ($result !== null) {

            if ($result === false) {
                return true; // Wert hat sich nicht geändert
            }
            if ($TransitionTime !== null) {
                $result['transition'] = $TransitionTime;
            }
            return $this->SendSetCommand($result);
        }
        return false;
    }

    // Spezialvariablen & Konvertierung

    /**
     * processSpecialVariable
     *
     * Verarbeitet spezielle Variablen mit besonderen Anforderungen
     *
     * Verarbeitungsschritte:
     * 1. Prüft ob Variable in specialVariables definiert
     * 2. Konvertiert Property zu Ident und Label
     * 3. Registriert Variable falls nicht vorhanden
     * 4. Passt Wert entsprechend Variablentyp an
     * 5. Setzt Wert mit Debug-Ausgaben
     *
     * @param string $key Name der zu verarbeitenden Property
     * @param mixed $value Zu setzender Wert
     *                    Kann sein:
     *                    - String: Direkter Wert
     *                    - Array: Wird konvertiert
     *                    - Bool: Wird angepasst
     *                    - Int/Float: Wird skaliert
     *
     * @return bool True wenn Variable verarbeitet wurde,
     *              False wenn keine Spezialvariable
     *
     * Beispiel:
     * ```php
     * // Verarbeitet Farbtemperatur
     * $this->processSpecialVariable("color_temp", 4000);
     *
     * // Verarbeitet RGB-Farbe
     * $this->processSpecialVariable("color", ["r" => 255, "g" => 0, "b" => 0]);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::processPayload() Ruft diese Methode auf
     * @see \Zigbee2MQTT\ModulBase::processVariable() Ruft diese Methode auf
     *
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::getOrRegisterVariable()
     * @see \Zigbee2MQTT\ModulBase::adjustSpecialValue()
     * @see \Zigbee2MQTT\ModulBase::SetValueDirect()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see sprintf()
     * @see gettype()
     */
    private function processSpecialVariable(string $key, mixed $value): bool
    {
        if (!isset(self::$specialVariables[$key])) {
            return false;
        }

        $variableProps = ['property' => $key];
        $ident = $key;
        $formattedLabel = $this->convertLabelToName($key);
        $variableID = $this->getOrRegisterVariable($ident, $variableProps, $formattedLabel);

        if (!$variableID) {
            return true;
        }

        // Spezielle Verarbeitung für die Variable
        $adjustedValue = $this->adjustSpecialValue($ident, $value);

        // Debug-Ausgabe des verarbeiteten Wertes
        $debugValue = \is_array($adjustedValue) ? json_encode($adjustedValue) : $adjustedValue;
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: ', $key . ' verarbeitet: ' . $key . ' => ' . $debugValue, 0);

        // Wert setzen
        $this->SetValueDirect($ident, $adjustedValue);

        $this->SendDebug(__FUNCTION__, \sprintf('SetValueDirect aufgerufen für %s mit Wert: %s (Typ: %s)', $ident, \is_array($adjustedValue) ? json_encode($adjustedValue) : $adjustedValue, gettype($adjustedValue)), 0);
        // Allgemeine Aktualisierung von Preset-Variablen
        $this->updatePresetVariable($ident, $adjustedValue);
        return true;
    }

    /**
     * adjustSpecialValue
     *
     * Passt den Wert spezieller Variablen entsprechend ihrer Anforderungen an
     *
     * Verarbeitungsschritte:
     * 1. Debug-Ausgabe des Eingangswerts
     * 2. Spezifische Konvertierung je nach Variablentyp
     * 3. Debug-Ausgabe des konvertierten Werts
     *
     * Unterstützte Variablentypen:
     * - last_seen: Konvertiert Millisekunden zu Sekunden
     * - color_mode: Wandelt Farbmodus in Großbuchstaben (hs->HS, xy->XY)
     * - color_temp_kelvin: Rechnet Kelvin in Mired um (1.000.000/K)
     *
     * @param string $ident Identifikator der Variable (last_seen, color_mode, color_temp_kelvin)
     * @param mixed $value Zu konvertierender Wert
     *                    - LastSeen: Integer (Millisekunden)
     *                    - ColorMode: String (hs, xy)
     *                    - ColorTempKelvin: Integer (2000-6500K)
     *
     * @return mixed Konvertierter Wert
     *               - LastSeen: Integer (Sekunden)
     *               - ColorMode: String (HS, XY)
     *               - ColorTempKelvin: String (Mired)
     *               - Default: Originalwert
     *
     * Beispiel:
     * ```php
     * // LastSeen konvertieren
     * $this->adjustSpecialValue("last_seen", 1600000000000); // Returns: 1600000000
     *
     * // ColorMode konvertieren
     * $this->adjustSpecialValue("color_mode", "hs"); // Returns: "HS"
     *
     * // Kelvin zu Mired
     * $this->adjustSpecialValue("color_temp_kelvin", 4000); // Returns: "250"
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::convertKelvinToMired()
     * @see \Zigbee2MQTT\ModulBase::normalizeValueToRange()
     * @see \Zigbee2MQTT\ModulBase::convertMillivoltToVolt()
     * @see \IPSModule::SendDebug()
     * @see is_array()
     * @see json_encode()
     * @see intdiv()
     * @see strtoupper()
     */
    private function adjustSpecialValue(string $ident, mixed $value): mixed
    {
        $debugValue = \is_array($value) ? json_encode($value) : $value;
        $this->SendDebug(__FUNCTION__, 'Processing special variable: ' . $ident . ' with value: ' . $debugValue, 0);
        switch ($ident) {
            case 'last_seen':
                // Umrechnung von Millisekunden auf Sekunden
                // $value nur mit Gleitkommazahlen Division durchführen um 32Bit-Systeme zu unterstützen
                // Anschließend zu INT casten.
                $adjustedValue = (int) ($value / 1000);
                $this->SendDebug(__FUNCTION__, 'Converted value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'color_mode':
                // Konvertierung von 'hs' zu 'HS' und 'xy' zu 'XY'
                $adjustedValue = strtoupper($value);
                $this->SendDebug(__FUNCTION__, 'Converted value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'color_temp_kelvin':
                // Umrechnung von Kelvin zu Mired
                return $this->convertKelvinToMired($value);
            case 'brightness':
                // Konvertiere Gerätewert in Prozentwert (0-100)
                $adjustedValue = $this->normalizeValueToRange($value, false);
                $this->SendDebug(__FUNCTION__, 'Converted brightness value: ' . $adjustedValue, 0);
                return $adjustedValue;
            case 'voltage':
                // Konvertiere mV zu V
                $adjustedValue = self::convertMillivoltToVolt($value);
                $this->SendDebug(__FUNCTION__, 'Converted voltage value: ' . $adjustedValue, 0);
                return $adjustedValue;
            default:
                return $value;
        }
    }

    /**
     * convertMillivoltToVolt
     *
     * Konvertiert Millivolt in Volt, wenn der Wert größer als 400 ist.
     *
     * @param float $value Der zu konvertierende Wert in Millivolt.
     * @return float Der konvertierte Wert in Volt.
     */
    private static function convertMillivoltToVolt(float $value): float
    {
        if ($value > 400) { // Werte über 400 sind in mV
            return $value * 0.001; // Umrechnung von mV in V mit Faktor 0.001
        }
        return $value; // Werte <= 400 sind bereits in V
    }

    /**
     * convertOnOffValue
     *
     * Konvertiert Werte zwischen ON/OFF und bool.
     * Zentrale Konvertierungsfunktion für State-Handler.
     *
     * @param mixed $value Zu konvertierender Wert:
     *                    - String: "ON"/"OFF" wird zu true/false
     *                    - Bool: true/false wird zu "ON"/"OFF"
     *                    - Andere: Direkte Bool-Konvertierung
     * @param bool $toBool True wenn Konvertierung zu Boolean, False wenn zu ON/OFF String
     *
     * @return mixed Konvertierter Wert:
     *              - Bei toBool=true: Boolean true/false
     *              - Bei toBool=false: String "ON"/"OFF"
     *
     * @see \Zigbee2MQTT\ModulBase::handleStateVariable() Hauptnutzer der Funktion
     * @see \Zigbee2MQTT\ModulBase::processSpecialCases() Weitere Nutzung
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable() Weitere Nutzung
     * @see is_string() Prüft ob Wert ein String ist
     * @see strtoupper() Konvertiert String zu Großbuchstaben
     * @see bool() Boolean Typkonvertierung
     */
    private function convertOnOffValue($value, bool $toBool = true): mixed
    {
        if ($toBool) {
            if (\is_string($value)) {
                return strtoupper($value) === 'ON';
            }
            return (bool) $value;
        } else {
            return $value ? 'ON' : 'OFF';
        }
    }

    /**
     * convertLabelToName
     *
     * Konvertiert ein Label in einen formatierten Namen mit Großbuchstaben am Wortanfang
     * und behält bestimmte Abkürzungen in Großbuchstaben. Speichert den konvertierten Namen in einer JSON-Datei.
     *
     * @param string $label Das zu formatierende Label
     * @return string Das formatierte Label
     *
     * @see \Zigbee2MQTT\ModulBase::isValueInLocaleJson()
     * @see \Zigbee2MQTT\ModulBase::addValueToTranslationsBuffer()
     * @see \IPSModule::SendDebug()
     * @see str_replace()
     * @see str_ireplace()
     * @see strtolower()
     * @see ucwords()
     * @see ucfirst()
     */
    private function convertLabelToName(string $label): string
    {
        // Liste von Abkürzungen die in Großbuchstaben bleiben sollen
        $upperCaseWords = ['HS', 'RGB', 'XY', 'HSV', 'HSL', 'LED'];
        $this->SendDebug(__FUNCTION__, 'Initial Label: ' . $label, 0);

        // Alle Unterstriche (egal ob einfach oder mehrfach) durch ein einzelnes Leerzeichen ersetzen
        $label = preg_replace('/_+/', ' ', $label);
        $this->SendDebug(__FUNCTION__, 'After replacing underscores with spaces: ' . $label, 0);

        // Konvertiere jeden Wortanfang in Großbuchstaben
        $label = ucwords($label);

        // Ersetze bekannte Abkürzungen durch ihre Großbuchstaben-Version
        foreach ($upperCaseWords as $upperWord) {
            $label = str_ireplace(
                [" $upperWord", ' ' . ucfirst(strtolower($upperWord))],
                " $upperWord",
                $label
            );
        }

        $this->SendDebug(__FUNCTION__, 'Converted Label: ' . $label, 0);

        // Prüfe, ob der Name in der locale.json vorhanden ist
        // Füge den Namen zum missingTranslations Buffer hinzu
        $this->isValueInLocaleJson($label, 'lable');
        return $label;
    }

    // Profilmanagement

    /**
     * registerVariableProfile
     *
     * Registriert ein Variablenprofil basierend auf dem Expose-Typ oder einem optionalen State-Mapping.
     *
     * @param array $expose Die Expose-Daten mit folgenden Schlüsseln:
     *                     - 'type': Typ des Exposes (binary, enum, numeric) (string)
     *                     - 'property' oder 'name': Name der Eigenschaft (string)
     *                     - 'value_on': Optional - Wert für "An"-Zustand bei binary (mixed)
     *                     - 'value_off': Optional - Wert für "Aus"-Zustand bei binary (mixed)
     *                     - 'values': Optional - Array möglicher Werte bei enum (array)
     *                     - 'unit': Optional - Einheit bei numeric (string)
     *                     - 'value_min': Optional - Minimaler Wert bei numeric (float|int)
     *                     - 'value_max': Optional - Maximaler Wert bei numeric (float|int)
     *
     * @return string Name des erstellten/vorhandenen Profils
     *                - '~Switch' für Standard-Schalter
     *                - 'Z2M.[property]' für benutzerdefinierte Profile
     *                - Systemprofil-Name wenn verfügbar
     *
     * Beispiel:
     * ```php
     * // Binary Switch
     * $expose = [
     *     'type' => 'binary',
     *     'property' => 'state',
     *     'value_on' => 'ON',
     *     'value_off' => 'OFF'
     * ];
     * $profile = $this->registerVariableProfile($expose);
     * // Ergebnis: '~Switch'
     * ```
     *
     *
     * @see \Zigbee2MQTT\ModulBase::registerStateMappingProfile()
     * @see \Zigbee2MQTT\ModulBase::getStandardProfile()
     * @see \Zigbee2MQTT\ModulBase::isValidStandardProfile()
     * @see \Zigbee2MQTT\ModulBase::handleProfileType()
     * @see strtolower()
     * @see strtoupper()
     * @see is_string()
     * @see str_replace()
     *
     */
    private function registerVariableProfile(array $expose): string
    {
        $type = $expose['type'] ?? '';
        $property = $expose['property'] ?? $expose['name'];
        $ProfileName = 'Z2M.' . strtolower($property);

        // Entferne das doppelte Präfix, falls vorhanden
        $ProfileName = str_replace('Z2M.Z2M_', 'Z2M.', $ProfileName);
        $ProfileName = str_replace('&', '_and_', $ProfileName);

        // State-Mapping prüfen
        if (isset(self::$stateDefinitions[$property])) {
            return $this->registerStateMappingProfile($property);
        }

        // Enum-Profil-Logik
        if ($type === 'enum' && isset($expose['values'])) {
            return $this->registerEnumProfile($expose, $ProfileName);
        }

        // Standard-Profil prüfen
        if ($type === 'binary') {
            if (isset($expose['value_on']) && isset($expose['value_off'])) {
                $valueOn = $expose['value_on'];
                $valueOff = $expose['value_off'];

                // Prüfen, ob die Werte Strings sind, bevor strtoupper verwendet wird
                if (
                    ($valueOn === true && $valueOff === false) ||
                    ($valueOn === false && $valueOff === true) ||
                    (
                        \is_string($valueOn) && \is_string($valueOff) &&
                        (
                            (strtoupper($valueOn) === 'ON' && strtoupper($valueOff) === 'OFF') ||
                            (strtoupper($valueOn) === 'TRUE' && strtoupper($valueOff) === 'FALSE')
                        )
                    )
                ) {
                    return '~Switch';
                } else {
                    // Erstelle Profilwerte für boolean-Variable
                    $profileValues = [
                        [false, $this->convertLabelToName($valueOff), '', 0xFF0000],  // Rot für Aus (false)
                        [true, $this->convertLabelToName($valueOn), '', 0x00FF00]     // Grün für An (true)
                    ];

                    // Registriere das Boolean-Profil direkt
                    $this->RegisterProfileBooleanEx(
                        $ProfileName,
                        'Power',  // Icon
                        '',       // Prefix
                        '',       // Suffix
                        $profileValues
                    );

                    $this->SendDebug(__FUNCTION__, 'Custom Boolean-Profil erstellt: ' . $ProfileName . ' mit Werten: ' . json_encode($profileValues), 0);
                    return $ProfileName;
                }
            }
            return '~Switch';
        }

        // Typ-spezifisches Profil erstellen
        return $this->handleProfileType($type, $expose, $ProfileName);
    }

    /**
     * getStandardProfile
     *
     * Holt das Standardprofil basierend auf Typ und Eigenschaft.
     *
     * Diese Methode sucht in den vordefinierten Standardprofilen (VariableUseStandardProfile)
     * nach einem passenden Profil für die übergebene Kombination aus Typ und Eigenschaft.
     *
     * @param string $type Der Typ des Exposes (z.B. 'binary', 'numeric', 'enum')
     * @param string $property Die Eigenschaft des Exposes (z.B. 'temperature', 'humidity')
     * @param string|null $groupType Optional - Spezifischer Gruppentyp für erweiterte Profilzuordnung
     *
     * @return string Der Name des Standardprofils oder leer, wenn kein Standardprofil definiert ist
     *                     - '~Temperature' für Temperatur-Eigenschaften
     *                     - '~Humidity' für Feuchtigkeits-Eigenschaften
     *                     - '~Battery' für Batterie-Eigenschaften
     *                     - leer wenn kein passendes Profil gefunden wurde
     *
     * Beispiel:
     * ```php
     * // Temperatur-Profil
     * $profile = $this->getStandardProfile('numeric', 'temperature');
     * // Ergebnis: '~Temperature'
     *
     * // Gruppen-spezifisches Profil
     * $profile = $this->getStandardProfile('binary', 'state', 'light');
     * // Ergebnis: '~Switch'
     * ```
     *
     * @see \IPSModule::SendDebug()
     * @see json_encode()
     */
    private function getStandardProfile(string $type, string $property, ?string $groupType = null): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $this->SendDebug(__FUNCTION__ . ' (' . $caller . ')', "Checking for standard profile with type: $type, property: $property, groupType: $groupType", 0);

        // Überprüfen, ob ein Standardprofil für den Typ und die Eigenschaft definiert ist
        foreach (self::$VariableUseStandardProfile as $entry) {
            $this->SendDebug(__FUNCTION__, 'Checking entry: ' . json_encode($entry), 0);
            if (isset($entry['type']) && ($entry['type'] === $type || $entry['type'] === '') && $entry['feature'] === $property) {
                $this->SendDebug(__FUNCTION__, "Found standard profile for type: $type, property: $property", 0);
                return $entry['profile'];
            }
            if (isset($entry['group_type']) && ($entry['group_type'] === $groupType || $entry['group_type'] === '') && $entry['feature'] === $property) {
                $this->SendDebug(__FUNCTION__, "Found standard profile for groupType: $groupType, property: $property", 0);
                return $entry['profile'];
            }
        }

        // Kein Standardprofil gefunden
        $this->SendDebug(__FUNCTION__, "No standard profile found for type: $type, property: $property, groupType: $groupType", 0);
        return '';
    }

    /**
     * getVariableTypeFromProfile
     *
     * Bestimmt den Variablentyp basierend auf verschiedenen Kriterien.
     *
     * @param string $type Der Expose-Typ (z.B. 'binary', 'numeric', 'enum', 'string', 'text', 'composite')
     * @param string $feature Name der Eigenschaft (z.B. 'state', 'brightness', 'temperature')
     * @param string $unit Optional - Die Einheit des Wertes (z.B. '°C', 'W', '%')
     * @param float $value_step Optional - Die Schrittweite für numerische Werte (Standard: 1.0)
     * @param string|null $groupType Optional - Gruppentyp für spezielle Mappings
     *
     * @return string Der ermittelte Variablentyp ('bool', 'int', 'float', 'string')
     *
     * @note Für 'numeric' Typen gilt folgende Logik:
     *       - Returns 'float' wenn:
     *         * Die Einheit in FLOAT_UNITS definiert ist (z.B. 'W', '°C', 'V')
     *         * value_step keine ganze Zahl ist (z.B. 0.5)
     *       - Returns 'int' wenn:
     *         * Keine der float-Bedingungen zutrifft
     *
     * Beispiel:
     * ```php
     * // Float Beispiel (Temperatur)
     * $type = $this->getVariableTypeFromProfile('numeric', 'temperature', '°C', 0.5);
     * // Ergebnis: 'float'
     *
     * // Integer Beispiel (Helligkeit)
     * $type = $this->getVariableTypeFromProfile('numeric', 'brightness', '%', 1.0);
     * // Ergebnis: 'int'
     * ```
     *
     * @see \IPSModule::SendDebug()
     * @see is_string()
     * @see str_replace()
     * @see in_array()
     * @see fmod()
     */
    private function getVariableTypeFromProfile(string $type, string $feature, string $unit = '', float $value_step = 1.0, ?string $groupType = null): string
    {
        // Prüfen, ob ein spezifisches Mapping existiert.
        // Wichtig: Nicht nur auf den Feature-Namen matchen, da z.B. "position"
        // je nach Gerätetyp numerisch (Cover) oder enum (Kontakt) sein kann.
        foreach (self::$VariableUseStandardProfile as $entry) {
            if (($entry['feature'] ?? '') !== $feature) {
                continue;
            }

            $typeMatches = !isset($entry['type']) || $entry['type'] === '' || $entry['type'] === $type;
            $groupMatches = !isset($entry['group_type']) || $entry['group_type'] === '' || $entry['group_type'] === $groupType;

            if (!$typeMatches || !$groupMatches) {
                continue;
            }

            $this->SendDebug(__FUNCTION__, 'Found specific mapping: ' . json_encode($entry), 0);

            switch ($entry['variableType']) {
                case VARIABLETYPE_BOOLEAN:
                    return 'bool';
                case VARIABLETYPE_INTEGER:
                    return 'int';
                case VARIABLETYPE_FLOAT:
                    return 'float';
                case VARIABLETYPE_STRING:
                    return 'string';
            }
        }

        // Prüfen, ob die Einheit in den Float-Einheiten enthalten ist
        if (!empty($unit) && \is_string($unit)) {
            // Debug der Original-Einheit
            $this->SendDebug(__FUNCTION__, 'Original unit: ' . bin2hex($unit), 0);

            // Unit kommt aus JSON und ist UTF-8; keine AUTO-Rekonvertierung durchführen.
            $unitTrimmed = str_replace(' ', '', $unit);

            // Erweiterte Debug-Ausgaben
            $this->SendDebug(__FUNCTION__, 'Unit normalized (hex): ' . bin2hex($unitTrimmed), 0);
            $this->SendDebug(__FUNCTION__, 'Unit normalized (readable): ' . $unitTrimmed, 0);
            $this->SendDebug(__FUNCTION__, 'FLOAT_UNITS content: ' . json_encode(self::FLOAT_UNITS), 0);

            if (\in_array($unitTrimmed, self::FLOAT_UNITS, true)) {
                // Wenn unit in FLOAT_UNITS und step eine Ganzzahl ist -> integer
                if ($value_step != 1.0 && fmod($value_step, 1) === 0.0) {
                    $this->SendDebug(__FUNCTION__, 'Unit in FLOAT_UNITS but step is integer, returning integer', 0);
                    return 'integer';
                }
                // Sonst float
                return 'float';
            }
        }

        // Wenn unit nicht in FLOAT_UNITS, aber step eine Dezimalzahl
        if ($value_step != 1.0 && fmod($value_step, 1) !== 0.0) {
            $this->SendDebug(__FUNCTION__, 'Value step is not an integer, returning float', 0);
            return 'float';
        }

        // Allgemeines Typ-Mapping
        $typeMapping = [
            'binary'    => 'bool',
            'numeric'   => 'int',    // Standardmäßig 'numeric' auf 'int' abbilden
            'enum'      => 'string',
            'string'    => 'string',
            'text'      => 'string',
            'composite' => 'composite',
            // Weitere Mapping-Optionen hinzufügen
        ];

        $this->SendDebug(__FUNCTION__, 'Returning type from typeMapping: ' . ($typeMapping[$type] ?? 'string'), 0);
        return $typeMapping[$type] ?? 'string';
    }

    /**
     * isValidStandardProfile
     *
     * Überprüft, ob ein Standardprofil gültig ist.
     *
     * Diese Methode überprüft, ob ein Standardprofil vergeben ist und ob es existiert.
     *
     * @param string $profile Der Name des Standardprofils.
     * @return bool Gibt true zurück, wenn das Standardprofil gültig ist, andernfalls false.
     *
     * @see IPS_VariableProfileExists()
     * @see strpos()
     */
    private static function isValidStandardProfile(string $profile): bool
    {
        // Überprüfen, ob das Profil nicht null und nicht leer ist
        if ($profile === '') {
            return false;
        }
        // Überprüfen, ob das Profil existiert
        if (IPS_VariableProfileExists($profile)) {
            return true;
        }
        // Überprüfen, ob es sich um ein Systemprofil handelt (beginnt mit '~')
        if (strpos($profile, '~') === 0) {
            return true;
        }
        return false;
    }

    /**
     * handleProfileType
     *
     * Handhabt die Erstellung eines Profils basierend auf dem Typ des Exposes.
     *
     * Diese Methode erstellt ein Profil für den angegebenen Expose-Typ (z. B. binary, enum, numeric).
     * Sie ruft die entsprechenden Methoden zur Erstellung des Profils auf.
     *
     * @param string $type Der Typ des Exposes (z. B. 'binary', 'enum', 'numeric').
     * @param array $expose Die Expose-Daten, die Informationen wie Typ, Werte und Einheiten enthalten.
     * @param string $ProfileName Der Name des zu erstellenden Profils.
     *
     * @return string Der Name des erstellten Profils.
     *
     * @see \Zigbee2MQTT\ModulBase::registerBinaryProfile()
     * @see \Zigbee2MQTT\ModulBase::registerEnumProfile()
     * @see \Zigbee2MQTT\ModulBase::registerNumericProfile()
     * @see \Zigbee2MQTT\ModulBase::handleProfileType()
     * @see \Zigbee2MQTT\ModulBase::registerSpecialVariable()
     * @see \IPSModule::SendDebug()
     * @see strtolower()
     * @see json_encode()
     */
    private function handleProfileType(string $type, array $expose, string $ProfileName): string
    {
        $this->SendDebug(__FUNCTION__, 'Processing type: ' . $type . ' for profile: ' . $ProfileName, 0);
        $this->SendDebug(__FUNCTION__, 'Expose data: ' . json_encode($expose), 0);

        switch ($type) {
            case 'binary':
                return $this->registerBinaryProfile($ProfileName);

            case 'enum':
                return $this->registerEnumProfile($expose, $ProfileName);

            case 'numeric':
                $result = $this->registerNumericProfile($expose);
                if (!isset($result['mainProfile'])) {
                    $this->SendDebug(__FUNCTION__, 'Error: No mainProfile returned from registerNumericProfile', 0);
                }
                $this->SendDebug(__FUNCTION__, 'Created numeric profile: ' . $result['mainProfile'], 0);
                return $result['mainProfile'];

            case 'climate':
                // Für climate-Typen die Features einzeln verarbeiten
                if (isset($expose['features'])) {
                    foreach ($expose['features'] as $feature) {
                        $featureProfileName = 'Z2M.' . strtolower($feature['property']);
                        $this->handleProfileType($feature['type'], $feature, $featureProfileName);
                    }
                }
                return $ProfileName;

            case 'color_temp':
                $this->registerSpecialVariable($expose);
                return $ProfileName;

            default:
                $this->SendDebug(__FUNCTION__, 'Unsupported profile type: ' . $type, 0);
                return ''; // Fallback: kein Profil
        }
    }

    // Profiltypen

    /**
     * registerBinaryProfile
     *
     * Erstellt ein binäres Profil für Variablen mit zwei Zuständen.
     *
     * Diese Methode erstellt ein Profil für boolesche Werte mit folgenden Eigenschaften:
     * - Zwei Zustände (An/Aus bzw. true/false)
     * - Farbkodierung (Grün für An, Rot für Aus)
     * - Power-Icon für die Visualisierung
     * - Übersetzbare Beschriftungen
     *
     * @param string $ProfileName Der eindeutige Name für das zu erstellende Profil (z.B. 'Z2M.Switch')
     *
     * @return string Der Name des erstellten Profils, identisch mit dem Eingabeparameter
     *
     * Beispiel:
     * ```php
     * $profile = $this->registerBinaryProfile('Z2M.Switch');
     * // Erstellt ein Profil mit den Werten:
     * // false -> "Aus" (rot)
     * // true  -> "An"  (grün)
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::RegisterProfileBooleanEx()
     * @see \IPSModule::SendDebug()
     */
    private function registerBinaryProfile(string $ProfileName): string
    {
        // Registriere das Boolean-Profil mit ON/OFF Werten
        $this->RegisterProfileBooleanEx(
            $ProfileName,
            'Power',  // Icon
            '',       // Prefix
            '',       // Suffix
            [
                [false, 'Off', '', 0xFF0000],  // Rot für Aus
                [true, 'On', '', 0x00FF00]     // Grün für An
            ]
        );

        $this->SendDebug(__FUNCTION__, 'Binary-Profil erstellt: ' . $ProfileName, 0);
        return $ProfileName;
    }

    /**
     * registerEnumProfile
     *
     * Erstellt ein Profil für Enum-Werte basierend auf den Expose-Daten.
     *
     * @param array $expose Die Expose-Daten mit folgenden Schlüsseln:
     *                     - 'values': Array mit möglichen Enum-Werten (erforderlich)
     *                     Beispiel: ['off', 'on', 'toggle']
     * @param string $ProfileName Basis-Name des zu erstellenden Profils
     *                           Der tatsächliche Profilname wird um einen CRC32-Hash erweitert
     *
     * @return string Name des erstellten Profils (Format: BasisName.HashWert)
     *
     * Beispiel:
     * ```php
     * $expose = [
     *     'values' => ['auto', 'manual', 'boost']
     * ];
     * $profile = $this->registerEnumProfile($expose, 'Z2M.Mode');
     * // Ergebnis: Z2M.Mode.a1b2c3d4
     * ```
     *
     * @note Die Werte werden automatisch:
     *       - Sortiert für konsistente Hash-Generierung
     *       - In lesbare Form konvertiert (z.B. manual -> Manual)
     *       - In missingTranslations Buffer hinzufügen falls nicht vorhanden
     *
     * @see \Zigbee2MQTT\ModulBase::isValueInLocaleJson()
     * @see \Zigbee2MQTT\ModulBase::RegisterProfileStringEx()
     * @see \IPSModule::SendDebug()
     * @see sort()
     * @see implode()
     * @see dechex()
     * @see crc32()
     * @see ucwords()
     * @see str_replace()
     * @see json_encode()
     */
    private function registerEnumProfile(array $expose, string $ProfileName): string
    {
        if (!isset($expose['values'])) {
            $this->SendDebug(__FUNCTION__, 'Keine Werte für Enum-Profil gefunden', 0);
            return $ProfileName;
        }

        // Sortiere Werte für konsistente CRC32-Berechnung
        sort($expose['values']);

        // Erstelle eindeutigen Profilnamen basierend auf den Werten
        $tmpProfileName = implode('', $expose['values']);
        $ProfileName .= '.' . dechex(crc32($tmpProfileName));

        // Erstelle Profilwerte
        $profileValues = [];
        foreach ($expose['values'] as $value) {
            $readableValue = ucwords(str_replace('_', ' ', (string) $value));
            // Prüfe, ob der Wert in der locale.json vorhanden ist
            $this->isValueInLocaleJson($readableValue, 'value');
            $profileValues[] = [(string) $value, $readableValue, '', 0x00FF00];
        }

        // Registriere das Profil
        $this->RegisterProfileStringEx(
            $ProfileName,
            'Menu',
            '',
            '',
            $profileValues
        );

        $this->SendDebug(__FUNCTION__, 'Enum-Profil erstellt: ' . $ProfileName . ' mit Werten: ' . json_encode($profileValues), 0);
        return $ProfileName;
    }

    /**
     * registerNumericProfile
     *
     * Erstellt ein numerisches Variablenprofil (ganzzahlig oder Gleitkomma) basierend auf den Expose-Daten.
     *
     * @param array $expose Die Expose-Daten mit folgenden Schlüsseln:
     *                     - 'type': Typ des Exposes (string)
     *                     - 'property': Name der Eigenschaft (string)
     *                     - 'unit': Optional - Einheit des Wertes (string)
     *                     - 'value_step': Optional - Schrittweite (float|int)
     *                     - 'value_min': Optional - Minimaler Wert (float|int)
     *                     - 'value_max': Optional - Maximaler Wert (float|int)
     *                     - 'presets': Optional - Array mit vordefinierten Werten
     *
     * @return array Assoziatives Array mit:
     *               - 'mainProfile': string - Name des Hauptprofils
     *               - 'presetProfile': string|null - Name des Preset-Profils, falls vorhanden
     *
     * Beispiel:
     * ```php
     * $expose = [
     *     'type' => 'numeric',
     *     'property' => 'temperature',
     *     'unit' => '°C',
     *     'value_min' => 0,
     *     'value_max' => 40,
     *     'value_step' => 0.5
     * ];
     * $result = $this->registerNumericProfile($expose);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::getVariableTypeFromProfile()
     * @see \Zigbee2MQTT\ModulBase::getStandardProfile()
     * @see \Zigbee2MQTT\ModulBase::isValidStandardProfile()
     * @see \Zigbee2MQTT\ModulBase::getFullRangeProfileName()
     * @see strtolower()
     * @see strtoupper()
     */
    private function registerNumericProfile(array $expose): array
    {
        // Frühe Typ-Bestimmung
        $type = $expose['type'] ?? '';
        $feature = $expose['property'] ?? '';
        $unit = isset($expose['unit']) && \is_string($expose['unit']) ? $expose['unit'] : '';
        $value_step = isset($expose['value_step']) ? (float) $expose['value_step'] : 1.0;

        // Bestimme Variablentyp
        $variableType = $this->getVariableTypeFromProfile($type, $feature, $unit, $value_step);
        $this->SendDebug(__FUNCTION__, 'Initial Variable Type: ' . $variableType, 0);

        // Standardprofil-Prüfung
        $standardProfile = $this->getStandardProfile($type, $feature);
        if ($standardProfile !== '') {
            if (self::isValidStandardProfile($standardProfile)) {
                return ['mainProfile' => $standardProfile, 'presetProfile' => null];
            }
            return ['mainProfile' => $standardProfile, 'presetProfile' => null];
        }

        // Eigenes Profil erstellen
        $fullRangeProfileName = self::getFullRangeProfileName($expose);
        $min = $expose['value_min'] ?? 0;
        $max = $expose['value_max'] ?? 0;
        $step = $expose['value_step'] ?? 1.0;

        // Einheit direkt als UTF-8 verwenden.
        $unitWithSpace = '';
        if ($unit !== '') {
            $unitWithSpace = ' ' . $unit;
        }

        // Profil entsprechend Variablentyp erstellen
        if ($variableType === 'float') {
            $this->RegisterProfileFloat($fullRangeProfileName, '', '', $unitWithSpace, (float) $min, (float) $max, (float) $step, 2);
            $this->SendDebug(__FUNCTION__, 'Created Float Profile: ' . $fullRangeProfileName, 0);
        } else {
            $this->RegisterProfileInteger($fullRangeProfileName, '', '', $unitWithSpace, (int) $min, (int) $max, (float) $step);
            $this->SendDebug(__FUNCTION__, 'Created Integer Profile: ' . $fullRangeProfileName, 0);
        }

        // Preset-Handling
        $presetProfileName = null;
        if (isset($expose['presets']) && !empty($expose['presets'])) {
            $formattedLabel = $this->convertLabelToName($feature);
            $presetProfileName = $this->registerPresetProfile($expose['presets'], $formattedLabel, $variableType, $expose);
        }

        return ['mainProfile' => $fullRangeProfileName, 'presetProfile' => $presetProfileName];
    }

    /**
     * registerPresetProfile
     *
     * Registriert ein Variablenprofil für Presets basierend auf den übergebenen Preset-Daten.
     *
     * Diese Funktion generiert ein Profil für eine Preset-Variable, das verschiedene vordefinierte Werte enthält.
     * Der Profilname wird dynamisch basierend auf dem übergebenen Label und den Min- und Max-Werten erstellt.
     * Falls ein Profil mit diesem Namen bereits existiert, wird es gelöscht und neu erstellt.
     *
     * Jedes Preset im übergebenen Array wird mit seinem Namen und Wert dem Profil hinzugefügt. Der Name des Presets
     * wird dabei ins Lesbare umgewandelt (z.B. von snake_case in normaler Text), und die zugehörigen Werte werden
     * als Assoziationen im Profil gespeichert. Die Presets erhalten außerdem eine standardmäßige weiße Farbe
     * für die Anzeige.
     *
     * @param array $presets Ein Array von Presets, die jeweils einen Namen und einen zugehörigen Wert enthalten.
     *                       Beispielstruktur eines Presets:
     *                       [
     *                           'name'  => 'coolest',    // Name des Presets
     *                           'value' => 153           // Wert des Presets
     *                       ]
     * @param string $label Der Name, der dem Profil zugeordnet wird. Leerzeichen im Label werden durch Unterstriche ersetzt.
     * @param string $variableType Der Variablentyp (z.B. 'float', 'int').
     * @param array $feature Die Expose-Daten, die die Eigenschaften des Features enthalten, einschließlich Min- und Max-Werten.
     *
     * @return string Der Name des erstellten Profils.
     *
     * @see \Zigbee2MQTT\ModulBase::RegisterProfileFloatEx()
     * @see \Zigbee2MQTT\ModulBase::RegisterProfileIntegerEx()
     * @see \IPSModule::LogMessage()
     * @see \IPSModule::Translate()
     * @see str_replace()
     * @see sprintf()
     * @see ucwords()
     */
    private function registerPresetProfile(array $presets, string $label, string $variableType, array $feature): string
    {
        // Profilname ohne Leerzeichen erstellen und Min- und Max-Werte hinzufügen
        $profileName = 'Z2M.' . str_replace(' ', '_', $label);
        $valueMin = $feature['value_min'] ?? null;
        $valueMax = $feature['value_max'] ?? null;

        if ($valueMin !== null && $valueMax !== null) {
            $profileName .= '_' . $valueMin . '_' . $valueMax;
        }

        $profileName .= '_Presets';

        // Prüfen ob vordefinierte Presets existieren
        $property = $feature['property'] ?? '';
        if (isset(self::$presetDefinitions[$property])) {
            $this->SendDebug(__FUNCTION__, 'Using predefined presets for: ' . $property, 0);
            $associations = [];
            foreach (self::$presetDefinitions[$property]['values'] as $value => $name) {
                $associations[] = [
                    $value,
                    $this->Translate($name),
                    '',
                    -1
                ];
            }
        } else {
            // Dynamische Presets verwenden
            $associations = [];
            foreach ($presets as $preset) {
                // Preset-Wert an den Variablentyp anpassen

                $presetValue = ($variableType === 'float') ? (float) $preset['value'] : (int) $preset['value'];
                $presetName = $this->Translate(ucwords(str_replace('_', ' ', $preset['name'])));
                $associations[] = [
                    $presetValue,
                    $presetName,
                    '',
                    -1
                ];
            }
        }

        // Neues Profil anlegen
        if ($variableType === 'float') {
            $this->RegisterProfileFloatEx($profileName, '', '', '', $associations);
        } else {
            $this->RegisterProfileIntegerEx($profileName, '', '', '', $associations);
        }

        return $profileName;
    }

    /**
     * checkExposeAttribute
     *
     * @return bool false wenn UpdateDeviceInfo ausgeführt wurde, sonst true
     */
    private function checkExposeAttribute(): bool
    {
        $mqttTopic = $this->ReadPropertyString(self::MQTT_TOPIC);

        // Erst prüfen ob MQTTTopic gesetzt ist
        if (empty($mqttTopic)) {
            $this->SendDebug(__FUNCTION__, 'MQTTTopic nicht gesetzt, überspringe Attribut Prüfung', 0);
            return true;
        }

        // Prüfe ob Expose-Attribute existiert und Daten enthält
        $exposes = $this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES);
        if (\count($exposes)) {
            return true;
        }

        $this->SendDebug(__FUNCTION__, 'Expose-Attribute nicht gefunden für Instance: ' . $this->InstanceID, 0);

        if (!$this->HasActiveParent()) {
            $this->SendDebug(__FUNCTION__, 'Parent nicht aktiv, überspringe UpdateDeviceInfo', 0);
            return true;
        }

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return true;
        }

        $this->SendDebug(__FUNCTION__, 'Starte UpdateDeviceInfo für Topic: ' . $mqttTopic, 0);
        if (!$this->UpdateDeviceInfo()) {
            $this->SendDebug(__FUNCTION__, 'UpdateDeviceInfo fehlgeschlagen', 0);
        }
        return false;
    }

    /**
     * getKnownVariables
     *
     * Lädt und verarbeitet die bekannten Variablen aus den gespeicherten JSON-Expose-Dateien.
     *
     * Diese Methode durchsucht das Zigbee2MQTTExposes-Verzeichnis nach einer JSON-Datei, die der aktuellen Instanz-ID entspricht.
     * Sie extrahiert alle Features aus den Exposes und erstellt daraus ein Array von bekannten Variablen.
     *
     * Der Prozess beinhaltet:
     * - Suche nach der JSON-Datei im Symcon-Kernel-Verzeichnis
     * - Laden und Dekodieren der JSON-Daten
     * - Extraktion der Features aus den Exposes
     * - Filterung nach Features mit 'property'-Attribut
     * - Normalisierung der Feature-Namen (Kleinbuchstaben, getrimmt)
     *
     * Dateistruktur:
     * {
     *     "exposes": [
     *         {
     *             "features": [...],
     *             "property": "example_property"
     *         }
     *     ]
     * }
     *
     * @internal Diese Methode wird intern vom Modul verwendet
     *
     * @return array Ein assoziatives Array mit bekannten Variablen, wobei der Key der normalisierte Property-Name ist
     *               und der Value die komplette Feature-Definition enthält.
     *               Format: ['property_name' => ['property' => 'name', ...]]
     *               Leeres Array wenn keine Variablen gefunden wurden.
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable() Verwendet die zurückgegebenen Variablen zur Registrierung, über
     * @see \Zigbee2MQTT\ModulBase::processVariable()
     * @see \IPSModule::SendDebug()
     * @see IPS_GetKernelDir()
     * @see file_exists()
     * @see file_get_contents()
     * @see json_decode()
     * @see json_encode()
     * @see array_map()
     * @see array_merge()
     * @see array_filter()
     * @see trim()
     * @see strtolower()
     */
    private function getKnownVariables(): array
    {
        $data = array_values($this->ReadAttributeArray(self::ATTRIBUTE_EXPOSES));
        if (!\count($data)) {
            $this->SendDebug(__FUNCTION__, 'Fehlende exposes oder features.', 0);
            return [];
        }

        $features = array_map(function ($expose)
        {
            return isset($expose['features']) ? $expose['features'] : [$expose];
        }, $data);

        $features = array_merge(...$features);

        // Icons und unerwünschte Properties filtern
        $filteredFeatures = array_filter($features, function ($feature)
        {
            // Icon Properties und andere unerwünschte Einträge ignorieren
            if (isset($feature['property'])) {
                if ($feature['property'] === 'icon') {
                    $this->SendDebug(__FUNCTION__, 'Icon-Property übersprungen: ' . json_encode($feature), 0);
                    return false;
                }
                if (strpos($feature['property'], 'Icon') !== false) {
                    $this->SendDebug(__FUNCTION__, 'Icon im Namen gefunden - übersprungen: ' . json_encode($feature), 0);
                    return false;
                }
                return true;
            }
            return false;
        });

        $knownVariables = [];
        foreach ($filteredFeatures as $feature) {
            $variableName = trim(strtolower($feature['property']));
            $knownVariables[$variableName] = $feature;
            if ($variableName == 'occupancy') {
                $knownVariables['no_occupancy_since'] = [
                    'property'=> 'no_occupancy_since'
                ];
            }
        }

        $this->SendDebug(__FUNCTION__ . ' Known Variables Array:', json_encode($knownVariables), 0);

        return $knownVariables;
    }

    /**
     * isValueInLocaleJson
     *
     * Prüft, ob ein Wert in der locale.json vorhanden ist.
     *
     * @param string $value Der zu prüfende Wert.
     * @return bool Gibt true zurück, wenn der Wert in der locale.json vorhanden ist, andernfalls false.
     *
     *@see file_exists()
     *@see strtoupper()
     *@see substr()
     *@see json_decode()
     */
    private function isValueInLocaleJson(string $Text, string $Type): bool
    {
        $translation = json_decode(file_get_contents(__DIR__ . '/locale_z2m.json'), true);
        $language = IPS_GetSystemLanguage();
        $code = explode('_', $language)[0];
        if (isset($translation['translations'])) {
            if (isset($translation['translations'][$language])) {
                if (isset($translation['translations'][$language][$Text])) {
                    return true;
                }
            } elseif (isset($translation['translations'][$code])) {
                if (isset($translation['translations'][$code][$Text])) {
                    return true;
                }
            }
        }
        $this->addValueToTranslationsBuffer($Text, $Type);
        return false;
    }

    /**
     * addValueToTranslationsBuffer
     *
     * Fügt einen Wert zum Missingtranslations Buffer hinzu, wenn er noch nicht vorhanden ist.
     * Gibt eine Liste an Begriffen, die noch in der locale.json ergänzt werden müssen.
     *
     * @param string $value Der hinzuzufügende Wert.
     * @return void
     *
     * @see file_exists()
     * @see file_get_contents()
     * @see json_decode()
     * @see json_encode()
     * @see in_array()
     * @see file_put_contents()
     */
    private function addValueToTranslationsBuffer(string $value, string $type): void
    {
        $translations = $this->missingTranslations;
        $missingKVP = [$type => $value];
        // Füge den neuen Begriff hinzu, wenn er noch nicht existiert
        if (!\in_array($missingKVP, $translations)) {
            $translations[] = $missingKVP;
            $this->missingTranslations = $translations;
        }
    }

    /**
     * registerVariable
     *
     * Registriert eine Variable basierend auf den Feature-Informationen
     *
     * @param array|string $feature Feature-Information als Array oder Feature-ID als String
     *                             Array-Format:
     *                             - 'property': (string) Identifikator der Variable
     *                             - 'type': (string) Datentyp (numeric, binary, enum, etc.)
     *                             - 'unit': (string, optional) Einheit der Variable
     *                             - 'value_step': (float, optional) Schrittweite für numerische Werte
     *                             - 'features': (array, optional) Sub-Features für composite Variablen
     *                             - 'presets': (array, optional) Voreingestellte Werte
     *                             - 'access': (int, optional) Zugriffsrechte (0b001=read, 0b010=write, 0b100=notify)
     *                             - 'color_mode': (bool, optional) Für Farbvariablen
     * @param string|null $exposeType Optional, überschreibt den Feature-Typ
     *
     * @return void
     *
     * @throws \Exception Bei ungültigen Feature-Informationen
     *
     * Beispiele:
     * ```php
     * // Einfache Variable
     * $this->registerVariable(['property' => 'state', 'type' => 'binary']);
     *
     * // Composite Variable (z.B. weekly_schedule)
     * $this->registerVariable([
     *     'property' => 'weekly_schedule',
     *     'type' => 'composite',
     *     'features' => [
     *         ['property' => 'monday', 'type' => 'string']
     *     ]
     * ]);
     *
     * // Variable mit Presets
     * $this->registerVariable([
     *     'property' => 'mode',
     *     'type' => 'enum',
     *     'presets' => ['auto', 'manual']
     * ]);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::getStateConfiguration()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::registerSpecialVariable()
     * @see \Zigbee2MQTT\ModulBase::getVariableTypeFromProfile()
     * @see \Zigbee2MQTT\ModulBase::getStandardProfile()
     * @see \Zigbee2MQTT\ModulBase::registerVariableProfile()
     * @see \Zigbee2MQTT\ModulBase::registerColorVariable()
     * @see \Zigbee2MQTT\ModulBase::registerPresetVariables()
     * @see \IPSModule::RegisterVariableBoolean()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableString()
     * @see \IPSModule::Translate()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::GetIDForIdent()
     * @see is_array()
     * @see json_encode()
     * @see ucfirst()
     * @see str_replace()
     */
    private function registerVariable(mixed $feature, ?string $exposeType = null): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        $featureProperty = \is_array($feature) ? $feature['property'] : $feature;

        // Frühe Validierung der Property
        if (empty($featureProperty)) {
            $this->SendDebug(__FUNCTION__, 'Error: Empty property/identifier provided', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__ . ' Registriere Variable für Property: ', $featureProperty, 0);

        // Übergebe das komplette Feature-Array für Access-Check
        $stateConfig = $this->getStateConfiguration($featureProperty, \is_array($feature) ? $feature : null);
        if ($stateConfig !== null) {
            $formattedLabel = $this->convertLabelToName($featureProperty);

            // Registriere Variable basierend auf dataType
            switch ($stateConfig['dataType']) {
                case VARIABLETYPE_BOOLEAN:
                    $this->RegisterVariableBoolean(
                        $stateConfig['ident'],
                        $this->Translate($formattedLabel),
                        $stateConfig['profile']
                    );
                    break;
                case VARIABLETYPE_STRING:
                    $this->RegisterVariableString(
                        $stateConfig['ident'],
                        $this->Translate($formattedLabel),
                        $stateConfig['profile']
                    );
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'Unsupported state dataType: ' . $stateConfig['dataType'], 0);
                    return;
            }

            // Zentrale EnableAction-Prüfung für State-Variablen
            // Prüfe erst auf explizite enableAction-Definition in stateConfig
            if (isset($stateConfig['enableAction'])) {
                // Explizit definierter enableAction-Wert hat Vorrang
                if ($stateConfig['enableAction']) {
                    $this->checkAndEnableAction($stateConfig['ident'], \is_array($feature) ? $feature : null, true);
                }
                // Wenn explizit false, dann kein EnableAction - auch nicht über Access-Rechte
            } else {
                // Kein expliziter enableAction-Wert, verwende Access-basierte Prüfung
                $this->checkAndEnableAction($stateConfig['ident'], \is_array($feature) ? $feature : null);
            }
            return;
        }

        // Überprüfung auf spezielle Fälle
        if (isset(self::$specialVariables[$feature['property']])) {
            $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
            if (\in_array($feature['property'], $aFiltered, true)) {
                $this->SendDebug(__FUNCTION__, 'Skipping filtered special variable: ' . $feature['property'], 0);
                return;
            }
            $this->registerSpecialVariable($feature);
            return;
        }

        // Setze den Typ auf den übergebenen Expose-Typ, falls vorhanden
        if ($exposeType !== null) {
            $feature['type'] = $exposeType;
        }

        // Berücksichtige den Gruppentyp, falls vorhanden, ohne den ursprünglichen Typ zu überschreiben
        $groupType = $feature['group_type'] ?? null;

        $this->SendDebug(__FUNCTION__ . ' :: Registering Feature', json_encode($feature), 0);

        $type = $feature['type'];
        $property = $featureProperty; // Bereits validiert
        $unit = $feature['unit'] ?? '';
        $step = isset($feature['value_step']) ? (float) $feature['value_step'] : 1.0;

        // Bestimmen des Variablentyps basierend auf Typ, Feature und Einheit
        $variableType = $this->getVariableTypeFromProfile($type, $property, $unit, $step, $groupType);

        // Überprüfen, ob ein Standardprofil verwendet werden soll
        $profileName = $this->getStandardProfile($type, $property, $groupType);

        // Profil vor der Variablenerstellung erstellen, falls kein Standardprofil verwendet wird
        if ($profileName === '') {
            $profileName = $this->registerVariableProfile($feature);
        }

        // Registrierung der Variable basierend auf dem Variablentyp
        $ident = str_replace('&', '_and_', $property);
        switch ($variableType) {
            case 'bool':
                $this->SendDebug(__FUNCTION__, 'Registering Boolean Variable: ' . $property, 0);
                $this->RegisterVariableBoolean($ident, $this->Translate($this->convertLabelToName($property)), $profileName);
                break;
            case 'int':
                $this->SendDebug(__FUNCTION__, 'Registering Integer Variable: ' . $property, 0);
                $this->RegisterVariableInteger($ident, $this->Translate($this->convertLabelToName($property)), $profileName);
                break;
            case 'float':
                $this->SendDebug(__FUNCTION__, 'Registering Float Variable: ' . $property, 0);
                $this->RegisterVariableFloat($ident, $this->Translate($this->convertLabelToName($property)), $profileName);
                break;
            case 'string':
                $this->SendDebug(__FUNCTION__, 'Registering String Variable: ' . $property, 0);
                $this->RegisterVariableString($ident, $this->Translate($this->convertLabelToName($property)), $profileName);
                break;
            case 'text':
                $this->SendDebug(__FUNCTION__, 'Registering Text Variable: ' . $property, 0);
                $this->RegisterVariableString($ident, $this->Translate($this->convertLabelToName($property))); // Kein Profilname übergeben
                break;
                // Zusätzliche Registrierung für 'composite' Farb-Variablen
            case 'composite':
                $this->SendDebug(__FUNCTION__, 'Registering Composite Variable: ' . $property, 0);

                // Bestehende Color-Variable Logik beibehalten
                if (isset($feature['color_mode'])) {
                    $this->registerColorVariable($feature);
                    return;
                }

                // Feature-Verarbeitung
                if (isset($feature['features'])) {
                    foreach ($feature['features'] as $subFeature) {
                        // Bilde Sub-Properties
                        $subProperty = $subFeature['property'];
                        $subProperty = str_replace('&', '_and_', $subFeature['property']);
                        $subFeature['property'] = $ident . '__' . $subProperty;

                        // Preset-Handling für Sub-Features
                        if (isset($subFeature['presets'])) {
                            $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
                            $subPresetIdent = $subFeature['property'] . '_presets';
                            if (!\in_array($subPresetIdent, $aFiltered, true)) {
                                $variableType = $this->getVariableTypeFromProfile(
                                    $subFeature['type'] ?? 'numeric',
                                    $subFeature['property'],
                                    $subFeature['unit'] ?? '',
                                    $subFeature['value_step'] ?? 1.0
                                );
                                $this->registerPresetVariables(
                                    $subFeature['presets'],
                                    $subFeature['property'],
                                    $variableType,
                                    $subFeature
                                );
                            }
                        }

                        // Rekursiver Aufruf mit einzelnem Feature
                        $this->registerVariable($subFeature, $exposeType);
                    }
                }
                break;

            case 'list':
                // Hauptvariable als JSON Array
                $this->RegisterVariableString(
                    $ident,
                    $this->Translate($this->convertLabelToName($property))
                );

                // Registriere item_type als composite
                if (isset($feature['item_type'])) {
                    $itemFeature = $feature['item_type'];
                    $itemFeature['property'] = $ident . '_item';
                    $this->registerVariable($itemFeature);
                }
                break;

            default:
                $this->SendDebug(__FUNCTION__, 'Unsupported variable type: ' . $variableType, 0);
                return;
        }

        // Zentrale EnableAction-Prüfung für die Hauptvariable, außer bei composite
        if ($variableType != 'composite') {
            $this->checkAndEnableAction($ident, $feature);
        }
        // Zusätzliche Registrierung der color_temp_kelvin Variable, wenn color_temp registriert wird
        if ($property === 'color_temp') {
            $kelvinIdent = $property . '_kelvin';
            $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
            if (!\in_array($kelvinIdent, $aFiltered, true)) {
                $this->RegisterVariableInteger($kelvinIdent, $this->Translate('Color Temperature Kelvin'), '~TWColor');
                $variableId = $this->GetIDForIdent($kelvinIdent);
                // color_temp_kelvin ist eine spezielle UI-Variable, die immer EnableAction haben soll
                $this->checkAndEnableAction($kelvinIdent, null, true);
            }
        }
        // Preset-Verarbeitung nach der normalen Variablenregistrierung
        if (isset($feature['presets']) && !empty($feature['presets'])) {
            $presetIdent = $property . '_presets';
            $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
            if (!\in_array($presetIdent, $aFiltered, true)) {
                $variableType = $this->getVariableTypeFromProfile($type, $property, $unit, $step, $groupType);
                $this->registerPresetVariables($feature['presets'], $feature['property'], $variableType, $feature);
                $this->SendDebug(__FUNCTION__, 'Registered presets for: ' . $feature['property'], 0);
            }
        }
        return;
    }

    /**
     * registerColorVariable
     *
     * Registriert Farbvariablen für verschiedene Farbmodelle.
     *
     * Diese Methode erstellt und registriert spezielle Variablen für die Farbsteuerung
     * von Zigbee-Geräten. Unterstützt werden die Farbmodelle:
     * - XY-Farbraum (color_xy)
     * - HSV-Farbraum (color_hs)
     * - RGB-Farbraum (color_rgb)
     *
     * @param array $feature Array mit Eigenschaften des Features:
     *                       - 'name': Name des Farbmodells ('color_xy', 'color_hs', 'color_rgb')
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::Translate()
     * @see debug_backtrace()
     */
    private function registerColorVariable(array $feature): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        $aFiltered = $this->ReadAttributeArray(self::ATTRIBUTE_FILTERED);
        switch ($feature['name']) {
            case 'color_xy':
                if (\in_array('color', $aFiltered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color', 0);
                    break;
                }
                $this->RegisterVariableInteger('color', $this->Translate($this->convertLabelToName('color')), '~HexColor');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->checkAndEnableAction('color', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_xy', 'color', 0);
                break;
            case 'color_hs':
                if (\in_array('color_hs', $aFiltered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color_hs', 0);
                    break;
                }
                $this->RegisterVariableInteger('color_hs', $this->Translate($this->convertLabelToName('color_hs')), '~HexColor');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->checkAndEnableAction('color_hs', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_hs', 'color_hs', 0);
                break;
            case 'color_rgb':
                if (\in_array('color_rgb', $aFiltered, true)) {
                    $this->SendDebug(__FUNCTION__, 'Skipping filtered color variable: color_rgb', 0);
                    break;
                }
                $this->RegisterVariableInteger('color_rgb', $this->Translate($this->convertLabelToName('color_rgb')), '~HexColor');
                // Farbvariablen erhalten IMMER EnableAction, unabhängig von Access-Prüfung
                $this->checkAndEnableAction('color_rgb', null, true);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_rgb', 'color_rgb', 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Unhandled composite type', $feature['name'], 0);
                break;
        }
    }

    /**
     * registerPresetVariables
     *
     * Registriert Variablen und Profile für Presets eines Features.
     *
     * Diese Funktion erstellt für ein Feature eine zusätzliche Preset-Variable mit entsprechendem Profil.
     * Sie wird verwendet, um vordefinierte Werte (Presets) für bestimmte Eigenschaften eines Geräts
     * zugänglich zu machen.
     *
     * @param array $presets Array mit Preset-Definitionen. Jedes Preset enthält:
     *                       - 'name': Name des Presets (string)
     *                       - 'value': Wert des Presets (mixed)
     * @param string $label Bezeichnung für die Variable
     * @param string $variableType Typ der Variable ('float' oder 'int')
     * @param array $feature Feature-Definition mit zusätzlichen Eigenschaften wie:
     *                       - 'property': Name der Eigenschaft
     *                       - 'name': Anzeigename
     *                       - 'value_min': Minimaler Wert (optional)
     *                       - 'value_max': Maximaler Wert (optional)
     * @return void
     *
     * Beispiel:
     * ```php
     * $presets = [
     *     ['name' => 'low', 'value' => 20],
     *     ['name' => 'medium', 'value' => 50],
     *     ['name' => 'high', 'value' => 100]
     * ];
     * $this->registerPresetVariables($presets, 'Brightness', 'int', ['property' => 'brightness', 'name' => 'Brightness']);
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::registerPresetProfile()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::Translate()
     * @see \IPSModule::GetIDForIdent()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::EnableAction()
     */
    private function registerPresetVariables(array $presets, string $property, string $variableType, array $feature): void
    {
        $this->SendDebug(__FUNCTION__, 'Registriere Preset-Variablen für: ' . $property, 0);

        // Hole ident für Preset-Variable
        $presetIdent = $property . '_presets';

        // Name formatieren
        $formattedLabel = $this->convertLabelToName($property);

        // Profil registrieren
        $profileName = $this->registerPresetProfile($presets, $formattedLabel, $variableType, $feature);

        // Variable anhand Typ registrieren
        if ($variableType === 'float') {
            $this->RegisterVariableFloat($presetIdent, $this->Translate($formattedLabel . ' Presets'), $profileName);
        } else {
            $this->RegisterVariableInteger($presetIdent, $this->Translate($formattedLabel . ' Presets'), $profileName);
        }

        // Zentrale EnableAction-Prüfung für Preset-Variable
        $this->checkAndEnableAction($presetIdent, $feature);
    }

    /**
     * registerSpecialVariable
     *
     * Registriert spezielle Variablen.
     *
     * @param array $feature Feature-Eigenschaften
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::adjustSpecialValue()
     * @see \Zigbee2MQTT\ModulBase::convertLabelToName()
     * @see \Zigbee2MQTT\ModulBase::SetValue()
     * @see \IPSModule::GetBuffer()
     * @see \IPSModule::SendDebug()
     * @see \IPSModule::RegisterVariableFloat()
     * @see \IPSModule::RegisterVariableInteger()
     * @see \IPSModule::RegisterVariableString()
     * @see \IPSModule::RegisterVariableBoolean()
     * @see \IPSModule::Translate()
     * @see \IPSModule::EnableAction()
     * @see sprintf()
     * @see json_encode()
     */
    private function registerSpecialVariable($feature): void
    {
        // Während Migration keine Variablen erstellen
        if ($this->BUFFER_PROCESSING_MIGRATION) {
            return;
        }

        $ident = $feature['property'];
        $this->SendDebug(__FUNCTION__, \sprintf('Checking special case for %s: %s', $ident, json_encode($feature)), 0);

        if (!isset(self::$specialVariables[$ident])) {
            return;
        }

        $varDef = self::$specialVariables[$ident];
        $formattedLabel = $this->convertLabelToName($ident);

        // Wert anpassen wenn nötig
        if (isset($feature['value'])) {
            $value = $this->adjustSpecialValue($ident, $feature['value']);
        }

        switch ($varDef['type']) {
            case VARIABLETYPE_FLOAT:
                $this->RegisterVariableFloat($ident, $this->Translate($formattedLabel), $varDef['profile']);
                break;
            case VARIABLETYPE_INTEGER:
                $this->RegisterVariableInteger($ident, $this->Translate($formattedLabel), $varDef['profile']);
                break;
            case VARIABLETYPE_STRING:
                $this->RegisterVariableString($ident, $this->Translate($formattedLabel), $varDef['profile']);
                break;
            case VARIABLETYPE_BOOLEAN:
                $this->RegisterVariableBoolean($ident, $this->Translate($formattedLabel), $varDef['profile']);
                break;
        }

        // Zentrale EnableAction-Prüfung für spezielle Variable
        $this->checkAndEnableAction($ident, $feature);

        return;
    }

    /**
     * getStateConfiguration
     *
     * Prüft und liefert die Konfiguration für State-basierte Features.
     *
     * Diese Methode analysiert ein Feature und bestimmt, ob es sich um ein State-Feature handelt.
     *
     * Sie prüft drei Szenarien:
     * 1. Vordefinierte States aus stateDefinitions
     * 2. Enum-Typ States (z.B. "state" mit definierten Werten)
     * 3. Standard State-Pattern als Boolean (z.B. "state", "state_left")
     *
     * Bei Enum-States wird automatisch ein eindeutiges Profil erstellt:
     * - Profilname: Z2M.[property].[hash]
     * - Hash basiert auf den Enum-Werten
     * - Enthält alle definierten Enum-Werte mit Icons
     *
     * Die zurückgegebene Konfiguration enthält:
     * - type: Typ des States (z.B. 'switch', 'enum')
     * - dataType: IPS Variablentyp (z.B. VARIABLETYPE_BOOLEAN, VARIABLETYPE_STRING)
     * - values: Mögliche Zustände (z.B. ['ON', 'OFF'] oder ['OPEN', 'CLOSE', 'STOP'])
     * - profile: Zu verwendenes IPS-Profil (z.B. '~Switch' oder 'Z2M.state.hash')
     * - ident: Normalisierter Identifikator
     * - enableAction: Optional - Nur bei explizit definierten States aus stateDefinitions
     *
     * Hinweis: EnableAction wird nur zurückgegeben wenn explizit in stateDefinitions
     * definiert. Ansonsten wird EnableAction zentral in registerVariable() über
     * checkAndEnableAction() basierend auf Access-Rechten bestimmt.
     *
     * @param string $featureId Feature-Identifikator (z.B. 'state', 'state_left')
     * @param array|null $feature Optionales Feature-Array mit weiteren Eigenschaften:
     *                           - type: Datentyp ('enum', 'binary')
     *                           - values: Array möglicher Enum-Werte
     *                           Hinweis: Access-Rechte werden für EnableAction-Entscheidung
     *                           an registerVariable() weitergegeben
     *
     * @return array|null Array mit State-Konfiguration oder null wenn kein State-Feature
     *
     * Beispiel:
     * ```php
     * // Standard boolean state
     * $config = $this->getStateConfiguration('state');
     * // Ergebnis: ['type' => 'switch', 'dataType' => VARIABLETYPE_BOOLEAN, 'profile' => '~Switch', 'ident' => 'state']
     *
     * // Enum state mit Profilerstellung
     * $config = $this->getStateConfiguration('state', [
     *     'type' => 'enum',
     *     'values' => ['OPEN', 'CLOSE', 'STOP']
     * ]);
     * // Ergebnis: ['type' => 'enum', 'dataType' => VARIABLETYPE_STRING, 'profile' => 'Z2M.state.hash', 'ident' => 'state']
     *
     * // Vordefinierter state
     * $config = $this->getStateConfiguration('valve_state');
     * // Ergebnis: Konfiguration aus stateDefinitions (inklusive enableAction falls definiert)
     * ```
     *
     * @see \Zigbee2MQTT\ModulBase::registerVariable() Verwendet die Konfiguration und trifft EnableAction-Entscheidung
     * @see \Zigbee2MQTT\ModulBase::checkAndEnableAction() Zentrale EnableAction-Logik
     * @see \IPSModule::SendDebug()
     * @see preg_match()
     */
    private function getStateConfiguration(string $featureId, ?array $feature = null): ?array
    {
        // Basis state-Pattern
        $statePattern = '/^state(?:_[a-z0-9]+)?$/i';

        if (preg_match($statePattern, $featureId)) {
            $this->SendDebug(__FUNCTION__, 'State-Konfiguration für: ' . $featureId, 0);

            // Prüfe ZUERST auf vordefinierte States
            if (isset(static::$stateDefinitions[$featureId])) {
                $stateConfig = static::$stateDefinitions[$featureId];
                // Stelle sicher, dass ident und profile Keys existieren
                $stateConfig['ident'] = $stateConfig['ident'] ?? $featureId;
                $stateConfig['profile'] = $stateConfig['profile'] ?? '';
                return $stateConfig;
            }

            // Dann auf enum type
            if (isset($feature['type']) && $feature['type'] === 'enum' && isset($feature['values'])) {

                // Profil-Werte abholen
                $enumFeature = [
                    'type'     => 'enum',
                    'property' => $featureId,
                    'values'   => $feature['values']
                ];

                // Profil anlegen
                $profileName = $this->registerEnumProfile($enumFeature, 'Z2M.' . $featureId);

                // Daten zur Variablenregistrierung zurückgeben
                return [
                    'type'         => 'enum',
                    'dataType'     => VARIABLETYPE_STRING,
                    'values'       => $feature['values'],
                    'profile'      => $profileName,
                    'ident'        => $featureId
                ];
            }

            // Nur wenn kein enum type und kein vordefinierter state, dann boolean
            return [
                'type'         => 'switch',
                'dataType'     => VARIABLETYPE_BOOLEAN,
                'values'       => ['ON', 'OFF'],
                'profile'      => '~Switch',
                'ident'        => $featureId
            ];
        }

        // Prüfe auf vordefinierte States wenn kein state pattern matched
        if (isset(static::$stateDefinitions[$featureId])) {
            // Registriere gefundenes StateMappingProfil
            $this->registerStateMappingProfile($featureId);
            $stateConfig = static::$stateDefinitions[$featureId];
            // Stelle sicher, dass ident und profile Keys existieren
            $stateConfig['ident'] = $stateConfig['ident'] ?? $featureId;
            $stateConfig['profile'] = $stateConfig['profile'] ?? '';
            return $stateConfig;
        }

        return null;
    }

    /**
     * getFullRangeProfileName
     *
     * Erzeugt den vollständigen Namen eines Variablenprofils basierend auf den Expose-Daten.
     *
     * Diese Methode generiert den vollständigen Namen eines Variablenprofils für ein bestimmtes Feature
     * (Expose). Falls das Feature minimale und maximale Werte (`value_min`, `value_max`) enthält, werden
     * diese in den Profilnamen integriert.
     *
     * @param array $feature Ein Array, das die Eigenschaften des Features enthält.
     *
     * @return string Der vollständige Name des Variablenprofils.
     */
    private static function getFullRangeProfileName($feature): string
    {
        $name = 'Z2M.' . str_replace('&', '_and_', $feature['name']);
        $valueMin = $feature['value_min'] ?? null;
        $valueMax = $feature['value_max'] ?? null;

        if ($valueMin !== null && $valueMax !== null) {
            $name .= '_' . $valueMin . '_' . $valueMax;
        }

        return $name;
    }

    /**
     * registerStateMappingProfile
     *
     * Handhabt die Erstellung eines Zustandsmusters (State Mapping) für ein gegebenes Identifikator.
     *
     * Diese Methode erstellt ein Variablenprofile. Das Profil enthält zwei Zustände,
     * die aus den vordefinierten Zustandsdefinitionen (`stateDefinitions`) abgeleitet werden.
     *
     * @param string $ProfileName Der ProfileName, für den das Zustandsmuster erstellt werden soll.
     *
     * @return string|null Der Name des erstellten Profils oder null, wenn kein Zustandsmuster existiert.
     *
     * @see \Zigbee2MQTT\ModulBase::RegisterProfileStringEx()
     * @see \IPSModule::SendDebug()
     */
    private function registerStateMappingProfile(string $featureProperty): ?string
    {
        $stateInfo = self::$stateDefinitions[$featureProperty];
        $this->RegisterProfileStringEx(
            $stateInfo['profile'],
            '',
            '',
            '',
            [
                [$stateInfo['values'][0], $stateInfo['values'][0], '', 0xFF0000],
                [$stateInfo['values'][1], $stateInfo['values'][1], '', 0x00FF00]
            ]
        );

        $this->SendDebug(__FUNCTION__, 'State mapping profile created for: ' . $stateInfo['profile'], 0);
        return $stateInfo['profile'];
    }

    /**
     * isCompositeKey
     *
     * Prüft ob ein Key ein Composite Key ist (enthält '__').
     * Zentrale Prüfmethode um Code-Duplikate zu vermeiden.
     *
     * @param string $key Der zu prüfende Key
     *
     * @return bool True wenn Key ein Composite Key ist, sonst False
     *
     * @see \Zigbee2MQTT\ModulBase::processVariable() Hauptnutzer
     * @see \Zigbee2MQTT\ModulBase::handleStandardVariable() Weiterer Nutzer
     * @see strpos() String Position Prüfung
     */
    private function isCompositeKey(string $key): bool
    {
        return strpos($key, '__') !== false;
    }

    /**
     * Aktualisiert eine zugehörige Preset-Variable, falls vorhanden
     *
     * @param string $ident Identifikator der Hauptvariable
     * @param mixed $value Zu setzender Wert
     * @return void
     */
    private function updatePresetVariable(string $ident, mixed $value): void
    {
        $presetIdent = $ident . '_presets';

        // Prüfe ob die Preset-Variable existiert
        if (@$this->GetIDForIdent($presetIdent) !== false) {
            // Variable existiert, also aktualisieren wir direkt ihren Wert
            $this->SetValueDirect($presetIdent, $value);
            $this->SendDebug(__FUNCTION__, "Updated $presetIdent with value: " . (\is_array($value) ? json_encode($value) : $value), 0);
        }
    }

    /**
     * checkAndEnableAction
     *
     * Zentrale Hilfsfunktion zur konsistenten EnableAction-Prüfung
     *
     * Diese Methode implementiert die einheitliche Logik für EnableAction basierend auf:
     * 1. Access-Rechte aus Feature-Array (0b010 Flag für Schreibzugriff)
     * 2. Access-Rechte aus knownVariables
     * 3. Spezielle Variablen (color_temp_kelvin)
     *
     * @param string $ident Identifikator der Variable
     * @param array|null $feature Optional: Feature-Array mit Access-Informationen
     * @param bool $forceEnable Optional: Erzwingt EnableAction (für spezielle Variablen)
     *
     * @return void
     *
     * @see \Zigbee2MQTT\ModulBase::getKnownVariables()
     * @see \IPSModule::EnableAction()
     * @see \IPSModule::SendDebug()
     */
    private function checkAndEnableAction(string $ident, ?array $feature = null, bool $forceEnable = false): void
    {
        // Spezielle Variablen oder erzwungene Aktivierung
        if ($forceEnable) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (forced/special variable)', 0);
            return;
        }

        // Prüfe Access-Rechte aus Feature-Array
        if (isset($feature['access']) && ($feature['access'] & 0b010) != 0) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (has write access from feature)', 0);
            return;
        }

        // Prüfe Access-Rechte aus knownVariables
        $knownVariables = $this->getKnownVariables();
        if (isset($knownVariables[$ident]['access']) && ($knownVariables[$ident]['access'] & 0b010) != 0) {
            $this->EnableAction($ident);
            $this->SendDebug(__FUNCTION__, 'Enabled action for ' . $ident . ' (has write access from known variables)', 0);
            return;
        }

        // Keine Berechtigung gefunden
        $this->SendDebug(__FUNCTION__, 'Skipped EnableAction for ' . $ident . ' (no write access)', 0);
    }
}
