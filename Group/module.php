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
        $this->RegisterAttributeInteger('GroupId', 0);
        $this->RegisterMessage($this->InstanceID, IM_CHANGEATTRIBUTE);
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges()
    {
        $GroupId = $this->ReadPropertyInteger('GroupId');
        $this->SetSummary($GroupId ? 'Group Id: ' . $GroupId : '');
        if ($GroupId == 0) {
            if ($this->GetStatus() == IS_ACTIVE) {
                $this->LogMessage($this->Translate('No group ID configured'), KL_WARNING);
            }
        } else {
            if ($this->ReadAttributeInteger('GroupId') != $GroupId) {
                $this->WriteAttributeInteger('GroupId', $GroupId);
            }
        }
        // Führe parent::ApplyChanges zuerst aus
        parent::ApplyChanges();
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
            case IM_CHANGEATTRIBUTE:
                if (($Data[0] == 'GroupId') && ($Data[1] != 0) && ($this->GetStatus() == IS_CREATING)) {
                    $this->UpdateDeviceInfo();
                }
                return;
        }
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (count($this->missingTranslations)) {
            $Form['elements'][3]['visible'] = true;
        }
        return json_encode($Form);
    }

    /**
     * RequestAction
     *
     * @param  string $ident
     * @param  mixed $value
     * @return void
     */
    public function RequestAction($ident, $value)
    {
        if ($ident == 'ShowGroupIdEditWarning') {
            $this->UpdateFormField('GroupIdWarning', 'visible', true);
            return;
        }
        if ($ident == 'EnableGroupIdEdit') {
            $this->UpdateFormField('GroupId', 'enabled', true);
            return;
        }
        parent::RequestAction($ident, $value);
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
        // Aufruf der Methode aus der ModulBase-Klasse
        $Result = $this->LoadDeviceInfo();
        if (!$Result) {
            return false;
        }
        if (!isset($Result['foundGroup'])) {
            trigger_error($this->Translate('Group not found. Check topic.'), E_USER_NOTICE);
            return false;

        }
        unset($Result['foundGroup']);
        // Aufruf der Methode aus der ModulBase-Klasse
        $this->WriteAttributeArray(self::ATTRIBUTE_EXPOSES, $Result);
        $this->mapExposesToVariables($Result);
        return true;
    }
}
