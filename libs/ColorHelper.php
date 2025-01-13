<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

trait ColorHelper
{
    protected function HexToRGB($value)
    {
        $RGB = [];
        $RGB[0] = (($value >> 16) & 0xFF);
        $RGB[1] = (($value >> 8) & 0xFF);
        $RGB[2] = ($value & 0xFF);
        $this->SendDebug('HexToRGB', 'R: ' . $RGB[0] . ' G: ' . $RGB[1] . ' B: ' . $RGB[2], 0);
        return $RGB;
    }

    protected function HSToRGB($hue, $saturation)
    {
        $hue /= 360;
        $saturation /= 100;
        $brightness = 1;
        if ($saturation == 0) {
            $r = $g = $b = $brightness;
        } else {
            $hue *= 6;
            $i = floor($hue);
            $f = $hue - $i;
            $p = $brightness * (1 - $saturation);
            $q = $brightness * (1 - $saturation * $f);
            $t = $brightness * (1 - $saturation * (1 - $f));
            switch ($i) {
                case 0: $r = $brightness;
                    $g = $t;
                    $b = $p;
                    break;
                case 1: $r = $q;
                    $g = $brightness;
                    $b = $p;
                    break;
                case 2: $r = $p;
                    $g = $brightness;
                    $b = $t;
                    break;
                case 3: $r = $p;
                    $g = $q;
                    $b = $brightness;
                    break;
                case 4: $r = $t;
                    $g = $p;
                    $b = $brightness;
                    break;
                default: $r = $brightness;
                    $g = $p;
                    $b = $q;
                    break;
            }
        }
        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);
        $colorHS = sprintf('#%02x%02x%02x', $r, $g, $b);
        return $colorHS;
    }

    protected function RGBToHSB($R, $G, $B)
    {
        $R /= 255.0;
        $G /= 255.0;
        $B /= 255.0;
        $max = max($R, $G, $B);
        $min = min($R, $G, $B);
        $delta = $max - $min;
        $brightness = $max * 100;
        $saturation = ($max == 0) ? 0 : ($delta / $max) * 100;
        $hue = 0;
        if ($delta != 0) {
            if ($max == $R) {
                $hue = 60 * (($G - $B) / $delta);
            } elseif ($max == $G) {
                $hue = 60 * (($B - $R) / $delta) + 120;
            } elseif ($max == $B) {
                $hue = 60 * (($R - $G) / $delta) + 240;
            }
            if ($hue < 0) {
                $hue += 360;
            }
        }
        $this->SendDebug(__FUNCTION__ . ' Output HSB', "Hue: $hue, Saturation: $saturation, Brightness: $brightness", 0);
        return ['hue' => $hue, 'saturation' => $saturation, 'brightness' => $brightness];
    }

    protected function xyToHEX($x, $y, $bri)
    {

        // Calculate XYZ values
        $z = 1 - $x - $y;
        $Y = $bri / 254; // Brightness coeff.
        if ($y == 0) {
            $X = 0;
            $Z = 0;
        } else {
            $X = ($Y / $y) * $x;
            $Z = ($Y / $y) * $z;
        }

        // Convert to sRGB D65 (official formula on meethue)
        // old formula
        // $r = $X * 3.2406 - $Y * 1.5372 - $Z * 0.4986;
        // $g = - $X * 0.9689 + $Y * 1.8758 + $Z * 0.0415;
        // $b = $X * 0.0557 - $Y * 0.204 + $Z * 1.057;
        // formula 2016
        $r = $X * 1.656492 - $Y * 0.354851 - $Z * 0.255038;
        $g = -$X * 0.707196 + $Y * 1.655397 + $Z * 0.036152;
        $b = $X * 0.051713 - $Y * 0.121364 + $Z * 1.011530;

        // Apply reverse gamma correction
        $r = ($r <= 0.0031308 ? 12.92 * $r : (1.055) * pow($r, (1 / 2.4)) - 0.055);
        $g = ($g <= 0.0031308 ? 12.92 * $g : (1.055) * pow($g, (1 / 2.4)) - 0.055);
        $b = ($b <= 0.0031308 ? 12.92 * $b : (1.055) * pow($b, (1 / 2.4)) - 0.055);

        // Calculate final RGB
        $r = ($r < 0 ? 0 : round($r * 255));
        $g = ($g < 0 ? 0 : round($g * 255));
        $b = ($b < 0 ? 0 : round($b * 255));

        $r = ($r > 255 ? 255 : $r);
        $g = ($g > 255 ? 255 : $g);
        $b = ($b > 255 ? 255 : $b);

        // Create a web RGB string (format #xxxxxx)
        $this->SendDebug('RGB', 'R: ' . $r . ' G: ' . $g . ' B: ' . $b, 0);

        //$RGB = "#".substr("0".dechex($r),-2).substr("0".dechex($g),-2).substr("0".dechex($b),-2);
        $color = sprintf('#%02x%02x%02x', $r, $g, $b);
        return $color;
    }

    protected function RGBToXy($RGB)
    {
        // Get decimal RGB
        $RGB = sprintf('#%02x%02x%02x', $RGB[0], $RGB[1], $RGB[2]);
        $r = hexdec(substr($RGB, 1, 2));
        $g = hexdec(substr($RGB, 3, 2));
        $b = hexdec(substr($RGB, 5, 2));

        // Calculate rgb as coef
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;

        // Apply gamma correction
        $r = ($r > 0.04045 ? pow(($r + 0.055) / 1.055, 2.4) : ($r / 12.92));
        $g = ($g > 0.04045 ? pow(($g + 0.055) / 1.055, 2.4) : ($g / 12.92));
        $b = ($b > 0.04045 ? pow(($b + 0.055) / 1.055, 2.4) : ($b / 12.92));

        // Convert to XYZ (official formula on meethue)
        // old formula
        //$X = $r * 0.649926 + $g * 0.103455 + $b * 0.197109;
        //$Y = $r * 0.234327 + $g * 0.743075 + $b * 0.022598;
        //$Z = $r * 0        + $g * 0.053077 + $b * 1.035763;
        // formula 2016
        $X = $r * 0.664511 + $g * 0.154324 + $b * 0.162028;
        $Y = $r * 0.283881 + $g * 0.668433 + $b * 0.047685;
        $Z = $r * 0.000088 + $g * 0.072310 + $b * 0.986039;

        // Calculate xy and bri
        if (($X + $Y + $Z) == 0) {
            $x = 0;
            $y = 0;
        } else { // round to 4 decimal max (=api max size)
            $x = round($X / ($X + $Y + $Z), 4);
            $y = round($Y / ($X + $Y + $Z), 4);
        }
        $bri = round($Y * 254);
        if ($bri > 254) {
            $bri = 254;
        }

        $cie['x'] = $x;
        $cie['y'] = $y;
        $cie['bri'] = $bri;
        return $cie;
    }
}
