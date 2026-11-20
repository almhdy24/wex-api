<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\ForbiddenException;
use App\Services\PaymentAccountService;
use CodeIgniter\HTTP\ResponseInterface;

class PaymentAccountAdminController extends BaseApiController
{
    protected PaymentAccountService $service;

    public function __construct(?PaymentAccountService $service = null)
    {
        $this->service = $service ?? new PaymentAccountService();
    }

    protected function assertAdminOnly(): void
    {
        $user = $this->request->auth_user ?? null;
        if (!$user || $user->role !== 'admin') {
            throw new ForbiddenException('إدارة حسابات الدفع مقتصرة حصرياً على المشرفين ذوي صلاحية الإدارة (admin).');
        }
    }

    public function index(): ResponseInterface
    {
        $this->assertAdminOnly();
        $accounts = $this->service->getAllAccounts();
        $data = array_map(static fn($a) => $a->toArray(), $accounts);
        return $this->respondSuccess($data);
    }

    public function show(int $id): ResponseInterface
    {
        $this->assertAdminOnly();
        $account = $this->service->getById($id);
        return $this->respondSuccess($account->toArray());
    }

    public function create(): ResponseInterface
    {
        $this->assertAdminOnly();
        $user = $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $account = $this->service->createAccount(
            data: $body,
            adminUserId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($account->toArray(), 'تمت إضافة حساب الدفع بنجاح.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $this->assertAdminOnly();
        $user = $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $account = $this->service->updateAccount(
            id: $id,
            data: $body,
            adminUserId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($account->toArray(), 'تم تحديث بيانات حساب الدفع بنجاح.');
    }

    public function delete(int $id): ResponseInterface
    {
        $this->assertAdminOnly();
        $user = $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $this->service->deleteAccount(
            id: $id,
            data: $body,
            adminUserId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم حذف حساب الدفع بنجاح.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $this->assertAdminOnly();
        $user = $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $account = $this->service->toggleAccount(
            id: $id,
            data: $body,
            adminUserId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($account->toArray(), 'تم تغيير حالة تفعيل حساب الدفع.');
    }

    public function reorder(): ResponseInterface
    {
        $this->assertAdminOnly();
        $user = $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $ids = $body['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $this->service->reorderAccounts(
            orderedIds: $ids,
            data: $body,
            adminUserId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم تحديث ترتيب حسابات الدفع.');
    }
}
