<?php

namespace App\Services;

use App\Entities\PaymentAccount;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\PaymentAccountModel;

class PaymentAccountService
{
    protected PaymentAccountModel $accounts;
    protected AuditLogService $auditLogs;
    protected MailService $mailService;

    public function __construct(
        ?PaymentAccountModel $accounts = null,
        ?AuditLogService $auditLogs = null,
        ?MailService $mailService = null
    ) {
        $this->accounts    = $accounts ?? new PaymentAccountModel();
        $this->auditLogs   = $auditLogs ?? new AuditLogService();
        $this->mailService = $mailService ?? new MailService();
    }

    /**
     * Get active accounts for customers and order processing (sorted).
     *
     * @return PaymentAccount[]
     */
    public function getActiveAccounts(): array
    {
        return $this->accounts
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Get all accounts for admin management.
     *
     * @return PaymentAccount[]
     */
    public function getAllAccounts(): array
    {
        return $this->accounts
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Get a single account by ID.
     */
    public function getById(int $id): PaymentAccount
    {
        $account = $this->accounts->find($id);
        if (!$account) {
            throw new NotFoundException('حساب الدفع المطلوب غير موجود.');
        }
        return $account;
    }

    /**
     * Create a payment account. Requires confirm flag.
     */
    public function createAccount(array $data, int $adminUserId, ?string $ip = null): PaymentAccount
    {
        $this->assertConfirmed($data);

        $bankName      = trim((string) ($data['bank_name'] ?? ''));
        $accountName   = trim((string) ($data['account_name'] ?? ''));
        $accountNumber = trim((string) ($data['account_number'] ?? ''));
        $branch        = isset($data['branch']) ? trim((string) $data['branch']) : null;
        $notes         = isset($data['notes']) ? trim((string) $data['notes']) : null;
        $isActive      = isset($data['is_active']) ? (int) \App\Libraries\Input::bool($data['is_active']) : 1;
        $sortOrder     = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        $errors = [];
        if ($bankName === '') {
            $errors['bank_name'] = 'اسم البنك مطلوب.';
        }
        if ($accountName === '') {
            $errors['account_name'] = 'اسم صاحب الحساب مطلوب.';
        }
        if ($accountNumber === '') {
            $errors['account_number'] = 'رقم الحساب مطلوب.';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException('يرجى التحقق من صحة حقول حساب الدفع.', $errors);
        }

        $account = new PaymentAccount([
            'bank_name'      => $bankName,
            'account_name'   => $accountName,
            'account_number' => $accountNumber,
            'branch'         => $branch,
            'notes'          => $notes,
            'is_active'      => $isActive,
            'sort_order'     => $sortOrder,
        ]);

        $id = (int) $this->accounts->insert($account);
        $saved = $this->getById($id);

        $this->auditLogs->log(
            userId: $adminUserId,
            action: 'payment_account_created',
            entity: 'payment_account',
            entityId: $id,
            details: [
                'old' => null,
                'new' => $saved->toArray(),
            ],
            ip: $ip
        );

        $this->sendAlertEmail('إضافة حساب دفع بنكي جديد', [
            'action'         => 'إضافة حساب جديد',
            'bank_name'      => $bankName,
            'account_name'   => $accountName,
            'account_number' => $accountNumber,
            'admin_user_id'  => $adminUserId,
        ]);

        return $saved;
    }

    /**
     * Update an existing payment account. Requires confirm flag.
     */
    public function updateAccount(int $id, array $data, int $adminUserId, ?string $ip = null): PaymentAccount
    {
        $this->assertConfirmed($data);

        $existing = $this->getById($id);
        $oldData = $existing->toArray();

        $updateData = [];
        if (isset($data['bank_name'])) {
            $val = trim((string) $data['bank_name']);
            if ($val === '') {
                throw new ValidationFailedException('اسم البنك مطلوب.', ['bank_name' => 'اسم البنك مطلوب.']);
            }
            $updateData['bank_name'] = $val;
        }

        if (isset($data['account_name'])) {
            $val = trim((string) $data['account_name']);
            if ($val === '') {
                throw new ValidationFailedException('اسم صاحب الحساب مطلوب.', ['account_name' => 'اسم صاحب الحساب مطلوب.']);
            }
            $updateData['account_name'] = $val;
        }

        if (isset($data['account_number'])) {
            $val = trim((string) $data['account_number']);
            if ($val === '') {
                throw new ValidationFailedException('رقم الحساب مطلوب.', ['account_number' => 'رقم الحساب مطلوب.']);
            }
            $updateData['account_number'] = $val;
        }

        if (array_key_exists('branch', $data)) {
            $updateData['branch'] = $data['branch'] !== null ? trim((string) $data['branch']) : null;
        }

        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'] !== null ? trim((string) $data['notes']) : null;
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int) \App\Libraries\Input::bool($data['is_active']);
        }

        if (isset($data['sort_order'])) {
            $updateData['sort_order'] = (int) $data['sort_order'];
        }

        if (!empty($updateData)) {
            $this->accounts->update($id, $updateData);
        }

        $updated = $this->getById($id);

        $this->auditLogs->log(
            userId: $adminUserId,
            action: 'payment_account_updated',
            entity: 'payment_account',
            entityId: $id,
            details: [
                'old' => $oldData,
                'new' => $updated->toArray(),
            ],
            ip: $ip
        );

        $this->sendAlertEmail('تعديل بيانات حساب دفع بنكي', [
            'action'         => 'تعديل حساب دفع',
            'account_id'     => $id,
            'bank_name'      => $updated->bank_name,
            'account_number' => $updated->account_number,
            'admin_user_id'  => $adminUserId,
        ]);

        return $updated;
    }

    /**
     * Delete a payment account. Requires confirm flag.
     */
    public function deleteAccount(int $id, array $data, int $adminUserId, ?string $ip = null): void
    {
        $this->assertConfirmed($data);

        $existing = $this->getById($id);
        $oldData = $existing->toArray();

        $this->accounts->delete($id);

        $this->auditLogs->log(
            userId: $adminUserId,
            action: 'payment_account_deleted',
            entity: 'payment_account',
            entityId: $id,
            details: [
                'old' => $oldData,
                'new' => null,
            ],
            ip: $ip
        );

        $this->sendAlertEmail('حذف حساب دفع بنكي', [
            'action'         => 'حذف حساب دفع',
            'account_id'     => $id,
            'bank_name'      => $existing->bank_name,
            'account_number' => $existing->account_number,
            'admin_user_id'  => $adminUserId,
        ]);
    }

    /**
     * Toggle active state. Requires confirm flag.
     */
    public function toggleAccount(int $id, array $data, int $adminUserId, ?string $ip = null): PaymentAccount
    {
        $this->assertConfirmed($data);

        $existing = $this->getById($id);
        $newActive = $existing->is_active ? 0 : 1;

        $this->accounts->update($id, ['is_active' => $newActive]);
        $updated = $this->getById($id);

        $this->auditLogs->log(
            userId: $adminUserId,
            action: 'payment_account_toggled',
            entity: 'payment_account',
            entityId: $id,
            details: [
                'old' => ['is_active' => $existing->is_active],
                'new' => ['is_active' => $newActive],
            ],
            ip: $ip
        );

        $this->sendAlertEmail('تغيير حالة تفعيل حساب الدفع', [
            'action'         => $newActive ? 'تفعيل الحساب' : 'تعطيل الحساب',
            'account_id'     => $id,
            'bank_name'      => $updated->bank_name,
            'account_number' => $updated->account_number,
            'admin_user_id'  => $adminUserId,
        ]);

        return $updated;
    }

    /**
     * Reorder accounts by array of IDs. Requires confirm flag.
     */
    public function reorderAccounts(array $orderedIds, array $data, int $adminUserId, ?string $ip = null): void
    {
        $this->assertConfirmed($data);

        $sort = 1;
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $this->accounts->update($id, ['sort_order' => $sort++]);
            }
        }

        $this->auditLogs->log(
            userId: $adminUserId,
            action: 'payment_accounts_reordered',
            entity: 'payment_account',
            entityId: null,
            details: ['ordered_ids' => $orderedIds],
            ip: $ip
        );

        $this->sendAlertEmail('إعادة ترتيب حسابات الدفع', [
            'action'        => 'إعادة ترتيب الحسابات',
            'admin_user_id' => $adminUserId,
        ]);
    }

    /**
     * Asserts that the request includes an explicit confirmation flag.
     */
    protected function assertConfirmed(array $data): void
    {
        $confirmed = $data['confirm'] ?? false;
        if ($confirmed !== true && $confirmed !== '1' && $confirmed !== 1 && $confirmed !== 'true') {
            throw new ValidationFailedException(
                'يتطلب تعديل بيانات الدفع تأكيداً صريحاً نظراً لحساسية وأهمية الحسابات البنكية.',
                ['confirm' => 'مطلوب تأكيد صريح (confirm = true) لتطبيق التغيير.']
            );
        }
    }

    /**
     * Sends an email notification to the shop notification email address.
     */
    protected function sendAlertEmail(string $actionTitle, array $payload): void
    {
        $notifyEmail = env('SHOP_NOTIFY_EMAIL', 'admin@wextechnology.sd');
        $subject = 'تنبيه أمني: تم تعديل بيانات الدفع - ' . $actionTitle;

        $this->mailService->enqueue(
            userId: null,
            toEmail: $notifyEmail,
            subject: $subject,
            template: 'payment_accounts_changed',
            payload: array_merge($payload, [
                'action_title' => $actionTitle,
                'changed_at'   => gmdate('Y-m-d H:i:s'),
            ])
        );
    }
}
