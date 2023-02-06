# Group
   Mit dieser Instanz werden die Gruppen von Zigbee2MQTT in IP-Symcon abgebildet.

   ## Inhaltverzeichnis
   1. [Grundlagen Gruppen](#1-Gruppen)
   2. [Konfiguration](#2-konfiguration)
   3. [Funktionen](#3-funktionen)

   ## 1. Gruppen in Z2M
   In Z2M gibt es die Möglichkeit, Devices in Gruppen zusammen zu fassen und diese dann wie ein einzelnes Gerät zu steuern. Sinn macht Dies zum Beispiel, wenn man 5 Deckenstrahler als eine Device ansprechen will:
   ![Gruppen_Übersicht](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Gruppen_Z2M_Übersicht.jpg)
   ![Gruppen_datails](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Gruppen_Z2M_Details.jpg)

   Genauere Informationen gibt es direkt auf der Seite von Zigbee2mqtt: https://www.zigbee2mqtt.io/guide/configuration/devices-groups.html<br>**Wichtig:**<br>Bitte lest Euch in der Z2M-Anleitung die Themen genauestens durch. In Gruppen können nicht alle Eigenschaften der einzelnen Devices bedient werden. Zusätzlich lassen sich über Z2M noch Szenen erstellen, welche den Gruppen oder einzelnen Devices zugeordnet werden können.

   **Das Handling von Gruppen in Symcon ist dem der einzelnen Devices gleich.**

   ## 2. Konfiguration

   ![Konfiguration Device](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Device_Konfiguration.jpg)
   **Nummer** | **Feld** | **Beschreibung**
   ------------ | ------------- | -------------
   **1** | **Gateway konfigurieren** | Hier kann das zur Instanz zugehörige Gateway direkt aufgerufen und bearbeitet werden.
   **2** | **Gateway ändern** | Dient zur Auswahl des zur Instanz zugehörigen Gateways. In der Regel wird dies bei Anlegen der Instanz über den Konfigurator direkt gesetzt.
   **3** | **InstanzID kopieren** | Kopiert die Instanz ID in die Zwischenablage.
   **4** | **Instanzobjekt bearbeiten** | Hier können Objekt-Name und Baum-Position vorgegeben werden. Unter Visuelle Einstellungen finden sich die Icon-Vergabe, Ob das Objekt angezeigt oder versteckt werden soll und ob das Objekt aktiviert sein soll. Unter weitere Einstellungen kann dem Onjekt eine BEschreibung zugefügt werden.
   **5** | **Ereignisse** | Zeigt eine Übersicht, welche Ereignisse mit der Instanz verbunden sind. Über den Button Neu lassen sich neue Ereignisse zu der Instanz einrichten (Ausgelöst, zyklisch oder per Wochenplan). Die zugehörigen Eregnisse können direkt bearbeitet werden. ![Ereignisse](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/Device_Ereignisse.jpg)
   **6** | **Statusvariablen** | Hier lassen sich alle der Instanz zugehörigen Variablen bearbeiten ![Variablen](https://github.com/Burki24/IPS-Zigbee2MQTT/blob/featureWithUserExtension/docs/pictures/device_statusvariablen.jpg)
   **7** | **Debug** | Mit die wichtigste Funktion. Hier werden alle Debug-Informationen der Instanz protokolliert. Wichtig, weil hier auch zu sehen ist, ob Werte des MQTT-Expose nicht zugeordnet werden können, oder noch Profile zu den angelegten Variablen fehlen. Zusätzlich werden hier euch weitere Fehler der Kommunikation zwischen Gateway und Instanz ersichtlich. Sollte es Probleme mit einer Instanz geben, können diese nur adäquat bearbeitet werden, wenn der Meldung (unter Issues oder im Forum) ein Debug beigelegt wird. Dazu bitte im Debug-Fenster auf "Download" gehen und die heruntergeladene Datei als *.zip Datei der Meldung beifügen.
   **8** | **MQTT Base Topic** | Dieses wird vom Konfigurator bei Anlage der Instanz automatisch auf "zigbee2mqtt gesetzt und sollte auch so belassen werden. <br> **Ausnahme:** <br> Ihr habt zwei Zigbee Netzwerke bei Euch. Dann dürfen beide nicht das gleiche Topic haben. <br> Bsp.:<br> Netzwerk 1 hat das standard-Topic zigbee2mqtt. Wenn das zweite Netzwerk ebenfalls auf zigbee2mqtt posted, kommt es zu Fehlermeldungen im 1. Netzwerk über Z2M, da dort die betroffenen Instanzen nicht bekannt sind meldet der Z2M-Dienst "Name unknown Device". Wenn also Beide Netzwerke das gleiche Topic nutzen, werden Aktionen von Symcon aus an beide Z2M-Netzwerke gesendet. Abhilfe schafft hier die Trennung der Netze auch auf MQTT-Ebene (z.B. Z2M-OG für das Netzwerk im Obergeschoss und Z2M-EG für das Netzwerk im Erdgeschoss). Dann weiß Symcon, an welches MQTT-Netzwerk die Aktionen gehen sollen. <br> **Wichtig:** <br>Der Konfigurator erkennt das genutzte Topic nicht selbstständig. Er setzt es IMMER auf zigbee2mqtt. Dann muss es im Nachgang geändert werden.
   **9** | **MQTT Topic** | Das Topic, welches die Instanz in Z2M nutzt. Beim Anlernen von Geräten an Z2m erhält jedes Gerät einen "friendly_name". Standard ist hier die IEEE-Adresse. Dies kann im Nachgang aber geändert werden. Dann erkennt der Konfigurator dies und setzt das geänderte Topic automatisch ein. <br> **Wichtig:** <br>Wird das Topic, oder besser, der friendly_name in Z2M im Nachgang geändert, ändert sich auch das Topic in MQTT. Hier muss in Symcon dann das Topic der Instanz ebenfalls händisch nachgepflegt werden, da es nicht automatisch übernommen wird. Es macht also Sinn, direkt nach dem Anlernen den friendly_name in Z2M anzupassen und dann erst die Instanz in Symcon über den Konfigurator anzulegen.
   **10** | **Geräteinformationen abrufen** | Bei Gruppen kann es passieren, dass nicht alle oder auch keine Eigenschaften (Variablen) bei der Anlage der Instanz angelegt werden. Hier bitte IMMER nach dem Anlegen nochmal die Eigenschaften abrufen.
   ## 3. Funktionen

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