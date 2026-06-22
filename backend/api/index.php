<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ImportHelper.php';
$config = require __DIR__ . '/../config.php';

function json_response($code, $message, $data = null) {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'supplier_list':
            handleSupplierList();
            break;
        case 'supplier_import_template':
            handleImportTemplate();
            break;
        case 'supplier_import_upload':
            handleImportUpload();
            break;
        case 'supplier_import_process':
            handleImportProcess();
            break;
        case 'supplier_import_tasks':
            handleImportTasks();
            break;
        case 'supplier_import_fail_details':
            handleFailDetails();
            break;
        case 'supplier_import_fail_export':
            handleFailExport();
            break;
        default:
            json_response(1, '未知的操作: ' . $action);
    }
} catch (Exception $e) {
    json_response(1, $e->getMessage());
}

function handleSupplierList() {
    global $config;
    $db = Database::getInstance();
    $page = (int)($_GET['page'] ?? 1);
    $pageSize = (int)($_GET['pageSize'] ?? 20);
    $keyword = $_GET['keyword'] ?? '';
    $offset = ($page - 1) * $pageSize;
    $where = 'WHERE 1=1';
    $params = [];
    if (!empty($keyword)) {
        $where .= ' AND (company_name LIKE :kw OR unified_social_credit_code LIKE :kw OR contact_name LIKE :kw OR contact_phone LIKE :kw)';
        $params['kw'] = '%' . $keyword . '%';
    }
    $sql = "SELECT * FROM supplier_kyb {$where} ORDER BY created_at DESC LIMIT :offset, :pageSize";
    $list = $db->fetchAll($sql, array_merge($params, [
        'offset' => (int)$offset,
        'pageSize' => (int)$pageSize,
    ]));
    $countSql = "SELECT COUNT(*) as cnt FROM supplier_kyb {$where}";
    $total = $db->fetch($countSql, $params);
    json_response(0, 'success', [
        'list' => $list,
        'pagination' => [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => (int)$total['cnt'],
            'totalPages' => (int)ceil($total['cnt'] / $pageSize),
        ],
    ]);
}

function handleImportTemplate() {
    $helper = new ImportHelper();
    $helper->generateTemplateCSV();
}

function handleImportUpload() {
    global $config;
    if (empty($_FILES['file'])) {
        json_response(1, '请选择要上传的文件');
    }
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(1, '文件上传失败，错误码: ' . $file['error']);
    }
    if ($file['size'] > $config['upload']['max_size']) {
        json_response(1, '文件大小超过限制，最大支持 ' . round($config['upload']['max_size'] / 1024 / 1024, 2) . 'MB');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $config['upload']['allowed_extensions'])) {
        json_response(1, '不支持的文件格式，仅支持: ' . implode(', ', $config['upload']['allowed_extensions']));
    }
    $uploadDir = $config['upload']['dir'];
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $subDir = date('Ymd') . '/';
    if (!is_dir($uploadDir . $subDir)) {
        mkdir($uploadDir . $subDir, 0755, true);
    }
    $fileName = uniqid('import_') . '.' . $ext;
    $filePath = $uploadDir . $subDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        json_response(1, '文件保存失败，请检查目录权限');
    }
    $totalRows = 0;
    $handle = fopen($filePath, 'r');
    if ($handle) {
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        fclose($handle);
        $totalRows = max(0, $totalRows - 1);
    }
    $helper = new ImportHelper();
    $operator = $_POST['operator'] ?? 'system';
    $taskId = $helper->createTask($file['name'], $filePath, $totalRows, $operator);
    json_response(0, '上传成功', [
        'task_id' => $taskId,
        'file_name' => $file['name'],
        'total_rows' => $totalRows,
    ]);
}

function handleImportProcess() {
    $taskId = (int)($_POST['task_id'] ?? $_GET['task_id'] ?? 0);
    if (!$taskId) {
        json_response(1, '缺少task_id参数');
    }
    $helper = new ImportHelper();
    $result = $helper->processTask($taskId);
    if ($result['success']) {
        json_response(0, '导入完成', $result);
    } else {
        json_response(1, $result['message'] ?? '导入失败');
    }
}

function handleImportTasks() {
    $page = (int)($_GET['page'] ?? 1);
    $pageSize = (int)($_GET['pageSize'] ?? 20);
    $helper = new ImportHelper();
    $result = $helper->getTaskList($page, $pageSize);
    json_response(0, 'success', $result);
}

function handleFailDetails() {
    $taskId = (int)($_GET['task_id'] ?? 0);
    if (!$taskId) {
        json_response(1, '缺少task_id参数');
    }
    $page = (int)($_GET['page'] ?? 1);
    $pageSize = (int)($_GET['pageSize'] ?? 50);
    $helper = new ImportHelper();
    $result = $helper->getFailDetails($taskId, $page, $pageSize);
    json_response(0, 'success', $result);
}

function handleFailExport() {
    global $config;
    $taskId = (int)($_GET['task_id'] ?? 0);
    if (!$taskId) {
        json_response(1, '缺少task_id参数');
    }
    $helper = new ImportHelper();
    $db = Database::getInstance();
    $task = $db->fetch('SELECT * FROM supplier_import_tasks WHERE id = :id', ['id' => $taskId]);
    if (!$task) {
        json_response(1, '任务不存在');
    }
    $details = $db->fetchAll(
        'SELECT * FROM supplier_import_fail_details WHERE task_id = :task_id ORDER BY row_number ASC',
        ['task_id' => $taskId]
    );
    $columns = $config['import']['template_columns'];
    $headers = array_values($columns);
    $headers[] = '错误信息';
    $filename = '导入失败明细_' . $taskId . '_' . date('YmdHis') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);
    foreach ($details as $detail) {
        $rowData = json_decode($detail['row_data'], true);
        $row = [];
        foreach (array_keys($columns) as $col) {
            $row[] = $rowData[$col] ?? '';
        }
        $row[] = $detail['error_message'];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
