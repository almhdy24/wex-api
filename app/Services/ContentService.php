<?php

namespace App\Services;

use App\Entities\FaqItem;
use App\Entities\HeroSlide;
use App\Entities\HomeSection;
use App\Entities\Page;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\FaqItemModel;
use App\Models\HeroSlideModel;
use App\Models\HomeSectionModel;
use App\Models\PageModel;

class ContentService
{
    protected HeroSlideModel $heroSlides;
    protected HomeSectionModel $homeSections;
    protected FaqItemModel $faqItems;
    protected PageModel $pages;
    protected AuditLogService $auditLogs;

    public function __construct(
        ?HeroSlideModel $heroSlides = null,
        ?HomeSectionModel $homeSections = null,
        ?FaqItemModel $faqItems = null,
        ?PageModel $pages = null,
        ?AuditLogService $auditLogs = null
    ) {
        $this->heroSlides   = $heroSlides ?? new HeroSlideModel();
        $this->homeSections = $homeSections ?? new HomeSectionModel();
        $this->faqItems     = $faqItems ?? new FaqItemModel();
        $this->pages        = $pages ?? new PageModel();
        $this->auditLogs    = $auditLogs ?? new AuditLogService();
    }

    // ==========================================
    // HERO SLIDES
    // ==========================================

    public function getActiveHeroSlides(): array
    {
        return $this->heroSlides
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getAllHeroSlides(): array
    {
        return $this->heroSlides
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getHeroSlideById(int $id): HeroSlide
    {
        $slide = $this->heroSlides->find($id);
        if (!$slide) {
            throw new NotFoundException('الشريحة المطلوبة غير موجودة.');
        }
        return $slide;
    }

    public function createHeroSlide(array $data, int $userId, ?string $ip = null): HeroSlide
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new ValidationFailedException('عنوان الشريحة مطلوب.', ['title' => 'عنوان الشريحة مطلوب.']);
        }

        $slide = new HeroSlide([
            'title'        => $title,
            'subtitle'     => isset($data['subtitle']) ? trim((string) $data['subtitle']) : null,
            'image'        => isset($data['image']) ? trim((string) $data['image']) : null,
            'button_label' => isset($data['button_label']) ? trim((string) $data['button_label']) : null,
            'link'         => isset($data['link']) ? trim((string) $data['link']) : null,
            'sort_order'   => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'is_active'    => isset($data['is_active']) ? (int) \App\Libraries\Input::bool($data['is_active']) : 1,
        ]);

        $id = (int) $this->heroSlides->insert($slide);
        $saved = $this->getHeroSlideById($id);

        $this->auditLogs->log($userId, 'hero_slide_created', 'hero_slide', $id, ['new' => $saved->toArray()], $ip);
        return $saved;
    }

    public function updateHeroSlide(int $id, array $data, int $userId, ?string $ip = null): HeroSlide
    {
        $existing = $this->getHeroSlideById($id);
        $oldData = $existing->toArray();

        $update = [];
        if (isset($data['title'])) {
            $val = trim((string) $data['title']);
            if ($val === '') {
                throw new ValidationFailedException('عنوان الشريحة مطلوب.', ['title' => 'عنوان الشريحة مطلوب.']);
            }
            $update['title'] = $val;
        }
        if (array_key_exists('subtitle', $data)) {
            $update['subtitle'] = $data['subtitle'] !== null ? trim((string) $data['subtitle']) : null;
        }
        if (array_key_exists('image', $data)) {
            $update['image'] = $data['image'] !== null ? trim((string) $data['image']) : null;
        }
        if (array_key_exists('button_label', $data)) {
            $update['button_label'] = $data['button_label'] !== null ? trim((string) $data['button_label']) : null;
        }
        if (array_key_exists('link', $data)) {
            $update['link'] = $data['link'] !== null ? trim((string) $data['link']) : null;
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) \App\Libraries\Input::bool($data['is_active']);
        }

        if (!empty($update)) {
            $this->heroSlides->update($id, $update);
        }

        $updated = $this->getHeroSlideById($id);
        $this->auditLogs->log($userId, 'hero_slide_updated', 'hero_slide', $id, ['old' => $oldData, 'new' => $updated->toArray()], $ip);
        return $updated;
    }

    public function deleteHeroSlide(int $id, int $userId, ?string $ip = null): void
    {
        $existing = $this->getHeroSlideById($id);
        $this->heroSlides->delete($id);
        $this->auditLogs->log($userId, 'hero_slide_deleted', 'hero_slide', $id, ['old' => $existing->toArray()], $ip);
    }

    public function toggleHeroSlide(int $id, int $userId, ?string $ip = null): HeroSlide
    {
        $existing = $this->getHeroSlideById($id);
        $newActive = $existing->is_active ? 0 : 1;
        $this->heroSlides->update($id, ['is_active' => $newActive]);
        $updated = $this->getHeroSlideById($id);
        $this->auditLogs->log($userId, 'hero_slide_toggled', 'hero_slide', $id, ['is_active' => $newActive], $ip);
        return $updated;
    }

    public function reorderHeroSlides(array $orderedIds, int $userId, ?string $ip = null): void
    {
        $sort = 1;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $this->heroSlides->update($id, ['sort_order' => $sort++]);
            }
        }
        $this->auditLogs->log($userId, 'hero_slides_reordered', 'hero_slide', null, ['ordered_ids' => $orderedIds], $ip);
    }

    // ==========================================
    // HOME SECTIONS
    // ==========================================

    public function getActiveHomeSections(): array
    {
        return $this->homeSections
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getAllHomeSections(): array
    {
        return $this->homeSections
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getHomeSectionById(int $id): HomeSection
    {
        $sec = $this->homeSections->find($id);
        if (!$sec) {
            throw new NotFoundException('القسم المطلوب غير موجود.');
        }
        return $sec;
    }

    public function createHomeSection(array $data, int $userId, ?string $ip = null): HomeSection
    {
        $key   = trim((string) ($data['key'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $source = in_array($data['source'] ?? '', ['newest', 'featured', 'category', 'type', 'accessories'], true) ? $data['source'] : 'newest';

        $errors = [];
        if ($key === '') {
            $errors['key'] = 'رمز القسم مطلوب.';
        }
        if ($title === '') {
            $errors['title'] = 'عنوان القسم مطلوب.';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException('يرجى التحقق من حقول القسم.', $errors);
        }

        $sec = new HomeSection([
            'key'          => $key,
            'title'        => $title,
            'source'       => $source,
            'source_value' => isset($data['source_value']) ? trim((string) $data['source_value']) : null,
            'limit'        => isset($data['limit']) ? max(1, min(24, (int) $data['limit'])) : 8,
            'sort_order'   => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'is_active'    => isset($data['is_active']) ? (int) \App\Libraries\Input::bool($data['is_active']) : 1,
        ]);

        $id = (int) $this->homeSections->insert($sec);
        $saved = $this->getHomeSectionById($id);

        $this->auditLogs->log($userId, 'home_section_created', 'home_section', $id, ['new' => $saved->toArray()], $ip);
        return $saved;
    }

    public function updateHomeSection(int $id, array $data, int $userId, ?string $ip = null): HomeSection
    {
        $existing = $this->getHomeSectionById($id);
        $oldData = $existing->toArray();

        $update = [];
        if (isset($data['key'])) {
            $val = trim((string) $data['key']);
            if ($val === '') {
                throw new ValidationFailedException('رمز القسم مطلوب.', ['key' => 'رمز القسم مطلوب.']);
            }
            $update['key'] = $val;
        }
        if (isset($data['title'])) {
            $val = trim((string) $data['title']);
            if ($val === '') {
                throw new ValidationFailedException('عنوان القسم مطلوب.', ['title' => 'عنوان القسم مطلوب.']);
            }
            $update['title'] = $val;
        }
        if (isset($data['source']) && in_array($data['source'], ['newest', 'featured', 'category', 'type', 'accessories'], true)) {
            $update['source'] = $data['source'];
        }
        if (array_key_exists('source_value', $data)) {
            $update['source_value'] = $data['source_value'] !== null ? trim((string) $data['source_value']) : null;
        }
        if (isset($data['limit'])) {
            $update['limit'] = max(1, min(24, (int) $data['limit']));
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) \App\Libraries\Input::bool($data['is_active']);
        }

        if (!empty($update)) {
            $this->homeSections->update($id, $update);
        }

        $updated = $this->getHomeSectionById($id);
        $this->auditLogs->log($userId, 'home_section_updated', 'home_section', $id, ['old' => $oldData, 'new' => $updated->toArray()], $ip);
        return $updated;
    }

    public function deleteHomeSection(int $id, int $userId, ?string $ip = null): void
    {
        $existing = $this->getHomeSectionById($id);
        $this->homeSections->delete($id);
        $this->auditLogs->log($userId, 'home_section_deleted', 'home_section', $id, ['old' => $existing->toArray()], $ip);
    }

    public function toggleHomeSection(int $id, int $userId, ?string $ip = null): HomeSection
    {
        $existing = $this->getHomeSectionById($id);
        $newActive = $existing->is_active ? 0 : 1;
        $this->homeSections->update($id, ['is_active' => $newActive]);
        $updated = $this->getHomeSectionById($id);
        $this->auditLogs->log($userId, 'home_section_toggled', 'home_section', $id, ['is_active' => $newActive], $ip);
        return $updated;
    }

    public function reorderHomeSections(array $orderedIds, int $userId, ?string $ip = null): void
    {
        $sort = 1;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $this->homeSections->update($id, ['sort_order' => $sort++]);
            }
        }
        $this->auditLogs->log($userId, 'home_sections_reordered', 'home_section', null, ['ordered_ids' => $orderedIds], $ip);
    }

    // ==========================================
    // FAQ ITEMS
    // ==========================================

    public function getActiveFaqItems(): array
    {
        return $this->faqItems
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getAllFaqItems(): array
    {
        return $this->faqItems
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getFaqItemById(int $id): FaqItem
    {
        $item = $this->faqItems->find($id);
        if (!$item) {
            throw new NotFoundException('سؤال الشائع المطلوب غير موجود.');
        }
        return $item;
    }

    public function createFaqItem(array $data, int $userId, ?string $ip = null): FaqItem
    {
        $question = trim((string) ($data['question'] ?? ''));
        $answer   = trim((string) ($data['answer'] ?? ''));

        $errors = [];
        if ($question === '') {
            $errors['question'] = 'نص السؤال مطلوب.';
        }
        if ($answer === '') {
            $errors['answer'] = 'نص الإجابة مطلوب.';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException('يرجى التحقق من حقول السؤال والإجابة.', $errors);
        }

        $faq = new FaqItem([
            'question'   => $question,
            'answer'     => $answer,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'is_active'  => isset($data['is_active']) ? (int) \App\Libraries\Input::bool($data['is_active']) : 1,
        ]);

        $id = (int) $this->faqItems->insert($faq);
        $saved = $this->getFaqItemById($id);

        $this->auditLogs->log($userId, 'faq_item_created', 'faq_item', $id, ['new' => $saved->toArray()], $ip);
        return $saved;
    }

    public function updateFaqItem(int $id, array $data, int $userId, ?string $ip = null): FaqItem
    {
        $existing = $this->getFaqItemById($id);
        $oldData = $existing->toArray();

        $update = [];
        if (isset($data['question'])) {
            $val = trim((string) $data['question']);
            if ($val === '') {
                throw new ValidationFailedException('نص السؤال مطلوب.', ['question' => 'نص السؤال مطلوب.']);
            }
            $update['question'] = $val;
        }
        if (isset($data['answer'])) {
            $val = trim((string) $data['answer']);
            if ($val === '') {
                throw new ValidationFailedException('نص الإجابة مطلوب.', ['answer' => 'نص الإجابة مطلوب.']);
            }
            $update['answer'] = $val;
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) \App\Libraries\Input::bool($data['is_active']);
        }

        if (!empty($update)) {
            $this->faqItems->update($id, $update);
        }

        $updated = $this->getFaqItemById($id);
        $this->auditLogs->log($userId, 'faq_item_updated', 'faq_item', $id, ['old' => $oldData, 'new' => $updated->toArray()], $ip);
        return $updated;
    }

    public function deleteFaqItem(int $id, int $userId, ?string $ip = null): void
    {
        $existing = $this->getFaqItemById($id);
        $this->faqItems->delete($id);
        $this->auditLogs->log($userId, 'faq_item_deleted', 'faq_item', $id, ['old' => $existing->toArray()], $ip);
    }

    public function toggleFaqItem(int $id, int $userId, ?string $ip = null): FaqItem
    {
        $existing = $this->getFaqItemById($id);
        $newActive = $existing->is_active ? 0 : 1;
        $this->faqItems->update($id, ['is_active' => $newActive]);
        $updated = $this->getFaqItemById($id);
        $this->auditLogs->log($userId, 'faq_item_toggled', 'faq_item', $id, ['is_active' => $newActive], $ip);
        return $updated;
    }

    public function reorderFaqItems(array $orderedIds, int $userId, ?string $ip = null): void
    {
        $sort = 1;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $this->faqItems->update($id, ['sort_order' => $sort++]);
            }
        }
        $this->auditLogs->log($userId, 'faq_items_reordered', 'faq_item', null, ['ordered_ids' => $orderedIds], $ip);
    }

    // ==========================================
    // PAGES
    // ==========================================

    public function getActivePages(): array
    {
        return $this->pages
            ->select('id, slug, title, updated_at')
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getAllPages(): array
    {
        return $this->pages
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getPageBySlug(string $slug): Page
    {
        $page = $this->pages->where('slug', $slug)->where('is_active', 1)->first();
        if (!$page) {
            throw new NotFoundException('الصفحة المطلوبة غير موجودة.');
        }
        return $page;
    }

    public function getPageById(int $id): Page
    {
        $page = $this->pages->find($id);
        if (!$page) {
            throw new NotFoundException('الصفحة المطلوبة غير موجودة.');
        }
        return $page;
    }

    public function createPage(array $data, int $userId, ?string $ip = null): Page
    {
        $slug  = trim((string) ($data['slug'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $body  = trim((string) ($data['body'] ?? ''));

        $errors = [];
        if ($slug === '') {
            $errors['slug'] = 'معرف الرابط (slug) مطلوب.';
        } elseif ($this->pages->where('slug', $slug)->countAllResults() > 0) {
            $errors['slug'] = 'معرف الرابط مستخدم بالفعل.';
        }
        if ($title === '') {
            $errors['title'] = 'عنوان الصفحة مطلوب.';
        }
        if ($body === '') {
            $errors['body'] = 'محتوى الصفحة مطلوب.';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException('يرجى التحقق من حقول الصفحة.', $errors);
        }

        $page = new Page([
            'slug'      => $slug,
            'title'     => $title,
            'body'      => $body,
            'is_active' => isset($data['is_active']) ? (int) \App\Libraries\Input::bool($data['is_active']) : 1,
        ]);

        $id = (int) $this->pages->insert($page);
        $saved = $this->getPageById($id);

        $this->auditLogs->log($userId, 'page_created', 'page', $id, ['new' => $saved->toArray()], $ip);
        return $saved;
    }

    public function updatePage(int $id, array $data, int $userId, ?string $ip = null): Page
    {
        $existing = $this->getPageById($id);
        $oldData = $existing->toArray();

        $update = [];
        if (isset($data['slug'])) {
            $val = trim((string) $data['slug']);
            if ($val === '') {
                throw new ValidationFailedException('معرف الرابط (slug) مطلوب.', ['slug' => 'معرف الرابط مطلوب.']);
            }
            $duplicate = $this->pages->where('slug', $val)->where('id !=', $id)->countAllResults();
            if ($duplicate > 0) {
                throw new ValidationFailedException('معرف الرابط مستخدم بالفعل.', ['slug' => 'معرف الرابط مستخدم بالفعل.']);
            }
            $update['slug'] = $val;
        }
        if (isset($data['title'])) {
            $val = trim((string) $data['title']);
            if ($val === '') {
                throw new ValidationFailedException('عنوان الصفحة مطلوب.', ['title' => 'عنوان الصفحة مطلوب.']);
            }
            $update['title'] = $val;
        }
        if (isset($data['body'])) {
            $val = trim((string) $data['body']);
            if ($val === '') {
                throw new ValidationFailedException('محتوى الصفحة مطلوب.', ['body' => 'محتوى الصفحة مطلوب.']);
            }
            $update['body'] = $val;
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) \App\Libraries\Input::bool($data['is_active']);
        }

        if (!empty($update)) {
            $this->pages->update($id, $update);
        }

        $updated = $this->getPageById($id);
        $this->auditLogs->log($userId, 'page_updated', 'page', $id, ['old' => $oldData, 'new' => $updated->toArray()], $ip);
        return $updated;
    }

    public function deletePage(int $id, int $userId, ?string $ip = null): void
    {
        $existing = $this->getPageById($id);
        $this->pages->delete($id);
        $this->auditLogs->log($userId, 'page_deleted', 'page', $id, ['old' => $existing->toArray()], $ip);
    }

    public function togglePage(int $id, int $userId, ?string $ip = null): Page
    {
        $existing = $this->getPageById($id);
        $newActive = $existing->is_active ? 0 : 1;
        $this->pages->update($id, ['is_active' => $newActive]);
        $updated = $this->getPageById($id);
        $this->auditLogs->log($userId, 'page_toggled', 'page', $id, ['is_active' => $newActive], $ip);
        return $updated;
    }
}
