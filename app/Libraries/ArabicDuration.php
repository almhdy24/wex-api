<?php

namespace App\Libraries;

/**
 * Arabic hour count after a preposition ("لمدة …"): 1 ساعة واحدة · 2 ساعتين ·
 * 3–10 N ساعات · 11+ N ساعة. Web twin: wex-web src/utils/arabicDuration.js.
 */
final class ArabicDuration
{
    public const TOKEN = '{{lock_duration}}';

    public static function hours(int|string|float|null $value): string
    {
        $n = max(1, (int) round((float) $value));
        if ($n === 1) {
            return 'ساعة واحدة';
        }
        if ($n === 2) {
            return 'ساعتين';
        }
        return $n <= 10 ? $n . ' ساعات' : $n . ' ساعة';
    }

    /**
     * Renders the tokens admin-editable content may use: {{lock_duration}}, {{shop_address}},
     * {{working_hours}} (all from settings; unset shop facts render as '—').
     */
    public static function render(?string $text, int|string|null $lockHours = null): string
    {
        $text = (string) $text;
        if (!str_contains($text, '{{')) {
            return $text;
        }
        $settings = new \App\Services\SettingService();
        $lockHours ??= $settings->get('lock_hours', '4');
        $map = [
            'lock_duration' => self::hours($lockHours),
            'shop_address'  => trim((string) $settings->get('shop_address', '')) ?: '—',
            'working_hours' => trim((string) $settings->get('working_hours', '')) ?: '—',
        ];
        return preg_replace_callback('/\{\{\s*(lock_duration|shop_address|working_hours)\s*\}\}/u', static fn ($m) => $map[$m[1]], $text) ?? $text;
    }
}
