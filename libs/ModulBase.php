<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

require_once __DIR__ . '/BufferHelper.php';
require_once __DIR__ . '/SemaphoreHelper.php';
require_once __DIR__ . '/VariableProfileHelper.php';
require_once __DIR__ . '/MQTTHelper.php';
require_once __DIR__ . '/ColorHelper.php';

/**
 * ModulBase
 *
 * Basisklasse für Geräte (Devices module.php) und Gruppen (Groups module.php)
 */
abstract class ModulBase extends \IPSModule
{
    use BufferHelper;
    use Semaphore;
    use ColorHelper;
    use VariableProfileHelper;
    use SendData;

    /** @var string $ExtensionTopic Muss überschrieben werden für den ReceiveFilter */
    protected static $ExtensionTopic = '';

    /** @var array $stateTypeMapping
     * Gehört zu RequestAction
     * Hier werden die Fälle behandelt, wo standard-Aktionen nicht funktionieren.
     * boolean zu string, wenn ausser true und false andere Werte gesendet werden.
     * numeric werden speziell formatiert, wenn ein spezielles Format gewünscht wird.
     */
    protected static $stateTypeMapping = [
        'Z2M_ChildLock'                         => ['type' => 'lockunlock', 'dataType' => VARIABLETYPE_STRING],
        'Z2M_StateWindow'                       => ['type' => 'openclose', 'dataType' => VARIABLETYPE_STRING],
        'Z2M_AutoLock'                          => ['type' => 'automode', 'dataType' => VARIABLETYPE_STRING],
        'Z2M_ValveState'                        => ['type' => 'valve', 'dataType' => VARIABLETYPE_STRING],
    ];

    /** @var array $stateMappings
     * Gehört zu RequestAction
     * Erweiterte Zustandsmappings
     * Setzt ankommende Werte auf true/false zur Nutzung als boolean in Symcon
     */
    protected static $stateMappings = [
        'onoff'      => ['ON', 'OFF'],
        'openclose'  => ['OPEN', 'CLOSE'],
        'lockunlock' => ['LOCK', 'UNLOCK'],
        'automanual' => ['AUTO', 'MANUAL'],
        'valve'      => ['OPEN', 'CLOSED'],
    ];

    /** @var array $floatUnits
     * Gehört zu registerVariableProfile
     * Erkennung Float
     * Entscheidet über Float oder Integer profile
     */
    protected static $floatUnits = [
        '°C', '°F', 'K', 'mg/L', 'µg/m³', 'g/m³', 'mV', 'V', 'kV', 'µV', 'A', 'mA', 'µA', 'W', 'kW', 'MW', 'GW',
        'Wh', 'kWh', 'MWh', 'GWh', 'Hz', 'kHz', 'MHz', 'GHz', 'lux', 'lx', 'cd', 'ppm', 'ppb', 'ppt', 'pH', 'm', 'cm',
        'mm', 'µm', 'nm', 'l', 'ml', 'dl', 'm³', 'cm³', 'mm³', 'g', 'kg', 'mg', 'µg', 'ton', 'lb', 's', 'ms', 'µs',
        'ns', 'min', 'h', 'd', 'rad', 'sr', 'Bq', 'Gy', 'Sv', 'kat', 'mol', 'mol/l', 'N', 'Pa', 'kPa', 'MPa', 'GPa',
        'bar', 'mbar', 'atm', 'torr', 'psi', 'ohm', 'kohm', 'mohm', 'S', 'mS', 'µS', 'F', 'mF', 'µF', 'nF', 'pF', 'H',
        'mH', 'µH', '%', 'dB', 'dBA', 'dBC'
    ];

    /** @var array<array{type: string, feature: string, profile: string, variableType: string}
     * Ein Array, das Standardprofile für bestimmte Gerätetypen und Eigenschaften definiert.
     *
     * Jedes Element des Arrays enthält folgende Schlüssel:
     *
     * - 'type' (string): Der Gerätetyp, z. B. 'cover' oder 'light'. Ein leerer Wert ('') bedeutet, dass der Typ nicht relevant ist.
     * - 'feature' (string): Die spezifische Eigenschaft oder das Feature des Geräts, z. B. 'position', 'temperature'.
     * - 'profile' (string): Das Symcon-Profil, das für dieses Feature verwendet wird, z. B. '~Shutter.Reversed' oder '~Battery.100'.
     * - 'variableType' (string): Der Variablentyp, der für dieses Profil verwendet wird, z. B. 'int' für Integer oder 'float' für Gleitkommazahlen.
     *
     * Beispieleintrag:
     * [
     *   'type' => 'cover',
     *   'feature' => 'position',
     *   'profile' => '~Shutter.Reversed',
     *   'variableType' => 'int'
     * ]
     */
    protected static $VariableUseStandardProfile = [
        ['type' => 'cover', 'feature' => 'position', 'profile' => '~Shutter.Reversed', 'variableType' => 'int'],
        ['type' => '', 'feature' => 'temperature', 'profile' => '~Temperature', 'variableType' => 'float'],
        ['type' => '', 'feature' => 'humidity', 'profile' => '~Humidity.F', 'variableType' => 'float'],
        ['type' => '', 'feature' => 'local_temperature', 'profile' => '~Temperature', 'variableType' => 'float'],
        ['type' => '', 'feature' => 'battery', 'profile' => '~Battery', 'variableType' => 'int'],
        ['type' => '', 'feature' => 'current', 'profile' => '~Ampere', 'variableType' => 'float'],
        ['type' => '', 'feature' => 'voltage', 'profile' => '~Volt', 'variableType' => 'float'],
        ['type' => '', 'feature' => 'energy', 'profile' => '~Electricity', 'variableType' => 'float'],
        ['type' => '', 'feature' => 'power', 'profile' => '~Watt', 'variableType' => 'float'],
        ['type' => '', 'feature' => 'battery', 'profile' => '~Battery.100', 'variableType' => 'int'],
        ['type' => '', 'feature' => 'occupancy', 'profile' => '~Presence', 'variableType' => 'bool'],
        ['type' => '', 'feature' => 'pi_heating_demand', 'profile' => '~Valve', 'variableType' => 'int'],
        ['type' => '', 'feature' => 'presence', 'profile' => '~Presence', 'variableType' => 'bool'],
        ['type' => '', 'feature' => 'illuminance_lux', 'profile' => '~Illumination', 'variableType' => 'int']
    ];

    /** @var array $stringVariablesNoResponse
     * Gehört zu RequestAction
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
     * - 'Z2M_Effect': Aktualisiert den zuletzt gesetzten Effekt.
     */
    protected static $stringVariablesNoResponse = [
        'Z2M_Effect',
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
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTBaseTopic', '');
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->TransactionData = [];
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
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
        $Filter2 = preg_quote('"Topic":"' . $BaseTopic . '/SymconExtension/response/' . static::$ExtensionTopic . $MQTTTopic);
        $this->SendDebug('Filter', '.*(' . $Filter1 . '|' . $Filter2 . ').*', 0);
        $this->SetReceiveDataFilter('.*(' . $Filter1 . '|' . $Filter2 . ').*');
        if (($this->HasActiveParent()) && (IPS_GetKernelRunlevel() == KR_READY)) {
            $this->UpdateDeviceInfo();
        }
        $this->SetStatus(IS_ACTIVE);
    }

    /**
     * Verarbeitet eine Aktion, die durch die Benutzeroberfläche oder ein Skript ausgelöst wird.
     *
     * Diese Methode wird verwendet, um verschiedene Aktionen für ein Gerät basierend auf seinem Identifikator (Ident) auszuführen.
     * Sie unterstützt spezielle Aktionen wie das Aktualisieren von Geräteinformationen, die Farbsteuerung und die Handhabung
     * von Preset- oder String-Variablen, die keine Rückmeldung von Zigbee2MQTT erhalten.
     *
     * - Aktualisiert Geräteinformationen bei 'UpdateInfo'.
     * - Handhabt Preset-Aktionen durch Umleitung zur Hauptvariable.
     * - Sendet Befehle an Zigbee2MQTT und aktualisiert String-Variablen ohne Rückmeldung.
     *
     * @param string $ident Der Identifikator der Variable, auf die die Aktion ausgeführt werden soll.
     * @param mixed $value Der Wert, der der Variable zugewiesen werden soll.
     *
     * @return void
     */
    public function RequestAction($ident, $value)
    {
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Aufgerufen für Ident: ', $ident . ' mit Wert: ' . json_encode($value), 0);

        // Sonderfall: Geräteinformationen aktualisieren
        if ($ident == 'UpdateInfo') {
            $this->UpdateDeviceInfo();
            return;
        }

        // Handhabung von Preset-Variablen
        if (strpos($ident, '_Presets') !== false) {
            $mainIdent = str_replace('_Presets', '', $ident);
            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__, "Preset action detected, redirecting to main ident: $mainIdent", 0);
            $this->SetValue($mainIdent, $value);
            $payloadKey = self::convertIdentToPayloadKey($mainIdent);
            $payload = [$payloadKey => $value];
            $this->SendSetCommand($payload);
            return;
        }

        // Prüfen, ob die Variable in der Liste der String-Variablen ohne Rückmeldung ist
        if (in_array($ident, self::$stringVariablesNoResponse)) {
            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Handling string variable without response: ' . $ident, $value, 0);

            // Sende den Befehl an das Gerät
            $payloadKey = self::convertIdentToPayloadKey($ident);
            $payload = [$payloadKey => $value];
            $this->SendSetCommand($payload);

            // Aktualisiere die String-Variable, um den gesetzten Wert anzuzeigen
            $this->SetValue($ident, $value);
            return;
        }

        // Handhabung von Color-Variablen
        switch ($ident) {
            case 'Z2M_Color':
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Color', $value, 0);
                $this->setColor($value, 'cie');
                return;
            case 'Z2M_ColorHS':
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Color HS', $value, 0);
                $this->setColor($value, 'hs');
                return;
            case 'Z2M_ColorRGB':
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Color RGB', $value, 0);
                $this->setColor($value, 'cie', 'color_rgb');
                return;
            case 'Z2M_ColorTempKelvin':
                $convertedValue = strval(intval(round(1000000 / $value, 0)));
                $payloadKey = self::convertIdentToPayloadKey($ident);
                $payload = [$payloadKey => $convertedValue];
                $this->SendSetCommand($payload);
                return;
        }

        // Wandelt den $Ident zum passenden Expose um
        $payloadKey = self::convertIdentToPayloadKey($ident);

        // Umwandlung von true/false zu "ON"/"OFF" für state
        if ($payloadKey === 'state' && is_bool($value)) {
            $value = $value ? 'ON' : 'OFF';
            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Converted boolean to state: ', $value, 0);
        }

        $payload = [$payloadKey => $value];

        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Sending payload: ', json_encode($payload), 0);
        $this->SendSetCommand($payload);
    }

    /**
     * Verarbeitet eingehende MQTT-Daten und aktualisiert die entsprechenden Variablen.
     *
     * Diese Funktion wird aufgerufen, wenn Daten über den MQTT-Kanal empfangen werden. Sie dekodiert den JSON-String,
     * identifiziert das zugehörige MQTT-Topic und verarbeitet das Payload basierend auf dem empfangenen Topic.
     * Zusätzlich werden Gerätestatus und Variablen wie "Status" und andere expose-Daten aktualisiert.
     *
     * @param string $JSONString Der JSON-String, der die empfangenen MQTT-Daten enthält.
     *
     * @return string
     */
    public function ReceiveData($JSONString)
    {
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: JSONString: ', $JSONString, 0);

        // Lese die MQTT-Base- und Topic-Konfiguration aus den Instanzeinstellungen
        $BaseTopic = $this->ReadPropertyString('MQTTBaseTopic');
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');

        // Überprüfen, ob BaseTopic oder MQTTTopic leer sind; falls ja, Abbruch
        if (empty($BaseTopic) || empty($MQTTTopic)) {
            return '';
        }

        // Dekodiere den JSON-String in ein assoziatives Array
        $Buffer = json_decode($JSONString, true);

        // Überprüfen, ob das Topic im Buffer vorhanden ist; falls nicht, Abbruch
        if (!isset($Buffer['Topic'])) {
            return '';
        }

        // MQTT-Topic und Payload verarbeiten
        $ReceiveTopic = $Buffer['Topic'];
        $Topic = substr($ReceiveTopic, strlen($BaseTopic) + 1);
        $Topics = explode('/', $Topic);

        // Debug-Ausgaben des empfangenen Topics und Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: MQTT FullTopic', $ReceiveTopic, 0);
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: MQTT first Topic', $Topics[0], 0);
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: MQTT Payload', utf8_decode($Buffer['Payload']), 0);

        // Separates Verarbeiten des Verfügbarkeitsstatus (online/offline)
        if (end($Topics) == 'availability') {
            $this->RegisterVariableBoolean('Z2M_Status', $this->Translate('Status'), 'Z2M.DeviceStatus');
            $this->SetValue('Z2M_Status', $Buffer['Payload'] == 'online');
            return '';
        }

        // Dekodiere das empfangene Payload
        $Payload = json_decode(utf8_decode($Buffer['Payload']), true);

        // Verarbeite Antworten auf bestimmte Befehle, wie "getDeviceInfo" oder "getGroupInfo"
        if ($Topic == 'SymconExtension/response/getDeviceInfo/' . $MQTTTopic) {
            if (isset($Payload['transaction'])) {
                $this->UpdateTransaction($Payload);
            }
            return '';
        }
        if ($Topic == 'SymconExtension/response/getGroupInfo/' . $MQTTTopic) {
            if (isset($Payload['transaction'])) {
                $this->UpdateTransaction($Payload);
            }
            return '';
        }

        // Verarbeite expose-Daten, wenn vorhanden
        if (isset($Payload['exposes'])) {
            $this->mapExposesToVariables($Payload['exposes']);
        }

        // Füge den Variablen Typinformationen hinzu und dekodiere die Daten
        $PayloadWithTypes = $this->AppendVariableTypes($Payload);
        if (is_array($PayloadWithTypes)) {
            $this->DecodeData($PayloadWithTypes);
        }
        return '';
    }

    /**
     * Erweitert die Farbumwandlungs- und Steuerungsfunktion, um zusätzliche Farbmodi und Parameter zu unterstützen.
     *
     * Diese Methode ermöglicht das Setzen der Farbe eines Geräts über verschiedene Farbmodi, darunter:
     * - 'cie' (XY-Farbraum): Konvertiert Farben aus Hex- oder RGB-Formaten in den XY-Farbraum.
     * - 'hs' (Hue-Saturation): Konvertiert Farben auf Basis von Farbton (Hue) und Sättigung (Saturation) in RGB.
     * - 'hsv' (Hue-Saturation-Value): Konvertiert Farben auf Basis von Farbton, Sättigung und Helligkeit (Value) in RGB.
     *
     * Zusätzlich können über das `$params`-Array weitere Payload-Parameter hinzugefügt werden.
     * Je nach verwendetem Zigbee2MQTT-Modus kann die Farbe als `color` (XY) oder `color_rgb` im Payload gesendet werden.
     *
     * **Unterstützte Modi**:
     * - `cie`: Verwendet den XY-Farbraum und konvertiert Farben aus Hexadezimal oder RGB.
     * - `hs`: Verwendet Farbton (Hue) und Sättigung (Saturation), konvertiert zu RGB und dann zu XY.
     * - `hsv`: Verwendet Farbton (Hue), Sättigung (Saturation) und Helligkeit (Value), konvertiert zu RGB und dann zu XY.
     *
     * @param mixed  $color  Die Farbe, entweder im Hexadezimal-Format (z.B. '#FF0000') oder als Array für RGB/HSB/HSV.
     *                       - Für 'cie' kann die Farbe als Hex- oder RGB-Array übergeben werden.
     *                       - Für 'hs' und 'hsv' wird die Farbe als Array erwartet, z.B. ['hue' => 360, 'saturation' => 100].
     * @param string $mode   Der Farbmodus, der verwendet werden soll. Unterstützte Modi sind:
     *                       - 'cie': Hex oder RGB wird in den XY-Farbraum konvertiert.
     *                       - 'hs': Konvertiert Hue und Saturation in RGB, dann in XY.
     *                       - 'hsv': Konvertiert Hue, Saturation und Value in RGB, dann in XY.
     * @param array  $params Zusätzliche Parameter, die dem Payload hinzugefügt werden sollen (z.B. 'brightness', 'transition').
     * @param string $Z2MMode Der Zigbee2MQTT-Modus, der verwendet werden soll. Standardmäßig 'color' (XY-Farbraum), kann auch 'color_rgb' sein,
     *                        abhängig davon, wie das Gerät Farben verarbeitet.
     *                        - 'color': Farbe wird im XY-Farbraum gesendet.
     *                        - 'color_rgb': Farbe wird im RGB-Format gesendet.
     *
     * @return void
     */
    public function setColorExt(mixed $color, string $mode, array $params = [], string $Z2MMode = 'color')
    {
        switch ($mode) {
            case 'cie':
                // Prüft, ob die Farbe im Hex-Format übergeben wurde und konvertiert sie zu RGB
                if (preg_match('/^#[a-f0-9]{6}$/i', strval($color))) {
                    $color = ltrim($color, '#');
                    $color = hexdec($color);
                }
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToXy($RGB);
                $Payload = $Z2MMode == 'color' ? ['color' => $cie, 'brightness' => $cie['bri']] : ['color_rgb' => $cie];
                break;

            case 'hs':
                // Konvertiere den Farbwert in RGB mit Hue/Saturation
                if (is_array($color)) {
                    $RGB = $this->HSToRGB($color['hue'], $color['saturation']);
                } else {
                    $RGB = $this->HexToRGB($color);
                }
                $cie = $this->RGBToXy($RGB);
                $Payload = ['color' => $cie];
                break;

            case 'hsv':
                // Verwende die HSVToRGB Konvertierung
                $RGB = $this->HSVToRGB($color['hue'], $color['saturation'], $color['value']);
                $cie = $this->RGBToXy($RGB);
                $Payload = ['color' => $cie];
                break;

            default:
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColorExt', 'Invalid mode ' . $mode, 0);
                return;
        }

        // Zusätzliche Parameter zum Payload hinzufügen
        foreach ($params as $key => $value) {
            $Payload[$key] = $value;
        }

        // Payload senden
        $this->SendSetCommand($Payload);
    }

    /**
     * Sendet einen Set-Befehl an das MQTT-Gerät.
     *
     * Diese Methode generiert das entsprechende Topic basierend auf den Instanzeinstellungen
     * und sendet die Payload-Daten an das MQTT-Gerät.
     *
     * @param array $Payload Die Daten, die an das Gerät gesendet werden sollen. Diese enthalten die Einstellungen oder Befehle für das Gerät.
     *
     * @return void
     */
    public function SendSetCommand(array $Payload)
    {
        // MQTT-Topic für den Set-Befehl generieren
        $Topic = '/' . $this->ReadPropertyString('MQTTTopic') . '/set';

        // Debug-Ausgabe des zu sendenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Payload to be sent: ', json_encode($Payload), 0);

        // Sende die Daten an das Gerät
        $this->SendData($Topic, $Payload, 0);
    }

    /**
     * Muss überschrieben werden
     * Fragt Exposes ab und verarbeitet die Antwort.
     *
     * @return bool
     */
    abstract protected function UpdateDeviceInfo(): bool;

    /**
     * Fügt den übergebenen Payload-Daten die entsprechenden Variablentypen hinzu.
     * Diese Methode durchläuft die übergebenen Payload-Daten, prüft, ob die zugehörige
     * Variable existiert, und fügt den Variablentyp als neuen Schlüssel-Wert-Paar hinzu.
     *
     * Beispiel:
     * Wenn der Key 'temperature' vorhanden ist und die zugehörige Variable existiert, wird
     * ein neuer Eintrag 'temperature_type' hinzugefügt, der den Typ der Variable enthält.
     *
     * @param array $Payload Assoziatives Array mit den Payload-Daten.
     *
     * @return array Das modifizierte Payload-Array mit den hinzugefügten Variablentypen.
     */
    protected function AppendVariableTypes($Payload)
    {
        // Zeige das eingehende Payload im Debug
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Incoming Payload: ', json_encode($Payload), 0);

        foreach ($Payload as $key => $value) {
            // Konvertiere den Key in einen Variablen-Ident
            $ident = self::convertPropertyToIdent($key);

            // Prüfe, ob die Variable existiert
            $objectID = @$this->GetIDForIdent($ident);
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Variable exists for Ident: ', $ident, 0);
            if ($objectID) {
                // Hole den Typ der existierenden Variablen
                $variableType = IPS_GetVariable($objectID)['VariableType'];

                // Füge dem Payload den Variablentyp als neuen Schlüssel hinzu
                $Payload[$key . '_type'] = $variableType;
            }
        }

        // Zeige das modifizierte Payload im Debug
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Modified Payload with Types: ', json_encode($Payload), 0);

        // Gib das modifizierte Payload zurück
        return $Payload;
    }

    /**
     * Verarbeitet eingehende Payload-Daten und registriert Variablen für unbekannte Datenfelder.
     *
     * Diese Methode dekodiert die übergebenen Daten (Payload) und verarbeitet sie, indem sie Variablen
     * dynamisch registriert und deren Werte setzt. Die Funktion überprüft, ob Variablen existieren,
     * ob die Daten bereits zugewiesen wurden und ob Preset-Profile erforderlich sind. Es wird geprüft,
     * ob ein Mapping für den Zustand existiert, und der Wert wird entsprechend umgewandelt.
     * Variablen, deren Werte sich nicht geändert haben, werden übersprungen.
     *
     * **Funktionsweise:**
     * 1. Iteriert durch den Payload, um die Daten zu verarbeiten.
     * 2. Überspringt spezielle Typ-Informationen (wie '_type').
     * 3. Konvertiert den Key (Property-Name) in einen Ident-Namen.
     * 4. Wenn die Variable noch nicht existiert, wird sie registriert (Boolean, Integer, Float, String).
     * 5. Falls Preset-Daten vorhanden sind, wird geprüft, ob ein Preset-Profil existiert oder erstellt werden muss.
     * 6. Besondere Behandlung für spezielle Felder wie `last_seen`.
     * 7. Vergleicht den aktuellen Wert der Variable mit dem neuen Wert. Falls der Wert gleich ist, wird der Vorgang übersprungen.
     * 8. Falls eine Typ-Information verfügbar ist, wird der Wert basierend auf dem Variablentyp verarbeitet.
     * 9. Wenn ein State-Mapping vorhanden ist, wird der Wert entsprechend umgewandelt und gesetzt.
     * 10. Wenn keine Typ-Informationen vorliegen, wird der Wert direkt gesetzt.
     *
     * **Spezielle Fälle:**
     * - `last_seen`: Wird von Millisekunden in Sekunden umgerechnet.
     * - Preset-Profile: Wenn sie im Payload definiert sind, wird ein zugehöriges Preset-Profil und eine Variable erstellt.
     *
     * @param array $Payload Das eingehende Payload-Datenarray, das verarbeitet werden soll.
     *                       Das Array enthält Key-Value-Paare, wobei der Key den Namen der Variablen repräsentiert,
     *                       und der Value den Wert der Variable. Optional kann das Payload auch Preset-Informationen enthalten.
     *
     * @return void
     */
    protected function DecodeData($Payload)
    {
        // Debug-Ausgabe des eingehenden Payloads
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: json incoming: ', json_encode($Payload), 0);

        // Schleife durch die Payload-Daten
        foreach ($Payload as $key => $value) {
            // Überspringe Typ-Informationen (z.B. '_type')
            if (strpos($key, '_type') !== false) {
                continue;
            }

            // Erstelle den Ident-Namen basierend auf dem Property-Namen
            $ident = self::convertPropertyToIdent($key);
            $variableTypeKey = $key . '_type';

            // Prüfe, ob die Variable existiert, falls nicht, registriere sie
            if (!@$this->GetIDForIdent($ident)) {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: DecodeData', 'Variable not found, registering: ' . $key, 0);

                // Überprüfen, ob der Payload 'exposes' enthält und Variablen registrieren
                if (isset($Payload['exposes'])) {
                    $this->mapExposesToVariables($Payload['exposes']);
                } else {
                    // Variable basierend auf dem Wertetyp registrieren
                    if (is_bool($value)) {
                        $this->RegisterVariableBoolean($ident, $this->Translate(ucfirst($key)), '~Switch');
                    } elseif (is_int($value)) {
                        if ($key === 'last_seen') {
                            $this->RegisterVariableInteger($ident, $this->Translate('Last Seen'), '~UnixTimestamp');
                        } else {
                            $this->RegisterVariableInteger($ident, $this->Translate(ucfirst($key)));
                        }
                    } elseif (is_float($value)) {
                        $this->RegisterVariableFloat($ident, $this->Translate(ucfirst($key)));
                    } else {
                        $this->RegisterVariableString($ident, $this->Translate(ucfirst($key)));
                    }
                }

                // Überprüfe, ob die Variable erfolgreich registriert wurde
                if (!@$this->GetIDForIdent($ident)) {
                    $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: DecodeData', 'Error: Variable could not be registered: ' . $key, 0);
                    continue;
                }
            }

            // Prüfe, ob Preset-Profile erstellt werden müssen
            if (isset($Payload[$key]['presets'])) {
                $presetIdent = $ident . '_Presets';
                if (!@$this->GetIDForIdent($presetIdent)) {
                    $this->registerPresetProfile($Payload[$key]['presets'], $key);
                }
            }

            // Spezieller Fall: last_seen muss von Millisekunden auf Sekunden umgerechnet werden
            if ($key === 'last_seen') {
                $value = intval($value / 1000);
            }

            // Verarbeite den Wert basierend auf dem Typ
            if (isset($Payload[$variableTypeKey])) {
                $variableType = $Payload[$variableTypeKey];

                // Prüfe, ob ein State-Mapping existiert und wende es an
                if (array_key_exists($key, self::$stateMappings)) {
                    $mappedValue = self::convertStateBasedOnMapping($key, $value, $variableType);
                    $this->handleStateChange($key, $ident, ucfirst(str_replace('_', ' ', $key)), $Payload, self::$stateMappings[$key]);
                } else {
                    // Verarbeite den Wert basierend auf dem Typ
                    switch ($variableType) {
                        case 0: // Boolean
                            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: processed', 'Boolean processed: ' . $key, 0);
                            $mappedValue = self::convertStateBasedOnMapping($key, $value, $variableType);
                            $this->SetValue($ident, $mappedValue);
                            break;
                        case 1: // Integer
                            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: processed', 'Integer processed: ' . $key, 0);
                            $this->SetValue($ident, $value);
                            break;
                        case 2: // Float
                            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: processed', 'Float processed: ' . $key, 0);
                            $this->SetValue($ident, $value);
                            break;
                        case 3: // String
                            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: processed', 'String processed: ' . $key, 0);
                            $this->SetValue($ident, is_array($value) ? json_encode($value) : $value);
                            break;
                        default:
                            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: processed', 'Unknown Variable Type: ' . $variableType, 0);
                            break;
                    }
                }
            } else {
                // Fallback: Wert ohne Typinformationen setzen
                $this->SetValue($ident, $value);
            }
        }
    }

    /**
     * Setzt den Wert einer existierenden Variable, passt den Wert entsprechend dem Variablentyp an.
     *
     * Diese Methode überprüft den Datentyp der vorhandenen Variable und setzt den übergebenen Wert entsprechend an.
     * Sie unterstützt sowohl einfache Datentypen (Boolean, Integer, Float, String) als auch komplexe Typen wie Farbvariablen
     * (z.B. 'Z2M_Color') und Preset-Variablen.
     *
     * **Funktionsweise**:
     * - Wenn die zu setzende Variable eine Farbvariable ist (z.B. 'Z2M_Color'), wird der Wert als Array erwartet und in den
     *   entsprechenden Farbwert (z.B. HEX oder HSV) umgewandelt.
     * - Für Preset-Variablen wird der Wert in der Hauptvariable und der zugehörigen Preset-Variable gesetzt.
     * - Der Typ der Variablen wird überprüft, und der Wert wird entsprechend angepasst (Boolean, Integer, Float oder String).
     * - Falls die Variable ein Profil mit Assoziationen besitzt, wird der Wert entsprechend dem Profil angepasst.
     *
     * **Besondere Behandlung**:
     * - Farbvariablen: Unterstützt XY-, HEX- und HSV-Farben, die in entsprechende RGB- oder HEX-Werte umgerechnet werden.
     * - Preset-Variablen: Setzt den Wert sowohl in der Hauptvariable als auch in der Preset-Variable (z.B. 'Z2M_Color_Presets').
     *
     * **Typkonvertierung**:
     * - Boolean: Der übergebene Wert wird in einen Boolean konvertiert.
     * - Integer: Der Wert wird in einen Integer konvertiert.
     * - Float: Der Wert wird in einen Float konvertiert.
     * - String: Der Wert wird in einen String konvertiert.
     *
     * @param string $Ident Der Identifikator der zu setzenden Variable.
     *                      - Kann entweder eine Hauptvariable oder eine Preset-Variable sein.
     * @param mixed $Value Der zu setzende Wert. Je nach Variablentyp kann der Wert ein Boolean, Integer, Float oder String sein.
     *                     - Für Farbvariablen wird ein Array mit den Farbinformationen (z.B. 'x' und 'y' für XY-Werte,
     *                       oder 'hue', 'saturation', 'value' für HSV) erwartet.
     *
     * @return void Gibt keine Rückgabe, setzt jedoch den Wert der angegebenen Variable.
     *
     * @throws Exception Wenn die Variable nicht existiert, wird eine Debug-Nachricht ausgegeben.
     */
    protected function SetValue($Ident, $Value)
    {
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Incoming value for ' . $Ident, (is_array($Value) ? json_encode($Value) : $Value), 0);

        // Standardwert für adjustedValue setzen
        $adjustedValue = $Value;

        // Überprüfen, ob die Variable existiert
        if (!@$this->GetIDForIdent($Ident)) {
            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: No variable found for', $Ident, 0);
            return;
        }

        // Debug-Ausgabe: Verarbeitet die Variable
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Handling variable: ' . $Ident, '', 0);

        // Farbvariablen und Preset-Variablen spezifische Behandlung
        if (strpos($Ident, '_Presets') !== false || $Ident === 'Z2M_ColorTemp' || $Ident === 'Z2M_ColorTempStartup' || $Ident === 'Z2M_Color') {
            // Spezielle Behandlung für Farbvariablen (z.B. Z2M_Color)
            if ($Ident === 'Z2M_Color') {
                // Verarbeite den Farbwert, der als Array übergeben wird
                if (is_array($Value)) {
                    if (isset($Value['x']) && isset($Value['y'])) {
                        // Konvertiere XY-Farbwerte in einen HEX-Farbwert
                        $rgb = $this->xyToHEX($Value['x'], $Value['y'], 255);
                        $adjustedValue = hexdec(str_replace('#', '', $rgb)); // Hex in Integer umwandeln
                    } elseif (isset($Value['hue']) && isset($Value['saturation']) && isset($Value['value'])) {
                        // Konvertiere HSV-Werte in einen RGB-Farbwert
                        $rgb = $this->HSVToRGB($Value['hue'], $Value['saturation'], $Value['value']);
                        $adjustedValue = hexdec(str_replace('#', '', $rgb)); // RGB in Integer umwandeln
                    }
                } else {
                    $adjustedValue = (int) $Value; // Fallback auf Integer, falls kein Array übergeben wird
                }
            }

            // Überprüfen, ob die Variable ein Profil hat
            $profileName = IPS_GetVariable($this->GetIDForIdent($Ident))['VariableProfile'];

            if ($profileName) {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Using profile for ' . $Ident, $profileName, 0);

                // Profilinformationen abrufen und Assoziationen verarbeiten
                $profileData = IPS_GetVariableProfile($profileName);

                if ($profileData && isset($profileData['Associations'])) {
                    $associations = $profileData['Associations'];

                    // Wert anhand der Profilassoziationen anpassen
                    foreach ($associations as $association) {
                        if ($association['Value'] == $Value) {
                            $adjustedValue = $association['Value'];
                            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Adjusted value from profile: ', $adjustedValue, 0);
                            break;
                        }
                    }
                } else {
                    $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: No associations found for profile: ', $profileName, 0);
                }
            } else {
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: No profile found for variable: ', $Ident, 0);
            }

            // Preset-Variable Verarbeitung
            if (strpos($Ident, '_Presets') !== false) {
                // Setze den Wert für die Hauptvariable (ohne '_Presets')
                $mainIdent = str_replace('_Presets', '', $Ident);
                if (@$this->GetIDForIdent($mainIdent)) {
                    $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Setting main variable value: ', $adjustedValue, 0);
                    parent::SetValue($mainIdent, $adjustedValue); // Hauptvariable setzen
                }
            } else {
                // Setze den Wert für die Preset-Variable (füge '_Presets' an den Ident)
                $presetIdent = $Ident . '_Presets';
                if (@$this->GetIDForIdent($presetIdent)) {
                    $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Setting preset variable value: ', $adjustedValue, 0);
                    parent::SetValue($presetIdent, $adjustedValue); // Preset-Variable setzen
                } else {
                    $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Preset variable does not exist: ', $presetIdent, 0);
                }
            }
        } else {
            // Typ der vorhandenen Variable abrufen
            $varType = IPS_GetVariable($this->GetIDForIdent($Ident))['VariableType'];
            // Standardverarbeitung für andere Variablentypen
            switch ($varType) {
                case VARIABLETYPE_BOOLEAN:
                    $adjustedValue = (bool) $Value;
                    break;
                case VARIABLETYPE_INTEGER:
                    $adjustedValue = (int) $Value;
                    break;
                case VARIABLETYPE_FLOAT:
                    $adjustedValue = (float) $Value;
                    break;
                case VARIABLETYPE_STRING:
                    $adjustedValue = (string) $Value;
                    break;
                default:
                    $adjustedValue = $Value; // Fallback, falls der Typ unbekannt ist
                    break;
            }
        }

        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: Adjusted value for ' . $Ident, (is_array($adjustedValue) ? json_encode($adjustedValue) : $adjustedValue), 0);

        // Setze den Wert in die Variable
        parent::SetValue($Ident, $adjustedValue);
    }

    /**
     * Mappt die übergebenen Exposes auf Variablen und registriert diese.
     * Diese Funktion verarbeitet die übergebenen Exposes (z.B. Sensoreigenschaften) und registriert sie als Variablen.
     * Wenn ein Expose mehrere Features enthält, werden diese ebenfalls einzeln registriert.
     *
     * @param array $exposes Ein Array von Exposes, das die Geräteeigenschaften oder Sensoren beschreibt.
     *
     * @return void
     */
    protected function mapExposesToVariables(array $exposes)
    {
        // Debug-Ausgabe für die übergebenen Exposes
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: All Exposes', json_encode($exposes), 0);

        // Durchlaufen aller Exposes
        foreach ($exposes as $expose) {
            // Prüfen, ob das Expose mehrere Features enthält
            if (isset($expose['features'])) {
                // Jedes Feature einzeln registrieren und den übergeordneten Expose-Typ mitgeben
                foreach ($expose['features'] as $feature) {
                    $this->registerVariable($feature, $expose['type']); // Expose-Typ mitgeben
                }
            } else {
                // Einzelnes Expose registrieren
                $this->registerVariable($expose, $expose['type']); // Expose-Typ mitgeben
            }
        }
    }


    /**
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
     * @return void
     *
     * @throws InvalidArgumentException Wenn der Modus ungültig ist.
     *
     * @example
     * // Setze eine Farbe im HSL-Modus.
     * $this->setColor(0xFF5733, 'hsl', 'color');
     *
     * // Setze eine Farbe im HSV-Modus.
     * $this->setColor(0x4287f5, 'hsv', 'color');
     */
    private function setColor(int $color, string $mode, string $Z2MMode = 'color')
    {
        switch ($mode) {
            case 'cie':
                // Nutze die HexToRGB- und RGBToXy-Funktion aus der ColorHelper-Datei
                $RGB = $this->HexToRGB($color);
                $cie = $this->RGBToXy($RGB);

                // Füge die Farbe dem Payload hinzu
                if ($Z2MMode === 'color') {
                    $Payload['color'] = $cie;
                    $Payload['brightness'] = $cie['bri'];
                } elseif ($Z2MMode === 'color_rgb') {
                    $Payload['color_rgb'] = $cie;
                } else {
                    return;
                }

                $this->SendSetCommand($Payload);
                break;

            case 'hs':
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->HexToRGB($color);
                $HSB = $this->RGBToHSB($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSB Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    $Payload = [
                        'color' => [
                            'hue'        => $HSB['hue'],
                            'saturation' => $HSB['saturation'],
                        ],
                        'brightness' => $HSB['brightness']
                    ];
                } else {
                    return;
                }

                $this->SendSetCommand($Payload);
                break;

            case 'hsl':
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->HexToRGB($color);
                $HSL = $this->RGBToHSL($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSL Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    $Payload = [
                        'color' => [
                            'hue'        => $HSL['hue'],
                            'saturation' => $HSL['saturation'],
                            'lightness'  => $HSL['lightness']
                        ]
                    ];
                } else {
                    return;
                }

                $this->SendSetCommand($Payload);
                break;

            case 'hsv':
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - Input Color', json_encode($color), 0);

                $RGB = $this->HexToRGB($color);
                $HSV = $this->RGBToHSV($RGB[0], $RGB[1], $RGB[2]);

                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor - RGB Values for HSV Conversion', 'R: ' . $RGB[0] . ', G: ' . $RGB[1] . ', B: ' . $RGB[2], 0);

                if ($Z2MMode == 'color') {
                    $Payload = [
                        'color' => [
                            'hue'        => $HSV['hue'],
                            'saturation' => $HSV['saturation'],
                            'value'      => $HSV['value']
                        ]
                    ];
                } else {
                    return;
                }

                $this->SendSetCommand($Payload);
                break;

            default:
                $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: setColor', 'Invalid Mode ' . $mode, 0);
                throw new InvalidArgumentException('Invalid color mode: ' . $mode);
        }
    }

    /**
     * Konvertiert einen Eigenschaftsnamen (Property) in einen Identifikator (Ident).
     * Wandelt das Property von snake_case in CamelCase um und fügt das Präfix "Z2M_" hinzu.
     *
     * @param string $property Der Eigenschaftsname (Property) im snake_case Format.
     *
     * @return string Der erzeugte Identifikator im CamelCase Format mit "Z2M_" Präfix.
     */
    private static function convertPropertyToIdent($property)
    {
        $ident = 'Z2M_';
        $words = explode('_', strtolower($property)); // Zerlegt den String in einzelne Wörter
        $camelCased = array_map('ucfirst', $words);   // Wandelt die Wörter in CamelCase um
        return $ident . implode('', $camelCased);     // Fügt die Wörter zusammen und gibt den Identifikator zurück
    }

    /**
     * Konvertiert einen Identifikator (Ident) in einen Payload-Key.
     * Entfernt das Präfix "Z2M_" und wandelt CamelCase in snake_case um.
     *
     * @param string $ident Der Identifikator im CamelCase Format.
     *
     * @return string Der erzeugte Payload-Key im snake_case Format.
     */
    private static function convertIdentToPayloadKey($ident)
    {
        $identWithoutPrefix = str_replace('Z2M_', '', $ident); // Entfernt das Präfix "Z2M_"
        if (preg_match('/state(?=[a-zA-Z])/i', $identWithoutPrefix)) {
            $identWithoutPrefix = preg_replace('/state(?=[a-zA-Z])/i', 'state', $identWithoutPrefix); // Bearbeitet spezifische "state" Keys
        }
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $identWithoutPrefix)); // Konvertiert CamelCase in snake_case
    }

    /**
     * Wandelt einen Zustand (State) basierend auf den Mappings um.
     * Überprüft, ob es für den angegebenen Key ein spezielles State-Mapping gibt und wandelt den Wert entsprechend um.
     *
     * @param string $key Der Key, für den der Zustand überprüft wird.
     * @param mixed $value Der zu konvertierende Wert.
     * @param int $variableType Der Typ der Variable, um den Wert korrekt zu konvertieren.
     *
     * @return mixed Der konvertierte Wert basierend auf dem State-Mapping.
     */
    private static function convertStateBasedOnMapping($key, $value, $variableType)
    {
        // Wenn der Key in den State-Mappings existiert, nutze das spezifische Mapping
        if (array_key_exists($key, self::$stateTypeMapping)) {
            $mapping = self::$stateTypeMapping[$key]; // Holt das Mapping für den Key
            // Hol das spezifische State-Mapping, falls es existiert, andernfalls nutze 'ON'/'OFF'
            $stateMapping = $mapping['type'] ? self::$stateMappings[$mapping['type']] : ['ON', 'OFF'];
            // Wandelt den Wert basierend auf dem Mapping in true/false um
            $convertedValue = in_array(strtoupper($value), $stateMapping) ? ($value === $stateMapping[0]) : false;
            return self::convertByVariableType($convertedValue, $mapping['dataType']); // Konvertiert den Wert basierend auf dem Datentyp
        }
        // Fallback: true/false immer in ON/OFF umwandeln, falls kein spezifisches Mapping existiert
        return $value === 'ON' ? true : ($value === 'OFF' ? false : $value);
    }

    /**
     * Konvertiert einen Wert basierend auf dem angegebenen Variablentyp (dataType).
     * Diese Funktion prüft, welcher Datentyp benötigt wird (z.B. String, Float, Integer, Boolean)
     * und konvertiert den übergebenen Wert entsprechend.
     *
     * @param mixed $value Der zu konvertierende Wert.
     * @param int $dataType Der Ziel-Datentyp (VARIABLETYPE_STRING, VARIABLETYPE_FLOAT, VARIABLETYPE_INTEGER, VARIABLETYPE_BOOLEAN).
     *
     * @return mixed Der konvertierte Wert entsprechend dem angegebenen Datentyp.
     */
    private function convertByVariableType($value, $dataType)
    {
        // Debug-Ausgabe vor der Rückgabe
        switch ($dataType) {
            case VARIABLETYPE_STRING:
                $convertedValue = (string) $value;
                break;
            case VARIABLETYPE_FLOAT:
                $convertedValue = (float) $value;
                break;
            case VARIABLETYPE_INTEGER:
                $convertedValue = (int) $value;
                break;
            case VARIABLETYPE_BOOLEAN:
                $convertedValue = (bool) $value;
                break;
            default:
                $convertedValue = (string) $value; // Fallback zu String, wenn kein gültiger Datentyp übergeben wird
                break;
        }

        // Debug-Ausgabe des konvertierten Wertes und des Datentyps
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Converted value: ', json_encode($convertedValue) . ' of type: ' . $dataType, 0);

        return $convertedValue; // Rückgabe des konvertierten Wertes
    }

    /**
     * Handhabt die Zustandsänderung einer Variablen basierend auf dem bereitgestellten Payload und dem State-Mapping.
     * Diese Methode überprüft, ob der Zustand im Payload vorhanden ist, und wendet das entsprechende Mapping (z.B. 'ON' => true, 'OFF' => false) an,
     * um den neuen Zustand der Variablen zu setzen.
     *
     * @param string $payloadKey Der Schlüssel im Payload, der die Zustandsinformation enthält.
     * @param string $ident Die Identifikation der Variable, deren Wert gesetzt werden soll.
     * @param string $debugTitle Ein Titel, der in den Debug-Ausgaben verwendet wird.
     * @param array $Payload Das empfangene Payload-Array, das den aktuellen Zustand enthält.
     * @param array|null $stateMapping (Optional) Ein spezifisches State-Mapping, das verwendet werden soll (z.B. ['ON' => true, 'OFF' => false]).
     *
     * @return void
     */
    private function handleStateChange($payloadKey, $ident, $debugTitle, $Payload, $stateMapping = null)
    {
        // Prüfen, ob der Zustandsschlüssel im Payload vorhanden ist
        if (array_key_exists($payloadKey, $Payload)) {
            $state = $Payload[$payloadKey];

            // Standard-Mapping verwenden, falls kein spezifisches Mapping übergeben wurde
            $stateMapping = $stateMapping ?? ['ON' => true, 'OFF' => false];

            // Überprüfen, ob der Zustand im Mapping existiert
            if (array_key_exists($state, $stateMapping)) {
                // Den neuen Zustand der Variablen setzen
                $this->SetValue($ident, $stateMapping[$state]);
            } else {
                // Debug-Ausgabe, wenn der Zustand undefiniert ist
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: ' . $debugTitle, 'Undefined State: ' . $state, 0);
            }
        }
    }

    /**
     * Registriert eine Variable basierend auf den Eigenschaften des übergebenen Features.
     *
     * Diese Funktion verarbeitet ein einzelnes Feature und erstellt eine Variable in Symcon basierend auf dessen Typ.
     * Es unterstützt die Registrierung von Boolean-, Numeric-, Enum-, String-, und Composite-Variablen.
     * Zusätzlich werden Standardprofile aus einer vordefinierten Liste angewendet, und es wird überprüft, ob erweiterte
     * Profile existieren (z. B. `.100`).
     *
     * Unterstützte Typen:
     * - binary: Boolean-Variablen mit einem zugehörigen Profil.
     * - numeric: Integer- oder Float-Variablen mit optionalen Presets und Min/Max-Werten.
     * - enum: Zeichenketten-Variablen mit vordefinierten Enum-Werten.
     * - string/text: Zeichenketten-Variablen.
     * - composite: Spezielle Farbvariablen wie `color_xy`, `color_hs`, und `color_rgb`.
     *
     * Falls das Feature Presets enthält, wird eine zusätzliche Preset-Variable mit einem zugehörigen Profil erstellt.
     *
     * @param array $feature Das Feature-Array, das Informationen über den Typ, die Einheit, Min/Max-Werte, Presets und andere
     *                       Eigenschaften enthält, die zur Registrierung der Variable verwendet werden.
     *
     * @return void
     */
    private function registerVariable($feature, $exposeType = null)
    {
        // Setze den Typ auf den übergebenen Expose-Typ, falls vorhanden
        if ($exposeType !== null) {
            $feature['type'] = $exposeType;  // Den Typ direkt in das Feature übernehmen
        }
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Registering Feature', json_encode($feature), 0);
        $type = $feature['type'];
        $property = $feature['property'] ?? '';
        $ident = $this->convertPropertyToIdent($property);
        $label = ucwords(str_replace('_', ' ', $feature['property'] ?? $property));
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Using Expose Type: ', $type, 0);

        // Überprüfung auf Standardprofil und zugehörigen Variablentyp
        $standardProfile = $this->getStandardProfile($type, $property);
        $variableType = $this->getVariableTypeFromProfile($type, $property);

        // Debug-Ausgabe zur Prüfung, ob Standardprofil und Variablentyp gefunden wurden
        $this->SendDebug(__FUNCTION__ . ' :: Standard Profile: ', $standardProfile ?? 'none', 0);
        $this->SendDebug(__FUNCTION__ . ' :: Variable Type: ', $variableType ?? 'none', 0);

        // Wenn ein Standardprofil angegeben ist, prüfe auf Erweiterung
        if ($standardProfile !== null) {
            $extendedProfile = $this->checkForExtendedProfile($standardProfile);

            // Wenn ein erweitertes Profil gefunden wurde, nutze es
            if ($extendedProfile !== null) {
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Using extended profile: ', $extendedProfile, 0);
                $standardProfile = $extendedProfile;
            }

            // Registriere die Variable basierend auf dem Typ aus dem Profil
            if ($variableType === 'int') {
                $this->RegisterVariableInteger($ident, $this->Translate($label), $standardProfile);
            } elseif ($variableType === 'float') {
                $this->RegisterVariableFloat($ident, $this->Translate($label), $standardProfile);
            } elseif ($variableType === 'bool') {
                $this->RegisterVariableBoolean($ident, $this->Translate($label), $standardProfile); // Boolean-Profil registrieren
            } else {
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Unsupported variable type: ' . $variableType, '', 0);
            }
            return;
        }

        // Prüfen, ob Presets vorhanden sind
        if (isset($feature['presets']) && is_array($feature['presets'])) {
            // Hole den vollständigen Namen des Hauptprofils inklusive Min/Max-Werten
            $fullRangeProfileName = $this->getFullRangeProfileName($feature);
            $presetProfileName = $fullRangeProfileName . '_Presets';

            // Debug-Ausgabe des Preset-Profilnamens
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating Preset Profile', $presetProfileName, 0);

            // Preset-Profil direkt erstellen
            $this->RegisterProfileInteger($presetProfileName, '', '', '', 0, 0, 0);
            foreach ($feature['presets'] as $preset) {
                $presetValue = $preset['value'];
                $presetName = $this->Translate(ucwords(str_replace('_', ' ', $preset['name'])));
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Adding preset: ', $presetName . ' with value ' . $presetValue, 0);
                IPS_SetVariableProfileAssociation($presetProfileName, $presetValue, $presetName, '', 0xFFFFFF);
            }

            // Preset-Variable registrieren
            $this->RegisterVariableInteger($ident . '_Presets', $this->Translate($label . ' Presets'), $presetProfileName);
            $this->EnableAction($ident . '_Presets');
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Preset profile and variable created for ' . $label, $presetProfileName, 0);
        }

        // Überprüfen, ob ein Mapping vorhanden ist und den Datentyp ggf. anpassen
        if (array_key_exists($ident, self::$stateTypeMapping)) {
            $mappedType = self::$stateTypeMapping[$ident]['dataType'];
            switch ($mappedType) {
                case VARIABLETYPE_STRING:
                    $type = 'string';
                    break;
                case VARIABLETYPE_BOOLEAN:
                    $type = 'binary';
                    break;
                case VARIABLETYPE_INTEGER:
                    $type = 'integer';
                    break;
                case VARIABLETYPE_FLOAT:
                    $type = 'numeric';
                    break;
                default:
                    $type = $type;
                    break;
            }
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Type mapped from stateTypeMapping', $type, 0);
        }

        $isFloat = in_array($feature['unit'] ?? '', self::$floatUnits);

        // Überprüfen, ob die Variable bereits existiert, bevor sie neu angelegt wird
        $objectID = @$this->GetIDForIdent($ident);
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Object ID: ', (string) $objectID, 0);
        if ($objectID) {
            return;
        }

        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Registering new variable: ', $ident, 0);

        // Registriere die Hauptvariable basierend auf ihrem Typ
        switch ($type) {
            case 'binary':
                $this->RegisterVariableBoolean($ident, $this->Translate($label), '~Switch');
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating binary', $ident, 0);
                break;
            case 'numeric':
                $profiles = $this->registerNumericProfile($feature, $isFloat);
                if ($isFloat) {
                    $this->RegisterVariableFloat($ident, $this->Translate($label), $profiles['mainProfile']);
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating float', $ident, 0);
                } else {
                    $this->RegisterVariableInteger($ident, $this->Translate($label), $profiles['mainProfile']);
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating integer', $ident, 0);
                }

                // Preset-Profile hinzufügen, falls vorhanden
                if ($profiles['presetProfile'] !== null) {
                    $presetIdent = $ident . '_Presets';
                    $this->RegisterVariableInteger($presetIdent, $this->Translate($label . ' Presets'), $profiles['presetProfile']);
                    $this->EnableAction($presetIdent);
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Created preset variable with profile: ', $profiles['presetProfile'], 0);
                }
                break;
            case 'enum':
                $profileName = $this->registerVariableProfile($feature);
                $this->RegisterVariableString($ident, $this->Translate($label), $profileName);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating string', $ident, 0);
                break;
            case 'string':
                $profileName = $this->registerVariableProfile($feature);
                $this->RegisterVariableString($ident, $this->Translate($label), $profileName);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating string (from stateTypeMapping)', $ident, 0);
                break;
            case 'text':
                $this->RegisterVariableString($ident, $this->Translate($label));
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating text', $ident, 0);
                break;
            case 'composite':
                if ($feature['name'] == 'color_xy') {
                    $this->RegisterVariableInteger('Z2M_Color', $this->Translate('Color'), 'HexColor');
                    $this->EnableAction('Z2M_Color');
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_xy', 'Z2M_Color', 0);
                } elseif ($feature['name'] == 'color_hs') {
                    $this->RegisterVariableInteger('Z2M_ColorHS', $this->Translate('Color HS'), 'HexColor');
                    $this->EnableAction('Z2M_ColorHS');
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_hs', 'Z2M_ColorHS', 0);
                } elseif ($feature['name'] == 'color_rgb') {
                    $this->RegisterVariableInteger('Z2M_ColorRGB', $this->Translate('Color RGB'), 'HexColor');
                    $this->EnableAction('Z2M_ColorRGB');
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating composite color_rgb', 'Z2M_ColorRGB', 0);
                } else {
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Unhandled composite type', $feature['name'], 0);
                }
                break;
            default:
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Unhandled type', $type, 0);
                break;
        }

        // Prüfen, ob die Variable schaltbar sein soll (access Bit 0b010)
        $isSwitchable = ($feature['access'] & 0b010) != 0;
        $this->MaintainAction($ident, $isSwitchable);
        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: MaintainAction set for', $ident, 0);
    }


    /**
     * Überprüft, ob ein erweitertes Variablenprofil existiert.
     *
     * Diese Funktion überprüft, ob das angegebene Basisprofil existiert und ob es eine erweiterte Version
     * dieses Profils gibt. Ein erweitertes Profil wird durch eine Punktnotation gekennzeichnet (z. B. `.100`).
     * Falls ein solches erweitertes Profil gefunden wird, gibt die Funktion dieses Profil zurück.
     *
     * Beispiele für erweiterte Profile sind Profile wie `~Battery.100`, wobei `.100` eine Erweiterung darstellt.
     *
     * @param string $baseProfile Der Name des Basisprofils, das überprüft werden soll.
     *
     * @return string|null Gibt das erweiterte Profil zurück, falls eines gefunden wird, oder null, wenn kein passendes Profil existiert.
     */
    private function checkForExtendedProfile($baseProfile)
    {
        // Prüfen, ob das Basisprofil ohne Erweiterung existiert
        if (IPS_VariableProfileExists($baseProfile)) {
            // Prüfen, ob das Profil eine Erweiterung hat (z.B. .100)
            if (strpos($baseProfile, '.') !== false) {
                // Prüfen, ob das Profil mit der Erweiterung existiert
                $this->SendDebug(__FUNCTION__ . ' :: Checking for extended profile: ', $baseProfile, 0);

                // Rückgabe des erweiterten Profils, wenn es existiert
                return $baseProfile;
            }
            // Wenn das Profil keine Erweiterung hat, prüfen, ob ein Profil mit einer Erweiterung existiert
            else {
                $extendedProfile = $baseProfile . '.100'; // Beispiel für eine mögliche Erweiterung
                if (IPS_VariableProfileExists($extendedProfile)) {
                    $this->SendDebug(__FUNCTION__ . ' :: Extended profile found: ', $extendedProfile, 0);
                    return $extendedProfile; // Rückgabe des erweiterten Profils
                }
            }
        }

        // Falls kein erweitertes Profil existiert, Debug-Ausgabe
        $this->SendDebug(__FUNCTION__ . ' :: No extended profile found for: ', $baseProfile, 0);
        return null;
    }

    /**
     * Sucht den Variablentyp für ein Standardprofil basierend auf dem Typ und Feature.
     *
     * Diese Funktion durchsucht das Array `VariableUseStandardProfile` und versucht, den zugehörigen
     * Variablentyp (z. B. int, float, bool) basierend auf dem Typ und dem Feature zu finden.
     * Wenn der Typ leer ist, wird er ignoriert und nur das Feature wird geprüft.
     *
     * @param string $type    Der Typ des Exposes (z. B. 'cover', 'light'). Wenn der Typ leer ist, wird nur das Feature geprüft.
     * @param string $feature Das spezifische Feature, nach dem gesucht wird (z. B. 'position', 'temperature').
     *
     * @return string|null Gibt den Variablentyp zurück (z. B. 'int', 'float', 'bool'), wenn ein passender Eintrag gefunden wird,
     *                     andernfalls null.
     */
    private function getVariableTypeFromProfile(string $type, string $feature): ?string
    {
        // Durchlaufe alle Einträge in $VariableUseStandardProfile
        foreach (self::$VariableUseStandardProfile as $entry) {
            // Überprüfe, ob der Typ übereinstimmt oder leer ist
            if (($entry['type'] === $type || $entry['type'] === '') && $entry['feature'] === $feature) {
                // Rückgabe des zugehörigen Variablentyps
                return $entry['variableType'];
            }
        }
        // Wenn kein Eintrag gefunden wurde, gib null zurück
        return null;
    }

    /**
     * Erzeugt den vollständigen Namen eines Variablenprofils basierend auf den Expose-Daten.
     *
     * Diese Methode generiert den vollständigen Namen eines Variablenprofils für ein bestimmtes Feature
     * (Expose). Der Profilname wird basierend auf dem Namen des Features gebildet. Falls das Feature
     * minimale und maximale Werte (`value_min`, `value_max`) enthält, werden diese in den Profilnamen
     * integriert, um eine eindeutige Benennung sicherzustellen. Zusätzlich wird optional eine Einheit
     * (falls vorhanden) an den Namen angefügt. Der resultierende Name kann zum Erstellen oder Identifizieren
     * eines Variablenprofils verwendet werden.
     *
     * Beispiel:
     * Wenn das Feature den Namen "temperature", einen minimalen Wert von 10 und einen maximalen Wert von 30 hat,
     * wird der Profilname "Z2M.temperature_10_30" generiert. Ohne Min- und Max-Werte wird nur "Z2M.temperature" verwendet.
     *
     * @param array $feature Ein Array, das die Eigenschaften des Features enthält. Erwartete Schlüssel sind:
     *  - 'name' (string): Der Name des Features, der als Basis für den Profilnamen verwendet wird.
     *  - 'value_min' (int|float|null, optional): Der minimale Wert des Features, der in den Profilnamen eingefügt wird, wenn er nicht null ist.
     *  - 'value_max' (int|float|null, optional): Der maximale Wert des Features, der in den Profilnamen eingefügt wird, wenn er nicht null ist.
     *  - 'unit' (string|null, optional): Eine optionale Einheit, die dem Profilnamen als Suffix hinzugefügt werden kann.
     *
     * @return string Der vollständige Name des Variablenprofils, basierend auf den Eigenschaften des Features.
     */
    private function getFullRangeProfileName($feature)
    {
        $ProfileName = 'Z2M.' . $feature['name'];
        $min = $feature['value_min'] ?? 0;
        $max = $feature['value_max'] ?? 0;
        $unit = isset($feature['unit']) ? ' ' . $feature['unit'] : '';
        return ($min !== 0 || $max !== 0) ? $ProfileName . '_' . $min . '_' . $max : $ProfileName;
    }

    /**
     * Registriert ein Variablenprofil basierend auf dem Expose-Typ oder einem optionalen State-Mapping.
     *
     * Diese Funktion erstellt und registriert ein Variablenprofil für den angegebenen Expose-Typ (z. B. binary, enum, numeric)
     * oder ein optionales State-Mapping. Sie überprüft zuerst, ob ein Standardprofil aus den Konfigurationsdaten existiert
     * oder ob bereits ein vorhandenes Systemprofil verwendet werden kann. Falls nicht, wird ein neues Profil auf Grundlage
     * der Expose-Daten erstellt.
     *
     * - Für binäre Exposes werden Boolean-Profile mit "on"/"off" Werten erstellt.
     * - Für Enum-Exposes werden String-Profile mit einer Liste möglicher Werte erstellt.
     * - Für numerische Exposes wird die Funktion `registerNumericProfile` verwendet.
     *
     * @param array $expose       Die Expose-Daten, die Informationen wie Typ, Werte und Einheiten enthalten.
     * @param array|null $stateMapping  Optionales Mapping für spezifische Zustände (z. B. LOCK/UNLOCK). Kann verwendet werden, um spezielle
     *                                  Mappings basierend auf dem Gerätetyp anzuwenden.
     *
     * @return string Der Name des erstellten oder vorhandenen Variablenprofils. Im Falle eines Systemprofils wird dieses direkt genutzt.
     */
    private function registerVariableProfile($expose, $stateMapping = null)
    {
        // Profilname basierend auf Expose oder Mapping
        $ProfileName = 'Z2M.' . ($expose['property'] ?? $expose['name']);
        $unit = isset($expose['unit']) ? ' ' . $expose['unit'] : '';

        // Ident für den StateTypeMapping ermitteln
        $ident = $this->convertPropertyToIdent($expose['property'] ?? $expose['name']);

        // Überprüfung auf stateTypeMapping
        if (isset(self::$stateTypeMapping[$ident])) {
            $mappedType = self::$stateTypeMapping[$ident]['type'];
            if (isset(self::$stateMappings[$mappedType])) {
                // String-Profil mit Mappings erstellen
                $this->RegisterProfileStringEx($ProfileName, '', '', '', [
                    [self::$stateMappings[$mappedType][0], $this->Translate(self::$stateMappings[$mappedType][0]), '', 0xFF0000],
                    [self::$stateMappings[$mappedType][1], $this->Translate(self::$stateMappings[$mappedType][1]), '', 0x00FF00]
                ]);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: State mapping profile created for: ', $ProfileName . json_encode(self::$stateMappings[$mappedType]), 0);
                return $ProfileName;
            }
        }

        // Prüfen, ob ein Standardprofil zugewiesen werden soll
        $standardProfile = $this->getStandardProfile($expose['type'], $expose['property'] ?? $expose['name']);
        if ($standardProfile !== null) {
            // Wenn das Profil ein Systemprofil ist, benutze es direkt ohne Registrierung
            if (strpos($standardProfile, '~') === 0 && IPS_VariableProfileExists($standardProfile)) {
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Using existing system profile: ', $standardProfile, 0);
                return $standardProfile;
            }

            // Wenn das Standardprofil existiert, benutze es direkt
            $variableType = $this->getVariableTypeFromProfile($expose['type'], $expose['property'] ?? $expose['name']);
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Using standard profile: ', $standardProfile . ' with variable type: ' . $variableType, 0);
            return $standardProfile;
        }

        // Erstelle das Profil basierend auf dem Expose-Typ
        switch ($expose['type']) {
            case 'binary':
                // Boolean-Profil erstellen (z.B. Plug-Status)
                $this->RegisterProfileBooleanEx($ProfileName, 'Plug', '', '', [
                    [false, $this->Translate('off'), '', 0xFF0000],
                    [true, $this->Translate('on'), '', 0x00FF00]
                ]);
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Boolean profile created for: ', $ProfileName, 0);
                break;

            case 'enum':
                // String-Profil mit Werten erstellen
                if (array_key_exists('values', $expose)) {
                    sort($expose['values']);  // Sortiere die Werte für Konsistenz
                    $tmpProfileName = implode('', $expose['values']);
                    $ProfileName .= '.' . dechex(crc32($tmpProfileName));  // Erstelle einen einzigartigen Profilnamen

                    // Erstelle das Profil basierend auf den Enum-Werten
                    $profileValues = [];
                    foreach ($expose['values'] as $value) { // $value kann auch ein int sein, cast zu string
                        $readableValue = ucwords(str_replace('_', ' ', (string) $value));
                        $translatedValue = $this->Translate($readableValue);
                        $profileValues[] = [(string) $value, $translatedValue, '', 0x00FF00];
                    }
                    $this->RegisterProfileStringEx($ProfileName, 'Menu', '', '', $profileValues);
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Enum profile created for: ' . $ProfileName, json_encode($profileValues), 0);
                } else {
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Variable profile missing for enum type', '', 0);
                }
                break;

            case 'numeric':
                // Numerisches Profil für den Expose-Typ erstellen
                return $this->registerNumericProfile($expose)['mainProfile'];

            default:
                // Unbekannter Typ
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Type not handled: ', $expose['type'], 0);
                break;
        }

        return $ProfileName;
    }

    /**
     * Sucht ein Standardprofil basierend auf dem Typ und dem Feature.
     *
     * Diese Funktion durchsucht das Array der vordefinierten Standardprofile (`$VariableUseStandardProfile`)
     * und versucht, ein passendes Profil basierend auf dem übergebenen Typ und Feature zu finden.
     * Wenn der Typ im Profil-Eintrag leer ist, wird er ignoriert und nur das Feature geprüft.
     *
     * @param string $type    Der Typ des Exposes (z. B. 'cover', 'light'). Wenn der Typ leer ist, wird nur das Feature geprüft.
     * @param string $feature Das spezifische Feature, nach dem gesucht wird (z. B. 'position', 'temperature').
     *
     * @return string|null Gibt den Namen des Standardprofils zurück, wenn eines gefunden wird, oder null, wenn kein passendes Profil existiert.
     */
    private function getStandardProfile(string $type, string $feature): ?string
    {
        // Überprüfe, ob ein vordefiniertes Standardprofil vorhanden ist
        foreach (self::$VariableUseStandardProfile as $entry) {
            // Nur den Typ prüfen, wenn er im Eintrag gesetzt ist
            if ((!empty($entry['type']) && $entry['type'] === $type) || empty($entry['type'])) {
                if ($entry['feature'] === $feature) {
                    return $entry['profile'];  // Rückgabe des Standardprofils
                }
            }
        }
        return null;
    }

    /**
     * Registriert ein numerisches Variablenprofil (ganzzahlig oder Gleitkomma) basierend auf den Expose-Daten.
     *
     * Diese Funktion erstellt und registriert ein numerisches Variablenprofil für ganzzahlige oder Gleitkommawerte
     * auf Grundlage der im Expose definierten Werte. Es unterstützt optionale Min- und Max-Werte sowie Einheiten
     * (z. B. % bei Helligkeit) und erstellt auch Preset-Profile, falls diese im Expose vorhanden sind.
     *
     * Wenn ein Standardprofil aus den Konfigurationsdaten vorhanden ist, wird dieses verwendet und registriert.
     * Systemprofile (z. B. ~Battery.100) werden direkt verwendet, ohne neu registriert zu werden.
     *
     * @param array $expose  Die Expose-Daten, die die Eigenschaften des numerischen Profils enthalten, wie 'name',
     *                       'value_min', 'value_max', 'unit' und optional 'presets'.
     * @param bool  $isFloat Gibt an, ob das Profil für Fließkommazahlen (true) oder Ganzzahlen (false) erstellt werden soll.
     *
     * @return array Ein Array mit zwei Elementen:
     *               - 'mainProfile': Der Name des Hauptprofils.
     *               - 'presetProfile': Der Name des Preset-Profils (falls vorhanden), sonst null.
     */
    private function registerNumericProfile($expose, $isFloat = false)
    {
        // Überprüfung auf Standardprofil
        $standardProfile = $this->getStandardProfile($expose['type'], $expose['name']);
        if ($standardProfile !== null) {
            $variableType = $this->getVariableTypeFromProfile($expose['type'], $expose['name']);
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Using standard profile: ', $standardProfile . ' with variable type: ' . $variableType, 0);

            // Wenn das Standardprofil ein Systemprofil ist (z.B. ~Battery.100), benutze es direkt ohne Registrierung
            if (strpos($standardProfile, '~') === 0 && IPS_VariableProfileExists($standardProfile)) {
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Standard system profile exists: ', $standardProfile, 0);
                return ['mainProfile' => $standardProfile, 'presetProfile' => null];
            }

            // Rückgabe des Standardprofils, falls es nicht als Systemprofil existiert
            return ['mainProfile' => $standardProfile, 'presetProfile' => null];
        }

        // Wenn kein Standardprofil gefunden wird, generiere ein eigenes Profil basierend auf Min/Max Werten
        $profileName = 'Z2M.' . $expose['name'];
        $min = $expose['value_min'] ?? 0;
        $max = $expose['value_max'] ?? 0;
        $unit = isset($expose['unit']) ? ' ' . $expose['unit'] : '';

        // Sonderfall für Brightness-Expose (kein Unit gesendet, aber % wird verwendet)
        if ($expose['name'] === 'brightness') {
            $unit = ' %';
        }

        $fullRangeProfileName = ($min !== 0 || $max !== 0) ? $profileName . '_' . $min . '_' . $max : $profileName;

        $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Main Profile: ', $fullRangeProfileName, 0);

        // Hauptprofil erstellen, falls es nicht existiert (für integer/float Werte)
        if (!IPS_VariableProfileExists($fullRangeProfileName)) {
            if ($isFloat) {
                $this->RegisterProfileFloat($fullRangeProfileName, '', '', $unit, $min, $max, 1, 2);
            } else {
                $this->RegisterProfileInteger($fullRangeProfileName, '', '', $unit, $min, $max, 1);
            }
        } else {
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Profile already exists, skipping creation: ', $fullRangeProfileName, 0);
        }

        $presetProfileName = null;

        // Preset-Profil erstellen, falls Presets vorhanden sind
        if (isset($expose['presets']) && !empty($expose['presets'])) {
            $presetProfileName = $fullRangeProfileName . '_Presets';
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Creating preset profile: ', $presetProfileName, 0);

            if (!IPS_VariableProfileExists($presetProfileName)) {
                $this->RegisterProfileStringEx($presetProfileName, '', '', '', []);

                // Presets hinzufügen
                foreach ($expose['presets'] as $preset) {
                    $presetValue = $preset['value'];
                    $presetName = $this->Translate(ucwords(str_replace('_', ' ', $preset['name'])));
                    $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Adding preset: ', $presetName . ' with value ' . $presetValue, 0);
                    IPS_SetVariableProfileAssociation($presetProfileName, $presetValue, $presetName, '', 0xFFFFFF);
                }
            } else {
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Preset profile already exists, skipping creation: ', $presetProfileName, 0);
            }
        }

        // Rückgabe des Hauptprofils und des optionalen Preset-Profils
        return ['mainProfile' => $fullRangeProfileName, 'presetProfile' => $presetProfileName];
    }

    /**
     * Erstellt ein Preset-Profil für eine Variable, falls es noch nicht existiert, und fügt die Preset-Werte hinzu.
     *
     * Diese Methode generiert ein Profil für Preset-Variablen basierend auf dem übergebenen Label und den Preset-Daten.
     * Der Name des Profils wird dynamisch aus dem Label erzeugt, wobei Leerzeichen durch Unterstriche ersetzt werden und
     * der Zusatz "_Presets" hinzugefügt wird. Falls das Profil bereits existiert, wird es nicht erneut erstellt. Andernfalls
     * wird ein neues String-Profil erstellt, und jeder Preset-Wert wird mit seinem entsprechenden Namen als Assoziation hinzugefügt.
     *
     * Der Ablauf ist wie folgt:
     * 1. Es wird überprüft, ob das Profil bereits existiert.
     * 2. Wenn das Profil nicht existiert, wird es erstellt.
     * 3. Die Preset-Werte und deren zugehörige Namen werden als Assoziationen dem Profil hinzugefügt.
     * 4. Der Profilname wird zurückgegeben.
     *
     * Beispiel:
     * Angenommen, das Label ist "Color Temp" und die Presets bestehen aus Werten wie "coolest" (Wert 153),
     * "warmest" (Wert 500), wird das Profil "Z2M_Color_Temp_Presets" erstellt und die Preset-Werte werden
     * als Assoziationen in diesem Profil gespeichert.
     *
     * @param array $presets Ein Array, das die Preset-Daten enthält. Jeder Eintrag im Array sollte folgendes Format haben:
     *                       [
     *                           'name'  => 'coolest',    // Der Name des Presets.
     *                           'value' => 153           // Der numerische Wert des Presets.
     *                       ].
     * @param string $label Das Label, das für den Variablen-Identifikator verwendet wird. Leerzeichen werden durch Unterstriche ersetzt.
     *
     * @return string Gibt den Namen des erstellten oder existierenden Profils zurück.
     *
     * @throws Exception Falls ein Fehler beim Erstellen des Profils oder Hinzufügen der Assoziationen auftritt.
     */
    private function registerPresetProfile(array $presets, string $label)
    {
        // Profilname ohne Leerzeichen erstellen
        $profileName = 'Z2M_' . str_replace(' ', '_', $label) . '_Presets';

        // Überprüfen, ob das Profil bereits existiert
        if (!IPS_VariableProfileExists($profileName)) {
            // Neues Profil für Presets erstellen
            $this->RegisterProfileStringEx($profileName, '', '', '', []);

            // Füge die Presets hinzu
            foreach ($presets as $preset) {
                $presetValue = $preset['value'];
                $presetName = $this->Translate(ucwords(str_replace('_', ' ', $preset['name'])));
                $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Adding preset: ', $presetName . ' with value ' . $presetValue, 0);
                IPS_SetVariableProfileAssociation($profileName, $presetValue, $presetName, '', 0xFFFFFF);
            }

            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Created preset profile: ', $profileName, 0);
        } else {
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Preset profile already exists: ', $profileName, 0);
        }

        // Rückgabe des Profilnamens
        return $profileName;
    }

    /**
     * Erstellt ein Variablenprofil für Presets basierend auf den übergebenen Preset-Daten.
     *
     * Diese Funktion generiert ein Profil für eine Preset-Variable, das verschiedene vordefinierte Werte enthält.
     * Der Profilname wird dynamisch basierend auf dem übergebenen Label erstellt und Leerzeichen werden durch
     * Unterstriche ersetzt. Falls ein Profil mit diesem Namen bereits existiert, wird es gelöscht und neu erstellt.
     *
     * Jedes Preset im übergebenen Array wird mit seinem Namen und Wert dem Profil hinzugefügt. Der Name des Presets
     * wird dabei ins Lesbare umgewandelt (z.B. von snake_case in normaler Text), und die zugehörigen Werte werden
     * als Assoziationen im Profil gespeichert. Die Presets erhalten außerdem eine standardmäßige weiße Farbe
     * für die Anzeige.
     *
     * Ablauf:
     * - Der Profilname wird aus dem übergebenen Label generiert (Leerzeichen werden durch Unterstriche ersetzt).
     * - Wenn das Profil bereits existiert, wird es gelöscht.
     * - Ein neues String-Profil wird erstellt, und für jedes Preset wird eine Profil-Assoziation hinzugefügt.
     *
     * @param array $presets Ein Array von Presets, die jeweils einen Namen und einen zugehörigen Wert enthalten.
     *                       Beispielstruktur eines Presets:
     *                       [
     *                           'name'  => 'coolest',    // Name des Presets
     *                           'value' => 153           // Wert des Presets
     *                       ]
     * @param string $label Der Name, der dem Profil zugeordnet wird. Leerzeichen im Label werden durch Unterstriche ersetzt.
     *
     * @return string Der Name des erstellten Profils.
     */
    private function createPresetProfile(array $presets, string $label)
    {
        // Profilname ohne Leerzeichen erstellen
        $profileName = 'Z2M_' . str_replace(' ', '_', $label) . '_Presets';

        // Array für die Preset-Associations erstellen
        $presetAssociations = [];

        // Füge die Presets zum Array hinzu
        foreach ($presets as $preset) {
            $presetValue = $preset['value'];
            $presetName = $this->Translate(ucwords(str_replace('_', ' ', $preset['name'])));
            $this->SendDebug(__FUNCTION__ . ' :: Line ' . __LINE__ . ' :: Adding preset: ', $presetName . ' with value ' . $presetValue, 0);

            // Füge das Preset zum Array hinzu
            $presetAssociations[] = [$presetValue, $presetName, '', 0xFFFFFF];
        }

        // Neues Profil für Presets erstellen, jetzt mit den Assoziationen
        $this->RegisterProfileStringEx($profileName, '', '', '', $presetAssociations);

        return $profileName;
    }
}
