<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

if (!function_exists('fnmatch')) {
    function fnmatch($pattern, $string)
    {
        return preg_match('#^' . strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.']) . '$#i', $string);
    }
}

define('MQTT_GROUP_TOPIC', 'zigbee2mqtt');

trait MQTTHelper
{
    private static $MQTTDataArray = [
        'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
        'PacketType'       => 3,
        'QualityOfService' => 0,
        'Retain'           => false,
        'Topic'            => '',
        'Payload'          => ''
    ];

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
        return;
    }

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