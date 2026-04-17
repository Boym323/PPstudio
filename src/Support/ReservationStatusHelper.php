<?php
declare(strict_types=1);

namespace PPStudio\Support;

final class ReservationStatusHelper
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'nova' => 'Nová',
            'potvrzena' => 'Potvrzená',
            'dokoncena' => 'Dokončená',
            'zrusena' => 'Zrušená',
        ];
    }

    public static function label(string $status): string
    {
        $options = self::options();

        return $options[$status] ?? 'Neznámá';
    }
}
