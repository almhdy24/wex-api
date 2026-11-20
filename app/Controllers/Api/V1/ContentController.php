<?php

namespace App\Controllers\Api\V1;

use App\Services\ContentService;
use CodeIgniter\HTTP\ResponseInterface;

class ContentController extends BaseApiController
{
    protected ContentService $service;

    public function __construct(?ContentService $service = null)
    {
        $this->service = $service ?? new ContentService();
    }

    /**
     * GET /api/v1/hero-slides or /api/v1/content/hero-slides
     */
    public function heroSlides(): ResponseInterface
    {
        $slides = $this->service->getActiveHeroSlides();
        $data = array_map(static fn($s) => $s->toArray(), $slides);
        return $this->respondSuccess($data);
    }

    /**
     * GET /api/v1/home-sections or /api/v1/content/home-sections
     */
    public function homeSections(): ResponseInterface
    {
        $sections = $this->service->getActiveHomeSections();
        $data = array_map(static fn($s) => $s->toArray(), $sections);
        return $this->respondSuccess($data);
    }

    /**
     * GET /api/v1/faq or /api/v1/content/faq
     */
    public function faq(): ResponseInterface
    {
        $items = $this->service->getActiveFaqItems();
        $data = array_map(static fn($item) => $item->toArray(), $items);
        return $this->respondSuccess($data);
    }

    /**
     * GET /api/v1/pages or /api/v1/content/pages
     */
    public function pages(): ResponseInterface
    {
        $pages = $this->service->getActivePages();
        $data = array_map(static fn($p) => $p->toArray(), $pages);
        return $this->respondSuccess($data);
    }

    /**
     * GET /api/v1/pages/(:segment) or /api/v1/content/pages/(:segment)
     */
    public function page(string $slug): ResponseInterface
    {
        $page = $this->service->getPageBySlug($slug);
        return $this->respondSuccess($page->toArray());
    }
}
