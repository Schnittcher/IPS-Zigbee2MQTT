<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModulBase.php';

class Zigbee2MQTTGroup extends \Zigbee2MQTT\ModulBase
{
    /** @var mixed $ExtensionTopic Topic für den ReceiveFilter */
    protected static $ExtensionTopic = 'getGroupInfo/';

    /**
     * Create
     *
     * @return void
     */
    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('GroupId', 0);
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges()
    {
        $GroupId = $this->ReadPropertyInteger('GroupId');
        $GroupId = $GroupId ? 'Group Id: ' . $GroupId : '';
        $this->SetSummary($GroupId);
        //Never delete this line!
        parent::ApplyChanges();
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

        $Result = $this->SendData('/SymconExtension/request/getGroupInfo/' . $mqttTopic);
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' result', json_encode($Result), 0);

        if ($Result === false) {
            IPS_LogMessage(__CLASS__, "SendData für MQTTTopic '$mqttTopic' fehlgeschlagen.");
            return false;
        }

        if (array_key_exists('foundGroup', $Result)) {
            unset($Result['foundGroup']);
            // Aufruf der Methode aus der ModulBase-Klasse
            $this->mapExposesToVariables($Result);
            $this->SaveExposesToJson($Result);
            return true;
        }

        trigger_error($this->Translate('Group not found. Check topic'), E_USER_NOTICE);
        return false;
    }

    /**
     * SaveExposesToJson
     *
     * Speichert die Exposes in einer JSON-Datei.
     *
     * @param array $Result Die Exposes-Daten.
     *
     * @return void
     */
    private function SaveExposesToJson(array $Result): void
    {
        // Definieren des Verzeichnisnamens
        $verzeichnisName = 'Zigbee2MQTTExposes';
        $kernelDir = rtrim(IPS_GetKernelDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $neuesVerzeichnis = $kernelDir . $verzeichnisName;

        // Gruppenspezifische Informationen
        $groupID = $this->ReadPropertyInteger('GroupId');
        if ($groupID === 0) {
            IPS_LogMessage(__CLASS__, "GroupId ist nicht gesetzt. Exposes werden nicht gespeichert.");
            return;
        }

        // Dateipfad für die JSON-Datei basierend auf InstanceID und groupID
        $instanceID = $this->InstanceID;
        $dateiPfad = $neuesVerzeichnis . DIRECTORY_SEPARATOR . $instanceID . '_' . $groupID . '.json';

        // JSON-Daten mit Pretty-Print erstellen
        $jsonData = json_encode($Result, JSON_PRETTY_PRINT);
        if ($jsonData === false) {
            IPS_LogMessage(__CLASS__, "Fehler beim JSON-Encoding der Exposes für Gruppe ID '$groupID': " . json_last_error_msg());
            return;
        }

        // Schreiben der JSON-Daten in die Datei
        if (file_put_contents($dateiPfad, $jsonData) !== false) {
            IPS_LogMessage(__CLASS__, "Exposes erfolgreich als '{$instanceID}_{$groupID}.json' im Verzeichnis '$verzeichnisName' gespeichert.");
            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__, "Datei erfolgreich geschrieben: " . $dateiPfad, 0);
        } else {
            IPS_LogMessage(__CLASS__, "Fehler beim Schreiben von '{$instanceID}_{$groupID}.json' im Verzeichnis '$verzeichnisName'.");
            $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__, "Fehler beim Schreiben der Datei: " . $dateiPfad, 0);
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

        // Gruppenspezifische Informationen
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
