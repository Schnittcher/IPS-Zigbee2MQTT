# Configurator
Mit dieser Instanz werden die Geräte und Gruppen gesucht und können in Symcon angelegt werden.

## Inhaltverzeichnis
1. [Konfiguration](#1-konfiguration)
2. [Funktionen](#2-funktionen)

## 1. Konfiguration

Feld | Beschreibung
------------ | -------------
MQTT Topic | Hier wird das Topic des Systems eingetragen (Standardwert: zigbee2mqtt)

## 2. Funktionen

### Z2M_Command(string $topic, string $value)
Mit dies Funktion kann ein Befehl über MQTT abgesetzt werden.
Innerhalb dieser Funktion wird das Topic aus der Konfiguration (MQTT Topic) vorangestellt:

```php
$Payload['from'] = 'Lampe1';
$Payload['to'] = 'Lampe1Neu';
$Payload['homeassistant_rename'] = false;
$this->Command('request/device/rename', json_encode($Payload));
```
Dieses Beispiel bennennt die Lampe von Lampe1 in Lampe1Neu um.
Hier wird an das Topic "zigbee2mqtt/bridge/request/device/rename" gesendet.

### Z2M_CommandExt(string $topic, string $value)
Mit dies Funktion kann ein Befehl über MQTT abgesetzt werden.
Innerhalb dieser Funktion wird das Topic aus der Konfiguration (MQTT Topic) nicht vorgestellt:

```php
$Payload['state'] = '';
$this->Command('Lampe1/get', json_encode($Payload));
```
Dieses Beispiel ruft "State" von Lampe1 ab.
Hier wird an das Topic "zigbee2mqtt/Lampe1/get" gesendet.