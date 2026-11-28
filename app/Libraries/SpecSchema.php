<?php

namespace App\Libraries;

/**
 * Single source of truth for product specifications.
 *
 * Every product type (laptop / phone / accessory) has a schema of typed fields. The admin form
 * renders its inputs from `GET /api/v1/spec-schema`, the API validates `spec_data` against the
 * very same schema, and the public presenters derive the customer-facing `specs` label map,
 * card line and key chips from it, so the three can never disagree.
 *
 * Field shape: key, label, input (segmented|select|number|text|boolean|multi), options
 * [{value,label}], unit, min/max (number), required, when {field,in:[...]} (conditional),
 * group. Display rows (`display`) say how fields compose into the label shown to customers,
 * and which rows go on the card line / key chips.
 */
class SpecSchema
{
    public const ACCESSORY_SUBTYPES = [
        'mouse'          => 'ماوس',
        'keyboard'       => 'كيبورد',
        'charger_cable'  => 'شواحن وكوابل',
        'headphones'     => 'سماعات',
        'bags_cases'     => 'حقائب وأغطية',
        'storage_memory' => 'تخزين وذاكرة',
        'other'          => 'أخرى',
    ];

    private static function opts(array $values, ?string $suffix = null, string $prefix = ''): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if (is_int($k)) {
                $out[] = ['value' => (string) $v, 'label' => $prefix . $v . ($suffix ? ' ' . $suffix : '')];
            } else {
                $out[] = ['value' => (string) $k, 'label' => (string) $v];
            }
        }
        return $out;
    }

    /** Explicit [value,label] pairs (PHP turns numeric-string array keys into ints). */
    private static function pairs(array $pairs): array
    {
        return array_map(static fn ($p) => ['value' => (string) $p[0], 'label' => (string) $p[1]], $pairs);
    }

    private static function gens(): array
    {
        $o = [];
        for ($i = 4; $i <= 14; $i++) {
            $o[] = ['value' => (string) $i, 'label' => 'الجيل ' . $i];
        }
        return $o;
    }

    private static function colors(): array
    {
        return self::opts([
            'black' => 'أسود', 'white' => 'أبيض', 'silver' => 'فضي', 'gray' => 'رمادي', 'blue' => 'أزرق',
            'red' => 'أحمر', 'green' => 'أخضر', 'gold' => 'ذهبي', 'pink' => 'وردي', 'purple' => 'بنفسجي',
        ]);
    }

    /** @return array<string,array> keyed by product type */
    public static function all(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cpuFamilies = self::opts([
            'i3' => 'Core i3', 'i5' => 'Core i5', 'i7' => 'Core i7', 'i9' => 'Core i9',
            'ryzen3' => 'Ryzen 3', 'ryzen5' => 'Ryzen 5', 'ryzen7' => 'Ryzen 7', 'ryzen9' => 'Ryzen 9',
            'm1' => 'Apple M1', 'm2' => 'Apple M2', 'm3' => 'Apple M3',
            'celeron' => 'Celeron', 'pentium' => 'Pentium', 'other' => 'أخرى',
        ]);
        $ramGb       = self::opts(['4', '8', '16', '32', '64'], 'GB');
        $ramType     = self::opts(['DDR3' => 'DDR3', 'DDR4' => 'DDR4', 'DDR5' => 'DDR5', 'LPDDR4' => 'LPDDR4', 'LPDDR5' => 'LPDDR5']);
        $storageGb   = self::pairs([[128, '128 GB'], [256, '256 GB'], [512, '512 GB'], [1024, '1 TB'], [2048, '2 TB']]);
        $storageType = self::opts(['SSD' => 'SSD', 'NVMe' => 'NVMe', 'HDD' => 'HDD', 'eMMC' => 'eMMC']);

        $cache = [
            'laptop' => [
                'type'   => 'laptop',
                'label'  => 'حواسيب',
                'icon'   => 'laptop',
                'hint_images' => 'ننصح بـ 5 صور على الأقل: الواجهة، لوحة المفاتيح، الجانبين، الشاشة وهي مضاءة',
                'fields' => [
                    ['key' => 'model', 'label' => 'الموديل', 'input' => 'text', 'required' => true, 'group' => 'basic', 'placeholder' => 'مثال: ProBook 440 G7', 'max' => 80],
                    ['key' => 'cpu_family', 'label' => 'المعالج', 'input' => 'select', 'options' => $cpuFamilies, 'required' => true, 'group' => 'cpu'],
                    ['key' => 'cpu_gen', 'label' => 'جيل المعالج', 'input' => 'select', 'options' => self::gens(), 'group' => 'cpu',
                        'when' => ['field' => 'cpu_family', 'in' => ['i3', 'i5', 'i7', 'i9', 'ryzen3', 'ryzen5', 'ryzen7', 'ryzen9']]],
                    ['key' => 'cpu_model', 'label' => 'رقم المعالج (اختياري)', 'input' => 'text', 'group' => 'cpu', 'placeholder' => 'مثال: 8350U', 'max' => 20],
                    ['key' => 'ram_gb', 'label' => 'حجم الرام', 'input' => 'segmented', 'options' => $ramGb, 'required' => true, 'group' => 'memory'],
                    ['key' => 'ram_type', 'label' => 'نوع الرام', 'input' => 'segmented', 'options' => $ramType, 'group' => 'memory'],
                    ['key' => 'storage_gb', 'label' => 'سعة التخزين', 'input' => 'segmented', 'options' => $storageGb, 'required' => true, 'group' => 'memory'],
                    ['key' => 'storage_type', 'label' => 'نوع التخزين', 'input' => 'segmented', 'options' => $storageType, 'required' => true, 'group' => 'memory'],
                    ['key' => 'screen_inch', 'label' => 'حجم الشاشة', 'input' => 'segmented', 'options' => self::opts(['11.6', '13.3', '14', '15.6', '17.3'], 'بوصة'), 'required' => true, 'group' => 'screen'],
                    ['key' => 'resolution', 'label' => 'الدقة', 'input' => 'segmented', 'options' => self::opts(['HD' => 'HD', 'FHD' => 'Full HD', 'QHD' => 'QHD', '4K' => '4K']), 'group' => 'screen'],
                    ['key' => 'gpu_kind', 'label' => 'كرت الشاشة', 'input' => 'segmented', 'options' => self::opts(['integrated' => 'مدمج', 'dedicated' => 'مخصص']), 'required' => true, 'group' => 'gpu'],
                    ['key' => 'gpu_model', 'label' => 'موديل الكرت', 'input' => 'text', 'group' => 'gpu', 'placeholder' => 'مثال: GTX 1650', 'max' => 40,
                        'when' => ['field' => 'gpu_kind', 'in' => ['dedicated']]],
                    ['key' => 'battery_hours', 'label' => 'البطارية (ساعات تقريباً)', 'input' => 'number', 'min' => 1, 'max' => 24, 'unit' => 'ساعة', 'group' => 'extra'],
                    ['key' => 'os', 'label' => 'نظام التشغيل', 'input' => 'select', 'options' => self::opts(['win11' => 'Windows 11', 'win10' => 'Windows 10', 'macos' => 'macOS', 'linux' => 'Linux', 'none' => 'بدون نظام']), 'group' => 'extra'],
                ],
                'display' => [
                    ['label' => 'المعالج', 'icon' => 'cpu', 'value' => '{cpu_family} · {cpu_gen} · {cpu_model}', 'chip' => '{cpu_family.short}', 'card' => 1],
                    ['label' => 'الرام', 'icon' => 'ram', 'value' => '{ram_gb} {ram_type}', 'chip' => '{ram_gb.raw}GB', 'card' => 2],
                    ['label' => 'التخزين', 'icon' => 'storage', 'value' => '{storage_gb} {storage_type}', 'chip' => '{storage_gb.short} {storage_type}', 'card' => 3],
                    ['label' => 'كرت الشاشة', 'icon' => 'gpu', 'value' => '{gpu_kind} {gpu_model}'],
                    ['label' => 'الشاشة', 'icon' => 'screen', 'value' => '{screen_inch} {resolution}'],
                    ['label' => 'البطارية', 'icon' => 'battery', 'value' => '{battery_hours.raw} ساعة تقريباً'],
                    ['label' => 'نظام التشغيل', 'icon' => 'os', 'value' => '{os}'],
                    ['label' => 'الموديل', 'icon' => 'model', 'value' => '{model}'],
                ],
            ],
            'phone' => [
                'type'   => 'phone',
                'label'  => 'هواتف',
                'icon'   => 'phone',
                'hint_images' => 'ننصح بـ 4 صور على الأقل: الشاشة، الخلف، الجانبين',
                'fields' => [
                    ['key' => 'model', 'label' => 'الموديل', 'input' => 'text', 'required' => true, 'group' => 'basic', 'placeholder' => 'مثال: Galaxy S21', 'max' => 80],
                    ['key' => 'storage_gb', 'label' => 'سعة التخزين', 'input' => 'segmented', 'options' => self::pairs([[32, '32 GB'], [64, '64 GB'], [128, '128 GB'], [256, '256 GB'], [512, '512 GB'], [1024, '1 TB']]), 'required' => true, 'group' => 'memory'],
                    ['key' => 'ram_gb', 'label' => 'حجم الرام', 'input' => 'segmented', 'options' => self::opts(['2', '3', '4', '6', '8', '12', '16'], 'GB'), 'required' => true, 'group' => 'memory'],
                    ['key' => 'screen_inch', 'label' => 'حجم الشاشة (بوصة)', 'input' => 'number', 'min' => 3, 'max' => 8, 'unit' => 'بوصة', 'step' => 0.1, 'group' => 'screen'],
                    ['key' => 'camera_mp', 'label' => 'الكاميرا الرئيسية (ميجابكسل)', 'input' => 'number', 'min' => 2, 'max' => 250, 'unit' => 'MP', 'group' => 'extra'],
                    ['key' => 'battery_mah', 'label' => 'البطارية (mAh)', 'input' => 'number', 'min' => 1000, 'max' => 10000, 'unit' => 'mAh', 'group' => 'extra'],
                    ['key' => 'network', 'label' => 'الشبكة', 'input' => 'segmented', 'options' => self::opts(['4g' => '4G', '5g' => '5G']), 'required' => true, 'group' => 'extra'],
                    ['key' => 'battery_health', 'label' => 'صحة البطارية (%)', 'input' => 'number', 'min' => 50, 'max' => 100, 'unit' => '%', 'group' => 'extra', 'when_condition' => ['used_imported', 'refurbished']],
                    ['key' => 'color', 'label' => 'اللون', 'input' => 'select', 'options' => self::colors(), 'group' => 'extra'],
                    ['key' => 'sim_count', 'label' => 'عدد الشرائح', 'input' => 'segmented', 'options' => self::pairs([[1, 'شريحة واحدة'], [2, 'شريحتان']]), 'group' => 'extra'],
                ],
                'display' => [
                    ['label' => 'التخزين', 'icon' => 'storage', 'value' => '{storage_gb}', 'chip' => '{storage_gb.short}', 'card' => 1],
                    ['label' => 'الرام', 'icon' => 'ram', 'value' => '{ram_gb}', 'chip' => '{ram_gb.raw}GB', 'card' => 2],
                    ['label' => 'الشاشة', 'icon' => 'screen', 'value' => '{screen_inch.raw} بوصة'],
                    ['label' => 'الكاميرا', 'icon' => 'camera', 'value' => '{camera_mp.raw} ميجابكسل'],
                    ['label' => 'البطارية', 'icon' => 'battery', 'value' => '{battery_mah.raw} mAh'],
                    ['label' => 'الشبكة', 'icon' => 'network', 'value' => '{network}', 'chip' => '{network}', 'card' => 3],
                    ['label' => 'صحة البطارية', 'icon' => 'battery', 'value' => '{battery_health.raw}%'],
                    ['label' => 'اللون', 'icon' => 'color', 'value' => '{color}'],
                    ['label' => 'عدد الشرائح', 'icon' => 'sim', 'value' => '{sim_count}'],
                    ['label' => 'الموديل', 'icon' => 'model', 'value' => '{model}'],
                ],
            ],
            'accessory' => [
                'type'   => 'accessory',
                'label'  => 'ملحقات',
                'icon'   => 'accessory',
                'subtypes' => self::opts(self::ACCESSORY_SUBTYPES),
                'hint_images' => 'ننصح بـ 3 صور على الأقل: المنتج من الأمام، من الجانب، والعبوة',
                'fields' => [
                    ['key' => 'model', 'label' => 'الموديل', 'input' => 'text', 'group' => 'basic', 'placeholder' => 'مثال: MX Master 3', 'max' => 80],
                    ['key' => 'compat', 'label' => 'التوافق', 'input' => 'multi', 'options' => self::opts(['laptop' => 'لابتوب', 'phone' => 'هاتف', 'tablet' => 'تابلت', 'desktop' => 'كمبيوتر مكتبي']), 'group' => 'extra'],
                    ['key' => 'connection', 'label' => 'الاتصال', 'input' => 'segmented', 'options' => self::opts(['wired' => 'سلكي', 'bluetooth' => 'بلوتوث', 'wireless_24' => '2.4G']), 'required' => true, 'group' => 'extra'],
                    ['key' => 'color', 'label' => 'اللون', 'input' => 'select', 'options' => self::colors(), 'group' => 'extra'],
                ],
                'display' => [
                    ['label' => 'الاتصال', 'icon' => 'network', 'value' => '{connection}', 'chip' => '{connection}', 'card' => 1],
                    ['label' => 'التوافق', 'icon' => 'compat', 'value' => '{compat}'],
                    ['label' => 'اللون', 'icon' => 'color', 'value' => '{color}'],
                    ['label' => 'الموديل', 'icon' => 'model', 'value' => '{model}'],
                ],
            ],
        ];

        return $cache;
    }

    public static function forType(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function subtypeValues(): array
    {
        return array_keys(self::ACCESSORY_SUBTYPES);
    }

    private static function fieldMap(array $schema): array
    {
        $m = [];
        foreach ($schema['fields'] as $f) {
            $m[$f['key']] = $f;
        }
        return $m;
    }

    private static function isVisible(array $field, array $data, ?string $condition): bool
    {
        if (isset($field['when'])) {
            $cur = $data[$field['when']['field']] ?? null;
            if (!in_array((string) $cur, $field['when']['in'], true)) {
                return false;
            }
        }
        if (isset($field['when_condition']) && $condition !== null && !in_array($condition, $field['when_condition'], true)) {
            return false;
        }
        return true;
    }

    /**
     * Validates structured spec data. Returns errors keyed `spec_data.<field>` (Arabic text).
     * With $draft=true "required" is not enforced but present values are still type-checked.
     *
     * @return array<string,string>
     */
    public static function validate(string $type, array $data, bool $draft = false, ?string $condition = null): array
    {
        $schema = self::forType($type);
        if ($schema === null) {
            return [];
        }
        $errors = [];
        $fields = self::fieldMap($schema);

        foreach ($data as $k => $_) {
            if (!isset($fields[$k])) {
                $errors['spec_data.' . $k] = 'حقل مواصفات غير معروف';
            }
        }

        foreach ($schema['fields'] as $f) {
            $key = 'spec_data.' . $f['key'];
            if (!self::isVisible($f, $data, $condition)) {
                continue;
            }
            $v = $data[$f['key']] ?? null;
            $empty = $v === null || $v === '' || $v === [];
            if ($empty) {
                if (!empty($f['required']) && !$draft) {
                    $errors[$key] = 'هذا الحقل مطلوب: ' . $f['label'];
                }
                continue;
            }
            switch ($f['input']) {
                case 'segmented':
                case 'select':
                    $allowed = array_column($f['options'], 'value');
                    if (!is_scalar($v) || !in_array((string) $v, $allowed, true)) {
                        $errors[$key] = 'اختر قيمة من القائمة: ' . $f['label'];
                    }
                    break;
                case 'multi':
                    $allowed = array_column($f['options'], 'value');
                    if (!is_array($v) || array_diff(array_map('strval', $v), $allowed) !== []) {
                        $errors[$key] = 'اختيار غير صالح: ' . $f['label'];
                    }
                    break;
                case 'number':
                    if (!is_numeric($v) || (isset($f['min']) && $v < $f['min']) || (isset($f['max']) && $v > $f['max'])) {
                        $errors[$key] = sprintf('أدخل رقماً بين %s و %s: %s', $f['min'] ?? 0, $f['max'] ?? '∞', $f['label']);
                    }
                    break;
                case 'boolean':
                    if (!is_bool($v) && !in_array($v, [0, 1, '0', '1'], true)) {
                        $errors[$key] = 'قيمة غير صالحة: ' . $f['label'];
                    }
                    break;
                default:
                    if (!is_string($v) || mb_strlen($v) > ($f['max'] ?? 120)) {
                        $errors[$key] = 'النص طويل جداً: ' . $f['label'];
                    }
            }
        }

        return $errors;
    }

    /** Drops unknown keys and empty values so stored data is clean. */
    public static function clean(string $type, array $data, ?string $condition = null): array
    {
        $schema = self::forType($type);
        if ($schema === null) {
            return [];
        }
        $out = [];
        foreach ($schema['fields'] as $f) {
            if (!self::isVisible($f, $data, $condition)) {
                continue;
            }
            $v = $data[$f['key']] ?? null;
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $out[$f['key']] = is_string($v) ? trim($v) : $v;
        }
        return $out;
    }

    private static function optionLabel(array $field, string $v): string
    {
        foreach ($field['options'] ?? [] as $o) {
            if ($o['value'] === $v) {
                return $o['label'];
            }
        }
        return $v;
    }

    private static function render(string $tpl, array $fields, array $data): string
    {
        $out = [];
        foreach (preg_split('/\s*·\s*/u', $tpl) as $part) {
            $resolved = 0;
            $text = preg_replace_callback('/\{(\w+)(?:\.(\w+))?\}/', static function ($m) use ($fields, $data, &$resolved) {
                $key = $m[1];
                $mode = $m[2] ?? 'label';
                $v = $data[$key] ?? null;
                if ($v === null || $v === '' || $v === []) {
                    return '';
                }
                $resolved++;
                if (is_array($v)) {
                    return implode('، ', array_map(static fn ($x) => self::optionLabel($fields[$key] ?? [], (string) $x), $v));
                }
                $v = (string) $v;
                if ($mode === 'raw') {
                    return $v;
                }
                $label = self::optionLabel($fields[$key] ?? [], $v);
                if ($mode === 'short') {
                    // "Core i5" -> "i5", "256 GB" -> "256GB", "1 TB" -> "1TB"
                    $label = preg_replace('/^(Core|Apple)\s+/', '', $label);
                    $label = preg_replace('/\s+(GB|TB)$/', '$1', $label);
                }
                return $label;
            }, $part);
            // A part with literal text but no resolved placeholder ("ساعة تقريباً" alone) is dropped.
            if ($resolved > 0) {
                $out[] = trim(preg_replace('/\s+/u', ' ', $text));
            }
        }
        return implode(' · ', $out);
    }

    /** Customer-facing "label => value" map (what the public `specs` field carries). */
    public static function labelMap(string $type, array $data): array
    {
        $schema = self::forType($type);
        if ($schema === null) {
            return [];
        }
        $fields = self::fieldMap($schema);
        $map = [];
        foreach ($schema['display'] as $row) {
            $val = self::render($row['value'], $fields, $data);
            if ($val !== '') {
                $map[$row['label']] = $val;
            }
        }
        return $map;
    }

    /** @return string[] key chips in card order, e.g. ["i5","8GB","256GB SSD"]. */
    public static function chips(string $type, array $data): array
    {
        $schema = self::forType($type);
        if ($schema === null) {
            return [];
        }
        $fields = self::fieldMap($schema);
        $rows = array_filter($schema['display'], static fn ($r) => !empty($r['card']) && !empty($r['chip']));
        usort($rows, static fn ($a, $b) => $a['card'] <=> $b['card']);
        $out = [];
        foreach ($rows as $r) {
            $c = self::render($r['chip'], $fields, $data);
            if ($c !== '') {
                $out[] = $c;
            }
        }
        return $out;
    }

    // -----------------------------------------------------------------
    // Legacy free-text normalizer
    // -----------------------------------------------------------------

    private const KEY_ALIAS = [
        'cpu' => 'cpu', 'processor' => 'cpu', 'المعالج' => 'cpu', 'المعالِج' => 'cpu', 'معالج' => 'cpu',
        'ram' => 'ram', 'memory' => 'ram', 'الرام' => 'ram', 'الذاكرة' => 'ram', 'الذاكرة العشوائية' => 'ram', 'رام' => 'ram',
        'storage' => 'storage', 'ssd' => 'storage', 'hdd' => 'storage', 'التخزين' => 'storage', 'القرص' => 'storage', 'الهارد' => 'storage',
        'gpu' => 'gpu', 'graphics' => 'gpu', 'كرت الشاشة' => 'gpu', 'كرت الرسوميات' => 'gpu', 'كرت شاشة' => 'gpu',
        'display' => 'screen', 'screen' => 'screen', 'الشاشة' => 'screen', 'شاشة' => 'screen',
        'battery' => 'battery', 'البطارية' => 'battery',
        'os' => 'os', 'نظام التشغيل' => 'os',
        'camera' => 'camera', 'الكاميرا' => 'camera',
        'network' => 'network', 'الشبكة' => 'network',
        'color' => 'color', 'اللون' => 'color',
    ];

    /** Fixes Arabic transliterations: "8 قيقا دسديارفور" => "8 GB DDR4". */
    public static function normalizeText(string $v): string
    {
        $subs = [
            'جيجابايت' => 'GB', 'قيقا' => 'GB', 'جيقا' => 'GB', 'غيغا' => 'GB', 'جيجا' => 'GB', 'تيرا' => 'TB',
            'دسديارفور' => 'DDR4', 'دي دي ار 4' => 'DDR4', 'دي دي آر فور' => 'DDR4',
            'دسديارفايف' => 'DDR5', 'دي دي ار 5' => 'DDR5', 'دسديارثري' => 'DDR3',
            'اس اسدي' => 'SSD', 'اس اس دي' => 'SSD', 'إس إس دي' => 'SSD', 'اتش دي دي' => 'HDD',
            'انتل' => 'Intel', 'اي 5' => 'i5', 'اي 7' => 'i7', 'اي 3' => 'i3', 'اي 9' => 'i9', 'بوصه' => 'بوصة',
        ];
        return trim(strtr($v, $subs));
    }

    private static function looksLikeGpu(string $v): bool
    {
        return (bool) preg_match('/nvidia|rtx|gtx|radeon|intel|uhd|iris|amd|vega|مدمج|لا يوجد/iu', $v);
    }

    private static function looksLikeScreen(string $v): bool
    {
        return (bool) preg_match('/\b(1[0-9]|[89])(\.\d)?\b/', $v) && !preg_match('/gb|tb/i', $v)
            || (bool) preg_match('/بوصة|"|inch/iu', $v);
    }

    /**
     * Best-effort conversion of an old free-text `specs` map into structured `spec_data`.
     * Never throws and never discards: anything it cannot place stays in the legacy copy.
     *
     * @return array{data:array,needs_review:bool,unparsed:string[]}
     */
    public static function fromLegacy(string $type, array $legacy): array
    {
        $norm = [];
        foreach ($legacy as $k => $v) {
            if (!is_scalar($v)) {
                continue;
            }
            $key = self::KEY_ALIAS[mb_strtolower(trim((string) $k))] ?? self::KEY_ALIAS[trim((string) $k)] ?? null;
            if ($key !== null) {
                $norm[$key] = self::normalizeText((string) $v);
            }
        }

        // Swapped screen / graphics card (a common admin mistake): fix it.
        if (isset($norm['screen'], $norm['gpu']) && self::looksLikeGpu($norm['screen']) && self::looksLikeScreen($norm['gpu']) && !self::looksLikeGpu($norm['gpu'])) {
            [$norm['screen'], $norm['gpu']] = [$norm['gpu'], $norm['screen']];
        }

        $d = [];
        $schema = self::forType($type);
        $fields = $schema ? self::fieldMap($schema) : [];
        $pick = static function (string $key, string $value) use ($fields): ?string {
            foreach ($fields[$key]['options'] ?? [] as $o) {
                if ($o['value'] === $value) {
                    return $value;
                }
            }
            return null;
        };

        if (isset($norm['cpu'])) {
            $c = $norm['cpu'];
            if (preg_match('/\bi([3579])\b[\s-]*(\d{1,2})?(\d{3})?\s*([A-Z]{0,2}\d?[A-Z]{0,2})?/i', $c, $m) || preg_match('/i([3579])[-\s]?(\d{4,5}[A-Z]{0,2})/i', $c, $m)) {
                $d['cpu_family'] = 'i' . $m[1];
                if (preg_match('/i[3579][-\s]?(\d{4,5})([A-Z]{0,2})/i', $c, $mm)) {
                    $num = $mm[1];
                    $gen = strlen($num) === 5 ? (int) substr($num, 0, 2) : (int) substr($num, 0, 1);
                    if ($gen >= 4 && $gen <= 14) {
                        $d['cpu_gen'] = (string) $gen;
                    }
                    $d['cpu_model'] = strtoupper($num . $mm[2]);
                }
            } elseif (preg_match('/ryzen\s*([3579])/i', $c, $m)) {
                $d['cpu_family'] = 'ryzen' . $m[1];
            } elseif (preg_match('/\bM([123])\b/', $c, $m)) {
                $d['cpu_family'] = 'm' . $m[1];
            } elseif (preg_match('/celeron/i', $c)) {
                $d['cpu_family'] = 'celeron';
            } elseif (preg_match('/pentium/i', $c)) {
                $d['cpu_family'] = 'pentium';
            }
        }
        if (isset($norm['ram']) && preg_match('/(\d{1,2})/', $norm['ram'], $m) && $pick('ram_gb', $m[1])) {
            $d['ram_gb'] = $m[1];
            if (preg_match('/(LP)?DDR([345])/i', $norm['ram'], $t) && $pick('ram_type', strtoupper($t[0]))) {
                $d['ram_type'] = strtoupper($t[0]);
            }
        }
        if (isset($norm['storage']) && preg_match('/(\d+(?:\.\d+)?)\s*(TB|GB)?/i', $norm['storage'], $m)) {
            $n = (float) $m[1];
            $unit = strtoupper($m[2] ?? '');
            $gb = $unit === 'TB' || ($unit === '' && $n <= 4) ? (int) round($n * 1024) : (int) $n;
            if ($pick('storage_gb', (string) $gb)) {
                $d['storage_gb'] = (string) $gb;
            }
            if (preg_match('/nvme/i', $norm['storage'])) {
                $d['storage_type'] = 'NVMe';
            } elseif (preg_match('/ssd/i', $norm['storage'])) {
                $d['storage_type'] = 'SSD';
            } elseif (preg_match('/hdd/i', $norm['storage'])) {
                $d['storage_type'] = 'HDD';
            }
        }
        if (isset($norm['screen']) && preg_match('/(\d{1,2}(?:\.\d)?)/', $norm['screen'], $m)) {
            if ($type === 'phone') {
                $d['screen_inch'] = (float) $m[1];
            } elseif ($pick('screen_inch', rtrim(rtrim($m[1], '0'), '.') ?: $m[1]) || $pick('screen_inch', $m[1])) {
                $d['screen_inch'] = $pick('screen_inch', $m[1]) ? $m[1] : rtrim(rtrim($m[1], '0'), '.');
            }
            if (preg_match('/4K/i', $norm['screen'])) {
                $d['resolution'] = '4K';
            } elseif (preg_match('/FHD|1080|full\s*hd/i', $norm['screen'])) {
                $d['resolution'] = 'FHD';
            } elseif (preg_match('/\bHD\b|720/i', $norm['screen'])) {
                $d['resolution'] = 'HD';
            }
        }
        if (isset($norm['gpu'])) {
            if (preg_match('/nvidia|rtx|gtx|geforce|radeon\s*rx|quadro|mx\s?\d{3}/i', $norm['gpu'])) {
                $d['gpu_kind'] = 'dedicated';
                $d['gpu_model'] = mb_substr($norm['gpu'], 0, 40);
            } elseif (self::looksLikeGpu($norm['gpu'])) {
                $d['gpu_kind'] = 'integrated';
            }
        }
        if (isset($norm['battery']) && preg_match('/(\d{3,5})\s*mah/i', $norm['battery'], $m)) {
            $d['battery_mah'] = (int) $m[1];
        }
        if (isset($norm['network']) && preg_match('/5\s*g/i', $norm['network'])) {
            $d['network'] = '5g';
        } elseif (isset($norm['network']) && preg_match('/4\s*g|lte/i', $norm['network'])) {
            $d['network'] = '4g';
        }

        // What must be present for the record to count as fully migrated.
        $must = $type === 'laptop' ? ['cpu_family', 'ram_gb', 'storage_gb'] : ($type === 'phone' ? ['storage_gb'] : []);
        $unparsed = array_values(array_diff($must, array_keys($d)));

        return ['data' => $d, 'needs_review' => $unparsed !== [], 'unparsed' => $unparsed];
    }
}
