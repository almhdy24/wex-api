<?php

return [
    // General & System
    'ping_success'             => 'خدمة ويكس تكنولوجي تعمل بنجاح',
    'not_found'                => 'العنصر المطلوب غير موجود',
    'route_not_found'          => 'الرابط المطلوب غير موجود',
    'unauthorized'             => 'يجب تسجيل الدخول للمتابعة',
    'forbidden'                => 'ليس لديك صلاحية لتنفيذ هذا الإجراء',
    'validation_failed'        => 'البيانات المدخلة غير صحيحة، يرجى التحقق من الحقول',
    'conflict'                 => 'تعذر إتمام العملية بسبب تعارض في البيانات',
    'too_many_requests'        => 'تم تجاوز الحد المسموح به من الطلبات، يرجى الانتظار قليلاً ثم المحاولة مجدداً',
    'server_error'             => 'حدث خطأ غير متوقع في النظام، يجري العمل على معالجته',

    // Authentication
    'otp_sent'                 => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني',
    'otp_invalid'              => 'الرمز غير صحيح أو منتهي الصلاحية',
    'otp_max_attempts'         => 'تم تجاوز عدد المحاولات المسموحة، يرجى طلب رمز جديد',
    'otp_cooldown'             => 'يرجى الانتظار قليلاً قبل طلب رمز جديد',
    'name_required_new_user'   => 'يرجى إدخال اسمك لإكمال التسجيل لأول مرة',
    'google_auth_failed'       => 'فشل التحقق من حساب جوجل',
    'account_inactive'         => 'حسابك معطل مؤقتاً، يرجى التواصل مع إدارة المتجر',
    'refresh_token_invalid'    => 'انتهت صلاحية جلسة الدخول، يرجى تسجيل الدخول مجدداً',
    'logged_out'               => 'تم تسجيل الخروج بنجاح',
    'logged_out_all'           => 'تم إنهاء جميع الجلسات بنجاح',
    'profile_updated'          => 'تم تحديث البيانات بنجاح',
    'preferences_updated'      => 'تم حفظ تفضيلات الإشعارات',
    'account_deleted'          => 'تم إغلاق الحساب بنجاح',
    'invalid_phone'            => 'رقم الهاتف غير صالح، يرجى استخدام رقم سوداني صحيح (09XXXXXXXX أو 01XXXXXXXX)',

    // Products & Catalog
    'product_not_found'        => 'الجهاز غير موجود أو تم إيقافه',
    'product_created'          => 'تمت إضافة الجهاز بنجاح',
    'product_updated'          => 'تم تحديث بيانات الجهاز بنجاح',
    'product_deleted'          => 'تم حذف الجهاز بنجاح',
    'image_uploaded'           => 'تم رفع الصور بنجاح',
    'image_deleted'            => 'تم حذف الصورة بنجاح',
    'image_primary_set'        => 'تم تعيين الصورة كصورة رئيسية',
    'category_created'         => 'تمت إضافة الفئة بنجاح',
    'category_updated'         => 'تم تحديث الفئة بنجاح',
    'category_deleted'         => 'تم حذف الفئة بنجاح',
    'brand_created'            => 'تمت إضافة الماركة بنجاح',
    'brand_updated'            => 'تم تحديث الماركة بنجاح',
    'brand_deleted'            => 'تم حذف الماركة بنجاح',

    // Orders & Price Lock
    'order_created'            => 'تم إنشاء الطلب بنجاح، يرجى التحويل وتأكيد الدفع',
    'order_not_found'          => 'الطلب غير موجود',
    'stock_insufficient'       => 'لا يوجد مخزون كافٍ لهذا الجهاز',
    'order_cancelled'          => 'تم إلغاء الطلب بنجاح',
    'order_cannot_cancel'      => 'لا يمكن إلغاء الطلب في هذه المرحلة',
    'lock_expired'             => 'انتهت مهلة تثبيت السعر لهذا الطلب',
    'lock_extended'            => 'تم تمديد مهلة تثبيت السعر بنجاح',
    'reorder_success'          => 'تم إنشاء طلب جديد بالأسعار الحالية بنجاح',
    'status_transition_denied' => 'لا يمكن تغيير حالة الطلب الحالية إلى الحالة المطلوبة',
    'status_updated'           => 'تم تحديث حالة الطلب بنجاح',

    // Payment Proofs
    'proof_uploaded'           => 'تم استلام إشعار التحويل ويجري التحقق منه',
    'proof_not_allowed'        => 'لا يمكن رفع إشعار تحويل في حالة الطلب الحالية',
    'proof_approved'           => 'تم تأكيد الدفع بنجاح واعتماد الطلب',
    'proof_rejected'           => 'تم رفض إشعار التحويل',
    'proof_reject_note_req'    => 'سبب الرفض مطلوب لمساعدة العميل',
    'proof_not_found'          => 'إشعار التحويل غير موجود',

    // Settings & Rates
    'settings_updated'         => 'تم تحديث الإعدادات بنجاح',
    'rate_updated'             => 'تم تحديث سعر الصرف بنجاح',
    'shipping_zone_saved'      => 'تم حفظ منطقة التوصيل بنجاح',
    'shipping_zone_deleted'    => 'تم حذف منطقة التوصيل بنجاح',

    // Admin & Users
    'user_not_found'           => 'المستخدم غير موجود',
    'user_updated'             => 'تم تحديث بيانات المستخدم بنجاح',
    'role_updated'             => 'تم تغيير دور المستخدم بنجاح',
    'user_deactivated'         => 'تم تعطيل الحساب بنجاح',
    'user_activated'           => 'تم تفعيل الحساب بنجاح',
    'admin_created'            => 'تم تعيين المستخدم كمسؤول بنجاح',

    // Notifications
    'notification_read'        => 'تم تحديد الإشعار كمقروء',
    'all_notifications_read'   => 'تم تحديد جميع الإشعارات كمقروءة',

    // Payment Accounts
    'payment_account_created'   => 'تمت إضافة حساب الدفع بنجاح',
    'payment_account_updated'   => 'تم تحديث بيانات حساب الدفع بنجاح',
    'payment_account_deleted'   => 'تم حذف حساب الدفع بنجاح',
    'payment_account_toggled'   => 'تم تغيير حالة تفعيل حساب الدفع بنجاح',
    'payment_accounts_reordered'=> 'تم تحديث ترتيب حسابات الدفع بنجاح',

    // Announcements
    'announcement_created'     => 'تم إنشاء الإعلان بنجاح',
    'announcement_updated'     => 'تم تحديث الإعلان بنجاح',
    'announcement_deleted'     => 'تم حذف الإعلان بنجاح',
    'announcement_toggled'     => 'تم تغيير حالة تفعيل الإعلان بنجاح',

    // Content Management
    'hero_slide_created'       => 'تم إنشاء شريحة العرض بنجاح',
    'hero_slide_updated'       => 'تم تحديث شريحة العرض بنجاح',
    'hero_slide_deleted'       => 'تم حذف شريحة العرض بنجاح',
    'hero_slide_toggled'       => 'تم تغيير حالة تفعيل شريحة العرض بنجاح',
    'hero_slides_reordered'    => 'تم تحديث ترتيب شرائح العرض بنجاح',

    'home_section_created'     => 'تم إنشاء قسم الصفحة الرئيسية بنجاح',
    'home_section_updated'     => 'تم تحديث قسم الصفحة الرئيسية بنجاح',
    'home_section_deleted'     => 'تم حذف قسم الصفحة الرئيسية بنجاح',
    'home_section_toggled'     => 'تم تغيير حالة تفعيل قسم الصفحة الرئيسية بنجاح',
    'home_sections_reordered'  => 'تم تحديث ترتيب أقسام الصفحة الرئيسية بنجاح',

    'faq_created'              => 'تم إنشاء السؤال الشائع بنجاح',
    'faq_updated'              => 'تم تحديث السؤال الشائع بنجاح',
    'faq_deleted'              => 'تم حذف السؤال الشائع بنجاح',
    'faq_toggled'              => 'تم تغيير حالة تفعيل السؤال الشائع بنجاح',
    'faq_reordered'            => 'تم تحديث ترتيب الأسئلة الشائعة بنجاح',

    'page_created'             => 'تم إنشاء الصفحة بنجاح',
    'page_updated'             => 'تم تحديث الصفحة بنجاح',
    'page_deleted'             => 'تم حذف الصفحة بنجاح',
    'page_toggled'             => 'تم تغيير حالة تفعيل الصفحة بنجاح',

    // Recent Purchases
    'recent_purchase_hidden'   => 'تم تحديث حالة ظهور الطلب في شريط المشتريات',
    'ticker_settings_updated'  => 'تم تحديث إعدادات شريط المشتريات بنجاح',
];
