<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\ContentService;
use CodeIgniter\HTTP\ResponseInterface;

class FaqAdminController extends BaseApiController
{
    protected ContentService $service;

    public function __construct(?ContentService $service = null)
    {
        $this->service = $service ?? new ContentService();
    }

    public function index(): ResponseInterface
    {
        $faqs = $this->service->getAllFaqItems();
        $data = array_map(static fn($f) => $f->toArray(), $faqs);
        return $this->respondSuccess($data);
    }

    public function show(int $id): ResponseInterface
    {
        $faq = $this->service->getFaqItemById($id);
        return $this->respondSuccess($faq->toArray());
    }

    public function create(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $faq = $this->service->createFaqItem(
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($faq->toArray(), 'تم إنشاء السؤال الشائع بنجاح.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $faq = $this->service->updateFaqItem(
            id: $id,
            data: $body,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($faq->toArray(), 'تم تحديث السؤال الشائع بنجاح.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $this->service->deleteFaqItem(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم حذف السؤال الشائع بنجاح.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $faq = $this->service->toggleFaqItem(
            id: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess($faq->toArray(), 'تم تغيير حالة تفعيل السؤال الشائع.');
    }

    public function reorder(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        $ids = $body['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $this->service->reorderFaqItems(
            orderedIds: $ids,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم تحديث ترتيب الأسئلة الشائعة.');
    }
}
