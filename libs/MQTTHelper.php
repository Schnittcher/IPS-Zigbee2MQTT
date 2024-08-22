<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

trait MQTTHelper
{
    public function Command(string $topic, string $value)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topic;
        $Data['Payload'] = utf8_encode($value);
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
    }

    public function CommandExt(string $topic, string $value) //ohne MQTTTopic
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $this->ReadPropertyString('MQTTBaseTopic') . '/' . $topic;
        $Data['Payload'] = utf8_encode($value);
        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__ . ' Payload', $Data['Payload'], 0);
        $this->SendDataToParent($DataJSON);
    }
}

/**
 * @property array $TransactionData
 */
trait SendData
{
    private static $MQTTDataArray = [
        'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
        'PacketType'       => 3,
        'QualityOfService' => 0,
        'Retain'           => false,
        'Topic'            => '',
        'Payload'          => ''
    ];

    /**
     * SendData
     *
     * Sendet eine MQTT Nachricht an den Parent.
     * Wird aktuell nur zur Kommunikation mit dem Bridge Topic, sowie der Extension in Z2M verwendet,
     * durch die Funktionen UpdateDeviceInfo, UpdateGroupInfo, getDevices und getGroups
     * Bei aktivem Timeout wird die Nachtricht mit einer TransactionId versehen,
     * und auf eine eingehende Nachricht mit der entsprechenden TransactionId gewartet.
     * 
     * @param  string $Topic
     * @param  array $Payload
     * @param  int $Timeout default 5000ms, 0 = senden ohne auf die Antwort zuw arten
     * @return array|bool Enthält die Antwort als Array, oder True bei inaktivem Timeout, oder false im Fehlerfall.
     */
    protected function SendData(string $Topic, array $Payload = [], int $Timeout = 5000)
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

    /**
     * WaitForTransactionEnd
     *
     * Liefert die Antwort aus dem Buffer TransactionData.
     *
     * @param  int $TransactionId
     * @param  int $Timeout
     * @return array|false Enthält die Antwort, oder false beim erreichen des Timeout oder im Fehlerfall.
     */
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
                unset($Buffer[$TransactionId]['transaction']);
                return $Buffer[$TransactionId];
            }
            IPS_Sleep($Sleep);
        }
        $this->RemoveTransaction($TransactionId);
        return false;
    }
    //################# SENDQUEUE

    /**
     * AddTransaction
     *
     * Generiert eine TransactionId, fügt diese dem Payload hinzu und erzeugt einen Eintrag im Buffer TransactionData.
     *
     * @param  array $Payload MQTT Payload als Referenz
     * @return int Erzeugte TransactionId
     */
    private function AddTransaction(array &$Payload)
    {
        if (!$this->lock('TransactionData')) {
            throw new \Exception($this->Translate('TransactionData is locked'), E_USER_NOTICE);
        }
        $TransactionId = mt_rand(1, 10000);
        $Payload['transaction'] = $TransactionId;
        $TransactionData = $this->TransactionData;
        $TransactionData[$TransactionId] = [];
        $this->TransactionData = $TransactionData;
        $this->unlock('TransactionData');
        return $TransactionId;
    }

    /**
     * UpdateTransaction
     *
     * Aktualisiert einen Eintrag im TransactionData Buffer.
     *
     * @param  array $Data Payload welches im Buffer abgelegt werden soll.
     * @return void
     */
    private function UpdateTransaction(array $Data)
    {
        if (!$this->lock('TransactionData')) {
            throw new \Exception($this->Translate('TransactionData is locked'), E_USER_NOTICE);
        }
        $TransactionData = $this->TransactionData;
        if (array_key_exists($Data['transaction'], $TransactionData)) {
            $TransactionData[$Data['transaction']] = $Data;
            $this->TransactionData = $TransactionData;
            $this->unlock('TransactionData');
            return;
        }
        $this->unlock('TransactionData');
    }

    /**
     * RemoveTransaction
     *
     * Entfernt den Eintrag der TransactionId aus dem Buffer TransactionData.
     *
     * @param  int $TransactionId
     * @return void
     */
    private function RemoveTransaction(int $TransactionId)
    {
        if (!$this->lock('TransactionData')) {
            throw new \Exception($this->Translate('TransactionData is locked'), E_USER_NOTICE);
        }
        $TransactionData = $this->TransactionData;
        unset($TransactionData[$TransactionId]);
        $this->TransactionData = $TransactionData;
        $this->unlock('TransactionData');
    }

    /**
     * BuildRequest
     *
     * Erzeugt ein JSON-String für den Datenaustausch mit einem MQTT-Splitter
     *
     * @param  string $Topic MQTT Topic
     * @param  array $Payload MQTT Payload welches als JSON kodierter Payload gesetzt wird.
     * @return string JSON-String des Datenaustausch
     */
    private static function BuildRequest(string $Topic, array $Payload)
    {
        return json_encode(
            array_merge(
                self::$MQTTDataArray,
                [
                    'Topic'  => $Topic,
                    'Payload'=> utf8_encode(json_encode($Payload))
                ]
            ),
            JSON_UNESCAPED_SLASHES
        );
    }
}