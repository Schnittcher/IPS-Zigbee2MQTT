# Bridge  <!-- omit in toc -->
   Modul für alle Systemweiten Funktionen von Zigbee2MQTT


## Inhaltverzeichnis <!-- omit in toc -->
- [1. Gruppen in Z2M](#1-gruppen-in-z2m)
- [2. Konfiguration](#2-konfiguration)
- [3. Funktionen](#3-funktionen)

                                                                                                                                                                          |
## 3. Instanz-Funktionen

```php
bool Z2M_InstallSymconExtension(int $InstanzID);
```
Die aktuelle Symcon Erweiterung wird in Z2M installiert.  

--

```php
bool Z2M_SetLastSeen(int $InstanzID);
```
Die Konfiguration der `last_seen` Einstellung in Z2M wird auf `epoch` verändert, damit die Instanzen in Symcon den Wert korrekt darstellen können.  

--
```php
bool Z2M_SetPermitJoin(int $InstanzID, bool $PermitJoin);
```

--
```php
bool Z2M_SetLogLevel(int $InstanzID, string $LogLevel);
```

--
```php
bool Z2M_Restart(int $InstanzID);
```

--
```php
bool Z2M_CreateGroup(int $InstanzID, string $GroupName);
```

--
```php
bool Z2M_DeleteGroup(int $InstanzID, string $GroupName);
```

--
```php
bool Z2M_RenameGroup(int $InstanzID, string $OldName, string $NewName);
```

--
```php
bool Z2M_AddDeviceToGroup(int $InstanzID, string $GroupName, string $DeviceName);
```

--
```php
bool Z2M_RemoveDeviceFromGroup(int $InstanzID, string $GroupName, string $DeviceName);
```

--
```php
bool Z2M_RemoveAllDevicesFromGroup(int $InstanzID, string $GroupName);
```

--
```php
bool Z2M_Bind(int $InstanzID, string $SourceDevice, string $TargetDevice);
```

--
```php
bool Z2M_Unbind(int $InstanzID, string $SourceDevice, string $TargetDevice);
```

--
```php
bool Z2M_RequestNetworkmap(int $InstanzID);
```

--
```php
bool Z2M_RenameDevice(int $InstanzID, string $OldDeviceName, string $NewDeviceName);
```

--
```php
bool Z2M_RemoveDevice(int $InstanzID, string $DeviceName);
```

--
```php
bool Z2M_CheckOTAUpdate(int $InstanzID, string $DeviceName);
```

--
```php
bool Z2M_PerformOTAUpdate(int $InstanzID, string $DeviceName);
```
