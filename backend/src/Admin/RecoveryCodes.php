<?php

declare(strict_types=1);

namespace ContactForm\Admin;

final class RecoveryCodes
{
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const int CODE_LENGTH = 16;

    public static function generate(int $count = 10): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $characters = '';
            for ($index = 0; $index < self::CODE_LENGTH; $index++) {
                $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[implode('-', str_split($characters, 4))] = true;
        }

        return array_map('strval', array_keys($codes));
    }

    public static function normalize(string $code): ?string
    {
        $normalized = strtoupper(str_replace(['-', ' '], '', $code));
        $pattern = sprintf('/\A[%s]{%d}\z/', self::ALPHABET, self::CODE_LENGTH);

        return preg_match($pattern, $normalized) === 1 ? $normalized : null;
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code) ?? '');
    }
}
