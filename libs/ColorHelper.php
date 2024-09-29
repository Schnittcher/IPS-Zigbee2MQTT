<?php

declare(strict_types=1);

namespace Zigbee2MQTT;

trait ColorHelper
{
    protected function RGBToHSL($r, $g, $b)
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $hue = $saturation = $lightness = ($max + $min) / 2;

        if ($max == $min) {
            $hue = $saturation = 0; // Monochrome Farben
        } else {
            $delta = $max - $min;
            $saturation = $lightness > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);

            switch ($max) {
                case $r:
                    $hue = ($g - $b) / $delta + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $hue = ($b - $r) / $delta + 2;
                    break;
                case $b:
                    $hue = ($r - $g) / $delta + 4;
                    break;
            }
            $hue /= 6;
        }

        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: Output HSL', "Hue: " . ($hue * 360) . ", Saturation: " . ($saturation * 100) . ", Lightness: " . ($lightness * 100), 0);

        return [
            'hue' => round($hue * 360, 2),
            'saturation' => round($saturation * 100, 2),
            'lightness' => round($lightness * 100, 2),
        ];
    }

    protected function HSLToRGB($h, $s, $l)
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s == 0) {
            $r = $g = $b = $l * 255; // Monochrome Farben
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hueToRGB($p, $q, $h + 1 / 3);
            $g = $this->hueToRGB($p, $q, $h);
            $b = $this->hueToRGB($p, $q, $h - 1 / 3);
        }

        $RGB = [
            'r' => round($r * 255),
            'g' => round($g * 255),
            'b' => round($b * 255),
        ];

        // Debug-Ausgabe für die berechneten RGB-Werte
        $this->SendDebug(__FUNCTION__ . ' :: ' . __LINE__ . ' :: HSL to RGB Conversion', 'H: ' . ($h * 360) . ', S: ' . ($s * 100) . ', L: ' . ($l * 100) . ' => R: ' . $RGB['r'] . ', G: ' . $RGB['g'] . ', B: ' . $RGB['b'], 0);

        return $RGB;
    }

    private function hueToRGB($p, $q, $t)
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }
        return $p;
    }

    protected function HexToRGB($value)
    {
        $RGB = [];
        $RGB[0] = (($value >> 16) & 0xFF);
        $RGB[1] = (($value >> 8) & 0xFF);
        $RGB[2] = ($value & 0xFF);
        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: HexToRGB', 'R: ' . $RGB[0] . ' G: ' . $RGB[1] . ' B: ' . $RGB[2], 0);
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

    protected function HSVToRGB($hue, $saturation, $value)
    {
        $hue /= 360;
        $saturation /= 100;
        $value /= 100;
        $i = floor($hue * 6);
        $f = $hue * 6 - $i;
        $p = $value * (1 - $saturation);
        $q = $value * (1 - $f * $saturation);
        $t = $value * (1 - (1 - $f) * $saturation);
        switch ($i % 6) {
            case 0: $r = $value; $g = $t; $b = $p; break;
            case 1: $r = $q; $g = $value; $b = $p; break;
            case 2: $r = $p; $g = $value; $b = $t; break;
            case 3: $r = $p; $g = $q; $b = $value; break;
            case 4: $r = $t; $g = $p; $b = $value; break;
            case 5: $r = $value; $g = $p; $b = $q; break;
        }
        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);
        $colorHSV = sprintf('#%02x%02x%02x', $r, $g, $b);
        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: HSVToRGB', 'R: ' . $r . ' G: ' . $g . ' B: ' . $b, 0);
        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: HSVToRGB', 'HSV ' . $colorHSV, 0);

        return $colorHSV;
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
        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: Output HSB', "Hue: $hue, Saturation: $saturation, Brightness: $brightness", 0);
        return ['hue' => $hue, 'saturation' => $saturation, 'brightness' => $brightness];
    }

    protected function xyToHEX($x, $y, $bri)
    {
        // Berechnung der XYZ-Werte
        $z = 1 - $x - $y;
        $Y = $bri / 254; // Brightness Koeffizient.
        if ($y == 0) {
            $X = 0;
            $Z = 0;
        } else {
            $X = ($Y / $y) * $x;
            $Z = ($Y / $y) * $z;
        }

        // Umwandlung in sRGB D65 (offizielle Formel von Philips Hue)
        $r = $X * 1.656492 - $Y * 0.354851 - $Z * 0.255038;
        $g = -$X * 0.707196 + $Y * 1.655397 + $Z * 0.036152;
        $b = $X * 0.051713 - $Y * 0.121364 + $Z * 1.011530;

        // Gammakorrektur rückgängig machen
        $r = ($r <= 0.0031308 ? 12.92 * $r : (1.055) * pow($r, (1 / 2.4)) - 0.055);
        $g = ($g <= 0.0031308 ? 12.92 * $g : (1.055) * pow($g, (1 / 2.4)) - 0.055);
        $b = ($b <= 0.0031308 ? 12.92 * $b : (1.055) * pow($b, (1 / 2.4)) - 0.055);

        // Berechnung des RGB-Wertes
        $r = ($r < 0 ? 0 : round($r * 255));
        $g = ($g < 0 ? 0 : round($g * 255));
        $b = ($b < 0 ? 0 : round($b * 255));

        $r = ($r > 255 ? 255 : $r);
        $g = ($g > 255 ? 255 : $g);
        $b = ($b > 255 ? 255 : $b);

        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: RGB', 'R: ' . $r . ' G: ' . $g . ' B: ' . $b, 0);

        $color = sprintf('#%02x%02x%02x', $r, $g, $b);
        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: colorHEX', $color, 0);

        return $color;
    }

    protected function RGBToXy($RGB)
    {
        // RGB in Xy-Farbraum konvertieren
        $RGB = sprintf('#%02x%02x%02x', $RGB[0], $RGB[1], $RGB[2]);
        $r = hexdec(substr($RGB, 1, 2));
        $g = hexdec(substr($RGB, 3, 2));
        $b = hexdec(substr($RGB, 5, 2));

        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;

        $r = ($r > 0.04045 ? pow(($r + 0.055) / 1.055, 2.4) : ($r / 12.92));
        $g = ($g > 0.04045 ? pow(($g + 0.055) / 1.055, 2.4) : ($g / 12.92));
        $b = ($b > 0.04045 ? pow(($b + 0.055) / 1.055, 2.4) : ($b / 12.92));

        $X = $r * 0.664511 + $g * 0.154324 + $b * 0.162028;
        $Y = $r * 0.283881 + $g * 0.668433 + $b * 0.047685;
        $Z = $r * 0.000088 + $g * 0.072310 + $b * 0.986039;

        if (($X + $Y + $Z) == 0) {
            $x = 0;
            $y = 0;
        } else {
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
        $this->SendDebug(__FUNCTION__ . ' :: '. __LINE__.' :: RGBToXYX', json_encode($cie), 0);

        return $cie;
    }
}
