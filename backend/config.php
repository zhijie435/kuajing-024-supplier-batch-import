<?php
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

return [
    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME', 'course_edu'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'upload' => [
        'max_size' => (int)env('UPLOAD_MAX_SIZE', 10485760),
        'dir' => env('UPLOAD_DIR', __DIR__ . '/uploads/'),
        'base_url' => env('UPLOAD_BASE_URL', 'backend/uploads/'),
        'allowed_mime_types' => json_decode(env('UPLOAD_ALLOWED_MIME_TYPES', '{"text\/csv":"csv","application\/vnd.ms-excel":"csv"}'), true),
        'allowed_extensions' => json_decode(env('UPLOAD_ALLOWED_EXTENSIONS', '["csv","xlsx","xls"]'), true),
    ],
    'import' => [
        'batch_size' => 100,
        'template_columns' => [
            'company_name' => '企业全称',
            'unified_social_credit_code' => '统一社会信用代码',
            'legal_person' => '法定代表人',
            'legal_person_id_card' => '法人身份证号',
            'registered_capital' => '注册资本',
            'establish_date' => '成立日期',
            'business_scope' => '经营范围',
            'registered_address_province' => '省份',
            'registered_address_city' => '城市',
            'registered_address_district' => '区县',
            'registered_address_detail' => '详细地址',
            'contact_name' => '联系人姓名',
            'contact_phone' => '联系电话',
            'contact_email' => '联系邮箱',
        ],
    ],
];
