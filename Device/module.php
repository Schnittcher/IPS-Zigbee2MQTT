<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModulBase.php';

class Zigbee2MQTTDevice extends \Zigbee2MQTT\ModulBase
{
    /** @var mixed $ExtensionTopic Topic für den ReceiveFilter*/
    protected static $ExtensionTopic = 'getDeviceInfo/';

    /**
     * Create
     *
     * @return void
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString('IEEE', '');
        $this->RegisterAttributeString('Model', '');
        $this->RegisterAttributeString('Icon', '');
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges()
    {
        $this->SetSummary($this->ReadPropertyString('IEEE'));
        //Never delete this line!
        parent::ApplyChanges();
    }

    /**
     * GetConfigurationForm
     *
     * @todo Expertenbutton um Schreibschutz vom Feld ieeeAddr aufzuheben.
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Form['elements'][0]['items'][1]['image'] = $this->ReadAttributeString('Icon');
        return json_encode($Form);
    }

    /**
     * UpdateDeviceInfo
     *
     * Exposes von der Erweiterung in Z2M anfordern und verarbeiten.
     *
     * @return bool
     */
    protected function UpdateDeviceInfo(): bool
    {
        $mqttTopic = $this->ReadPropertyString('MQTTTopic');
        if (empty($mqttTopic)) {
            IPS_LogMessage(__CLASS__, "MQTTTopic ist nicht gesetzt.");
            return false;
        }

        $Result = $this->SendData('/SymconExtension/request/getDeviceInfo/' . $mqttTopic);
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' result', json_encode($Result), 0);

        if ($Result === false) {
            IPS_LogMessage(__CLASS__, "SendData für MQTTTopic '$mqttTopic' fehlgeschlagen.");
            return false;
        }

        if (array_key_exists('ieeeAddr', $Result)) {
            $currentIEEE = $this->ReadPropertyString('IEEE');
            if (empty($currentIEEE) && ($currentIEEE !== $Result['ieeeAddr'])) {
                // Einmalig die leere IEEE Adresse in der Konfiguration setzen.
                IPS_SetProperty($this->InstanceID, 'IEEE', $Result['ieeeAddr']);
                IPS_ApplyChanges($this->InstanceID);
                return true;
            }

            /**
             * @todo Icon sollte auch manuell über die Form neu geladen werden können
             */
            if (array_key_exists('model', $Result)) {
                $Model = $Result['model'];
                if ($Model !== 'Unknown Model') { // nur wenn Z2M ein Model liefert
                    if ($this->ReadAttributeString('Model') !== $Model) { // und das Model sich geändert hat
                        $Url = 'https://raw.githubusercontent.com/Koenkk/zigbee2mqtt.io/master/public/images/devices/' . $Model . '.png';
                        $this->SendDebug('loadImage', $Url, 0);
                        $ImageRaw = @file_get_contents($Url);
                        if ($ImageRaw !== false) {
                            $Icon = 'data:image/png;base64,' . base64_encode($ImageRaw);
                            $this->WriteAttributeString('Icon', $Icon);
                            $this->WriteAttributeString('Model', $Model);
                        } else {
                            IPS_LogMessage(__CLASS__, "Fehler beim Herunterladen des Icons von URL: $Url");
                        }
                    }
                }

                // *** Ergänzung: IEEE.json speichern ***
                // Überprüfen, ob die benötigten Schlüssel existieren
                if (isset($Result['ieeeAddr']) && isset($Result['exposes'])) {
                    // Extrahieren der benötigten Daten und Hinzufügen der Symcon-ID
                    $dataToSave = [
                        'symconId'  => $this->InstanceID,          // Hinzugefügt: Symcon-ID
                        'ieeeAddr'  => $Result['ieeeAddr'],
                        'model'     => $Result['model'],
                        'exposes'   => $Result['exposes']
                    ];

                    // JSON-Encode der Daten mit Pretty-Print
                    $jsonData = json_encode($dataToSave, JSON_PRETTY_PRINT);

                    if ($jsonData === false) {
                        IPS_LogMessage(__CLASS__, "Fehler beim JSON-Encoding der Daten für IEEE.json: " . json_last_error_msg());
                    } else {
                        // Pfad zum Kernel-Verzeichnis abrufen
                        $kernelDir = IPS_GetKernelDir();

                        // Sicherstellen, dass der Pfad mit einem Verzeichnis-Trenner endet
                        if (substr($kernelDir, -1) !== DIRECTORY_SEPARATOR) {
                            $kernelDir .= DIRECTORY_SEPARATOR;
                        }

                        // Pfad zum Zigbee2MQTTExposes-Verzeichnis erstellen
                        $verzeichnisName = 'Zigbee2MQTTExposes';
                        $vollerPfad = $kernelDir . $verzeichnisName . DIRECTORY_SEPARATOR;

                        // Sicherstellen, dass das Verzeichnis existiert (wird von ModulBase verwaltet)
                        // Daher ist diese Überprüfung optional, kann aber zur Sicherheit beibehalten werden
                        if (!file_exists($vollerPfad)) {
                            // Falls das Verzeichnis nicht existiert, versuchen es zu erstellen
                            if (!mkdir($vollerPfad, 0755, true)) {
                                IPS_LogMessage(__CLASS__, "Fehler beim Erstellen des Verzeichnisses '$verzeichnisName'.");
                                // Abbruch der Speicherung, da das Verzeichnis nicht existiert
                                return false;
                            } else {
                                IPS_LogMessage(__CLASS__, "Verzeichnis '$verzeichnisName' erfolgreich erstellt.");
                            }
                        }

                        // Dateipfad für die JSON-Datei basierend auf ieeeAddr
                        $instanceID = $this->InstanceID;
                        $ieeeAddr = $Result['ieeeAddr'];
                        // Optional: Entfernen von '0x' aus der IEEE-Adresse, falls gewünscht
                        // $ieeeAddr = ltrim($ieeeAddr, '0x');
                        $dateiPfad = $vollerPfad . $instanceID . '_' . $ieeeAddr . '.json';

                        // Schreiben der JSON-Daten in die Datei
                        if (file_put_contents($dateiPfad, $jsonData) !== false) {
                            IPS_LogMessage(__CLASS__, "IEEE.json erfolgreich als '$ieeeAddr.json' im Verzeichnis '$verzeichnisName' gespeichert.");
                        } else {
                            IPS_LogMessage(__CLASS__, "Fehler beim Schreiben von '$ieeeAddr.json' im Verzeichnis '$verzeichnisName'.");
                        }
                    }
                } else {
                    IPS_LogMessage(__CLASS__, "Die erforderlichen Schlüssel 'ieeeAddr' oder 'exposes' fehlen in \$Result.");
                }
            }

            // Aufruf der Methode aus der ModulBase-Klasse
            $this->mapExposesToVariables($Result['exposes']);
            return true;
        }
    }

        /**
     * Destroy
     *
     * Diese Methode wird aufgerufen, wenn die Instanz gelöscht wird.
     * Sie sorgt dafür, dass die zugehörige .json-Datei entfernt wird.
     *
     * @return void
     */
    public function Destroy()
    {
        // Wichtig: Zuerst die Parent Destroy Methode aufrufen
        parent::Destroy();

        // Holen der InstanceID
        $instanceID = $this->InstanceID;

        // Holen des Kernel-Verzeichnisses
        $kernelDir = IPS_GetKernelDir();

        // Definieren des Verzeichnisnamens
        $verzeichnisName = 'Zigbee2MQTTExposes';

        // Konstruktion des vollständigen Pfads zum Verzeichnis
        $vollerPfad = $kernelDir . $verzeichnisName . DIRECTORY_SEPARATOR;

        // Konstruktion des erwarteten Dateinamens mit InstanceID und Wildcard für ieeeAddr
        $dateiNamePattern = $instanceID . '_*.json';

        // Vollständiger Pfad mit Muster
        $dateiPfad = $vollerPfad . $dateiNamePattern;

        // Suche nach Dateien, die dem Muster entsprechen
        $files = glob($dateiPfad);

        // Überprüfung und Löschung der gefundenen Dateien
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    IPS_LogMessage(__CLASS__, "Datei erfolgreich gelöscht: $file");
                } else {
                    IPS_LogMessage(__CLASS__, "Fehler beim Löschen der Datei: $file");
                }
            }
        }
    }
}
