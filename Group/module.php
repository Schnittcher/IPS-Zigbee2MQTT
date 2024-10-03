<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/ModulBase.php';

class Zigbee2MQTTGroup extends \Zigbee2MQTT\ModulBase
{
    /** @var mixed $ExtensionTopic Topic für den ReceiveFilter*/
    protected static $ExtensionTopic = 'getGroupInfo/';

    /**
     * Create
     *
     * @return void
     */
    public function Create()
    {
        //Never delete this line!
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
        $Result = $this->SendData('/SymconExtension/request/getGroupInfo/' . $this->ReadPropertyString('MQTTTopic'));
        $this->SendDebug('result', json_encode($Result), 0);
        if ($Result === false) {
            return false;
        }
        if (array_key_exists('foundGroup', $Result)) {
            if ($Result['foundGroup']) {
                unset($Result['foundGroup']);
                $this->mapExposesToVariables($Result);
                return true;
            }
        }
        trigger_error($this->Translate('Group not found. Check topic'), E_USER_NOTICE);
        return false;
    }
}
