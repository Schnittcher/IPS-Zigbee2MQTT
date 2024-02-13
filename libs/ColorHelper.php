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

    protected function RGBToCIE($red, $green, $blue)
    {
        $red = ($red > 0.04045) ? pow(($red + 0.055) / (1.0 + 0.055), 2.4) : ($red / 12.92);
        $green = ($green > 0.04045) ? pow(($green + 0.055) / (1.0 + 0.055), 2.4) : ($green / 12.92);
        $blue = ($blue > 0.04045) ? pow(($blue + 0.055) / (1.0 + 0.055), 2.4) : ($blue / 12.92);

        $X = $red * 0.664511 + $green * 0.154324 + $blue * 0.162028;
        $Y = $red * 0.283881 + $green * 0.668433 + $blue * 0.047685;
        $Z = $red * 0.000088 + $green * 0.072310 + $blue * 0.986039;
        $this->SendDebug('RGBToCIE', 'X: ' . $X . ' Y: ' . $Y . ' Z: ' . $Z, 0);

        $cie['x'] = round(($X / ($X + $Y + $Z)), 4);
        $cie['y'] = round(($Y / ($X + $Y + $Z)), 4);

        return $cie;
    }

    protected function CIEToRGB($x, $y, $brightness = 255)
    {
        $z = 1.0 - $x - $y;
        $Y = $brightness / 255;
        $X = ($Y / $y) * $x;
        $Z = ($Y / $y) * $z;

        $red = $X * 1.656492 - $Y * 0.354851 - $Z * 0.255038;
        $green = -$X * 0.707196 + $Y * 1.655397 + $Z * 0.036152;
        $blue = $X * 0.051713 - $Y * 0.121364 + $Z * 1.011530;

        if ($red > $blue && $red > $green && $red > 1.0) {
            $green = $green / $red;
            $blue = $blue / $red;
            $red = 1.0;
        } elseif ($green > $blue && $green > $red && $green > 1.0) {
            $red = $red / $green;
            $blue = $blue / $green;
            $green = 1.0;
        } elseif ($blue > $red && $blue > $green && $blue > 1.0) {
            $red = $red / $blue;
            $green = $green / $blue;
            $blue = 1.0;
        }
        $red = $red <= 0.0031308 ? 12.92 * $red : (1.0 + 0.055) * $red ** (1.0 / 2.4) - 0.055;
        $green = $green <= 0.0031308 ? 12.92 * $green : (1.0 + 0.055) * $green ** (1.0 / 2.4) - 0.055;
        $blue = $blue <= 0.0031308 ? 12.92 * $blue : (1.0 + 0.055) * $blue ** (1.0 / 2.4) - 0.055;

        $red = ceil($red * 255);
        $green = ceil($green * 255);
        $blue = ceil($blue * 255);

        $color = sprintf('#%02x%02x%02x', $red, $green, $blue);

        return $color;
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
                case 0: $r = $brightness; $g = $t; $b = $p; break;
                case 1: $r = $q; $g = $brightness; $b = $p; break;
                case 2: $r = $p; $g = $brightness; $b = $t; break;
                case 3: $r = $p; $g = $q; $b = $brightness; break;
                case 4: $r = $t; $g = $p; $b = $brightness; break;
                default: $r = $brightness; $g = $p; $b = $q; break;
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
            $hue = 60 * (($G - $B) / $delta);
        } elseif ($max == $G) {
            $hue = 60 * (($B - $R) / $delta) + 120;
        } elseif ($max == $B) {
            $hue = 60 * (($R - $G) / $delta) + 240;
        }
        if ($hue < 0) {
            $hue += 360;
        }
        $this->SendDebug(__FUNCTION__ . ' Output HSB', "Hue: $hue, Saturation: $saturation, Brightness: $brightness", 0);
        return ['hue' => $hue, 'saturation' => $saturation, 'brightness' => $brightness];
    }
}
