<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Entities\User;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\OrderModel;
use App\Models\UserModel;
use App\Services\AuditLogService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Wex;

/**
 * UserAdminController
 *
 * User account management, role assignment, and active status toggling.
 * Accessible by Admin only.
 */
class UserAdminController extends BaseApiController
{
    protected UserModel $users;
    protected OrderModel $orders;
    protected AuditLogService $auditLogs;

    public function __construct(
        ?UserModel $users = null,
        ?OrderModel $orders = null,
        ?AuditLogService $auditLogs = null
    ) {
        $this->users     = $users ?? new UserModel();
        $this->orders    = $orders ?? new OrderModel();
        $this->auditLogs = $auditLogs ?? new AuditLogService();
    }

    /**
     * GET /api/v1/admin/users
     *
     * List users with optional role filtering, search query, and pagination.
     */
    public function index(): ResponseInterface
    {
        [$page, $perPage] = $this->getPaginationParams();

        $builder = $this->users;

        $role = $this->request->getGet('role');
        if (!empty($role) && in_array($role, Wex::ROLES, true)) {
            $builder->where('role', $role);
        }

        $isActive = $this->request->getGet('is_active');
        if ($isActive !== null && $isActive !== '') {
            $builder->where('is_active', (int) $isActive);
        }

        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $builder->groupStart()
                ->like('name', $q)
                ->orLike('email', $q)
                ->orLike('phone', $q)
                ->groupEnd();
        }

        $total  = $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;

        $users = $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);

        $items = array_map(function (User $u) {
            return [
                'id'                  => $u->id,
                'name'                => $u->name,
                'email'               => $u->email,
                'phone'               => $u->phone,
                'role'                => $u->role,
                'is_active'           => (bool) $u->is_active,
                'email_notifications' => (bool) $u->email_notifications,
                'last_login_at'       => $u->last_login_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($u->last_login_at)) : null,
                'created_at'          => $u->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($u->created_at)) : null,
            ];
        }, $users);

        return $this->respondSuccess(
            $items,
            'قائمة المستخدمين',
            ResponseInterface::HTTP_OK,
            [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]
        );
    }

    /**
     * GET /api/v1/admin/users/{id}
     *
     * View detailed user profile and purchasing stats.
     */
    public function show(int $id): ResponseInterface
    {
        $user = $this->users->find($id);
        if (!$user) {
            throw new NotFoundException('المستخدم غير موجود.');
        }

        // Purchasing stats
        $ordersCount = $this->orders->where('user_id', $id)->countAllResults();

        $completedOrdersRow = $this->orders
            ->selectSum('total_sdg', 'spent')
            ->where('user_id', $id)
            ->whereIn('status', [Wex::ORDER_STATUS_CONFIRMED, Wex::ORDER_STATUS_COMPLETED])
            ->first();

        $totalSpentSdg = $completedOrdersRow ? (int) ($completedOrdersRow->spent ?? 0) : 0;

        $data = [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'phone'               => $user->phone,
            'role'                => $user->role,
            'is_active'           => (bool) $user->is_active,
            'email_notifications' => (bool) $user->email_notifications,
            'last_login_at'       => $user->last_login_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($user->last_login_at)) : null,
            'orders_count'        => $ordersCount,
            'total_spent_sdg'     => $totalSpentSdg,
            'created_at'          => $user->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($user->created_at)) : null,
        ];

        return $this->respondSuccess($data, 'بيانات المستخدم');
    }

    /**
     * PATCH /api/v1/admin/users/{id}/role
     *
     * Change user role (customer, staff, admin).
     */
    public function updateRole(int $id): ResponseInterface
    {
        $currentUser = $this->currentUser();
        if ((int) $currentUser->id === $id) {
            throw new ForbiddenException('لا يمكنك تعديل صلاحيات حسابك بنفسك.');
        }

        $user = $this->users->find($id);
        if (!$user) {
            throw new NotFoundException('المستخدم غير موجود.');
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $newRole = trim((string) ($payload['role'] ?? ''));

        if (!in_array($newRole, Wex::ROLES, true)) {
            throw new ValidationFailedException('الدور المحدد غير صالح.', [
                'role' => 'يجب أن يكون الدور customer أو staff أو admin.',
            ]);
        }

        $oldRole = $user->role;
        $this->users->update($id, ['role' => $newRole]);

        $this->auditLogs->log(
            userId: (int) $currentUser->id,
            action: 'change_user_role',
            entity: 'users',
            entityId: $id,
            details: ['old_role' => $oldRole, 'new_role' => $newRole],
            ip: $this->request->getIPAddress()
        );

        $updatedUser = $this->users->find($id);

        return $this->respondSuccess([
            'id'   => $updatedUser->id,
            'role' => $updatedUser->role,
        ], 'تم تحديث دور المستخدم بنجاح');
    }

    /**
     * PATCH /api/v1/admin/users/{id}/status
     *
     * Activate or deactivate a user account.
     */
    public function updateStatus(int $id): ResponseInterface
    {
        $currentUser = $this->currentUser();
        if ((int) $currentUser->id === $id) {
            throw new ForbiddenException('لا يمكنك تعطيل حسابك بنفسك.');
        }

        $user = $this->users->find($id);
        if (!$user) {
            throw new NotFoundException('المستخدم غير موجود.');
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!isset($payload['is_active'])) {
            throw new ValidationFailedException('حالة الحساب مطلوبة.', [
                'is_active' => 'حقل is_active مطلوب (true أو false).',
            ]);
        }

        $newStatus = (bool) $payload['is_active'];
        $oldStatus = (bool) $user->is_active;

        $this->users->update($id, ['is_active' => $newStatus ? 1 : 0]);

        $this->auditLogs->log(
            userId: (int) $currentUser->id,
            action: 'change_user_status',
            entity: 'users',
            entityId: $id,
            details: ['old_is_active' => $oldStatus, 'new_is_active' => $newStatus],
            ip: $this->request->getIPAddress()
        );

        $updatedUser = $this->users->find($id);

        $msg = $newStatus ? 'تم تفعيل الحساب بنجاح' : 'تم تعطيل الحساب بنجاح';

        return $this->respondSuccess([
            'id'        => $updatedUser->id,
            'is_active' => (bool) $updatedUser->is_active,
        ], $msg);
    }
}
