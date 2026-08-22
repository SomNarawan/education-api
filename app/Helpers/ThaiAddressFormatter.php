<?php

namespace App\Helpers;

final class ThaiAddressFormatter
{
    public static function format(
        ?string $subdistrict,
        ?string $district,
        ?string $province,
        ?string $postalCode,
    ): ?string {
        if ($subdistrict === null && $district === null && $province === null) {
            return null;
        }

        $subdistrict ??= '-';
        $district ??= '-';
        $province ??= '-';
        $postalCode ??= '-';

        return $province === 'กรุงเทพมหานคร'
            ? "แขวง{$subdistrict} เขต{$district} {$province} {$postalCode}"
            : "ตำบล{$subdistrict} อำเภอ{$district} จังหวัด{$province} {$postalCode}";
    }
}
