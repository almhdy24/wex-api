<?php

namespace App\Services;

use App\Entities\Announcement;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\AnnouncementModel;

class AnnouncementService
{
    protected AnnouncementModel $announcements;
    protected AuditLogService $auditLogs;

    public function __construct(
        ?AnnouncementModel $announcements = null,
        ?AuditLogService $auditLogs = null
    ) {
        $this->announcements = $announcements ?? new AnnouncementModel();
        $this->auditLogs     = $auditLogs ?? new AuditLogService();
    }

    /**
     * Get active announcements within current time window.
     *
     * @return Announcement[]
     */
    public function getActiveAnnouncements(): array
    {
        $now = gmdate('Y-m-d H:i:s');

        return $this->announcements
            ->where('is_active', 1)
            ->groupStart()
                ->where('starts_at IS NULL', null, false)
                ->orWhere('starts_at <=', $now)
            ->groupEnd()
            ->groupStart()
                ->where('ends_at IS NULL', null, false)
                ->orWhere('ends_at >=', $now)
            ->groupEnd()
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Get all announcements for admin.
     *
     * @return Announcement[]
     */
    public function getAll(): array
    {
        return $this->announcements
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function getById(int $id): Announcement
    {
        $item = $this->announcements->find($id);
        if (!$item) {
            throw new NotFoundException('الإعلان المطلوب غير موجود.');
        }
        return $item;
    }

    public function create(array $data, int $userId, ?string $ip = null): Announcement
    {
        $kind          = in_array($data['kind'] ?? '', ['info', 'promo', 'warning'], true) ? $data['kind'] : 'info';
        $title         = trim((string) ($data['title'] ?? ''));
        $body          = trim((string) ($data['body'] ?? ''));
        $linkLabel     = isset($data['link_label']) ? trim((string) $data['link_label']) : null;
        $linkUrl       = isset($data['link_url']) ? trim((string) $data['link_url']) : null;
        $placement     = in_array($data['placement'] ?? '', ['banner', 'toast', 'home'], true) ? $data['placement'] : 'banner';
        $startsAt      = !empty($data['starts_at']) ? $data['starts_at'] : null;
        $endsAt        = !empty($data['ends_at']) ? $data['ends_at'] : null;
        $isActive      = isset($data['is_active']) ? (int) \App\Libraries\Input::bool($data['is_active']) : 1;
        $isDismissible = isset($data['is_dismissible']) ? (int) \App\Libraries\Input::bool($data['is_dismissible']) : 1;
        $sortOrder     = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'عنوان الإعلان مطلوب.';
        }
        if ($body === '') {
            $errors['body'] = 'نص الإعلان مطلوب.';
        }
        if (!\App\Libraries\SafeUrl::isSafeLink($linkUrl)) {
            $errors['link_url'] = 'رابط الإعلان غير صالح، استخدم مساراً يبدأ بـ / أو رابطاً يبدأ بـ https://';
        }
        if ($startsAt && $endsAt && strtotime($startsAt) > strtotime($endsAt)) {
            $errors['ends_at'] = 'تاريخ نهاية الإعلان يجب أن يكون بعد تاريخ البداية.';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException('يرجى التحقق من حقول الإعلان.', $errors);
        }

        $item = new Announcement([
            'kind'           => $kind,
            'title'          => $title,
            'body'           => $body,
            'link_label'     => $linkLabel,
            'link_url'       => $linkUrl,
            'placement'      => $placement,
            'starts_at'      => $startsAt,
            'ends_at'        => $endsAt,
            'is_active'      => $isActive,
            'is_dismissible' => $isDismissible,
            'sort_order'     => $sortOrder,
        ]);

        $id = (int) $this->announcements->insert($item);
        $saved = $this->getById($id);

        $this->auditLogs->log(
            userId: $userId,
            action: 'announcement_created',
            entity: 'announcement',
            entityId: $id,
            details: ['new' => $saved->toArray()],
            ip: $ip
        );

        return $saved;
    }

    public function update(int $id, array $data, int $userId, ?string $ip = null): Announcement
    {
        $existing = $this->getById($id);
        $oldData = $existing->toArray();

        $update = [];
        if (isset($data['kind']) && in_array($data['kind'], ['info', 'promo', 'warning'], true)) {
            $update['kind'] = $data['kind'];
        }
        if (isset($data['title'])) {
            $val = trim((string) $data['title']);
            if ($val === '') {
                throw new ValidationFailedException('عنوان الإعلان مطلوب.', ['title' => 'عنوان الإعلان مطلوب.']);
            }
            $update['title'] = $val;
        }
        if (isset($data['body'])) {
            $val = trim((string) $data['body']);
            if ($val === '') {
                throw new ValidationFailedException('نص الإعلان مطلوب.', ['body' => 'نص الإعلان مطلوب.']);
            }
            $update['body'] = $val;
        }
        if (array_key_exists('link_label', $data)) {
            $update['link_label'] = $data['link_label'] !== null ? trim((string) $data['link_label']) : null;
        }
        if (array_key_exists('link_url', $data)) {
            $update['link_url'] = $data['link_url'] !== null ? trim((string) $data['link_url']) : null;
            if (!\App\Libraries\SafeUrl::isSafeLink($update['link_url'])) {
                throw new ValidationFailedException('رابط الإعلان غير صالح.', [
                    'link_url' => 'رابط الإعلان غير صالح، استخدم مساراً يبدأ بـ / أو رابطاً يبدأ بـ https://',
                ]);
            }
        }
        if (isset($data['placement']) && in_array($data['placement'], ['banner', 'toast', 'home'], true)) {
            $update['placement'] = $data['placement'];
        }
        if (array_key_exists('starts_at', $data)) {
            $update['starts_at'] = !empty($data['starts_at']) ? $data['starts_at'] : null;
        }
        if (array_key_exists('ends_at', $data)) {
            $update['ends_at'] = !empty($data['ends_at']) ? $data['ends_at'] : null;
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) \App\Libraries\Input::bool($data['is_active']);
        }
        if (isset($data['is_dismissible'])) {
            $update['is_dismissible'] = (int) \App\Libraries\Input::bool($data['is_dismissible']);
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = (int) $data['sort_order'];
        }

        $checkStarts = $update['starts_at'] ?? $existing->starts_at;
        $checkEnds   = $update['ends_at'] ?? $existing->ends_at;
        if ($checkStarts && $checkEnds && strtotime($checkStarts) > strtotime($checkEnds)) {
            throw new ValidationFailedException('تاريخ نهاية الإعلان يجب أن يكون بعد تاريخ البداية.', [
                'ends_at' => 'تاريخ نهاية الإعلان غير صالح.',
            ]);
        }

        if (!empty($update)) {
            $this->announcements->update($id, $update);
        }

        $updated = $this->getById($id);

        $this->auditLogs->log(
            userId: $userId,
            action: 'announcement_updated',
            entity: 'announcement',
            entityId: $id,
            details: ['old' => $oldData, 'new' => $updated->toArray()],
            ip: $ip
        );

        return $updated;
    }

    public function delete(int $id, int $userId, ?string $ip = null): void
    {
        $existing = $this->getById($id);
        $this->announcements->delete($id);

        $this->auditLogs->log(
            userId: $userId,
            action: 'announcement_deleted',
            entity: 'announcement',
            entityId: $id,
            details: ['old' => $existing->toArray()],
            ip: $ip
        );
    }

    public function toggle(int $id, int $userId, ?string $ip = null): Announcement
    {
        $existing = $this->getById($id);
        $newActive = $existing->is_active ? 0 : 1;
        $this->announcements->update($id, ['is_active' => $newActive]);

        $updated = $this->getById($id);

        $this->auditLogs->log(
            userId: $userId,
            action: 'announcement_toggled',
            entity: 'announcement',
            entityId: $id,
            details: ['is_active' => $newActive],
            ip: $ip
        );

        return $updated;
    }
}
