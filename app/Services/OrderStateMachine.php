<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ConflictException;
use Config\Wex;

/**
 * OrderStateMachine
 *
 * Defines valid order statuses, allowed transitions, and side-effect rules.
 */
class OrderStateMachine
{
    /**
     * Transition rules matrix:
     * current_status => [allowed_target_statuses]
     */
    protected const ALLOWED_TRANSITIONS = [
        Wex::ORDER_STATUS_PENDING_PAYMENT => [
            Wex::ORDER_STATUS_PAYMENT_REVIEW,
            Wex::ORDER_STATUS_CANCELLED,
            Wex::ORDER_STATUS_EXPIRED,
        ],
        Wex::ORDER_STATUS_PAYMENT_REVIEW => [
            Wex::ORDER_STATUS_CONFIRMED,
            Wex::ORDER_STATUS_PENDING_PAYMENT, // rejected proof with valid lock
            Wex::ORDER_STATUS_EXPIRED,         // rejected proof with expired lock
            Wex::ORDER_STATUS_CANCELLED,
        ],
        Wex::ORDER_STATUS_CONFIRMED => [
            Wex::ORDER_STATUS_READY_FOR_PICKUP,
            Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
            Wex::ORDER_STATUS_COMPLETED,
            Wex::ORDER_STATUS_CANCELLED,
        ],
        Wex::ORDER_STATUS_READY_FOR_PICKUP => [
            Wex::ORDER_STATUS_COMPLETED,
            Wex::ORDER_STATUS_CANCELLED,
        ],
        Wex::ORDER_STATUS_OUT_FOR_DELIVERY => [
            Wex::ORDER_STATUS_COMPLETED,
            Wex::ORDER_STATUS_CANCELLED,
        ],
        Wex::ORDER_STATUS_COMPLETED => [], // Terminal state
        Wex::ORDER_STATUS_CANCELLED => [], // Terminal state
        Wex::ORDER_STATUS_EXPIRED   => [], // Terminal state
    ];

    /**
     * Checks if a transition from one status to another is legal.
     */
    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        return in_array($toStatus, $allowed, true);
    }

    /**
     * Asserts that a transition is legal, throwing a ConflictException otherwise.
     *
     * @throws ConflictException
     */
    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (!self::canTransition($fromStatus, $toStatus)) {
            $fromName = self::getStatusLabel($fromStatus);
            $toName   = self::getStatusLabel($toStatus);

            throw new ConflictException("لا يمكن تغيير حالة الطلب من [{$fromName}] إلى [{$toName}].");
        }
    }

    /**
     * Determines whether the transition into the new status requires restoring reserved stock.
     */
    public static function shouldRestoreStock(string $fromStatus, string $toStatus): bool
    {
        // If moving to cancelled or expired from an active holding state, release reserved stock
        if (in_array($toStatus, [Wex::ORDER_STATUS_CANCELLED, Wex::ORDER_STATUS_EXPIRED], true)) {
            return in_array($fromStatus, [
                Wex::ORDER_STATUS_PENDING_PAYMENT,
                Wex::ORDER_STATUS_PAYMENT_REVIEW,
                Wex::ORDER_STATUS_CONFIRMED,
            ], true);
        }

        return false;
    }

    /**
     * Friendly Arabic status label for UI/customer messages.
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            Wex::ORDER_STATUS_PENDING_PAYMENT  => 'في انتظار التحويل البنكي',
            Wex::ORDER_STATUS_PAYMENT_REVIEW   => 'مراجعة التحويل',
            Wex::ORDER_STATUS_CONFIRMED        => 'مؤكد وجاري التجهيز',
            Wex::ORDER_STATUS_READY_FOR_PICKUP => 'جاهز للاستلام من المحل',
            Wex::ORDER_STATUS_OUT_FOR_DELIVERY => 'خرج مع المندوب للتوصيل',
            Wex::ORDER_STATUS_COMPLETED        => 'مكتمل ومستلم',
            Wex::ORDER_STATUS_CANCELLED        => 'ملغي',
            Wex::ORDER_STATUS_EXPIRED          => 'منتهي الصلاحية',
            default                            => $status,
        };
    }
}
