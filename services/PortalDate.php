<?php

final class PortalDate
{
    public static function parseInput(string $value): ?DateTimeImmutable
    {
        foreach ([
            ['!d/m/Y', 'd/m/Y'],
            ['!Y-m-d', 'Y-m-d'],
        ] as [$inputFormat, $outputFormat]) {
            $pattern = $inputFormat === '!d/m/Y'
                ? '/^\d{2}\/\d{2}\/\d{4}$/D'
                : '/^\d{4}-\d{2}-\d{2}$/D';
            if (preg_match($pattern, $value) !== 1) {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat($inputFormat, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if (
                $date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($outputFormat) === $value
            ) {
                return $date;
            }
        }

        return null;
    }
}
