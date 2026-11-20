<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\AnnouncementService;
use CodeIgniter\HTTP\ResponseInterface;

class AnnouncementAdminController extends BaseApiController
{
    protected AnnouncementService $service;

    public function __construct(?AnnouncementService $service = null)
    {
        $this->service = $service ?? new AnnouncementService();
    }

    public function index(): ResponseInterface
    {
        $items = $this->service->getAll();
        $data = array_map(static fn($a) => $a->toArray(), $items);
        return $this->respondSuccess($data);
    }

    public function show(int $id): ResponseInterface
    {
        $item = $this->service->getById($id);
        return $this->respondSuccess($item->toArray());
    }

    public function create(): ResponseInterface
    {
        $user = $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $item = $this->service->create(
            data: $body,
            userId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($item->toArray(), 'تم إنشاء الإعلان بنجاح.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $user = $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $item = $this->service->update(
            id: $id,
            data: $body,
            userId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($item->toArray(), 'تم تحديث الإعلان بنجاح.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->request->auth_user;
        $this->service->delete(
            id: $id,
            userId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم حذف الإعلان بنجاح.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $user = $this->request->auth_user;
        $item = $this->service->toggle(
            id: $id,
            userId: (int) $user->id,
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($item->toArray(), 'تم تغيير حالة تفعيل الإعلان.');
    }
}
