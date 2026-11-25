<?php

namespace App\Commands;

use App\Models\NotificationModel;
use App\Models\OrderModel;
use App\Services\NotificationService;
use App\Services\PushService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\PushTemplates;
use Config\Wex;

/**
 * PushReminders
 *
 * Cron command to send:
 * 1. Customer price-lock expiring reminders (60m and 15m before lock_expires_at)
 * 2. Admin payment review reminders (orders in payment_review > 30 minutes)
 *
 * Intended to run every minute (or every 5 minutes) via cron:
 *   * * * * * cd /path/to/wex-api && php spark push:reminders >> /dev/null 2>&1
 */
class PushReminders extends BaseCommand
{
    protected $group       = 'Push';
    protected $name        = 'push:reminders';
    protected $description = 'Sends price-lock expiring reminders and admin payment-review reminders.';
    protected $usage       = 'push:reminders [review_minutes=30]';

    public function run(array $params)
    {
        $reviewMinutes = isset($params[0]) ? max(5, (int) $params[0]) : 30;
        $now = time();

        $orderModel        = new OrderModel();
        $notificationModel = new NotificationModel();
        $notifService      = new NotificationService();
        $pushService       = new PushService();

        $lockSent = 0;
        $reviewSent = 0;

        // 1. Customer price-lock expiring soon (60m and 15m windows)
        $pendingOrders = $orderModel
            ->where('status', Wex::ORDER_STATUS_PENDING_PAYMENT)
            ->where('lock_expires_at >', gmdate('Y-m-d H:i:s', $now))
            ->where('lock_expires_at <=', gmdate('Y-m-d H:i:s', $now + 3600))
            ->findAll();

        foreach ($pendingOrders as $order) {
            $expiresTs = strtotime($order->lock_expires_at);
            $minsLeft  = (int) ceil(($expiresTs - $now) / 60);
            if ($minsLeft <= 0) {
                continue;
            }

            $window = $minsLeft <= 15 ? 15 : 60;
            $tag    = "lock-expiring-{$order->order_number}-{$window}";

            // Dedupe: check if a price_lock_expiring notification for this window already exists
            $existing = $notificationModel
                ->where('order_id', $order->id)
                ->where('type', 'price_lock_expiring')
                ->like('data_json', $tag)
                ->first();

            if ($existing) {
                continue;
            }

            $tpl = PushTemplates::render('price_lock_expiring', [
                'order_number' => $order->order_number,
                'minutes'      => $minsLeft,
            ]);

            $notifService->notify(
                userId: (int) $order->user_id,
                type: 'price_lock_expiring',
                title: $tpl['title'],
                body: $tpl['body'],
                orderId: (int) $order->id,
                pushOptions: [
                    'order_number'          => $order->order_number,
                    'url'                   => "/orders/{$order->order_number}",
                    'tag'                   => $tag,
                    'expected_order_status' => Wex::ORDER_STATUS_PENDING_PAYMENT,
                ]
            );
            $lockSent++;
        }

        // 2. Admin payment awaiting review older than X minutes
        $cutoff = gmdate('Y-m-d H:i:s', $now - ($reviewMinutes * 60));
        $staleReviewOrders = $orderModel
            ->where('status', Wex::ORDER_STATUS_PAYMENT_REVIEW)
            ->where('updated_at <=', $cutoff)
            ->findAll();

        foreach ($staleReviewOrders as $order) {
            $existing = $notificationModel
                ->where('order_id', $order->id)
                ->where('type', 'admin_payment_reminder')
                ->first();

            if ($existing) {
                continue;
            }

            $tpl = PushTemplates::render('admin_payment_reminder', [
                'order_number' => $order->order_number,
                'minutes'      => $reviewMinutes,
            ]);

            $pushService->sendToRole([Wex::ROLE_ADMIN, Wex::ROLE_STAFF], [
                'type'                  => 'admin_payment_reminder',
                'title'                 => $tpl['title'],
                'body'                  => $tpl['body'],
                'order_id'              => (int) $order->id,
                'order_number'          => $order->order_number,
                'url'                   => "/admin/orders/{$order->order_number}",
                'tag'                   => "admin-review-{$order->order_number}",
                'expected_order_status' => Wex::ORDER_STATUS_PAYMENT_REVIEW,
            ]);
            $reviewSent++;
        }

        CLI::write("push:reminders complete: lock_expiring={$lockSent}, admin_review={$reviewSent}", 'green');
        return EXIT_SUCCESS;
    }
}
