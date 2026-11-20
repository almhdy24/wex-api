<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\ContentService;
use CodeIgniter\HTTP\ResponseInterface;

class PageAdminController extends BaseApiController
{
    protected ContentService $service;

    public function __construct(?ContentService $service = null)
    {
        $this->service = $service ?? new ContentService();
    }

    public function index(): ResponseInterface
    {
        $pages = $this->service->getAllPages();
        $data = array_map(static fn($p) => $p->toArray(), $pages);
        return $this->respondSuccess($data);
    }

    public function show(int $id): ResponseInterface
    {
        $page = $this->service->getPageById($id);
        return $this->respondSuccess($page->toArray());
    }

    public function create(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $page = $this->service->createPage(
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($page->toArray(), 'تم إنشاء الصفحة بنجاح.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $page = $this->service->updatePage(
            id: $id,
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($page->toArray(), 'تم تحديث الصفحة بنجاح.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $this->service->deletePage(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم حذف الصفحة بنجاح.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $page = $this->service->togglePage(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($page->toArray(), 'تم تغيير حالة تفعيل الصفحة.');
    }
}
