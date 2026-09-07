<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          VariableProfileHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

/**
 * Trait mit Hilfsfunktionen für Variablenprofile.
 */
trait VariableProfileHelper
{
    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ bool mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileBooleanEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations): void
    {
        $this->RegisterProfileEx(VARIABLETYPE_BOOLEAN, $Name, $Icon, $Prefix, $Suffix, $Associations);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ integer mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, float $StepSize = 0): void
    {
        $this->RegisterProfileEx(VARIABLETYPE_INTEGER, $Name, $Icon, $Prefix, $Suffix, $Associations, $MaxValue, $StepSize);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ float mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileFloatEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0): void
    {
        $this->RegisterProfileEx(VARIABLETYPE_FLOAT, $Name, $Icon, $Prefix, $Suffix, $Associations, $MaxValue, $StepSize, $Digits);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ string mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileStringEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations): void
    {
        $this->RegisterProfileEx(VARIABLETYPE_STRING, $Name, $Icon, $Prefix, $Suffix, $Associations);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ bool.
     *
     * @param string $Name   Name des Profils.
     * @param string $Icon   Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     */
    protected function RegisterProfileBoolean(string $Name, string $Icon, string $Prefix, string $Suffix): void
    {
        $this->RegisterProfile(VARIABLETYPE_BOOLEAN, $Name, $Icon, $Prefix, $Suffix, 0, 0, 0);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ integer.
     *
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param int    $MinValue Minimaler Wert.
     * @param int    $MaxValue Maximaler wert.
     * @param float  $StepSize Schrittweite
     */
    protected function RegisterProfileInteger(string $Name, string $Icon, string $Prefix, string $Suffix, int $MinValue, int $MaxValue, float $StepSize): void
    {
        $this->RegisterProfile(VARIABLETYPE_INTEGER, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ float.
     *
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param float  $MinValue Minimaler Wert.
     * @param float  $MaxValue Maximaler wert.
     * @param float  $StepSize Schrittweite
     */
    protected function RegisterProfileFloat(string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits): void
    {
        $this->RegisterProfile(VARIABLETYPE_FLOAT, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits);
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ VarType mit Assoziationen.
     *
     * @param int    $VarTyp       Typ der Variable
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param int|array  $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileEx(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, mixed $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0): void
    {
        if (is_int($Associations)) {
            $this->RegisterProfile($VarTyp, $Name, $Icon, $Prefix, $Suffix, $Associations, $MaxValue, $StepSize, $Digits);
            return;
        }
        if ((count($Associations) === 0) || ($VarTyp === VARIABLETYPE_BOOLEAN) || ($VarTyp === VARIABLETYPE_STRING)) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinMax = array_column($Associations, 0);
            sort($MinMax);
            $MinValue = $MinMax[0];
            if ($MaxValue == -1) {
                $MaxValue = $Associations[count($Associations) - 1][0];
            } else {
                $MaxValue = array_pop($MinMax);
            }
        }
        $this->RegisterProfile($VarTyp, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits);
        $old = IPS_GetVariableProfile($Name)['Associations'];
        $OldValues = array_column($old, 'Value');
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $this->Translate($Association[1]), $Association[2], $Association[3]);
            $OldKey = array_search($Association[0], $OldValues);
            if (!($OldKey === false)) {
                unset($OldValues[$OldKey]);
            }
        }
        foreach ($OldValues as $OldKey => $OldValue) {
            IPS_SetVariableProfileAssociation($Name, $OldValue, '', '', 0);
        }
    }

    /**
     * Erstellt und konfiguriert ein VariablenProfil für den Typ float.
     *
     * @param int    $VarTyp   Typ der Variable
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param float  $MinValue Minimaler Wert.
     * @param float  $MaxValue Maximaler wert.
     * @param float  $StepSize Schrittweite
     */
    protected function RegisterProfile(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, float $MinValue, float $MaxValue, float $StepSize, int $Digits = 0): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $VarTyp);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != $VarTyp) {
                IPS_DeleteVariableProfile($Name);
                $this->LogMessage('Variable profile type does not match for profile ' . $Name, KL_WARNING);
                //throw new \Exception('Variable profile type does not match for profile ' . $Name, E_USER_WARNING);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $this->Translate($Prefix), $this->Translate($Suffix));
        if (($VarTyp != VARIABLETYPE_BOOLEAN) && ($VarTyp != VARIABLETYPE_STRING)) {
            IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
        }
        if ($VarTyp == VARIABLETYPE_FLOAT) {
            IPS_SetVariableProfileDigits($Name, $Digits);
        }
    }

    /**
     * Löscht ein Variablenprofile, sofern es nicht außerhalb dieser Instanz noch verwendet wird.
     *
     * @param string $Name Name des zu löschenden Profils.
     */
    protected function UnregisterProfile(string $Name): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        IPS_DeleteVariableProfile($Name);
    }
}

/* @} */