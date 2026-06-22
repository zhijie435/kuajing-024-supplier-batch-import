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
        'allowed_mime_types' => json_decode(env('UPLOAD_ALLOWED_MIME_TYPES', '{"image\/jpeg":"jpg","image\/png":"png","image\/gif":"gif","application\/pdf":"pdf"}'), true),
        'allowed_extensions' => json_decode(env('UPLOAD_ALLOWED_EXTENSIONS', '["jpg","jpeg","png","gif","pdf"]'), true),
        'allowed_types' => json_decode(env('UPLOAD_ALLOWED_TYPES', '["business_license","legal_person_id_front","legal_person_id_back","other"]'), true),
    ],
    'kyb' => [
        'required_fields' => json_decode(env('KYB_REQUIRED_FIELDS', '["company_name","unified_social_credit_code","legal_person","contact_name","contact_phone"]'), true),
        'required_certs' => json_decode(env('KYB_REQUIRED_CERTS', '["business_license","legal_person_id_front","legal_person_id_back"]'), true),
        'status_pending' => (int)env('KYB_STATUS_PENDING', 0),
        'status_approved' => (int)env('KYB_STATUS_APPROVED', 1),
        'status_rejected' => (int)env('KYB_STATUS_REJECTED', 2),
        'cert_names' => [
            'business_license' => '营业执照',
            'legal_person_id_front' => '法人身份证正面',
            'legal_person_id_back' => '法人身份证反面',
        ],
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
