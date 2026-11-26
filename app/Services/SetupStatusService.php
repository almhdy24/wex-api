<?php

namespace App\Services;

/**
 * What a fresh shop still needs before it can take orders. Ordering stays closed (the
 * installer writes orders_enabled = 0) and cannot be switched on until every REQUIRED item
 * is done; RECOMMENDED items only show in the admin checklist.
 */
class SetupStatusService
{
    /**
     * @return array{complete: bool, missing: list<array{key: string, label: string, required: bool, link: string}>}
     */
    public function status(): array
    {
        $db = \Config\Database::connect();
        $s = [];
        foreach ($db->table('settings')->get()->getResult() as $row) {
            $s[$row->key] = trim((string) $row->value);
        }

        $missing = [];
        $add = static function (string $key, string $label, bool $required, string $link) use (&$missing) {
            $missing[] = ['key' => $key, 'label' => $label, 'required' => $required, 'link' => $link];
        };

        if (($s['shop_phone'] ?? '') === '' && ($s['shop_whatsapp'] ?? '') === '') {
            $add('contact', 'رقم الهاتف أو الواتساب', true, '/admin/settings#settings-store');
        }
        if (($s['shop_address'] ?? '') === '') {
            $add('address', 'عنوان المحل', true, '/admin/settings#settings-store');
        }
        if (($s['working_hours'] ?? '') === '') {
            $add('hours', 'ساعات العمل', true, '/admin/settings#settings-store');
        }
        if (!$db->tableExists('payment_accounts')
            || $db->table('payment_accounts')->where('is_active', 1)->countAllResults() === 0) {
            $add('payment_account', 'حساب دفع بنكي مفعّل', true, '/admin/payment-accounts');
        }
        if (($s['exchange_rate_sdg'] ?? '') === '' || (int) $s['exchange_rate_sdg'] <= 0) {
            $add('exchange_rate', 'سعر الصرف', true, '/admin/rates');
        }
        if (!$db->tableExists('shipping_zones')
            || $db->table('shipping_zones')->where('is_active', 1)->countAllResults() === 0) {
            $add('shipping_zones', 'مناطق التوصيل (اختياري للاستلام من المحل فقط)', false, '/admin/shipping-zones');
        }

        $requiredMissing = array_filter($missing, static fn ($m) => $m['required']);

        return ['complete' => $requiredMissing === [], 'missing' => $missing];
    }
}
