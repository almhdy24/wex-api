<?php

namespace App\Controllers\Api\V1;

use App\Services\AnnouncementService;
use CodeIgniter\HTTP\ResponseInterface;

class AnnouncementController extends BaseApiController
{
    protected AnnouncementService $service;

    public function __construct(?AnnouncementService $service = null)
    {
        $this->service = $service ?? new AnnouncementService();
    }

    public function index(): ResponseInterface
    {
        $items = $this->service->getActiveAnnouncements();
        $data = array_map(static function ($a) {
            return [
                'id'             => (int) $a->id,
                'kind'           => (string) $a->kind,
                'title'          => (string) $a->title,
                'body'           => (string) $a->body,
                'link_label'     => $a->link_label ? (string) $a->link_label : null,
                'link_url'       => $a->link_url ? (string) $a->link_url : null,
                'placement'      => (string) $a->placement,
                'is_dismissible' => (bool) $a->is_dismissible,
            ];
        }, $items);

        return $this->respondSuccess($data);
    }
}
