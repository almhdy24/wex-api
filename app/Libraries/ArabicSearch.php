<?php

namespace App\Libraries;

/**
 * Arabic-aware search normalisation (UX-SEARCH-01).
 *
 * normalize(): strips tashkeel/tatweel, unifies alef/hamza forms, ya/alef maqsura,
 * taa marbuta, converts Arabic-Indic digits, lower-cases Latin, collapses spaces.
 * index(): normalize() + cross-script brand/term spellings so "ديل" finds "Dell"
 * and "lenovo" finds "لينوفو".
 */
final class ArabicSearch
{
    /** Each group lists spellings of one term; any of them indexes all of them. */
    private const SYNONYMS = [
        ['dell', 'ديل'],
        ['lenovo', 'لينوفو', 'لنوفو'],
        ['hp', 'اتش بي', 'اتشبي', 'hewlett'],
        ['apple', 'ابل', 'ابيل'],
        ['macbook', 'ماك بوك', 'ماكبوك', 'mac'],
        ['iphone', 'ايفون', 'ايفوون'],
        ['samsung', 'سامسونج', 'سامسونغ', 'سامسونق'],
        ['asus', 'اسوس', 'ايسوس'],
        ['acer', 'ايسر', 'اسير'],
        ['thinkpad', 'ثينك باد', 'ثنك باد', 'ثينكباد'],
        ['latitude', 'لاتيتيود', 'لاتيتود'],
        ['elitebook', 'ايليت بوك', 'اليت بوك'],
        ['xiaomi', 'شاومي', 'شاومى'],
        ['huawei', 'هواوي'],
        ['microsoft', 'مايكروسوفت'],
        ['surface', 'سيرفس', 'سيرفيس'],
        ['laptop', 'لابتوب', 'لاب توب', 'حاسوب', 'كمبيوتر محمول'],
        ['phone', 'جوال', 'هاتف', 'موبايل'],
        ['charger', 'شاحن'],
        ['mouse', 'ماوس', 'فاره'],
        ['keyboard', 'كيبورد', 'لوحه مفاتيح'],
        ['ssd', 'اس اس دي'],
        ['ram', 'رام', 'ذاكره'],
    ];

    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $t = mb_strtolower($text, 'UTF-8');
        // tashkeel, superscript alef, tatweel
        $t = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $t);
        $t = strtr($t, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '،' => ' ', '؛' => ' ', '؟' => ' ',
        ]);
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t);

        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    /** Normalised text plus every cross-script spelling of terms it contains. */
    public static function index(string ...$parts): string
    {
        $base = self::normalize(implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== '')));
        $padded = ' ' . $base . ' ';
        $extra = [];
        foreach (self::SYNONYMS as $group) {
            foreach ($group as $form) {
                if (str_contains($padded, ' ' . self::normalize($form) . ' ') || (mb_strlen($form) >= 4 && str_contains($base, self::normalize($form)))) {
                    foreach ($group as $alt) {
                        $extra[] = self::normalize($alt);
                    }
                    break;
                }
            }
        }

        // Leading/trailing spaces let queries match at word starts (' ' . token), so a short
        // word like "ديل" does not match inside "تعديل".
        return ' ' . trim($base . ' ' . implode(' ', array_unique($extra))) . ' ';
    }

    /**
     * Query tokens (normalised, de-duplicated, max 6, min length 2 unless numeric).
     *
     * @return list<string>
     */
    public static function tokens(string $query): array
    {
        $out = [];
        foreach (explode(' ', self::normalize($query)) as $tok) {
            if ($tok !== '' && (mb_strlen($tok) >= 2 || ctype_digit($tok))) {
                $out[] = $tok;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 6);
    }
}
