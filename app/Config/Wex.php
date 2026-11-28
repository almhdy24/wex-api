<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Wex Technology Application Configuration and Constants
 */
class Wex extends BaseConfig
{
    // User Roles
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_STAFF    = 'staff';
    public const ROLE_ADMIN    = 'admin';

    public const ROLES = [
        self::ROLE_CUSTOMER,
        self::ROLE_STAFF,
        self::ROLE_ADMIN,
    ];

    // Product Types
    public const PRODUCT_TYPE_LAPTOP    = 'laptop';
    public const PRODUCT_TYPE_PHONE     = 'phone';
    public const PRODUCT_TYPE_ACCESSORY = 'accessory';

    public const PRODUCT_TYPES = [
        self::PRODUCT_TYPE_LAPTOP,
        self::PRODUCT_TYPE_PHONE,
        self::PRODUCT_TYPE_ACCESSORY,
    ];

    // Product Conditions
    public const CONDITION_NEW           = 'new';
    public const CONDITION_USED_IMPORTED = 'used_imported';

    public const CONDITION_REFURBISHED   = 'refurbished';

    public const CONDITIONS = [
        self::CONDITION_NEW,
        self::CONDITION_USED_IMPORTED,
        self::CONDITION_REFURBISHED,
    ];

    // Delivery Types
    public const DELIVERY_TYPE_PICKUP   = 'pickup';
    public const DELIVERY_TYPE_DELIVERY = 'delivery';

    public const DELIVERY_TYPES = [
        self::DELIVERY_TYPE_PICKUP,
        self::DELIVERY_TYPE_DELIVERY,
    ];

    // Order Statuses
    public const ORDER_STATUS_PENDING_PAYMENT  = 'pending_payment';
    public const ORDER_STATUS_PAYMENT_REVIEW   = 'payment_review';
    public const ORDER_STATUS_CONFIRMED        = 'confirmed';
    public const ORDER_STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const ORDER_STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const ORDER_STATUS_COMPLETED        = 'completed';
    public const ORDER_STATUS_CANCELLED        = 'cancelled';
    public const ORDER_STATUS_EXPIRED          = 'expired';

    public const ORDER_STATUSES = [
        self::ORDER_STATUS_PENDING_PAYMENT,
        self::ORDER_STATUS_PAYMENT_REVIEW,
        self::ORDER_STATUS_CONFIRMED,
        self::ORDER_STATUS_READY_FOR_PICKUP,
        self::ORDER_STATUS_OUT_FOR_DELIVERY,
        self::ORDER_STATUS_COMPLETED,
        self::ORDER_STATUS_CANCELLED,
        self::ORDER_STATUS_EXPIRED,
    ];

    // Payment Proof Statuses
    public const PROOF_STATUS_PENDING  = 'pending';
    public const PROOF_STATUS_APPROVED = 'approved';
    public const PROOF_STATUS_REJECTED = 'rejected';
    public const PROOF_STATUS_REPLACED = 'replaced';

    public const PROOF_STATUSES = [
        self::PROOF_STATUS_PENDING,
        self::PROOF_STATUS_APPROVED,
        self::PROOF_STATUS_REJECTED,
        self::PROOF_STATUS_REPLACED,
    ];

    // Email Outbox Statuses
    public const EMAIL_STATUS_PENDING = 'pending';
    public const EMAIL_STATUS_SENT    = 'sent';
    public const EMAIL_STATUS_FAILED  = 'failed';

    // Stock Statuses (for public API)
    public const STOCK_STATUS_IN_STOCK     = 'in_stock';
    public const STOCK_STATUS_LOW_STOCK    = 'low_stock';
    public const STOCK_STATUS_OUT_OF_STOCK = 'out_of_stock';

    // Default Settings Values
    public int $defaultExchangeRateSdg = 650;
    public int $defaultLockHours = 4;
    public int $defaultAppDiscountPercent = 3;
    public int $defaultRoundingStepSdg = 100;
    public int $defaultLowStockThreshold = 2;

    // Sudan 18 States (in Arabic)
    public const SUDAN_STATES = [
        'الخرطوم',
        'أم درمان',
        'بحري',
        'الجزيرة',
        'البحر الأحمر',
        'كسلا',
        'القضارف',
        'سنار',
        'النيل الأزرق',
        'النيل الأبيض',
        'الشمالية',
        'نهر النيل',
        'شمال كردفان',
        'جنوب كردفان',
        'غرب كردفان',
        'شمال دارفور',
        'جنوب دارفور',
        'غرب دارفور',
        'وسط دارفور',
        'شرق دارفور',
    ];
}
