<?php

namespace App\Services;

use App\Entities\AuditLog;
use App\Models\AuditLogModel;

/**
 * AuditLogService
 *
 * Records auditable actions taken by admin/staff or automated workers.
 */
class AuditLogService
{
    protected AuditLogModel $auditLogs;

    public function __construct(?AuditLogModel $auditLogs = null)
    {
        $this->auditLogs = $auditLogs ?? new AuditLogModel();
    }

    /**
     * Record an audit log entry.
     */
    public function log(
        ?int $userId,
        string $action,
        string $entity,
        ?int $entityId = null,
        array $details = [],
        ?string $ip = null
    ): void {
        $entry = new AuditLog([
            'user_id'    => $userId,
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'details'    => $details,
            'ip'         => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogs->insert($entry);
    }
}
