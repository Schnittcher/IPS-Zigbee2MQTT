# Configurator
Mit dieser Instanz werden die Geräte und Gruppen gesucht und können in Symcon angelegt werden.

## Inhaltverzeichnis
1. [Konfiguration](#1-konfiguration)
2. [Funktionen](#2-funktionen)

## 1. Konfiguration

![Übersicht Konfigurator](docs/pictures/konfigurator_ansicht.jpg)



Nummer | Name | Beschreibung
------------ | ------------- | -------------
**1** | **Gateway ändern** | ![Gateway ändern](docs/pictures/konfigurator_gatewayauswahl.jpg) <br>Hier gebt Ihr den MQTT-Knotenpunkt an. Wenn Ihr den Symcon internen MQTT-Broker nutzt, sehr Ihr dort die MQTT-Server Device. Wenn Ihr einen anderen Broker nutzt (z.B. Mosquitto) dann seht Ihr hier die MQTT-Klient-Device, die auf den Mosquitto-Broker zugreift.
**2** | **Gateway konfigurieren** | ![Gateway konfigurieren](docs/pictures/konfigurator_Gateway_konfigurieren.jpg) Unter diesem Punkt kann das MQTT-Gateway direkt aufgerufen werden, falls Ihr dort Änderungen vornehmen wollt.
**3** | **IEEE Adresse** | Zeigt die unveränderbare IEEE-Adresse der Zigbee-Devices
**4** | **Friendlyname** | Gibt den in Zigbee2MQTT (Z2M) angegebenen friendly_name an. <br> **WICHTIG: Wenn Ihr eine Struktur in die MQTT-Topics kriegen wollt, dann könnt ihr im friendly_name slashes nutzen (Etage/Raum/Sparte/Gerät) Durch den Slash wird dann die MQTT-Baumstruktur von Z2M aufgebaut:** <br>![MQTT Struktur](docs/pictures/mqtt_struktur.jpg)
**5** | **Hersteller** | Gibt an, von welchem Hersteller das Device ist.
**6** | **Model ID** | Die Geräte-Model ID des Herstellers
**7** | **Beschreibung** | Gibt den Geräte-Typ an (z.B. Smoke-Detector, Plug, Aqara door & window contact sensor, etc.)
**8** | **Energiequelle** | Gibt an, ob das Gerät mit Batterie oder Netzspannung versorgt ist.<br> **Wichtig um zu sehen, ob das Gerät als Router genutzt werden kann: Batterie = Nein, Netz = Ja**
**9** | **InstanzID** | Daran lässt sich zum einen erkennen, ob das Gerät bereits in Symcon angelegt ist und mit welcher Objekt-ID oder ob es noch nicht angelegt ist.
**10** | **Alle erstellen** | Legt alle erkannten Geräte in Symcon als Objekte an.<br> **Wichtig: Der Konfigurator legt alle Objekte unter "System" an die im friendly_name vorgegebene MQTT-Struktur wird dabei von Symcon nicht übernommen.** <br> Beim anlegen erhalten die Objekte automatisch den friendly_name als Objekt-Namen ![Objekt Name](docs/pictures/konfigurator_Objektname.jpg)
**11** | **Erstellen** | Hiermit lassen sich einzelne Devices als Objekte in Symcon anlegen. Auch hier wird die MQTT-Struktur NICHT übernommen. Und der friendly_name aus Z2M wird zum Objekt-Namen.
**12** | **Aktualisieren** | Aktualisiert die Device-Liste im Konfigurator. Dies ist sinnvoll, wenn neue Devices an Z2M angelernt worden sind. <br> **WICHTIG: Es kann manchmal notwendig sein, die Device-Liste zweimal zu aktualisieren, da nicht alle neu angelernten Devices gleich beim ersten mal mit gesendet werden.**
**13** | **Filter** | Hier lässt sich die Device-Liste nach bestimmten Schlagworten filtern. Es wird dabei auf alle Spalten Rücksicht genommen. Wenn ich Also "Osram" eingebe wird in allen Spalten das Wort "Osram" gesucht und bei Vorhandensein werden die betreffenden Devices in der Liste angezeigt: ![Osram](docs/pictures/konfigurator_osram.jpg)<br>Gibt man z.B. "01Mini" ein werden alle Devices mit der ModelID gezeigt:<br> ![01Mini](docs/pictures/konfigurator_miniZB.jpg)
**14** | **Mülleimer** | Hier können angelegte Objekte wieder gelöscht werden
**15** | **MQTT Base Topic** | Das Topic, welches Ihr in der configuration.yaml hinterlegt habt. <br> **WICHTIG: Bei Anlage des Konfigurators wird automatisch "zigbee2mqtt" eingetragen. Solltet Ihr ein anderes Topic gewählt haben, müsst Ihr dies hier anpassen.**
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