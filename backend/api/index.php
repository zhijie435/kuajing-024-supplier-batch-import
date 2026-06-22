<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$lang = $_GET['lang'] ?? $_POST['lang'] ?? null;

if ($lang) {
    I18n::setLang($lang);
}

$importHelper = null;
function getImportHelper() {
    global $importHelper;
    if ($importHelper === null) {
        require_once __DIR__ . '/../classes/ImportHelper.php';
        $importHelper = new ImportHelper();
    }
    return $importHelper;
}

switch ($action) {
    case 'get_translations':
        $translations = I18n::getAllTranslations();
        echo json_encode([
            'code' => 0,
            'data' => [
                'lang' => I18n::getLang(),
                'supported_langs' => I18n::getSupportedLangs(),
                'translations' => $translations,
            ],
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'translate':
        $key = $_GET['key'] ?? '';
        $params = isset($_GET['params']) ? json_decode($_GET['params'], true) : [];
        if (!is_array($params)) $params = [];
        $result = I18n::t($key, $params);
        echo json_encode([
            'code' => 0,
            'data' => $result,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'get_meta':
        global $config;
        echo json_encode([
            'code' => 0,
            'data' => [
                'lang' => I18n::getLang(),
                'supported_langs' => I18n::getSupportedLangs(),
                'currency' => Currency::getCurrency(),
                'supported_currencies' => Currency::getSupportedCurrencies(),
                'base_currency' => $config['base_currency'],
            ],
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'get_rates':
        $rates = Currency::getRates();
        $symbols = [];
        foreach (Currency::getSupportedCurrencies() as $c) {
            $symbols[$c] = Currency::getSymbol($c);
        }
        global $config;
        echo json_encode([
            'code' => 0,
            'data' => [
                'base' => $config['base_currency'],
                'currency' => Currency::getCurrency(),
                'supported_currencies' => Currency::getSupportedCurrencies(),
                'rates' => $rates,
                'symbols' => $symbols,
                'using_fallback' => Currency::isUsingFallback(),
            ],
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'convert':
        $amount = $_GET['amount'] ?? 0;
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $formatted = isset($_GET['format']) && $_GET['format'] === '1';
        $result = $formatted
            ? Currency::format($amount, $to, I18n::getLang())
            : Currency::convert($amount, $from, $to);
        echo json_encode([
            'code' => 0,
            'data' => $result,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'refresh_rates':
        $ok = Currency::refreshRates();
        echo json_encode([
            'code' => $ok ? 0 : 1,
            'message' => $ok ? 'Rates refreshed' : 'Failed to refresh, using fallback',
            'data' => Currency::getRates(),
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'get_courses':
        $courses = getCourses();
        echo json_encode([
            'code' => 0,
            'data' => $courses,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'supplier_import_template':
        $operator = $_GET['operator'] ?? $_POST['operator'] ?? 'system';
        try {
            $helper = getImportHelper();
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_DOWNLOAD_TEMPLATE,
                ImportHelper::OP_STATUS_SUCCESS,
                null,
                '下载导入模板'
            );
            $helper->generateTemplateCSV();
        } catch (Exception $e) {
            $helper = getImportHelper();
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_DOWNLOAD_TEMPLATE,
                ImportHelper::OP_STATUS_FAIL,
                null,
                '下载模板失败: ' . $e->getMessage()
            );
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'supplier_import_upload':
        $operator = $_POST['operator'] ?? 'system';
        $helper = getImportHelper();
        try {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('请选择要上传的文件');
            }
            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                throw new Exception('仅支持CSV格式文件');
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('文件大小不能超过10MB');
            }
            if ($file['size'] === 0) {
                throw new Exception('文件内容不能为空');
            }
            global $config;
            $uploadDir = $config['upload']['dir'] . 'supplier_import/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'import_' . date('YmdHis') . '_' . uniqid() . '.csv';
            $filePath = $uploadDir . $fileName;
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('文件上传失败');
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
            $taskId = $helper->createTask($file['name'], $filePath, $totalRows, $operator);
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_UPLOAD_FILE,
                ImportHelper::OP_STATUS_SUCCESS,
                $taskId,
                "上传文件成功: {$file['name']}, 共 {$totalRows} 行数据"
            );
            echo json_encode([
                'code' => 0,
                'message' => '上传成功',
                'data' => [
                    'task_id' => $taskId,
                    'file_name' => $file['name'],
                    'total_rows' => $totalRows,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_UPLOAD_FILE,
                ImportHelper::OP_STATUS_FAIL,
                null,
                '文件上传失败: ' . $e->getMessage()
            );
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'supplier_import_process':
        $taskId = $_POST['task_id'] ?? $_GET['task_id'] ?? 0;
        $operator = $_POST['operator'] ?? $_GET['operator'] ?? 'system';
        $helper = getImportHelper();
        try {
            if (!$taskId) {
                throw new Exception('任务ID不能为空');
            }
            $result = $helper->processTask($taskId);
            if (isset($result['success']) && !$result['success']) {
                $helper->addOperationLog(
                    $operator,
                    ImportHelper::OP_PROCESS_IMPORT,
                    ImportHelper::OP_STATUS_FAIL,
                    $taskId,
                    isset($result['message']) ? $result['message'] : '导入处理失败'
                );
                echo json_encode([
                    'code' => 1,
                    'message' => $result['message'] ?? '导入处理失败',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_PROCESS_IMPORT,
                ImportHelper::OP_STATUS_SUCCESS,
                $taskId,
                "导入处理完成: 总计 {$result['total']} 行, 成功 {$result['success_count']} 行, 失败 {$result['fail_count']} 行"
            );
            echo json_encode([
                'code' => 0,
                'message' => '导入处理完成',
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_PROCESS_IMPORT,
                ImportHelper::OP_STATUS_FAIL,
                $taskId,
                '导入处理异常: ' . $e->getMessage()
            );
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'supplier_import_tasks':
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $pageSize = isset($_GET['pageSize']) ? max(1, (int)$_GET['pageSize']) : 20;
        try {
            $helper = getImportHelper();
            $result = $helper->getTaskList($page, $pageSize);
            echo json_encode([
                'code' => 0,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'supplier_import_fail_details':
        $taskId = $_GET['task_id'] ?? 0;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $pageSize = isset($_GET['pageSize']) ? max(1, (int)$_GET['pageSize']) : 50;
        $operator = $_GET['operator'] ?? 'system';
        $helper = getImportHelper();
        try {
            if (!$taskId) {
                throw new Exception('任务ID不能为空');
            }
            $result = $helper->getFailDetails($taskId, $page, $pageSize);
            $task = $helper->getTaskById($taskId);
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_VIEW_FAIL,
                ImportHelper::OP_STATUS_SUCCESS,
                $taskId,
                "查看失败明细: 任务 {$taskId}, 共 " . ($task['fail_count'] ?? 0) . " 条失败记录"
            );
            echo json_encode([
                'code' => 0,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_VIEW_FAIL,
                ImportHelper::OP_STATUS_FAIL,
                $taskId,
                '查看失败明细失败: ' . $e->getMessage()
            );
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'supplier_import_fail_export':
        $taskId = $_GET['task_id'] ?? 0;
        $operator = $_GET['operator'] ?? 'system';
        $helper = getImportHelper();
        try {
            if (!$taskId) {
                throw new Exception('任务ID不能为空');
            }
            $task = $helper->getTaskById($taskId);
            if (!$task) {
                throw new Exception('任务不存在');
            }
            global $config;
            $columns = $config['import']['template_columns'];
            $headers = array_values($columns);
            $colKeys = array_keys($columns);
            array_unshift($headers, '行号', '错误信息');
            $filename = '失败明细_' . $task['file_name'] . '_' . date('YmdHis') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($output, $headers);
            $page = 1;
            $pageSize = 500;
            do {
                $result = $helper->getFailDetails($taskId, $page, $pageSize);
                foreach ($result['list'] as $item) {
                    $row = [$item['row_number'], $item['error_message']];
                    $rowData = $item['row_data'] ?: [];
                    foreach ($colKeys as $key) {
                        $row[] = isset($rowData[$key]) ? $rowData[$key] : '';
                    }
                    fputcsv($output, $row);
                }
                $page++;
            } while (!empty($result['list']) && count($result['list']) >= $pageSize);
            fclose($output);
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_EXPORT_FAIL,
                ImportHelper::OP_STATUS_SUCCESS,
                $taskId,
                "导出失败明细: 任务 {$taskId}, 共 " . ($task['fail_count'] ?? 0) . " 条失败记录"
            );
            exit;
        } catch (Exception $e) {
            $helper->addOperationLog(
                $operator,
                ImportHelper::OP_EXPORT_FAIL,
                ImportHelper::OP_STATUS_FAIL,
                $taskId,
                '导出失败明细失败: ' . $e->getMessage()
            );
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'supplier_operation_logs':
        $params = [
            'task_id' => $_GET['task_id'] ?? null,
            'operator' => $_GET['operator'] ?? null,
            'operation_type' => $_GET['operation_type'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'pageSize' => $_GET['pageSize'] ?? 20,
        ];
        try {
            $helper = getImportHelper();
            $result = $helper->getOperationLogs($params);
            echo json_encode([
                'code' => 0,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'supplier_add_operation_log':
        $operator = $_POST['operator'] ?? $_GET['operator'] ?? 'system';
        $operationType = $_POST['operation_type'] ?? $_GET['operation_type'] ?? '';
        $status = isset($_POST['status']) ? (int)$_POST['status'] : (isset($_GET['status']) ? (int)$_GET['status'] : 1);
        $taskId = $_POST['task_id'] ?? $_GET['task_id'] ?? null;
        $remark = $_POST['remark'] ?? $_GET['remark'] ?? null;
        try {
            if (!$operationType) {
                throw new Exception('操作类型不能为空');
            }
            $helper = getImportHelper();
            $logId = $helper->addOperationLog($operator, $operationType, $status, $taskId, $remark);
            echo json_encode([
                'code' => 0,
                'message' => '操作记录已添加',
                'data' => ['log_id' => $logId],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'code' => 1,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    default:
        echo json_encode([
            'code' => 0,
            'message' => 'Supported actions: get_translations, translate, get_meta, get_rates, convert, refresh_rates, get_courses, supplier_import_template, supplier_import_upload, supplier_import_process, supplier_import_tasks, supplier_import_fail_details, supplier_import_fail_export, supplier_operation_logs, supplier_add_operation_log',
        ], JSON_UNESCAPED_UNICODE);
}

function getCourses()
{
    return [
        ['id' => 1, 'title' => 'Vue 3 ' . I18n::t('course.list'), 'desc' => 'Vue 3 Composition API Mastery', 'teacher' => 'Teacher A', 'price' => 299, 'original_price' => 499, 'students' => 1234, 'rating' => 4.8, 'level' => 'beginner', 'lessons' => 24],
        ['id' => 2, 'title' => 'PHP 8 ' . I18n::t('course.list'), 'desc' => 'PHP OOP and Design Patterns', 'teacher' => 'Teacher B', 'price' => 599, 'original_price' => 899, 'students' => 876, 'rating' => 4.9, 'level' => 'intermediate', 'lessons' => 48],
        ['id' => 3, 'title' => 'Microservices ' . I18n::t('course.list'), 'desc' => 'Build scalable systems', 'teacher' => 'Teacher C', 'price' => 1299, 'original_price' => 1999, 'students' => 432, 'rating' => 4.7, 'level' => 'advanced', 'lessons' => 72],
        ['id' => 4, 'title' => 'Free Intro to Coding ' . I18n::t('course.list'), 'desc' => 'First steps into programming', 'teacher' => 'Teacher D', 'price' => 0, 'original_price' => 0, 'students' => 5678, 'rating' => 4.6, 'level' => 'beginner', 'lessons' => 12],
    ];
}
