<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          AttributeArrayHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

/**
 * Trait welcher Array in eine String Attribute schreiben und lesen kann.
 */
trait AttributeArrayHelper
{
    /**
     * Registriert ein Array Attribute.
     *
     * @access protected
     * @param string $name Attributname
     * @param array  $Value Standardwert des Attribut
     * @param int $Size Anzahl der zu verwendeten String Attribute
     */
    protected function RegisterAttributeArray(string $name, array $Value): void
    {
        $Data = json_encode($Value);
        $this->RegisterAttributeString($name, $Data);
    }

    /**
     * Liest den Inhalt eines Attribut aus.
     * @param string $name Name des Attribut
     * @return array Inhalt des Attribut
     */
    protected function ReadAttributeArray(string $name): array
    {
        return json_decode($this->ReadAttributeString($name), true);
    }

    /**
     * Schreibt ein Array in das Attribut
     * @param string $Name des Attribut
     * @param array $value Array welches in das Attribut geschrieben wird
     */
    protected function WriteAttributeArray(string $name, array $value): void
    {
        $Data = json_encode($value);
        $this->WriteAttributeString($name, $Data);
    }
}
