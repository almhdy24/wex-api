<?php

namespace Config;

/**
 * Centralized Modern Standard Arabic (Fusha) push & notification templates.
 * Never includes sensitive data (bank account numbers, phone numbers, receipt images, AED/rates, or admin names).
 */
class PushTemplates
{
    public const CATEGORY_MAP = [
        'order_created'          => 'order_updates',
        'order_confirmed'        => 'order_updates',
        'order_ready_pickup'     => 'order_updates',
        'order_out_delivery'     => 'order_updates',
        'order_completed'        => 'order_updates',
        'order_cancelled'        => 'order_updates',
        'order_expired'          => 'order_updates',
        'price_lock_expiring'    => 'order_updates',
        'payment_proof_uploaded' => 'payment_updates',
        'payment_approved'       => 'payment_updates',
        'payment_rejected'       => 'payment_updates',
        'announcement'           => 'announcements',
        'price_alert'            => 'price_alerts',
        'admin_new_order'        => 'new_orders',
        'admin_receipt_uploaded' => 'receipts',
        'admin_payment_reminder' => 'receipts',
        'admin_low_stock'        => 'low_stock',
        'test'                   => 'order_updates',
    ];

    public const CRITICAL_TYPES = [
        'order_created',
        'payment_approved',
        'payment_rejected',
        'order_ready_pickup',
        'order_out_delivery',
        'price_lock_expiring',
        'admin_new_order',
        'admin_receipt_uploaded',
        'admin_payment_reminder',
    ];

    public static function getCategory(string $type): string
    {
        return self::CATEGORY_MAP[$type] ?? 'order_updates';
    }

    public static function isCritical(string $type): bool
    {
        return in_array($type, self::CRITICAL_TYPES, true);
    }

    public static function render(string $type, array $vars = []): array
    {
        $orderNumber = (string) ($vars['order_number'] ?? '');
        $minutes     = (int) ($vars['minutes'] ?? 0);
        $productName = (string) ($vars['product_name'] ?? '');

        return match ($type) {
            'order_created' => [
                'title' => 'تم استلام طلبك بنجاح',
                'body'  => "تم إنشاء طلبك رقم {$orderNumber} وحجز السعر. ارفع إشعار التحويل لإتمام الطلب.",
            ],
            'payment_proof_uploaded' => [
                'title' => 'تم استلام إشعار التحويل',
                'body'  => "طلبك رقم {$orderNumber} قيد مراجعة التحويل البنكي حالياً.",
            ],
            'payment_approved' => [
                'title' => 'تم تأكيد الدفع بنجاح',
                'body'  => "تم تأكيد الدفع لطلبك رقم {$orderNumber} وجاري التجهيز.",
            ],
            'payment_rejected' => [
                'title' => 'يرجى مراجعة الطلب',
                'body'  => "تعذر اعتماد إشعار التحويل للطلب رقم {$orderNumber}. يرجى مراجعة الطلب.",
            ],
            'order_confirmed' => [
                'title' => 'جاري تجهيز طلبك',
                'body'  => "طلبك رقم {$orderNumber} مؤكد وجاري تجهيزه.",
            ],
            'order_ready_pickup' => [
                'title' => 'طلبك جاهز للاستلام',
                'body'  => "طلبك رقم {$orderNumber} جاهز للاستلام من المتجر.",
            ],
            'order_out_delivery' => [
                'title' => 'طلبك في الطريق إليك',
                'body'  => "طلبك رقم {$orderNumber} خرج للتوصيل مع المندوب.",
            ],
            'order_completed' => [
                'title' => 'تم تسليم طلبك',
                'body'  => "تم تسليم طلبك رقم {$orderNumber} بنجاح. شكراً لثقتك بويكس تكنولوجي.",
            ],
            'order_cancelled' => [
                'title' => 'تم إلغاء الطلب',
                'body'  => "تم إلغاء طلبك رقم {$orderNumber}.",
            ],
            'price_lock_expiring' => [
                'title' => 'قرب انتهاء حجز السعر',
                'body'  => "تبقى {$minutes} دقيقة على انتهاء حجز السعر للطلب رقم {$orderNumber}. يرجى رفع إشعار التحويل.",
            ],
            'order_expired' => [
                'title' => 'انتهى حجز السعر للطلب',
                'body'  => "انتهت مهلة حجز السعر للطلب رقم {$orderNumber} وتم إلغاء الحجز.",
            ],
            'admin_new_order' => [
                'title' => 'طلب جديد',
                'body'  => "تم استلام طلب جديد رقم {$orderNumber}.",
            ],
            'admin_receipt_uploaded' => [
                'title' => 'إشعار تحويل جديد',
                'body'  => "تم رفع إشعار تحويل بنكي للطلب رقم {$orderNumber}.",
            ],
            'admin_payment_reminder' => [
                'title' => 'تذكير بمراجعة الدفع',
                'body'  => "الطلب رقم {$orderNumber} بانتظار مراجعة الدفع منذ أكثر من {$minutes} دقيقة.",
            ],
            'admin_low_stock' => [
                'title' => 'تنبيه انخفاض المخزون',
                'body'  => "المنتج {$productName} وصل إلى حد المخزون المنخفض.",
            ],
            'test' => [
                'title' => 'إشعار تجريبي من ويكس',
                'body'  => 'تم تفعيل الإشعارات بنجاح على هذا الجهاز.',
            ],
            default => [
                'title' => (string) ($vars['title'] ?? 'إشعار من ويكس'),
                'body'  => (string) ($vars['body'] ?? ''),
            ],
        };
    }
}
