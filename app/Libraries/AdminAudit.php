<?php

namespace App\Libraries;

use App\Services\AuditLogService;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Thin helper so controllers can audit a mutation in one line without letting an
 * audit failure break the business action.
 */
final class AdminAudit
{
    /**
     * @param array<string, mixed> $details
     */
    public static function log(RequestInterface $request, ?object $actor, string $action, string $entity, ?int $entityId, array $details = []): void
    {
        try {
            (new AuditLogService())->log(
                $actor ? (int) $actor->id : null,
                $action,
                $entity,
                $entityId,
                $details,
                method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null
            );
        } catch (\Throwable $e) {
            log_message('error', 'Audit log write failed for {action}: {msg}', ['action' => $action, 'msg' => $e->getMessage()]);
        }
    }
}
