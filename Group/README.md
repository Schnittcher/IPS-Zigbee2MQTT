# Group
   Mit dieser INstanz werden die Geräte von Zigbee2MQTT in IP-Symcon abgebildet.
     
   ## Inhaltverzeichnis
   1. [Konfiguration](#1-konfiguration)
   2. [Funktionen](#2-funktionen)
   
   ## 1. Konfiguration
   
   Feld | Beschreibung
   ------------ | -------------
   MQTT Base Topic | Hier wird das Base Topic von Zigbee2MQTT eingetragen, dieses wird standardmäßig über den Konfigurator gesetzt.
   MQTT Topic | Hier wird der Gerätenamen eingetragen, dieser wird auch standardmäßig über den Konfigurator gesetzt.
   Geräteinformationen abrufen | Mit diesem Button können alle Variablen für das Gerät abgerufen werden, dies geschieht ebenfall Anlegen über den Konfigurator automatisch oder beim Speichern der Form.
   
   ## 2. Funktionen

   ```php
   RequestAction($VariablenID, $Value);
   ```
   Mit dieser Funktion können alle Aktionen einer Variable ausgelöst werden.

   **Beispiel:**
   
   Variable ID Status: 12345
   ```php
   RequestAction(12345, true); //Einschalten
   RequestAction(12345, false); //Ausschalten
   ```