<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\ContentService;
use CodeIgniter\HTTP\ResponseInterface;

class HeroSlideAdminController extends BaseApiController
{
    protected ContentService $service;

    public function __construct(?ContentService $service = null)
    {
        $this->service = $service ?? new ContentService();
    }

    public function index(): ResponseInterface
    {
        $slides = $this->service->getAllHeroSlides();
        $data = array_map(static fn($s) => $s->toArray(), $slides);
        return $this->respondSuccess($data);
    }

    public function show(int $id): ResponseInterface
    {
        $slide = $this->service->getHeroSlideById($id);
        return $this->respondSuccess($slide->toArray());
    }

    public function create(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $slide = $this->service->createHeroSlide(
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($slide->toArray(), 'تم إنشاء شريحة العرض بنجاح.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $slide = $this->service->updateHeroSlide(
            id: $id,
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($slide->toArray(), 'تم تحديث شريحة العرض بنجاح.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $this->service->deleteHeroSlide(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم حذف شريحة العرض بنجاح.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $slide = $this->service->toggleHeroSlide(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($slide->toArray(), 'تم تغيير حالة تفعيل شريحة العرض.');
    }

    public function reorder(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $ids = $body['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $this->service->reorderHeroSlides(
            orderedIds: $ids,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم تحديث ترتيب شرائح العرض.');
    }
}
