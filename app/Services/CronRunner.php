<?php

namespace App\Services;

/**
 * CronRunner
 *
 * Runs the periodic jobs in one bounded pass, for shared hosting without a real crontab
 * (HelioHost: Plesk "Scheduled Tasks" — CLI or "Fetch a URL" — or an external pinger).
 *
 * - Overlap-safe: a non-blocking flock on WRITEPATH/cron.lock; a second run returns 'busy'.
 * - Time-boxed: stops starting new tasks once CRON_TIME_BUDGET_SECONDS (default 20) is used.
 * - Idempotent: every task is safe to repeat (CAS expiry, leased outboxes, deduped reminders).
 * - integrity:check runs at most once per UTC day (state in WRITEPATH/cron_state.json).
 */
class CronRunner
{
    /** @var array<string, callable(): mixed> */
    protected array $tasks;

    public function __construct(?array $tasks = null)
    {
        $this->tasks = $tasks ?? [
            'orders:expire'    => static fn () => (new OrderService())->expireOverdueOrders(),
            'push:work'        => static fn () => (new PushService())->processOutbox(50),
            'push:reminders'   => static fn () => trim((string) command('push:reminders')),
            'mail:send'        => static fn () => (new MailService())->processOutbox(20),
            'integrity:check'  => static fn () => (new IntegrityService())->checkIntegrity()['total_violations'] ?? null,
        ];
    }

    /**
     * @return array{status: 'ok'|'busy', ran: array<string, mixed>, skipped: list<string>, seconds: float}
     */
    public function run(?float $budgetSeconds = null): array
    {
        $budget  = $budgetSeconds ?? max(1.0, (float) env('CRON_TIME_BUDGET_SECONDS', 20));
        $started = microtime(true);

        $lock = fopen(WRITEPATH . 'cron.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['status' => 'busy', 'ran' => [], 'skipped' => array_keys($this->tasks), 'seconds' => 0.0];
        }

        $state   = $this->readState();
        $ran     = [];
        $skipped = [];

        try {
            foreach ($this->tasks as $name => $task) {
                if ($name === 'integrity:check' && ($state['integrity_day'] ?? '') === gmdate('Y-m-d')) {
                    continue; // daily task already done today
                }
                if (microtime(true) - $started >= $budget) {
                    $skipped[] = $name;
                    continue;
                }
                try {
                    $ran[$name] = $task();
                    if ($name === 'integrity:check') {
                        $state['integrity_day'] = gmdate('Y-m-d');
                        if ((int) $ran[$name] > 0) {
                            log_message('critical', 'integrity:check found {n} violations', ['n' => $ran[$name]]);
                        }
                    }
                } catch (\Throwable $e) {
                    $ran[$name] = 'error';
                    log_message('error', 'cron task {task} failed: {msg}', ['task' => $name, 'msg' => $e->getMessage()]);
                }
            }
            $state['last_run'] = gmdate('c');
            $this->writeState($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['status' => 'ok', 'ran' => $ran, 'skipped' => $skipped, 'seconds' => round(microtime(true) - $started, 3)];
    }

    /**
     * Constant-time check of a presented token against CRON_TOKEN. A missing or short
     * (< 32 chars) configured token disables the HTTP runner entirely.
     */
    public static function tokenIsValid(?string $presented): bool
    {
        $expected = (string) env('CRON_TOKEN', '');
        if (strlen($expected) < 32 || $presented === null || $presented === '') {
            return false;
        }

        return hash_equals($expected, $presented);
    }

    protected function readState(): array
    {
        $raw = @file_get_contents(WRITEPATH . 'cron_state.json');
        $data = $raw ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    protected function writeState(array $state): void
    {
        @file_put_contents(WRITEPATH . 'cron_state.json', json_encode($state), LOCK_EX);
    }
}
