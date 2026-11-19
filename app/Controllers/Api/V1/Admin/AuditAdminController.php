<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Entities\AuditLog;
use App\Models\AuditLogModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AuditAdminController
 *
 * Audit trail viewer for administrative actions and security events.
 * Accessible by Admin only.
 */
class AuditAdminController extends BaseApiController
{
    protected AuditLogModel $auditLogs;
    protected UserModel $users;

    public function __construct(
        ?AuditLogModel $auditLogs = null,
        ?UserModel $users = null
    ) {
        $this->auditLogs = $auditLogs ?? new AuditLogModel();
        $this->users     = $users ?? new UserModel();
    }

    /**
     * GET /api/v1/admin/audit-logs
     *
     * List audit logs with pagination and optional filters.
     */
    public function index(): ResponseInterface
    {
        [$page, $perPage] = $this->getPaginationParams();

        $builder = $this->auditLogs;

        $action = $this->request->getGet('action');
        if (!empty($action)) {
            $builder->where('action', $action);
        }

        $userId = $this->request->getGet('user_id');
        if (!empty($userId)) {
            $builder->where('user_id', (int) $userId);
        }

        $entity = $this->request->getGet('entity');
        if (!empty($entity)) {
            $builder->where('entity', $entity);
        }

        $total  = $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;

        $logs = $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);

        // Preload actors
        $userIds = array_values(array_filter(array_map(fn($l) => $l->user_id, $logs)));
        $usersMap = [];
        if (!empty($userIds)) {
            $userRows = $this->users->whereIn('id', $userIds)->findAll();
            foreach ($userRows as $u) {
                $usersMap[$u->id] = [
                    'id'    => $u->id,
                    'name'  => $u->name,
                    'email' => $u->email,
                ];
            }
        }

        $items = array_map(function (AuditLog $l) use ($usersMap) {
            return [
                'id'         => $l->id,
                'user'       => $l->user_id ? ($usersMap[$l->user_id] ?? null) : null,
                'action'     => $l->action,
                'entity'     => $l->entity,
                'entity_id'  => $l->entity_id,
                'details'    => $l->getDetails(),
                'ip'         => $l->ip,
                'created_at' => $l->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($l->created_at)) : null,
            ];
        }, $logs);

        return $this->respondSuccess(
            $items,
            'سجل العمليات الإدارية',
            ResponseInterface::HTTP_OK,
            [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]
        );
    }
}
