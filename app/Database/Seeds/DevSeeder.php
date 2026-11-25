<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Wex;
use RuntimeException;

class DevSeeder extends Seeder
{
    public function run()
    {
        $env = getenv('CI_ENVIRONMENT') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : 'development');
        if (strtolower($env) === 'production') {
            throw new RuntimeException('DevSeeder cannot be executed in production environment.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $nowTs = time();

        // 1. Seed Customer User
        $customerEmail = 'customer@wex.sd';
        $customer = $this->db->table('users')->where('email', $customerEmail)->get()->getRow();
        if (!$customer) {
            $this->db->table('users')->insert([
                'name'                => 'عمر محمد أحمد',
                'email'               => $customerEmail,
                'phone'               => '+249912345679',
                'role'                => Wex::ROLE_CUSTOMER,
                'email_verified_at'   => $now,
                'email_notifications' => 1,
                'is_active'           => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            $customerId = (int) $this->db->insertID();
        } else {
            $customerId = (int) $customer->id;
            $this->db->table('users')->where('id', $customerId)->update([
                'name'                => 'عمر محمد أحمد',
                'phone'               => '+249912345679',
                'role'                => Wex::ROLE_CUSTOMER,
                'is_active'           => 1,
                'updated_at'          => $now,
            ]);
        }

        // 2. Seed Staff User
        $staffEmail = 'staff@wextechnology.com';
        $staff = $this->db->table('users')->where('email', $staffEmail)->get()->getRow();
        if (!$staff) {
            $this->db->table('users')->insert([
                'name'                => 'أحمد علي حسن',
                'email'               => $staffEmail,
                'phone'               => '+249912345670',
                'role'                => Wex::ROLE_STAFF,
                'email_verified_at'   => $now,
                'email_notifications' => 1,
                'is_active'           => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            $staffId = (int) $this->db->insertID();
        } else {
            $staffId = (int) $staff->id;
        }

        // Get admin user ID for review actor
        $admin = $this->db->table('users')->where('role', Wex::ROLE_ADMIN)->get()->getRow();
        $adminId = $admin ? (int) $admin->id : $staffId;

        // 3. Seed Active Payment Accounts
        $accounts = [
            [
                'bank_name'      => 'بنك الخرطوم (تطبيق بنكك)',
                'account_name'   => 'متجر التقنية',
                'account_number' => '100200300',
                'branch'         => 'الفرع الرئيسي - أم درمان',
                'notes'          => 'يرجى كتابة رقم الطلب في خانة الملاحظات عند التحويل عبر تطبيق بنكك.',
                'is_active'      => 1,
                'sort_order'     => 1,
            ],
            [
                'bank_name'      => 'بنك أم درمان الوطني (أوكاش)',
                'account_name'   => 'متجر التقنية',
                'account_number' => '200300400',
                'branch'         => 'فرع الرياض - الخرطوم',
                'notes'          => 'يرجى إرفاق إشعار التحويل بعد إتمام العملية مباشرة.',
                'is_active'      => 1,
                'sort_order'     => 2,
            ],
        ];

        foreach ($accounts as $acc) {
            $existing = $this->db->table('payment_accounts')->where('account_number', $acc['account_number'])->get()->getRow();
            if ($existing) {
                $this->db->table('payment_accounts')->where('id', $existing->id)->update([
                    'bank_name'    => $acc['bank_name'],
                    'account_name' => $acc['account_name'],
                    'branch'       => $acc['branch'],
                    'notes'        => $acc['notes'],
                    'is_active'    => 1,
                    'sort_order'   => $acc['sort_order'],
                    'updated_at'   => $now,
                ]);
            } else {
                $this->db->table('payment_accounts')->insert(array_merge($acc, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // 4. Seed Announcements
        $hasAnnounce = $this->db->table('announcements')->countAllResults();
        if ($hasAnnounce === 0) {
            $this->db->table('announcements')->insert([
                'kind'           => 'info',
                'title'          => 'مرحباً بكم في متجر ويكس تكنولوجي',
                'body'           => 'نوفر أجهزة لابتوب مستوردة بحالة ممتازة مع تثبيت السعر لطلبك عبر تطبيق بنكك.',
                'link_label'     => 'تصفح الأجهزة',
                'link_url'       => '/catalog',
                'placement'      => 'banner',
                'is_active'      => 1,
                'is_dismissible' => 1,
                'sort_order'     => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // Ensure receipt proof image exists
        $proofDir = WRITEPATH . 'uploads/proofs';
        if (!is_dir($proofDir)) {
            mkdir($proofDir, 0700, true);
        }
        $proofPath = $proofDir . '/receipt-bok-1001.jpg';
        if (!file_exists($proofPath)) {
            $img = imagecreatetruecolor(600, 800);
            $bg = imagecolorallocate($img, 245, 247, 250);
            $primary = imagecolorallocate($img, 26, 54, 93);
            imagefilledrectangle($img, 0, 0, 600, 800, $bg);
            imagestring($img, 5, 180, 50, 'BANK OF KHARTOUM', $primary);
            imagestring($img, 4, 160, 90, 'PAYMENT RECEIPT (PROFIT)', $primary);
            imagejpeg($img, $proofPath, 85);
            imagedestroy($img);
        }

        // 5. Seed Orders Queue (Varied states, no orphan states, consistent events)
        $productsBySlug = [];
        $prods = $this->db->table('products')->get()->getResultArray();
        foreach ($prods as $p) {
            $productsBySlug[$p['slug']] = $p;
        }

        if (empty($productsBySlug)) {
            return;
        }

        // Helper to retrieve product safely
        $pLatitude = $productsBySlug['dell-latitude-7490'] ?? reset($productsBySlug);
        $pMouse = $productsBySlug['logitech-wireless-mouse-b170'] ?? reset($productsBySlug);
        $pThinkpad = $productsBySlug['lenovo-thinkpad-t480'] ?? reset($productsBySlug);
        $pElitebook = $productsBySlug['hp-elitebook-840-g5'] ?? reset($productsBySlug);
        $pInspiron = $productsBySlug['dell-inspiron-15-3580'] ?? reset($productsBySlug);
        $pBackpack = $productsBySlug['laptop-backpack-waterproof-15-6'] ?? reset($productsBySlug);
        $pMacbook = $productsBySlug['macbook-air-2019'] ?? reset($productsBySlug);
        $pTuf = $productsBySlug['asus-tuf-fx505'] ?? reset($productsBySlug);
        $pAspire = $productsBySlug['acer-aspire-5'] ?? reset($productsBySlug);

        // Orders definition list
        $ordersToSeed = [
            // Order 1: payment_review (active review queue item in AdminPaymentsPage)
            [
                'order_number'    => 'WEX-261125-1001A',
                'status'          => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                'delivery_type'   => 'pickup',
                'shipping_zone_id'=> null,
                'delivery_address'=> null,
                'delivery_phone'  => '+249912345679',
                'customer_note'   => 'سأحضر للاستلام من فرع أم درمان بعد الظهر',
                'lock_expires_at' => gmdate('Y-m-d H:i:s', $nowTs + 3600 * 3),
                'items'           => [
                    ['product' => $pLatitude, 'qty' => 1],
                    ['product' => $pMouse,    'qty' => 1],
                ],
                'proof'           => [
                    'status'           => 'pending',
                    'file_path'        => 'uploads/proofs/receipt-bok-1001.jpg',
                    'sender_name'      => 'عمر محمد أحمد',
                    'reference_number' => 'BOK-9847120',
                    'reviewed_by'      => null,
                    'reviewed_at'      => null,
                ],
                'events'          => [
                    [
                        'from_status'   => null,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'event_type'    => 'order_created',
                        'note'          => 'تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'to_status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'event_type'    => 'payment_proof_uploaded',
                        'note'          => 'قام العميل برفع إشعار التحويل البنكي',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 1800),
                    ],
                ],
            ],

            // Order 2: pending_payment (with valid future lock)
            [
                'order_number'    => 'WEX-261125-1002B',
                'status'          => Wex::ORDER_STATUS_PENDING_PAYMENT,
                'delivery_type'   => 'delivery',
                'shipping_zone_id'=> 1,
                'delivery_address'=> 'أم درمان - الشهداء، جوار مول الواحة',
                'delivery_phone'  => '+249912345679',
                'customer_note'   => 'يرجى الاتصال قبل التحرك للتوصيل',
                'lock_expires_at' => gmdate('Y-m-d H:i:s', $nowTs + 3600 * 2),
                'items'           => [
                    ['product' => $pThinkpad, 'qty' => 1],
                ],
                'proof'           => null,
                'events'          => [
                    [
                        'from_status'   => null,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'event_type'    => 'order_created',
                        'note'          => 'تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 1200),
                    ],
                ],
            ],

            // Order 3: confirmed (with approved proof)
            [
                'order_number'    => 'WEX-261125-1003C',
                'status'          => Wex::ORDER_STATUS_CONFIRMED,
                'delivery_type'   => 'pickup',
                'shipping_zone_id'=> null,
                'delivery_address'=> null,
                'delivery_phone'  => '+249912345679',
                'customer_note'   => null,
                'lock_expires_at' => gmdate('Y-m-d H:i:s', $nowTs + 3600 * 4),
                'items'           => [
                    ['product' => $pElitebook, 'qty' => 1],
                ],
                'proof'           => [
                    'status'           => 'approved',
                    'file_path'        => 'uploads/proofs/receipt-bok-1001.jpg',
                    'sender_name'      => 'عمر محمد أحمد',
                    'reference_number' => 'BOK-8723419',
                    'reviewed_by'      => $adminId,
                    'reviewed_at'      => gmdate('Y-m-d H:i:s', $nowTs - 900),
                    'review_note'      => 'تمت مطابقة الحساب وتأكيد استلام المبلغ بنجاح.',
                ],
                'events'          => [
                    [
                        'from_status'   => null,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'event_type'    => 'order_created',
                        'note'          => 'تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 2),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'to_status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'event_type'    => 'payment_proof_uploaded',
                        'note'          => 'قام العميل برفع إشعار التحويل البنكي',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_CONFIRMED,
                        'event_type'    => 'payment_proof_approved',
                        'note'          => 'تم اعتماد إشعار التحويل البنكي وتأكيد الطلب',
                        'actor_user_id' => $adminId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 900),
                    ],
                ],
            ],

            // Order 4: ready_for_pickup
            [
                'order_number'    => 'WEX-261125-1004D',
                'status'          => Wex::ORDER_STATUS_READY_FOR_PICKUP,
                'delivery_type'   => 'pickup',
                'shipping_zone_id'=> null,
                'delivery_address'=> null,
                'delivery_phone'  => '+249912345679',
                'customer_note'   => null,
                'lock_expires_at' => gmdate('Y-m-d H:i:s', $nowTs + 3600 * 4),
                'items'           => [
                    ['product' => $pInspiron, 'qty' => 1],
                    ['product' => $pBackpack, 'qty' => 1],
                ],
                'proof'           => [
                    'status'           => 'approved',
                    'file_path'        => 'uploads/proofs/receipt-bok-1001.jpg',
                    'sender_name'      => 'عمر محمد أحمد',
                    'reference_number' => 'OCASH-541298',
                    'reviewed_by'      => $adminId,
                    'reviewed_at'      => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 3),
                    'review_note'      => 'تم التحقق من الحساب.',
                ],
                'events'          => [
                    [
                        'from_status'   => null,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'event_type'    => 'order_created',
                        'note'          => 'تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 5),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'to_status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'event_type'    => 'payment_proof_uploaded',
                        'note'          => 'قام العميل برفع إشعار التحويل البنكي',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 4),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_CONFIRMED,
                        'event_type'    => 'payment_proof_approved',
                        'note'          => 'تم اعتماد إشعار التحويل البنكي وتأكيد الطلب',
                        'actor_user_id' => $adminId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 3),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_CONFIRMED,
                        'to_status'     => Wex::ORDER_STATUS_READY_FOR_PICKUP,
                        'event_type'    => 'status_changed',
                        'note'          => 'الطلب جاهز للاستلام في فرع أم درمان',
                        'actor_user_id' => $staffId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 2),
                    ],
                ],
            ],

            // Order 5: out_for_delivery
            [
                'order_number'    => 'WEX-261125-1005E',
                'status'          => Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
                'delivery_type'   => 'delivery',
                'shipping_zone_id'=> 2, // الخرطوم
                'delivery_address'=> 'الخرطوم - حي الرياض، شارع المشتل، عمارة 14',
                'delivery_phone'  => '+249912345679',
                'courier_name'    => 'محمد عثمان البدوي',
                'courier_phone'   => '+249911223344',
                'customer_note'   => null,
                'lock_expires_at' => gmdate('Y-m-d H:i:s', $nowTs + 3600 * 4),
                'items'           => [
                    ['product' => $pMacbook, 'qty' => 1],
                ],
                'proof'           => [
                    'status'           => 'approved',
                    'file_path'        => 'uploads/proofs/receipt-bok-1001.jpg',
                    'sender_name'      => 'عمر محمد أحمد',
                    'reference_number' => 'BOK-1122334',
                    'reviewed_by'      => $adminId,
                    'reviewed_at'      => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 6),
                    'review_note'      => 'تم التأكيد.',
                ],
                'events'          => [
                    [
                        'from_status'   => null,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'event_type'    => 'order_created',
                        'note'          => 'تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 8),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'to_status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'event_type'    => 'payment_proof_uploaded',
                        'note'          => 'قام العميل برفع إشعار التحويل البنكي',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 7),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_CONFIRMED,
                        'event_type'    => 'payment_proof_approved',
                        'note'          => 'تم اعتماد إشعار التحويل البنكي وتأكيد الطلب',
                        'actor_user_id' => $adminId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 6),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_CONFIRMED,
                        'to_status'     => Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
                        'event_type'    => 'courier_assigned',
                        'note'          => 'تم تسليم الشحنة للمندوب محمد عثمان البدوي (+249911223344) للتوصيل',
                        'actor_user_id' => $staffId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 3600 * 2),
                    ],
                ],
            ],

            // Order 6: completed
            [
                'order_number'    => 'WEX-261125-1006F',
                'status'          => Wex::ORDER_STATUS_COMPLETED,
                'delivery_type'   => 'delivery',
                'shipping_zone_id'=> 1,
                'delivery_address'=> 'أم درمان - بانت شرق',
                'delivery_phone'  => '+249912345679',
                'courier_name'    => 'محمد عثمان البدوي',
                'courier_phone'   => '+249911223344',
                'customer_note'   => null,
                'lock_expires_at' => gmdate('Y-m-d H:i:s', $nowTs - 86400),
                'items'           => [
                    ['product' => $pTuf, 'qty' => 1],
                ],
                'proof'           => [
                    'status'           => 'approved',
                    'file_path'        => 'uploads/proofs/receipt-bok-1001.jpg',
                    'sender_name'      => 'عمر محمد أحمد',
                    'reference_number' => 'BOK-9988776',
                    'reviewed_by'      => $adminId,
                    'reviewed_at'      => gmdate('Y-m-d H:i:s', $nowTs - 90000),
                    'review_note'      => 'تم الاستلام.',
                ],
                'events'          => [
                    [
                        'from_status'   => null,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'event_type'    => 'order_created',
                        'note'          => 'تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 95000),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'to_status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'event_type'    => 'payment_proof_uploaded',
                        'note'          => 'قام العميل برفع إشعار التحويل البنكي',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 92000),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_CONFIRMED,
                        'event_type'    => 'payment_proof_approved',
                        'note'          => 'تم اعتماد إشعار التحويل البنكي وتأكيد الطلب',
                        'actor_user_id' => $adminId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 90000),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_CONFIRMED,
                        'to_status'     => Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
                        'event_type'    => 'courier_assigned',
                        'note'          => 'تم تسليم الشحنة للمندوب محمد عثمان البدوي (+249911223344) للتوصيل',
                        'actor_user_id' => $staffId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 88000),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
                        'to_status'     => Wex::ORDER_STATUS_COMPLETED,
                        'event_type'    => 'order_completed',
                        'note'          => 'تم تسليم الطلب للعميل بنجاح واستلام الشحنة',
                        'actor_user_id' => $staffId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 86400),
                    ],
                ],
            ],

            // Order 7: cancelled (no proof)
            [
                'order_number'    => 'WEX-261125-1007G',
                'status'          => Wex::ORDER_STATUS_CANCELLED,
                'delivery_type'   => 'pickup',
                'shipping_zone_id'=> null,
                'delivery_address'=> null,
                'delivery_phone'  => '+249912345679',
                'cancelled_reason'=> 'طلب العميل إلغاء الطلب لتغيير المواصفات',
                'customer_note'   => null,
                'lock_expires_at' => gmdate('Y-m-d H:i:s', $nowTs - 86400),
                'items'           => [
                    ['product' => $pAspire, 'qty' => 1],
                ],
                'proof'           => null,
                'events'          => [
                    [
                        'from_status'   => null,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'event_type'    => 'order_created',
                        'note'          => 'تم إنشاء الطلب وتثبيت السعر لمدة 4 ساعات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 90000),
                    ],
                    [
                        'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'to_status'     => Wex::ORDER_STATUS_CANCELLED,
                        'event_type'    => 'order_cancelled',
                        'note'          => 'تم إلغاء الطلب: طلب العميل إلغاء الطلب لتغيير المواصفات',
                        'actor_user_id' => $customerId,
                        'created_at'    => gmdate('Y-m-d H:i:s', $nowTs - 86400),
                    ],
                ],
            ],
        ];

        $rateSdg = 650;
        $discountPercent = 3;
        $step = 100;

        foreach ($ordersToSeed as $oData) {
            $existing = $this->db->table('orders')->where('order_number', $oData['order_number'])->get()->getRow();
            if ($existing) {
                // Delete existing order cascaded to items, events, proofs
                $this->db->table('orders')->where('id', $existing->id)->delete();
            }

            // Calculate totals
            $subtotalAed = 0;
            $itemsToInsert = [];

            foreach ($oData['items'] as $itemDef) {
                $p = $itemDef['product'];
                $qty = $itemDef['qty'];
                $unitAed = (int) $p['price_aed'];
                $lineAed = $unitAed * $qty;
                $rawUnitSdg = $unitAed * $rateSdg;
                $unitSdg = (int) (ceil($rawUnitSdg / $step) * $step);
                $lineSdg = $unitSdg * $qty;

                $subtotalAed += $lineAed;
                $itemsToInsert[] = [
                    'product_id'            => (int) $p['id'],
                    'product_name_snapshot' => $p['name'],
                    'product_type_snapshot' => $p['type'] ?? 'laptop',
                    'unit_price_aed'        => $unitAed,
                    'quantity'              => $qty,
                    'line_total_aed'        => $lineAed,
                    'unit_price_sdg'        => $unitSdg,
                    'line_total_sdg'        => $lineSdg,
                ];
            }

            $rawSubtotalSdg = $subtotalAed * $rateSdg;
            $subtotalSdg = (int) (ceil($rawSubtotalSdg / $step) * $step);
            $rawDiscount = ($subtotalSdg * $discountPercent) / 100;
            $discountSdg = (int) floor($rawDiscount);
            $afterDiscount = max(0, $subtotalSdg - $discountSdg);

            $deliveryFeeSdg = 0;
            if ($oData['shipping_zone_id']) {
                $zone = $this->db->table('shipping_zones')->where('id', $oData['shipping_zone_id'])->get()->getRow();
                $deliveryFeeSdg = $zone ? (int) $zone->fee_sdg : 5000;
            }

            $rawTotal = $afterDiscount + $deliveryFeeSdg;
            $totalSdg = (int) (ceil($rawTotal / $step) * $step);

            // Insert order
            $this->db->table('orders')->insert([
                'order_number'                  => $oData['order_number'],
                'user_id'                       => $customerId,
                'status'                        => $oData['status'],
                'delivery_type'                 => $oData['delivery_type'],
                'shipping_zone_id'              => $oData['shipping_zone_id'],
                'delivery_address'              => $oData['delivery_address'],
                'delivery_phone'                => $oData['delivery_phone'],
                'customer_note'                 => $oData['customer_note'] ?? null,
                'rate_sdg_snapshot'             => $rateSdg,
                'app_discount_percent_snapshot' => $discountPercent,
                'subtotal_aed'                  => $subtotalAed,
                'subtotal_sdg'                  => $subtotalSdg,
                'discount_sdg'                  => $discountSdg,
                'delivery_fee_sdg'              => $deliveryFeeSdg,
                'total_sdg'                     => $totalSdg,
                'lock_expires_at'               => $oData['lock_expires_at'],
                'courier_name'                  => $oData['courier_name'] ?? null,
                'courier_phone'                 => $oData['courier_phone'] ?? null,
                'cancelled_reason'              => $oData['cancelled_reason'] ?? null,
                'created_at'                    => $now,
                'updated_at'                    => $now,
            ]);

            $orderId = (int) $this->db->insertID();

            // Insert order items
            foreach ($itemsToInsert as $it) {
                $it['order_id'] = $orderId;
                $this->db->table('order_items')->insert($it);
            }

            // Insert proof if any
            if (!empty($oData['proof'])) {
                $p = $oData['proof'];
                $this->db->table('payment_proofs')->insert([
                    'order_id'         => $orderId,
                    'file_path'        => $p['file_path'],
                    'sender_name'      => $p['sender_name'],
                    'reference_number' => $p['reference_number'],
                    'status'           => $p['status'],
                    'reviewed_by'      => $p['reviewed_by'],
                    'reviewed_at'      => $p['reviewed_at'],
                    'review_note'      => $p['review_note'] ?? null,
                    'created_at'       => $p['reviewed_at'] ?? $now,
                ]);
            }

            // Insert timeline events
            foreach ($oData['events'] as $evt) {
                $evt['order_id'] = $orderId;
                $this->db->table('order_events')->insert($evt);
            }
        }
    }
}
