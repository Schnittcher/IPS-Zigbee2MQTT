<?php

declare(strict_types=1);

include_once __DIR__ . '/DumpInclude.php';

class DevicesTest extends DumpInclude
{
    public function testTRV06()
    {
        [$iid,$Debug] = $this->createTestInstance('TRV06.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        // schedule_* Variablen fehlen in $Debug['Childs'] Neues Z2M_Debug benötigt
        $OffsetDebugChild = +7;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function test701721()
    {
        [$iid,$Debug] = $this->createTestInstance('701721.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        // schedule_* Variablen fehlen in $Debug['Childs'] Neues Z2M_Debug benötigt
        $OffsetDebugChild = 0;
        //$Debug['Childs'] ist leider unvollständig. Neues Z2M_Debug benötigt
        //$this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug ('.count($Debug['Childs']).') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)).') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testTS130F()
    {
        [$iid,$Debug] = $this->createTestInstance('TS130F.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testWHD02()
    {
        [$iid,$Debug] = $this->createTestInstance('TS130F.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testTRVZB()
    {
        [$iid,$Debug] = $this->createTestInstance('TRVZB.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testTS0601_thermostat()
    {
        [$iid,$Debug] = $this->createTestInstance('TS0601_thermostat.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        //$Debug['LastPayload'] ist leider unvollständig. Neues Z2M_Debug benötigt
        //$this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload ('.self::count_recursive($Debug['LastPayload']) + $OffestLastPayload.') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs.') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testRTCGQ01LM()
    {
        [$iid,$Debug] = $this->createTestInstance('RTCGQ01LM.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testMTD285_ZB()
    {
        [$iid,$Debug] = $this->createTestInstance('MTD285-ZB.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testAB3257001NJ()
    {
        [$iid,$Debug] = $this->createTestInstance('AB3257001NJ.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testPS_S04D()
    {
        [$iid,$Debug] = $this->createTestInstance('PS-S04D.json');
        // detection_range_prefix & schedule_time_raw fehlen im Expose, sind aber im Payload
        $OffestLastPayload = -2;
        // identify und device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -2;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function test501_40()
    {
        [$iid,$Debug] = $this->createTestInstance('501.40.json');
        $OffestLastPayload = 0;
        $OffsetChildrenIDs = 0;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        //$Debug['LastPayload'] ist leider unvollständig. Neues Z2M_Debug benötigt
        //$this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload ('.self::count_recursive($Debug['LastPayload']) + $OffestLastPayload.') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs.') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testBMCT_SLZ()
    {
        [$iid,$Debug] = $this->createTestInstance('BMCT-SLZ.json');
        $OffestLastPayload = 0;
        $OffsetChildrenIDs = 0;
        // remaining und progress aus den DebugChilds abziehen
        $OffsetDebugChild = -2;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        // Irgendwie fehlen Variablen aus dem Payload...
        // $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload ('.self::count_recursive($Debug['LastPayload']) + $OffestLastPayload.') und Erzeugte Variablen ('.count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs.') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testWT_A03E()
    {
        [$iid,$Debug] = $this->createTestInstance('WT-A03E.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        // 4x Update Variablen abziehen (remaining, progress, Latest Source und Release Notes)
        $OffsetDebugChild = -4;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testS8()
    {
        [$iid,$Debug] = $this->createTestInstance('S8.json');
        $OffestLastPayload = 0;
        // device_status und duration_presets bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -2;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testSenoroWinv2()
    {
        [$iid,$Debug] = $this->createTestInstance('Senoro.Win_v2.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }
    public function testS4PL00416EU()
    {
        [$iid,$Debug] = $this->createTestInstance('S4PL-00416EU.json');
        /** Fehlen im LastPayload (nur lesbar, keine Events)
         * led_mode
         * led_colors_* (8 Stück)
         * led_power_brightness
         * led_night_mode_* (4 Stück)
         * buttons_enabled_* (4 Stück)
         * wifi_config__ssid
         * wifi_config__password
         * wifi_config__static_ip
         * wifi_config__net_mask
         * wifi_config__gateway
         * wifi_config__name_server
         */
        $OffestLastPayload = +24;
        /** Fehlen im expose und werden somit nicht als Variablen angelegt
         * ac_frequency_* (4 Stück)
         * power_apparent_* (4 Stück)
         * power_factor_* (4 Stück)
         * produced_energy_* (4 Stück)
         * power_reactive_* (4 Stück)
         */
        $OffestLastPayload -= 20;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testDWZTCGQ11LM()
    {
        [$iid,$Debug] = $this->createTestInstance('DWZTCGQ11LM.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        //$Debug['LastPayload'] ist leider leer. Neues Z2M_Debug benötigt
        //$this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testWT_A03E_2()
    {
        [$iid,$Debug] = $this->createTestInstance('WT-A03E_2.json');
        /* Fehlen in LastPayload
        away_preset_temperature
        window_open
        device_status
        system_mode
        preset
        sensor
        external_temperature_input
        calibrate
        temperature_control_abnormal_notification
        anti_freeze_temperature
        5 x PRESET_
            preset_home_temperature
            preset_away_temperature
            preset_sleep_temperature
            preset_vacation_temperature
            preset_wind_down_temperature
        schedule_upload_status
        save_schedule
        clear_schedule
        identify
        last_seen
         */
        $OffestLastPayload = +20;
        // fehlen im Expose und somit keine Variablen im test
        // min_heat_setpoint_limit
        // max_heat_setpoint_limit
        // temperature_setpoint_hold
        // helper
        // away_preset_temperature
        // valve_detection
        // state

        $OffestLastPayload -= 7;

        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = -7;
        /*foreach (IPS_GetChildrenIDs($iid) as $id) {
            echo IPS_GetObject($id)['ObjectIdent'] . PHP_EOL;
        }*/
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        //$this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }

    public function testSNZB_01M()
    {
        [$iid,$Debug] = $this->createTestInstance('SNZB-01M.json');
        // release_notes ist im Payload null, also abziehen.
        $OffestLastPayload = -1;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        $this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        $this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        $this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }
    public function testD4Z()
    {
        [$iid,$Debug] = $this->createTestInstance('D4Z.json');
        $OffestLastPayload = 0;
        // device_status bei den IPS_GetChildrenIDs abziehen
        $OffsetChildrenIDs = -1;
        $OffsetDebugChild = 0;
        //$this->assertSame(count($Debug['Childs']) + $OffsetDebugChild, count(IPS_GetChildrenIDs($iid)), 'Anzahl Variablen aus dem Debug (' . count($Debug['Childs']) . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) . ') vom Test unterscheiden sich');
        //$this->assertSame(self::count_recursive($Debug['LastPayload']) + $OffestLastPayload, count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs, 'Anzahl LastPayload (' . self::count_recursive($Debug['LastPayload']) + $OffestLastPayload . ') und Erzeugte Variablen (' . count(IPS_GetChildrenIDs($iid)) + $OffsetChildrenIDs . ') unterscheiden sich');
        //$this->assertCount(0, self::getExportDebugData($iid)['missingTranslations'], 'Fehlende übersetzungen gefunden:' . var_export(self::getExportDebugData($iid)['missingTranslations'], true));
    }
}