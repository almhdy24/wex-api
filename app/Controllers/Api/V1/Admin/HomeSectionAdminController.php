<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\ContentService;
use CodeIgniter\HTTP\ResponseInterface;

class HomeSectionAdminController extends BaseApiController
{
    protected ContentService $service;

    public function __construct(?ContentService $service = null)
    {
        $this->service = $service ?? new ContentService();
    }

    public function index(): ResponseInterface
    {
        $sections = $this->service->getAllHomeSections();
        $data = array_map(static fn($s) => $s->toArray(), $sections);
        return $this->respondSuccess($data);
    }

    public function show(int $id): ResponseInterface
    {
        $section = $this->service->getHomeSectionById($id);
        return $this->respondSuccess($section->toArray());
    }

    public function create(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $section = $this->service->createHomeSection(
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($section->toArray(), 'تم إنشاء قسم الصفحة الرئيسية بنجاح.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $section = $this->service->updateHomeSection(
            id: $id,
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($section->toArray(), 'تم تحديث قسم الصفحة الرئيسية بنجاح.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $this->service->deleteHomeSection(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم حذف قسم الصفحة الرئيسية بنجاح.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $section = $this->service->toggleHomeSection(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($section->toArray(), 'تم تغيير حالة تفعيل قسم الصفحة الرئيسية.');
    }

    public function reorder(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $ids = $body['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $this->service->reorderHomeSections(
            orderedIds: $ids,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم تحديث ترتيب أقسام الصفحة الرئيسية.');
    }
}
