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
        $Result = $this->SendData('/SymconExtension/request/getDeviceInfo/' . $this->ReadPropertyString('MQTTTopic'));
        $this->SendDebug('result', json_encode($Result), 0);
        if ($Result === false) {
            return false;
        }
        if (array_key_exists('ieeeAddr', $Result)) {
            if (empty($this->ReadPropertyString('IEEE')) && ($this->ReadPropertyString('IEEE') != $Result['ieeeAddr'])) {
                // Einmalig die leere IEEE Adresse in der Konfig setzen.
                IPS_SetProperty($this->InstanceID, 'IEEE', $Result['ieeeAddr']);
                IPS_ApplyChanges($this->InstanceID);
                return true;
            }
            /**
             * @todo Icon sollte auch manuell über die Form neu geladen werden können
             */
            if (array_key_exists('model', $Result)) {
                $Model = $Result['model'];
                if ($Model != 'Unknown Model') { // nur wenn Z2M ein Model liefert
                    if ($this->ReadAttributeString('Model') != $Model) { // und das Model sich geändert hat
                        $Url = 'https://raw.githubusercontent.com/Koenkk/zigbee2mqtt.io/master/public/images/devices/' . $Model . '.png';
                        $this->SendDebug('loadImage', $Url, 0);
                        $ImageRaw = @file_get_contents($Url);
                        if ($ImageRaw) {
                            $Icon = 'data:image/png;base64,' . base64_encode($ImageRaw);
                            $this->WriteAttributeString('Icon', $Icon);
                            $this->WriteAttributeString('Model', $Model);
                        }
                    }
                }
            }
            $this->mapExposesToVariables($Result['exposes']);
            return true;
        }
        trigger_error($this->Translate('Device not found. Check topic'), E_USER_NOTICE);
        return false;
    }
}
