[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/IPS-Zigbee2MQTT/workflows/Check%20Style/badge.svg)](https://github.com/Schnittcher/IPS-Zigbee2MQTT/actions)

# Zigbee2MQTT
   Anbindung von www.zigbee2mqtt.io an IP-Symcon.

   ## Inhaltverzeichnis
   1. [Voraussetzungen](#1-voraussetzungen)
   2. [Enthaltene Module](#2-enthaltene-module)
   3. [Installation](#3-installation)
   4. [Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)
   5. [Spenden](#5-spenden)
   6. [Lizenz](#6-lizenz)

## 1. Voraussetzungen

* mindestens IPS Version 6.1
* MQTT Server (IPS Modul) oder externer MQTT-Broker (z.B. Mosquitto)
* Installierte Symcon Erweiterung in Zigbee2MQTT

**Die Verknüpfung zwischen Coordinator und Symcon erfolgt nach dem folgenden Schema:**

Coordinator → zigbee2mqtt → MQTT-Server (Symcon oder extern) → Symcon-Zigbee2MQTT

**Coordinatoren können sowohl per USB als auch per LAN/WLAN in das System eingebunden werden. Hier bitte unbedingt die [Anleitung zu Zigbee2MQTT](https://www.zigbee2mqtt.io/guide/getting-started/) studieren!**

## 2. Enthaltene Module

* [Bridge](Bridge/README.md)
* [Configurator](Configurator/README.md)
* [Device](Device/README.md)
* [Group](Group/README.md)

## 3. Installation
Über den Module Store.

**Achtung**

Ab der Version 4.0 dieses Moduls werden einige Variablen geändert, dadurch können Archivdaten verloren gehen!
Die Nachfolgende Tabelle zeigt welche Variablen sich verändern.


Ident | Alter Variablentyp | Altes Profil | Neuer Variablentyp | Neues Profil |
------------ | ------------- | ------------ | ------------- | -------------
Z2M_Brightness | Integer | Z2M.Intensity.254 | variabel | Integer
Z2M_ColorTemp | Integer | Z2M.ColorTemperature | variabel | Integer
Z2M_CurrentHeatingSetpoint | Float | ~Temperature.Room | variabel | Float
Z2M_OccupiedHeatingSetpoint | Float | ~Temperature.Room | variabel | Float
Z2M_SystemMode | Integer | Z2M.SystemMode | variabel | String
Z2M_Preset | Integer | Z2M.ThermostatPreset | variabel | String
Z2M_RunningState | Integer | Z2M.Intensity.254 | variabel | String
Z2M_Battery_Low | Boolean | - | ~Battery | Boolean
Z2M_WaterLeak | Boolean | - | ~Alert | Integer
Z2M_Contact | Boolean | - | ~Window.Reversed | Boolean
Z2M_Consumer_Connected | Boolean | Z2M.ConsumerConnected | variabel | Boolean
Z2M_PowerOutageMemory | Integer | Z2M.PowerOutageMemory | variabel | variabel
Z2M_MotionSensitivity | Integer | Z2M.Sensitivity | variabel | String
Z2M_Linkquality | Integer | - | variabel | Integer
Z2M_VOC | Float | - | variabel | Integer
Z2M_Formaldehyd | Float | - | variabel | Integer
Z2M_BoostTime | Integer | - | variabel | Integer

### 3.1 Installation der IP-Symcon Extension in Zigbee2MQTT

Um Devices im Konfigurator anzeigen zu können und diese anzulegen und Eigenschaften abholen zu können, benötigt das Modul eine Extension in Z2M.
Dazu die Datei [IPSymconExtension.js](libs/IPSymconExtension.js) in das Verzeichnis **"/opt/zigbee2mqtt/data/extension"** auf dem Rechner, wo Z2M installiert ist ablegen.<br>

Alternativ kann über das Frontend die Datei angelegt werden und der Inhalt eingefügt werden.

Dazu geht Ihr auf den Punkt "Erweiterungen": <br> ![Erweiterungen](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Erweiterung_Z2M.jpg)<br> Legt eine neue Extension über den Plus-Button an:  ![Erweiterungen](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Erweiterung_erstellen_1.jpg)<br> Dann gebt Ihr der Erweiterung den Namen: symcon.js:<br> ![Erweiterung erstellen](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Erweiterung_erstellen.jpg)<br> Danach öffnet sich ein Fenster für die Code-Eingabe:<br> ![Code Eingabe](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Erweiterung_code.jpg). <br>Den dort bereits enthaltenen Code bitte komplett löschen. Danach wird der Code aus [IPSymconExtension.js](libs/IPSymconExtension.js) herein kopiert und gespeichert.<br> Danach Z2M bitte neu starten: <br>![Code Eingabe](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Erweiterung_neustart.jpg)<br>


## 4. Konfiguration in IP-Symcon
Bitte den einzelnen Modulen entnehmen.

# 5. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
