[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/IPS-Zigbee2MQTT/workflows/Check%20Style/badge.svg)](https://github.com/Schnittcher/IPS-Zigbee2MQTT/actions)

# Zigbee2MQTT
   Anbindung von www.zigbee2mqtt.io an IP-Symcon.

   ## Inhaltverzeichnis
- [Zigbee2MQTT](#zigbee2mqtt)
  - [Inhaltverzeichnis](#inhaltverzeichnis)
  - [1. Voraussetzungen](#1-voraussetzungen)
  - [2. Enthaltene Module](#2-enthaltene-module)
  - [3. Installation](#3-installation)
    - [3.1 Installation der IP-Symcon Extension in Zigbee2MQTT](#31-installation-der-ip-symcon-extension-in-zigbee2mqtt)
  - [4. Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)
- [5. Spenden](#5-spenden)
  - [6. Lizenz](#6-lizenz)

## 1. Voraussetzungen

* mindestens IPS Version 6.1
* MQTT Server (IPS Modul) oder externer MQTT-Broker (z.B. Mosquitto)
* Installierte Symcon Erweiterung in Zigbee2MQTT [siehe hier](#31-installation-der-ip-symcon-extension-in-zigbee2mqtt)


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

Für den Konfigurator in Symcon und auch für das korrekte Anlagen von Instanzen wird eine Erweiterung (Extension) in Z2M benötigt.

**Folgende Varianten zum Einreichten der Erweiterung sind möglich:**  

1. Über die [Bridge](Bridge/README.md)-Instanz in Symcon (empfohlen)
2. Über das Z2M Frontend den Inhalt der Datei [IPSymconExtension.js](libs/IPSymconExtension.js) unter dem Menüpunkt Erweiterungen hinzufügen.
3.  Die Datei [IPSymconExtension.js](libs/IPSymconExtension.js) in das Verzeichnis **"/opt/zigbee2mqtt/data/extension"** auf dem Rechner, wo Z2M installiert ist ablegen. (Experten)
   
**Anleitungen:**  

1. Ist in der Dokumentation der [Bridge](Bridge/README.md)-Instanz beschrieben.  
2. Das Frontend von Z2M im Browser öffnen und den Punkt "Erweiterungen" wählen.  
   ![Erweiterungen](/docs/pictures/Erweiterung_Z2M.jpg)  
   Eine neue Extension über den Plus-Button anlegen:  
   ![Erweiterungen](/docs/pictures/Erweiterung_erstellen_1.jpg)  
   Der Erweiterung einen Namen geben, z.B. symcon.js:  
   ![Erweiterung erstellen](/docs/pictures/Erweiterung_erstellen.jpg)  
   Es öffnet sich ein Fenster für die Code-Eingabe:  
   ![Code Eingabe](/docs/pictures/Erweiterung_code.jpg)  
   Den dort bereits enthaltenen Code bitte komplett löschen.  
   Anschließend der Inhalt (Code) aus [IPSymconExtension.js](libs/IPSymconExtension.js) einfügen und speichern.  
   Danach sollte Z2M neu gestartet werden:  
   ![Code Eingabe](/docs/pictures/Erweiterung_neustart.jpg)  
3. Sollte nur von versierten Usern gemacht werden, da es aufgrund der vielzahl an Systemen unter welchen Z2M laufen kann, keine global gültige Anleitung gibt.  

## 4. Konfiguration in IP-Symcon
Bitte den einzelnen Modulen entnehmen:

* [Bridge](Bridge/README.md)
* [Configurator](Configurator/README.md)
* [Device](Device/README.md)
* [Group](Group/README.md)

# 5. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 6. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)