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

    default:
        echo json_encode([
            'code' => 0,
            'message' => 'Supported actions: get_translations, translate, get_meta, get_rates, convert, refresh_rates, get_courses',
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
