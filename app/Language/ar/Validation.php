<?php

declare(strict_types=1);

/**
 * Arabic Translation for CodeIgniter 4 Validation Rules
 * Wex Technology API
 */
return [
    // Core Messages
    'noRuleSets'            => 'لم يتم تحديد مجموعات قواعد في إعدادات التحقق.',
    'ruleNotFound'          => 'القاعدة "{0}" غير صالحة.',
    'groupNotFound'         => 'مجموعة القواعد "{0}" غير موجودة.',
    'groupNotArray'         => 'مجموعة القواعد "{0}" يجب أن تكون مصفوفة.',
    'invalidTemplate'       => 'قالب التحقق "{0}" غير صالح.',

    // Rule Messages
    'alpha'                 => 'حقل {field} يجب أن يحتوي على أحرف هجائية فقط.',
    'alpha_dash'            => 'حقل {field} يجب أن يحتوي على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_numeric'         => 'حقل {field} يجب أن يحتوي على أحرف وأرقام فقط.',
    'alpha_numeric_punct'   => 'حقل {field} يجب أن يحتوي على أحرف وأرقام ومسافات وعلامات ترقيم مسموحة فقط.',
    'alpha_numeric_space'   => 'حقل {field} يجب أن يحتوي على أحرف وأرقام ومسافات فقط.',
    'alpha_space'           => 'حقل {field} يجب أن يحتوي على أحرف ومسافات فقط.',
    'decimal'               => 'حقل {field} يجب أن يحتوي على رقم عشري.',
    'differs'               => 'حقل {field} يجب أن يختلف عن حقل {param}.',
    'equals'                => 'حقل {field} يجب أن يتطابق تماماً مع: {param}.',
    'exact_length'          => 'حقل {field} يجب أن يكون بطول {param} حرفاً بالضبط.',
    'field_exists'          => 'حقل {field} يجب أن يكون موجوداً.',
    'greater_than'          => 'حقل {field} يجب أن يحتوي على قيمة أكبر من {param}.',
    'greater_than_equal_to' => 'حقل {field} يجب أن يحتوي على قيمة أكبر من أو تساوي {param}.',
    'hex'                   => 'حقل {field} يجب أن يحتوي على أحرف ست عشرية فقط.',
    'in_list'               => 'حقل {field} يجب أن يكون أحد القيم الآتية: {param}.',
    'integer'               => 'حقل {field} يجب أن يحتوي على عدد صحيح.',
    'is_natural'            => 'حقل {field} يجب أن يحتوي على أرقام فقط.',
    'is_natural_no_zero'    => 'حقل {field} يجب أن يحتوي على أرقام أكبر من الصفر.',
    'is_not_unique'         => 'حقل {field} يجب أن يحتوي على قيمة مسجلة مسبقاً في قاعدة البيانات.',
    'is_unique'             => 'حقل {field} يجب أن يحتوي على قيمة فريدة غير مكررة.',
    'less_than'             => 'حقل {field} يجب أن يحتوي على قيمة أقل من {param}.',
    'less_than_equal_to'    => 'حقل {field} يجب أن يحتوي على قيمة أقل من أو تساوي {param}.',
    'matches'               => 'حقل {field} لا يتطابق مع حقل {param}.',
    'max_length'            => 'حقل {field} لا يمكن أن يتجاوز {param} حرفاً.',
    'min_length'            => 'حقل {field} يجب أن يكون بطول {param} أحرف على الأقل.',
    'not_equals'            => 'حقل {field} لا يمكن أن يكون: {param}.',
    'not_in_list'           => 'حقل {field} يجب ألا يكون أحد القيم الآتية: {param}.',
    'numeric'               => 'حقل {field} يجب أن يحتوي على أرقام فقط.',
    'regex_match'           => 'حقل {field} ليس بالصيغة الصحيحة.',
    'required'              => 'حقل {field} مطلوب.',
    'required_with'         => 'حقل {field} مطلوب عند وجود {param}.',
    'required_without'      => 'حقل {field} مطلوب عند عدم وجود {param}.',
    'string'                => 'حقل {field} يجب أن يكون نصاً صالحاً.',
    'timezone'              => 'حقل {field} يجب أن يكون منطقة زمنية صالحة.',
    'valid_base64'          => 'حقل {field} يجب أن يكون نص base64 صالح.',
    'valid_email'           => 'حقل {field} يجب أن يحتوي على عنوان بريد إلكتروني صالح.',
    'valid_emails'          => 'حقل {field} يجب أن يحتوي على عناوين بريد إلكتروني صالحة.',
    'valid_ip'              => 'حقل {field} يجب أن يحتوي على عنوان IP صالح.',
    'valid_url'             => 'حقل {field} يجب أن يحتوي على رابط URL صالح.',
    'valid_url_strict'      => 'حقل {field} يجب أن يحتوي على رابط URL صالح وصارم.',
    'valid_date'            => 'حقل {field} يجب أن يحتوي على تاريخ صالح.',
    'valid_json'            => 'حقل {field} يجب أن يحتوي على نص JSON صالح.',

    // Credit Cards
    'valid_cc_number'       => 'حقل {field} لا يبدو كرقم بطاقة ائتمان صالح.',

    // Files
    'uploaded'              => 'الملف في حقل {field} غير صالح أو لم يتم رفعه.',
    'max_size'              => 'الملف في حقل {field} يتجاوز الحجم الأقصى المسموح به.',
    'is_image'              => 'الملف في حقل {field} ليس صورة صالحة.',
    'mime_in'               => 'نوع الملف في حقل {field} غير مسموح به.',
    'ext_in'                => 'امتداد الملف في حقل {field} غير مسموح به.',
    'max_dims'              => 'أبعاد الصورة في حقل {field} تتجاوز الطول أو العرض المسموح به.',
    'min_dims'              => 'أبعاد الصورة في حقل {field} أصغر من الطول أو العرض المطلوب.',
];
