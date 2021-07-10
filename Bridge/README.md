# IPS-Z2MBridge
   Anbindung von www.zigbee2mqtt.io an IP-Symcon.
   Modul für die Zigbee2MQTT Bridge
     
   ## Inhaltverzeichnis
   1. [Konfiguration](#1-konfiguration)
   2. [Funktionen](#2-funktionen)
   
   ## 1. Konfiguration
   
   Feld | Beschreibung
   ------------ | -------------
   MQTT Topic | Hier wird das Topic von der Bridge eingetragen.
   
   ## 2. Funktionen

   **Z2M_AddGroup(int $InstanceID, string $group_name, string $friendly_name)**\
   Mit dieser Funktion kann ein Gerät in eine Gruppe hinzugefügt werden.
   ```php
   Z2M_AddGroup(25537, "IkeaLeds", "IkeaLEDKueche");
   ```
   
   **Z2M_RemoveGroup(int $InstanceID, string $group_name, string $friendly_name)**\
   Mit dieser Funktion kann ein Gerät aus einer Gruppe gelöscht werden.
   ```php
   Z2M_RemoveGroup(25537, "IkeaLeds", "IkeaLEDKueche");
   ```
   
  **Z2M_RemoveAllGroup(int $InstanceID, string $group_name, string $friendly_name)**\
  Mit dieser Funktion kann ein Gerät aus allen Gruppen gelöscht werden.
  ```php
  Z2M_RemoveGroup(25537, "IkeaLeds", "IkeaLEDKueche");
  ```
  
  **Z2M_Bind(int $InstanceID, string $source_device, string $target_device)**\
  Diese Funktion verbindet zwei Geräte miteinander.
  ```php
  Z2M_Bind(25537, "IkeaLEDKueche1", "IkeaLEDKueche2");
  ```
  
  **Z2M_Unbind(int $InstanceID, string $source_device, string $target_device)**\
  Diese Funktion entfernt eine Verbindung zwischen zwei Geräten.
  ```php
  Z2M_Unind(25537, "IkeaLEDKueche1", "IkeaLEDKueche2");
  ```
  
  **Z2M_getGroupMembership(int $InstanceID, string $friendly_name)**\
  Mit dieser Funktion kann die Gruppenzugehörigkeit von Geräten angezeigt werden.
  ```php
  Z2M_getGroupMembership(25537, "IkeaLEDKueche1");
  ```
  
  **Z2M_Networkmap(int $InstanceID)**\
  Diese Funktion generiert eine Netzwerkkarte. (Noch nicht fertig) 
  ```php
  Z2M_Networkmap(25537);
  ```
  
  **Z2M_RenameDevice(int $InstanceID,$old_friendly_name, string $new_friendly_name)**\
  Mit dieser Funktion ist es möglich, die Geräte umzubenennen.
  ```php
  Z2M_RenameDevice(25537, "IkeaLEDKueche1", "IkeaLedKueche2");
  ```
  
  **Z2M_BanDevice(int $InstanceID, string $new_friendly_name)**\
  Bit dieser Funktion ist es möglich Geräte zu bannen.
  ```php
  Z2M_BanDevice(25537, "IkeaLEDKueche1");
  ```
  
  **Z2M_RemoveDevice(int $InstanceID, string $new_friendly_name)**\
  Mit dieser Funktion ist es möglich, Geräte zu löschen.
  ```php
  Z2M_RemoveDevice(25537, "IkeaLEDKueche1");
  ```