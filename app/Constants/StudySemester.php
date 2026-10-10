<?php

namespace App\Constants;

use InvalidArgumentException;

final class StudySemester
{
    public const FIRST = 1;

    public const SECOND = 2;

    public const SUMMER = 3;

    private const OPTIONS = [
        self::FIRST => ['ภาคต้น', 'First semester'],
        self::SECOND => ['ภาคปลาย', 'Second semester'],
        self::SUMMER => ['ภาคฤดูร้อน', 'Summer semester'],
    ];

    public static function values(): array
    {
        return array_keys(self::OPTIONS);
    }

    public static function nameTh(int $semester): string
    {
        return self::OPTIONS[$semester][0]
            ?? throw new InvalidArgumentException('Invalid study semester.');
    }

    public static function fromInput(string $value): ?int
    {
        $value = trim($value);

        foreach (self::OPTIONS as $semester => [$nameTh]) {
            if ($value === $nameTh) {
                return $semester;
            }
        }

        return null;
    }

    public static function options(): array
    {
        return array_map(
            fn (int $semester, array $names): array => [
                'id' => $semester,
                'name_th' => $names[0],
                'name_en' => $names[1],
            ],
            array_keys(self::OPTIONS),
            array_values(self::OPTIONS),
        );
    }
}
