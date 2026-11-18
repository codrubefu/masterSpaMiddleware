<?php

namespace App\Helper;

/**
 * Converts an amount to Romanian words (integer part only), used for
 * trznp.descval.
 */
class MoneyToWords
{
    public static function toWords($number): string
    {
        $number = (int) $number;
        if ($number == 0) {
            return 'zero';
        }
        $units = ['', 'unu', 'doi', 'trei', 'patru', 'cinci', 'șase', 'șapte', 'opt', 'nouă'];
        $teens = [
            'zece',
            'unsprezece',
            'doisprezece',
            'treisprezece',
            'paisprezece',
            'cincisprezece',
            'șaisprezece',
            'șaptesprezece',
            'optsprezece',
            'nouăsprezece'
        ];
        $tens = ['', '', 'douăzeci', 'treizeci', 'patruzeci', 'cincizeci', 'șaizeci', 'șaptezeci', 'optzeci', 'nouăzeci'];
        $hundreds = [
            '',
            'una sută',
            'două sute',
            'trei sute',
            'patru sute',
            'cinci sute',
            'șase sute',
            'șapte sute',
            'opt sute',
            'nouă sute'
        ];
        $result = '';
        // Millions
        if ($number >= 1000000) {
            $millions = intval($number / 1000000);
            if ($millions == 1) {
                $result .= 'un milion ';
            } else {
                $result .= self::convertHundreds($millions, $units, $teens, $tens, $hundreds) . ' milioane ';
            }
            $number %= 1000000;
        }
        // Thousands
        if ($number >= 1000) {
            $thousands = intval($number / 1000);
            if ($thousands == 1) {
                $result .= 'o mie ';
            } else {
                $result .= self::convertHundreds($thousands, $units, $teens, $tens, $hundreds) . ' mii ';
            }
            $number %= 1000;
        }
        // Hundreds, tens, units
        if ($number > 0) {
            $result .= self::convertHundreds($number, $units, $teens, $tens, $hundreds);
        }
        return trim($result);
    }

    private static function convertHundreds($number, $units, $teens, $tens, $hundreds): string
    {
        $result = '';
        // Hundreds
        if ($number >= 100) {
            $hundredsDigit = intval($number / 100);
            $result .= $hundreds[$hundredsDigit] . ' ';
            $number %= 100;
        }
        // Tens and units
        if ($number >= 20) {
            $tensDigit = intval($number / 10);
            $unitsDigit = $number % 10;
            $result .= $tens[$tensDigit];
            if ($unitsDigit > 0) {
                $result .= ' și ' . $units[$unitsDigit];
            }
        } elseif ($number >= 10) {
            $result .= $teens[$number - 10];
        } elseif ($number > 0) {
            $result .= $units[$number];
        }
        return trim($result);
    }
}
