<?php

namespace App\Presenters;

use App\Entities\Order;

/**
 * CustomerOrderPresenter
 *
 * Strict whitelist for customer-visible order representations.
 * Never includes rate_sdg_snapshot, subtotal_aed, unit_price_aed, or line_total_aed.
 */
class CustomerOrderPresenter
{
    /**
     * Formats an order detail response for customers.
     */
    public static function present(array|Order $order): array
    {
        $arr = is_array($order) ? $order : $order->toArray();

        $items = array_map(static function ($item) {
            $iArr = is_array($item) ? $item : $item->toArray();
            return [
                'id'             => (int) ($iArr['id'] ?? 0),
                'product_id'     => (int) ($iArr['product_id'] ?? 0),
                'name'           => (string) ($iArr['name'] ?? $iArr['product_name_snapshot'] ?? ''),
                'type'           => (string) ($iArr['type'] ?? $iArr['product_type_snapshot'] ?? ''),
                'unit_price_sdg' => (int) ($iArr['unit_price_sdg'] ?? 0),
                'quantity'       => (int) ($iArr['quantity'] ?? 1),
                'line_total_sdg' => (int) ($iArr['line_total_sdg'] ?? 0),
            ];
        }, $arr['items'] ?? []);

        $timeline = array_map(static function ($event) {
            $eArr = is_array($event) ? $event : $event->toArray();
            return [
                'id'          => (int) ($eArr['id'] ?? 0),
                'from_status' => $eArr['from_status'] ?? null,
                'to_status'   => (string) ($eArr['to_status'] ?? ''),
                'status_text' => (string) ($eArr['status_text'] ?? ''),
                'note'        => (string) ($eArr['note'] ?? ''),
                'created_at'  => $eArr['created_at'] ?? null,
            ];
        }, $arr['timeline'] ?? []);

        $proofs = array_map(static function ($p) {
            $pArr = is_array($p) ? $p : $p->toArray();
            return [
                'id'               => (int) ($pArr['id'] ?? 0),
                'status'           => (string) ($pArr['status'] ?? ''),
                'sender_name'      => $pArr['sender_name'] ?? null,
                'reference_number' => $pArr['reference_number'] ?? null,
                'review_note'      => $pArr['review_note'] ?? null,
                'image_url'        => (string) ($pArr['image_url'] ?? ''),
                'created_at'       => $pArr['created_at'] ?? null,
                'reviewed_at'      => $pArr['reviewed_at'] ?? null,
            ];
        }, $arr['payment_proofs'] ?? []);

        $bankDetails = isset($arr['bank_details']) && is_array($arr['bank_details']) ? [
            'bank_name'           => (string) ($arr['bank_details']['bank_name'] ?? ''),
            'bank_account_name'   => (string) ($arr['bank_details']['bank_account_name'] ?? ''),
            'bank_account_number' => (string) ($arr['bank_details']['bank_account_number'] ?? ''),
            'amount_sdg'          => (int) ($arr['bank_details']['amount_sdg'] ?? ($arr['total_sdg'] ?? 0)),
            'order_number'        => (string) ($arr['bank_details']['order_number'] ?? ($arr['order_number'] ?? '')),
        ] : null;

        return [
            'order_number'                  => (string) ($arr['order_number'] ?? ''),
            'status'                        => (string) ($arr['status'] ?? ''),
            'status_label'                  => (string) ($arr['status_label'] ?? ''),
            'delivery_type'                 => (string) ($arr['delivery_type'] ?? ''),
            'delivery_phone'                => $arr['delivery_phone'] ?? null,
            'delivery_address'              => $arr['delivery_address'] ?? null,
            'customer_note'                 => $arr['customer_note'] ?? null,
            'app_discount_percent_snapshot' => (int) ($arr['app_discount_percent_snapshot'] ?? 0),
            'subtotal_sdg'                  => (int) ($arr['subtotal_sdg'] ?? 0),
            'discount_sdg'                  => (int) ($arr['discount_sdg'] ?? 0),
            'delivery_fee_sdg'              => (int) ($arr['delivery_fee_sdg'] ?? 0),
            'total_sdg'                     => (int) ($arr['total_sdg'] ?? 0),
            'lock_expires_at'               => $arr['lock_expires_at'] ?? null,
            'seconds_remaining'             => (int) ($arr['seconds_remaining'] ?? 0),
            'courier_name'                  => $arr['courier_name'] ?? null,
            'courier_phone'                 => $arr['courier_phone'] ?? null,
            'cancelled_reason'              => $arr['cancelled_reason'] ?? null,
            'bank_details'                  => $bankDetails,
            'payment_accounts'              => $arr['payment_accounts'] ?? [],
            'payment_general_notes'         => (string) ($arr['payment_general_notes'] ?? ''),
            'items'                         => $items,
            'timeline'                      => $timeline,
            'payment_proofs'                => $proofs,
            'created_at'                    => $arr['created_at'] ?? null,
            'updated_at'                    => $arr['updated_at'] ?? null,
        ];
    }

    /**
     * Formats a single order item for customer list view.
     */
    public static function presentListItem(array|Order $order): array
    {
        $arr = is_array($order) ? $order : $order->toArray();

        return [
            'order_number'      => (string) ($arr['order_number'] ?? ''),
            'status'            => (string) ($arr['status'] ?? ''),
            'status_label'      => (string) ($arr['status_label'] ?? ''),
            'delivery_type'     => (string) ($arr['delivery_type'] ?? ''),
            'total_sdg'         => (int) ($arr['total_sdg'] ?? 0),
            'lock_expires_at'   => $arr['lock_expires_at'] ?? null,
            'seconds_remaining' => (int) ($arr['seconds_remaining'] ?? 0),
            'created_at'        => $arr['created_at'] ?? null,
        ];
    }

    /**
     * Formats a collection of orders for customer list view.
     */
    public static function presentCollection(array $orders): array
    {
        return array_map(static fn($o) => self::presentListItem($o), $orders);
    }
}
