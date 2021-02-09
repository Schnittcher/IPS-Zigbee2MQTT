<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/MQTTHelper.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/Zigbee2MQTTHelper.php';

class Zigbee2MQTTDevice extends IPSModule
{
    use ColorHelper;
    use MQTTHelper;
    use VariableProfileHelper;
    use Zigbee2MQTTHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        $this->RegisterPropertyString('MQTTTopic', '');

        if (!IPS_VariableProfileExists('Z2M.Sensitivity')) {
            $Associations = [];
            $Associations[] = [1, $this->Translate('Medium'), '', -1];
            $Associations[] = [2, $this->Translate('Low'), '', -1];
            $Associations[] = [3, $this->Translate('High'), '', -1];
            $this->RegisterProfileIntegerEx('Z2M.Sensitivity', '', '', '', $Associations);
        }

        if (!IPS_VariableProfileExists('Z2M.ColorTemperature')) {
            IPS_CreateVariableProfile('Z2M.ColorTemperature', 1);
        }
        IPS_SetVariableProfileDigits('Z2M.ColorTemperature', 0);
        IPS_SetVariableProfileIcon('Z2M.ColorTemperature', 'Bulb');
        IPS_SetVariableProfileText('Z2M.ColorTemperature', '', ' Mired');
        IPS_SetVariableProfileValues('Z2M.ColorTemperature', 50, 400, 1);

        if (!IPS_VariableProfileExists('Z2M.DeviceStatus')) {
            $this->RegisterProfileBooleanEx('Z2M.DeviceStatus', 'Network', '', '', [
                [false, 'Offline',  '', 0xFF0000],
                [true, 'Online',  '', 0x00FF00]
            ]);
        }
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        //Setze Filter für ReceiveData
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');
    }
}
