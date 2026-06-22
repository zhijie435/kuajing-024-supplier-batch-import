<?php
require_once __DIR__ . '/Database.php';

class ImportHelper {
    private $db;
    private $config;

    const STATUS_PENDING = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_FAILED = 3;

    const ROW_STATUS_PENDING = 0;
    const ROW_STATUS_SUCCESS = 1;
    const ROW_STATUS_FAILED = 2;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/../config.php';
    }

    public function createTask($fileName, $filePath, $totalRows, $operator = 'system') {
        return $this->db->insert('supplier_import_tasks', [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'total_rows' => $totalRows,
            'success_count' => 0,
            'fail_count' => 0,
            'status' => self::STATUS_PENDING,
            'operator' => $operator,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateTaskStatus($taskId, $status, $extra = []) {
        $data = array_merge([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], $extra);
        return $this->db->update(
            'supplier_import_tasks',
            $data,
            'id = :id',
            ['id' => $taskId]
        );
    }

    public function addFailDetail($taskId, $rowNum, $rowData, $errorMsg) {
        return $this->db->insert('supplier_import_fail_details', [
            'task_id' => $taskId,
            'row_number' => $rowNum,
            'row_data' => json_encode($rowData, JSON_UNESCAPED_UNICODE),
            'error_message' => $errorMsg,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function addImportRow($taskId, $rowNum, $rowData) {
        return $this->db->insert('supplier_import_rows', [
            'task_id' => $taskId,
            'row_number' => $rowNum,
            'row_data' => json_encode($rowData, JSON_UNESCAPED_UNICODE),
            'status' => self::ROW_STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateImportRow($rowId, $status, $supplierId = null, $errorMsg = null) {
        $data = [
            'status' => $status,
            'supplier_id' => $supplierId,
            'error_message' => $errorMsg,
        ];
        return $this->db->update(
            'supplier_import_rows',
            $data,
            'id = :id',
            ['id' => $rowId]
        );
    }

    public function validateRow($row, $rowNum) {
        $errors = [];
        $requiredFields = ['company_name', 'unified_social_credit_code', 'legal_person', 'contact_name', 'contact_phone'];
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                $columnName = $this->config['import']['template_columns'][$field] ?? $field;
                $errors[] = "{$columnName}不能为空";
            }
        }
        if (!empty($row['unified_social_credit_code'])) {
            $code = $row['unified_social_credit_code'];
            if (strlen($code) !== 18) {
                $errors[] = '统一社会信用代码必须为18位';
            }
            $exists = $this->db->fetch(
                'SELECT id FROM supplier_kyb WHERE unified_social_credit_code = :code',
                ['code' => $code]
            );
            if ($exists) {
                $errors[] = '统一社会信用代码已存在';
            }
        }
        if (!empty($row['contact_phone'])) {
            $phone = $row['contact_phone'];
            if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                $errors[] = '联系电话格式不正确';
            }
        }
        if (!empty($row['contact_email'])) {
            $email = $row['contact_email'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = '联系邮箱格式不正确';
            }
        }
        if (!empty($row['establish_date'])) {
            $date = $row['establish_date'];
            if (!preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $date) && !strtotime($date)) {
                $errors[] = '成立日期格式不正确，应为YYYY-MM-DD';
            }
        }
        if (!empty($row['legal_person_id_card'])) {
            $idCard = $row['legal_person_id_card'];
            if (!preg_match('/^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/', $idCard)) {
                $errors[] = '法人身份证号格式不正确';
            }
        }
        return $errors;
    }

    public function importRow($row, $rowNum, $taskId) {
        $errors = $this->validateRow($row, $rowNum);
        if (!empty($errors)) {
            $this->addFailDetail($taskId, $rowNum, $row, implode('; ', $errors));
            return ['success' => false, 'errors' => $errors];
        }
        try {
            $this->db->beginTransaction();
            $establishDate = null;
            if (!empty($row['establish_date'])) {
                $establishDate = date('Y-m-d', strtotime(str_replace('/', '-', $row['establish_date'])));
            }
            $supplierId = $this->db->insert('supplier_kyb', [
                'company_name' => trim($row['company_name']),
                'unified_social_credit_code' => trim($row['unified_social_credit_code']),
                'legal_person' => trim($row['legal_person']),
                'legal_person_id_card' => !empty($row['legal_person_id_card']) ? trim($row['legal_person_id_card']) : null,
                'registered_capital' => !empty($row['registered_capital']) ? trim($row['registered_capital']) : null,
                'establish_date' => $establishDate,
                'business_scope' => !empty($row['business_scope']) ? trim($row['business_scope']) : null,
                'registered_address_province' => !empty($row['registered_address_province']) ? trim($row['registered_address_province']) : null,
                'registered_address_city' => !empty($row['registered_address_city']) ? trim($row['registered_address_city']) : null,
                'registered_address_district' => !empty($row['registered_address_district']) ? trim($row['registered_address_district']) : null,
                'registered_address_detail' => !empty($row['registered_address_detail']) ? trim($row['registered_address_detail']) : null,
                'contact_name' => trim($row['contact_name']),
                'contact_phone' => trim($row['contact_phone']),
                'contact_email' => !empty($row['contact_email']) ? trim($row['contact_email']) : null,
                'status' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->commit();
            return ['success' => true, 'supplier_id' => $supplierId];
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->addFailDetail($taskId, $rowNum, $row, '数据库错误: ' . $e->getMessage());
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function processTask($taskId) {
        $task = $this->db->fetch(
            'SELECT * FROM supplier_import_tasks WHERE id = :id',
            ['id' => $taskId]
        );
        if (!$task) {
            throw new Exception('导入任务不存在');
        }
        if ((int)$task['status'] === self::STATUS_COMPLETED || (int)$task['status'] === self::STATUS_PROCESSING) {
            return ['success' => false, 'message' => '任务已处理或正在处理中'];
        }
        $this->updateTaskStatus($taskId, self::STATUS_PROCESSING);
        $filePath = $task['file_path'];
        if (!file_exists($filePath)) {
            $this->updateTaskStatus($taskId, self::STATUS_FAILED);
            throw new Exception('导入文件不存在');
        }
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->updateTaskStatus($taskId, self::STATUS_FAILED);
            throw new Exception('无法打开导入文件');
        }
        $columns = array_keys($this->config['import']['template_columns']);
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->updateTaskStatus($taskId, self::STATUS_FAILED);
            throw new Exception('导入文件为空');
        }
        $successCount = 0;
        $failCount = 0;
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $rowData = [];
            foreach ($columns as $idx => $col) {
                $rowData[$col] = isset($row[$idx]) ? $row[$idx] : '';
            }
            $this->addImportRow($taskId, $rowNum, $rowData);
            $result = $this->importRow($rowData, $rowNum, $taskId);
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        fclose($handle);
        $this->updateTaskStatus($taskId, self::STATUS_COMPLETED, [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
        return [
            'success' => true,
            'total' => $task['total_rows'],
            'success_count' => $successCount,
            'fail_count' => $failCount,
        ];
    }

    public function getTaskList($page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        $list = $this->db->fetchAll(
            'SELECT * FROM supplier_import_tasks ORDER BY created_at DESC LIMIT :offset, :pageSize',
            [
                'offset' => (int)$offset,
                'pageSize' => (int)$pageSize,
            ]
        );
        $total = $this->db->fetch('SELECT COUNT(*) as cnt FROM supplier_import_tasks');
        return [
            'list' => $list,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$total['cnt'],
                'totalPages' => (int)ceil($total['cnt'] / $pageSize),
            ],
        ];
    }

    public function getFailDetails($taskId, $page = 1, $pageSize = 50) {
        $offset = ($page - 1) * $pageSize;
        $list = $this->db->fetchAll(
            'SELECT * FROM supplier_import_fail_details WHERE task_id = :task_id ORDER BY row_number ASC LIMIT :offset, :pageSize',
            [
                'task_id' => (int)$taskId,
                'offset' => (int)$offset,
                'pageSize' => (int)$pageSize,
            ]
        );
        foreach ($list as &$item) {
            $item['row_data'] = json_decode($item['row_data'], true);
        }
        $total = $this->db->fetch(
            'SELECT COUNT(*) as cnt FROM supplier_import_fail_details WHERE task_id = :task_id',
            ['task_id' => (int)$taskId]
        );
        return [
            'list' => $list,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$total['cnt'],
                'totalPages' => (int)ceil($total['cnt'] / $pageSize),
            ],
        ];
    }

    public function generateTemplateCSV() {
        $columns = $this->config['import']['template_columns'];
        $headers = array_values($columns);
        $example = [
            '示例科技有限公司',
            '91110108MA01ABCD23',
            '张三',
            '110101199001011234',
            '100万人民币',
            '2020-01-01',
            '技术开发、技术咨询、技术服务',
            '北京市',
            '北京市',
            '海淀区',
            '中关村大街1号',
            '李四',
            '13800138000',
            'contact@example.com',
        ];
        $filename = '供应商导入模板_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $headers);
        fputcsv($output, $example);
        fclose($output);
        exit;
    }
}
