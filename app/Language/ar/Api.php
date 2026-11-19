<?php

return [
    // General & System
    'ping_success'             => 'خدمة ويكس تكنولوجي تعمل بنجاح',
    'not_found'                => 'العنصر المطلوب غير موجود',
    'route_not_found'          => 'الرابط المطلوب غير موجود',
    'unauthorized'             => 'يجب تسجيل الدخول للمتابعة',
    'forbidden'                => 'ليس لديك صلاحية لتنفيذ هذا الإجراء',
    'validation_failed'        => 'البيانات المدخلة غير صحيحة، تأكد من الحقول',
    'conflict'                 => 'تعذر إتمام العملية بسبب تعارض في البيانات',
    'too_many_requests'        => 'طلبات كثيرة خلال وقت قصير، انتظر شوية وجرب تاني',
    'server_error'             => 'حصل خطأ في النظام، بنعالجه حالياً',

    // Authentication
    'otp_sent'                 => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني',
    'otp_invalid'              => 'الرمز غير صحيح أو منتهي الصلاحية',
    'otp_max_attempts'         => 'تم تجاوز عدد المحاولات المسموحة، اطلب رمزاً جديداً',
    'otp_cooldown'             => 'يرجى الانتظار قليلاً قبل طلب رمز جديد',
    'name_required_new_user'   => 'يرجى كتابة اسمك لإكمال التسجيل لأول مرة',
    'google_auth_failed'       => 'فشل التحقق من حساب جوجل',
    'account_inactive'         => 'حسابك موقوف مؤقتاً، راجع المحل للتفعيل',
    'refresh_token_invalid'    => 'جلسة الدخول منتهية، يرجى تسجيل الدخول مرة ثانية',
    'logged_out'               => 'تم تسجيل الخروج بنجاح',
    'logged_out_all'           => 'تم إنهاء جميع الجلسات بنجاح',
    'profile_updated'          => 'تم تحديث البيانات بنجاح',
    'preferences_updated'      => 'تم حفظ تفضيلات الإشعارات',
    'account_deleted'          => 'تم إغلاق الحساب بنجاح',
    'invalid_phone'            => 'رقم الهاتف غير صالح، استخدم رقم سوداني مثل 09XXXXXXXX أو +249XXXXXXXXX',

    // Products & Catalog
    'product_not_found'        => 'الجهاز غير موجود أو تم إيقافه',
    'product_created'          => 'تمت إضافة الجهاز بنجاح',
    'product_updated'          => 'تم تحديث بيانات الجهاز',
    'product_deleted'          => 'تم حذف الجهاز بنجاح',
    'image_uploaded'           => 'تم رفع الصور بنجاح',
    'image_deleted'            => 'تم حذف الصورة',
    'image_primary_set'        => 'تم تعيين الصورة كصورة رئيسية',
    'category_created'         => 'تمت إضافة الفئة بنجاح',
    'category_updated'         => 'تم تحديث الفئة',
    'category_deleted'         => 'تم حذف الفئة',
    'brand_created'            => 'تمت إضافة الماركة بنجاح',
    'brand_updated'            => 'تم تحديث الماركة',
    'brand_deleted'            => 'تم حذف الماركة',

    // Orders & Price Lock
    'order_created'            => 'تم إنشاء الطلب بنجاح، يرجى التحويل وتأكيد الدفع',
    'order_not_found'          => 'الطلب غير موجود',
    'stock_insufficient'       => 'لا يوجد مخزون كافي لهذا الجهاز',
    'order_cancelled'          => 'تم إلغاء الطلب بنجاح',
    'order_cannot_cancel'      => 'لا يمكن إلغاء الطلب في هذه المرحلة',
    'lock_expired'             => 'انتهت مهلة تثبيت السعر لهذا الطلب',
    'lock_extended'            => 'تم تمديد مهلة تثبيت السعر بنجاح',
    'reorder_success'          => 'تم إنشاء طلب جديد بالأسعار الحالية بنجاح',
    'status_transition_denied' => 'لا يمكن تغيير حالة الطلب الحالية إلى الحالة المطلوبة',
    'status_updated'           => 'تم تحديث حالة الطلب بنجاح',

    // Payment Proofs
    'proof_uploaded'           => 'تم استلام إشعار التحويل وبنراجعه',
    'proof_not_allowed'        => 'لا يمكن رفع إشعار تحويل في حالة الطلب الحالية',
    'proof_approved'           => 'تم تأكيد الدفع بنجاح واعتماد الطلب',
    'proof_rejected'           => 'تم رفض إشعار التحويل',
    'proof_reject_note_req'    => 'سبب الرفض مطلوب لمساعدة العميل',
    'proof_not_found'          => 'إشعار التحويل غير موجود',

    // Settings & Rates
    'settings_updated'         => 'تم تحديث الإعدادات بنجاح',
    'rate_updated'             => 'تم تحديث سعر الصرف بنجاح',
    'shipping_zone_saved'      => 'تم حفظ منطقة التوصيل بنجاح',
    'shipping_zone_deleted'    => 'تم حذف منطقة التوصيل',

    // Admin & Users
    'user_not_found'           => 'المستخدم غير موجود',
    'user_updated'             => 'تم تحديث بيانات المستخدم',
    'role_updated'             => 'تم تغيير دور المستخدم بنجاح',
    'user_deactivated'         => 'تم تعطيل الحساب',
    'user_activated'           => 'تم تفعيل الحساب',
    'admin_created'            => 'تم تعيين المستخدم كمسؤول بنجاح',

    // Notifications
    'notification_read'        => 'تم تحديد الإشعار كمقروء',
    'all_notifications_read'   => 'تم تحديد جميع الإشعارات كمقروءة',
];
