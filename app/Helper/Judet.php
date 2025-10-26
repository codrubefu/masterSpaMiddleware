<?php

namespace App\Helper;

class Judet
{
    public static function getAll()
    {
        $regions = [
            1 => ["code" => "", "name" => ""],
            2 => ["code" => "RO-B", "name" => "Bucuresti-Sector1"],
            3 => ["code" => "RO-B", "name" => "Bucuresti-Sector2"],
            4 => ["code" => "RO-B", "name" => "Bucuresti-Sector3"],
            5 => ["code" => "RO-B", "name" => "Bucuresti-Sector4"],
            6 => ["code" => "RO-B", "name" => "Bucuresti-Sector5"],
            7 => ["code" => "RO-B", "name" => "Bucuresti-Sector6"],
            8 => ["code" => "RO-AB", "name" => "Alba"],
            9 => ["code" => "RO-AR", "name" => "Arad"],
            10 => ["code" => "RO-AG", "name" => "Arges"],
            11 => ["code" => "RO-BC", "name" => "Bacau"],
            12 => ["code" => "RO-BH", "name" => "Bihor"],
            13 => ["code" => "RO-BN", "name" => "Bistrita-Nasaud"],
            14 => ["code" => "RO-BT", "name" => "Botosani"],
            15 => ["code" => "RO-BV", "name" => "Brasov"],
            16 => ["code" => "RO-BR", "name" => "Braila"],
            17 => ["code" => "RO-BZ", "name" => "Buzau"],
            18 => ["code" => "RO-CS", "name" => "Caras-Severin"],
            19 => ["code" => "RO-CL", "name" => "Calarasi"],
            20 => ["code" => "RO-CJ", "name" => "Cluj"],
            21 => ["code" => "RO-CT", "name" => "Constanta"],
            22 => ["code" => "RO-CV", "name" => "Covasna"],
            23 => ["code" => "RO-DB", "name" => "Dambovita"],
            24 => ["code" => "RO-DJ", "name" => "Dolj"],
            25 => ["code" => "RO-GL", "name" => "Galati"],
            26 => ["code" => "RO-GR", "name" => "Giurgiu"],
            27 => ["code" => "RO-GJ", "name" => "Gorj"],
            28 => ["code" => "RO-HR", "name" => "Harghita"],
            29 => ["code" => "RO-HD", "name" => "Hunedoara"],
            30 => ["code" => "RO-IL", "name" => "Ialomita"],
            31 => ["code" => "RO-IS", "name" => "Iasi"],
            32 => ["code" => "RO-IF", "name" => "Ilfov"],
            33 => ["code" => "RO-MM", "name" => "Maramures"],
            34 => ["code" => "RO-MH", "name" => "Mehedinti"],
            35 => ["code" => "RO-MS", "name" => "Mures"],
            36 => ["code" => "RO-NT", "name" => "Neamt"],
            37 => ["code" => "RO-OT", "name" => "Olt"],
            38 => ["code" => "RO-PH", "name" => "Prahova"],
            39 => ["code" => "RO-SM", "name" => "Satu Mare"],
            40 => ["code" => "RO-SJ", "name" => "Salaj"],
            41 => ["code" => "RO-SB", "name" => "Sibiu"],
            42 => ["code" => "RO-SV", "name" => "Suceava"],
            43 => ["code" => "RO-TR", "name" => "Teleorman"],
            44 => ["code" => "RO-TM", "name" => "Timis"],
            45 => ["code" => "RO-TL", "name" => "Tulcea"],
            46 => ["code" => "RO-VS", "name" => "Vaslui"],
            47 => ["code" => "RO-VL", "name" => "Valcea"],
            48 => ["code" => "RO-VN", "name" => "Vrancea"]
        ];

        return $regions;
    }

    public static function getNameByCode($code)
    {
        $regions = self::getAll();
        foreach ($regions as $key => $region) {
            if ($region['code'] === strtoupper('RO-'.$code)) {
                return $key;
            }
        }
        return null;
    }

    public static function getCodeByName($name)
    {
        $regions = self::getAll();
        dd($name);
        foreach ($regions as $key => $region) {
            if (strtolower($region['name']) === strtolower($name)) {
                 return $key;
            }
        }

        return null;
    }
}
