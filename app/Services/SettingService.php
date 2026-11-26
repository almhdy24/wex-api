<?php

namespace App\Services;

use App\Entities\Setting;
use App\Exceptions\ValidationFailedException;
use App\Models\ExchangeRateModel;
use App\Models\SettingModel;
use App\Models\UserModel;

/**
 * SettingService
 *
 * Manages shop settings, public shop configuration, exchange rate updates,
 * and rate history auditing.
 */
class SettingService
{
    protected SettingModel $settings;
    protected ExchangeRateModel $exchangeRates;
    protected AuditLogService $auditLogs;
    protected UserModel $users;

    /**
     * Allowed settings keys that can be updated via the admin settings API.
     */
    public const BOOLEAN_KEYS = ['orders_enabled', 'ticker_enabled', 'push_enabled'];

    /**
     * Reads a stored boolean setting. Missing -> $default. '' counts as off: it is what a
     * JSON false was stored as before booleans were normalised, so old rows read correctly.
     */
    public static function isOn(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }
        return !in_array(strtolower(trim((string) $value)), ['0', '', 'false', 'off', 'no'], true);
    }

    protected const ALLOWED_SETTING_KEYS = [
        'shop_name',
        'shop_address',
        'shop_phone',
        'shop_whatsapp',
        'working_hours',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'lock_hours',
        'app_discount_percent',
        'rounding_step_sdg',
        'low_stock_threshold',
        'payment_general_notes',
        'orders_enabled',
        'orders_disabled_message',
        'ticker_enabled',
        'shop_map_link',
        'social_facebook',
        'social_instagram',
        'footer_text',
        'delivery_notes',
        'policy_inspection',
        'policy_inspection_title',
        'policy_pickup',
        'policy_return',
        'inspection_note',
        'returns_note',
        'extra_delivery_note',
        'push_enabled',
    ];

    public function __construct(
        ?SettingModel $settings = null,
        ?ExchangeRateModel $exchangeRates = null,
        ?AuditLogService $auditLogs = null,
        ?UserModel $users = null
    ) {
        $this->settings      = $settings ?? new SettingModel();
        $this->exchangeRates = $exchangeRates ?? new ExchangeRateModel();
        $this->auditLogs     = $auditLogs ?? new AuditLogService();
        $this->users         = $users ?? new UserModel();
    }

    /**
     * Get a setting by key with a fallback default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->settings->where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    /**
     * Set or update a single setting by key.
     */
    public function set(string $key, string $value): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->settings->where('key', $key)->first();
        if ($row) {
            $this->settings->where('key', $key)->set([
                'value'      => $value,
                'updated_at' => $now,
            ])->update();
        } else {
            $this->settings->insert([
                'key'        => $key,
                'value'      => $value,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Retrieve all settings as an associative key-value map with appropriate types.
     */
    public function getAllSettings(): array
    {
        $rows = $this->settings->findAll();
        $result = [];

        foreach ($rows as $row) {
            $val = $row->value;
            if (in_array($row->key, ['exchange_rate_sdg', 'lock_hours', 'app_discount_percent', 'rounding_step_sdg', 'low_stock_threshold'], true)) {
                $val = (int) $val;
            }
            $result[$row->key] = $val;
        }

        return $result;
    }

    /**
     * Retrieve public shop configuration for mobile/web apps.
     * Exchange rate and bank details are omitted for customers.
     */
    public function getPublicSettings(): array
    {
        $all = $this->getAllSettings();

        $ordersEnabled = self::isOn($all['orders_enabled'] ?? null, true);
        $tickerEnabled = self::isOn($all['ticker_enabled'] ?? null, false);

        return [
            'shop_name'               => (string) ($all['shop_name'] ?? 'Wex Technology'),
            'shop_address'            => (string) ($all['shop_address'] ?? ''),
            'shop_phone'              => (string) ($all['shop_phone'] ?? ''),
            'shop_whatsapp'           => (string) ($all['shop_whatsapp'] ?? ''),
            'working_hours'           => (string) ($all['working_hours'] ?? ''),
            'shop_map_link'           => (string) ($all['shop_map_link'] ?? ''),
            'social_facebook'         => (string) ($all['social_facebook'] ?? ''),
            'social_instagram'        => (string) ($all['social_instagram'] ?? ''),
            'footer_text'             => (string) ($all['footer_text'] ?? ''),
            'delivery_notes'          => (string) ($all['delivery_notes'] ?? ($all['extra_delivery_note'] ?? 'التوصيل متاح داخل ولاية الخرطوم والولايات المحددة.')),
            'extra_delivery_note'     => (string) ($all['extra_delivery_note'] ?? ($all['delivery_notes'] ?? '')),
            'policy_inspection_title' => (string) ($all['policy_inspection_title'] ?? 'استلام الأجهزة'),
            'policy_inspection'       => (string) ($all['policy_inspection'] ?? ($all['inspection_note'] ?? 'أجهزة مستوردة؛ يتأكد المشتري من مطابقة الجهاز ومواصفاته الفنية عند الاستلام.')),
            'inspection_note'         => (string) ($all['inspection_note'] ?? ($all['policy_inspection'] ?? '')),
            'policy_pickup'           => (string) ($all['policy_pickup'] ?? 'يمكنك استلام الجهاز من المحل بعد تأكيد الطلب.'),
            'policy_return'           => (string) ($all['policy_return'] ?? ($all['returns_note'] ?? 'نظراً لطبيعة الأجهزة المستوردة، يتم الاستلام والتأكد من الجهاز عند الاستلام.')),
            'returns_note'            => (string) ($all['returns_note'] ?? ($all['policy_return'] ?? '')),
            'lock_hours'              => (int) ($all['lock_hours'] ?? 4),
            'app_discount_percent'    => (int) ($all['app_discount_percent'] ?? 3),
            'orders_enabled'          => (bool) $ordersEnabled,
            'orders_disabled_message' => (string) ($all['orders_disabled_message'] ?? 'استقبال الطلبات متوقف مؤقتاً في الوقت الحالي، نعتذر عن الإزعاج.'),
            'ticker_enabled'          => (bool) $tickerEnabled,
            'payment_general_notes'   => (string) ($all['payment_general_notes'] ?? ''),
            'prices_updated_at'       => $this->getPricesUpdatedAt(),
        ];
    }

    /**
     * Records a new timestamp whenever exchange rate or product price_aed changes.
     */
    public function touchPricesUpdatedAt(): string
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->settings->where('key', 'prices_updated_at')->first();
        if ($row) {
            $this->settings->where('key', 'prices_updated_at')->set([
                'value'      => $now,
                'updated_at' => $now,
            ])->update();
        } else {
            $this->settings->insert([
                'key'        => 'prices_updated_at',
                'value'      => $now,
                'updated_at' => $now,
            ]);
        }

        return gmdate('Y-m-d\TH:i:s\Z', strtotime($now));
    }

    /**
     * Retrieves the latest prices_updated_at UTC ISO timestamp.
     */
    public function getPricesUpdatedAt(): string
    {
        $row = $this->settings->where('key', 'prices_updated_at')->first();
        if ($row && !empty($row->value)) {
            $ts = strtotime($row->value);
            return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : gmdate('Y-m-d\TH:i:s\Z');
        }

        $rateRow = $this->settings->where('key', 'exchange_rate_sdg')->first();
        if ($rateRow && !empty($rateRow->updated_at)) {
            return gmdate('Y-m-d\TH:i:s\Z', strtotime($rateRow->updated_at));
        }

        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * Update settings with validation, type verification, and audit logging.
     *
     * @param array<string, mixed> $data
     * @throws ValidationFailedException
     */
    public function updateSettings(array $data, ?int $userId = null, ?string $ip = null): array
    {
        $errors = [];
        $updates = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, self::ALLOWED_SETTING_KEYS, true)) {
                continue; // Ignore keys outside whitelist
            }

            // Numeric bounds checks
            if ($key === 'lock_hours') {
                $val = (int) $value;
                if ($val < 1 || $val > 72) {
                    $errors['lock_hours'] = 'فترة حجز السعر يجب أن تكون بين ساعة و 72 ساعة.';
                } else {
                    $updates[$key] = (string) $val;
                }
            } elseif ($key === 'app_discount_percent') {
                $val = (int) $value;
                if ($val < 0 || $val > 50) {
                    $errors['app_discount_percent'] = 'نسبة خصم التطبيق يجب أن تكون بين 0% و 50%.';
                } else {
                    $updates[$key] = (string) $val;
                }
            } elseif ($key === 'rounding_step_sdg') {
                $val = (int) $value;
                if ($val < 1 || $val > 1000) {
                    $errors['rounding_step_sdg'] = 'خطوة التقريب يجب أن تكون بين 1 و 1000 ج.س.';
                } else {
                    $updates[$key] = (string) $val;
                }
            } elseif ($key === 'low_stock_threshold') {
                $val = (int) $value;
                if ($val < 0 || $val > 100) {
                    $errors['low_stock_threshold'] = 'حد تنبيه المخزون يجب أن يكون بين 0 و 100.';
                } else {
                    $updates[$key] = (string) $val;
                }
            } elseif (in_array($key, self::BOOLEAN_KEYS, true)) {
                // JSON booleans: (string) false === '' was stored and read back as "on", so
                // turning orders off from the admin panel never reached the storefront.
                $updates[$key] = \App\Libraries\Input::bool($value) ? '1' : '0';
            } elseif ($key === 'shop_map_link') {
                $val = trim((string) $value);
                if (!\App\Libraries\SafeUrl::isSafeLink($val)) {
                    $errors['shop_map_link'] = 'رابط الخريطة غير صالح، يجب أن يبدأ بـ https://';
                } else {
                    $updates[$key] = $val;
                }
            } else {
                // String settings
                $updates[$key] = trim((string) $value);
            }
        }

        // Ordering cannot be opened before the shop is set up (fresh installs start closed).
        if (($updates['orders_enabled'] ?? null) === '1') {
            $setup = (new SetupStatusService())->status();
            if (!$setup['complete']) {
                $labels = array_map(static fn ($m) => $m['label'], array_filter($setup['missing'], static fn ($m) => $m['required']));
                $errors['orders_enabled'] = 'أكمل إعداد المتجر قبل فتح الطلبات: ' . implode('، ', $labels) . '.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationFailedException('بيانات الإعدادات غير صحيحة.', $errors);
        }

        if (empty($updates)) {
            return $this->getAllSettings();
        }

        $now = gmdate('Y-m-d H:i:s');
        $oldValues = [];

        foreach ($updates as $key => $val) {
            $row = $this->settings->where('key', $key)->first();
            if ($row) {
                $oldValues[$key] = $row->value;
                $this->settings->where('key', $key)->set([
                    'value'      => $val,
                    'updated_at' => $now,
                ])->update();
            } else {
                $oldValues[$key] = null;
                $this->settings->insert([
                    'key'        => $key,
                    'value'      => $val,
                    'updated_at' => $now,
                ]);
            }
        }

        // Record audit log
        $this->auditLogs->log(
            $userId,
            'update_settings',
            'settings',
            null,
            ['old' => $oldValues, 'new' => $updates],
            $ip
        );

        return $this->getAllSettings();
    }

    /**
     * Update exchange rate in whole SDG per 1 AED.
     * Records new setting, saves historical entry in exchange_rates, and writes audit log.
     *
     * @throws ValidationFailedException
     */
    public function updateExchangeRate(int $rateSdg, ?int $userId = null, ?string $ip = null): array
    {
        if ($rateSdg < 100 || $rateSdg > 50000) {
            throw new ValidationFailedException('سعر الصرف يجب أن يكون بين 100 و 50,000 ج.س للدرهم.', [
                'rate_sdg' => 'قيمة سعر الصرف خارج النطاق المقبول.',
            ]);
        }

        $now = gmdate('Y-m-d H:i:s');

        // Fetch old rate
        $oldRow = $this->settings->where('key', 'exchange_rate_sdg')->first();
        $oldRate = $oldRow ? (int) $oldRow->value : 0;

        // Update settings table
        if ($oldRow) {
            $this->settings->where('key', 'exchange_rate_sdg')->set([
                'value'      => (string) $rateSdg,
                'updated_at' => $now,
            ])->update();
        } else {
            $this->settings->insert([
                'key'        => 'exchange_rate_sdg',
                'value'      => (string) $rateSdg,
                'updated_at' => $now,
            ]);
        }

        // Record in exchange_rates history table
        $rateId = $this->exchangeRates->insert([
            'rate_sdg'   => $rateSdg,
            'changed_by' => $userId,
            'created_at' => $now,
        ]);

        // Write audit log
        $this->auditLogs->log(
            $userId,
            'rate_updated',
            'exchange_rates',
            $rateId,
            [
                'old_rate_sdg' => $oldRate,
                'new_rate_sdg' => $rateSdg,
            ],
            $ip
        );

        // Touch prices_updated_at
        $this->touchPricesUpdatedAt();

        return [
            'rate_sdg'   => $rateSdg,
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($now)),
        ];
    }

    /**
     * Get paginated exchange rate history.
     */
    public function getRateHistory(int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $total = $this->exchangeRates->countAllResults(false);
        $offset = ($page - 1) * $perPage;

        $rows = $this->exchangeRates
            ->orderBy('id', 'DESC')
            ->findAll($perPage, $offset);

        // Preload user names for changed_by
        $userIds = array_values(array_filter(array_map(fn($r) => $r->changed_by, $rows)));
        $usersMap = [];
        if (!empty($userIds)) {
            $userRows = $this->users->whereIn('id', $userIds)->findAll();
            foreach ($userRows as $u) {
                $usersMap[$u->id] = $u->name;
            }
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'         => $row->id,
                'rate_sdg'   => (int) $row->rate_sdg,
                'changed_by' => $row->changed_by ? [
                    'id'   => $row->changed_by,
                    'name' => $usersMap[$row->changed_by] ?? null,
                ] : null,
                'created_at' => $row->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row->created_at)) : null,
            ];
        }

        return [
            'items' => $items,
            'meta'  => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }
}
