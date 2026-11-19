<?php

namespace App\Commands;

use App\Services\OrderService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * OrdersExpire
 *
 * CLI command to process price-lock expirations on overdue pending_payment orders.
 * Intended to be triggered every minute via cron:
 *   * * * * * cd /path/to/wex-api && php spark orders:expire >> /dev/null 2>&1
 */
class OrdersExpire extends BaseCommand
{
    protected $group       = 'Orders';
    protected $name        = 'orders:expire';
    protected $description = 'Expires overdue pending_payment orders and restores product stock.';
    protected $usage       = 'orders:expire';

    public function run(array $params)
    {
        CLI::write('[' . gmdate('Y-m-d H:i:s') . ' UTC] Running orders:expire check...', 'yellow');

        $orderService = new OrderService();
        $expiredCount = $orderService->expireOverdueOrders();

        if ($expiredCount > 0) {
            CLI::write("Successfully expired {$expiredCount} overdue order(s) and restored stock.", 'green');
        } else {
            CLI::write('No overdue orders found.', 'white');
        }

        return EXIT_SUCCESS;
    }
}
