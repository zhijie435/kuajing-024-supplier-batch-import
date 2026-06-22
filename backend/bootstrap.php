<?php

require_once __DIR__ . '/classes/I18n.php';
require_once __DIR__ . '/classes/Currency.php';

$config = [
    'base_currency' => 'CNY',
    'default_lang' => 'zh-CN',
    'fallback_lang' => 'en-US',
    'supported_langs' => ['zh-CN', 'en-US', 'ja-JP'],
    'supported_currencies' => ['CNY', 'USD', 'JPY', 'EUR', 'HKD'],
    'exchange_rates_ttl' => 3600,
    'exchange_rates_cache_file' => __DIR__ . '/storage/exchange_rates.json',
];

I18n::init($config);
Currency::init($config);

$lang = I18n::detectLangFromRequest();
I18n::setLang($lang);

$currency = Currency::detectCurrencyFromRequest();
Currency::setCurrency($currency);

if ($lang !== ($_COOKIE['lang'] ?? '')) {
    setcookie('lang', $lang, time() + 86400 * 30, '/');
}
if ($currency !== ($_COOKIE['currency'] ?? '')) {
    setcookie('currency', $currency, time() + 86400 * 30, '/');
}
