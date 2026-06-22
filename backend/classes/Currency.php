<?php

class Currency
{
    private static $currency = 'CNY';
    private static $baseCurrency = 'CNY';
    private static $supportedCurrencies = ['CNY', 'USD', 'JPY', 'EUR', 'HKD'];
    private static $rates = [];
    private static $ttl = 3600;
    private static $cacheFile = '';
    private static $usingFallback = true;
    private static $initialized = false;
    private static $config = [];

    const FALLBACK_RATES = [
        'CNY' => 1.0,
        'USD' => 0.14,
        'JPY' => 21.0,
        'EUR' => 0.13,
        'HKD' => 1.09,
    ];

    const SYMBOLS = [
        'CNY' => '¥',
        'USD' => '$',
        'JPY' => '¥',
        'EUR' => '€',
        'HKD' => 'HK$',
    ];

    public static function init($config = [])
    {
        if (self::$initialized) return;
        self::$config = $config;
        if (isset($config['base_currency'])) self::$baseCurrency = $config['base_currency'];
        if (isset($config['supported_currencies'])) self::$supportedCurrencies = $config['supported_currencies'];
        if (isset($config['exchange_rates_ttl'])) self::$ttl = $config['exchange_rates_ttl'];
        self::$cacheFile = $config['exchange_rates_cache_file'] ?? (__DIR__ . '/../storage/exchange_rates.json');
        self::$currency = self::$baseCurrency;
        self::loadRates();
        self::$initialized = true;
    }

    public static function setCurrency($c)
    {
        if (in_array($c, self::$supportedCurrencies)) {
            self::$currency = $c;
            return true;
        }
        return false;
    }

    public static function getCurrency()
    {
        return self::$currency;
    }

    public static function getSupportedCurrencies()
    {
        return self::$supportedCurrencies;
    }

    public static function getBaseCurrency()
    {
        return self::$baseCurrency;
    }

    public static function getSymbol($c = null)
    {
        $code = $c ?? self::$currency;
        return self::SYMBOLS[$code] ?? $code;
    }

    public static function getRates()
    {
        return self::$rates;
    }

    public static function isUsingFallback()
    {
        return self::$usingFallback;
    }

    private static function loadRates()
    {
        $loaded = false;
        if (self::$cacheFile && file_exists(self::$cacheFile)) {
            $age = time() - filemtime(self::$cacheFile);
            if ($age < self::$ttl) {
                $data = @json_decode(file_get_contents(self::$cacheFile), true);
                if (is_array($data) && !empty($data)) {
                    self::$rates = self::normalizeRates($data);
                    self::$usingFallback = false;
                    $loaded = true;
                }
            }
        }
        if (!$loaded) {
            self::$rates = self::normalizeRates(self::FALLBACK_RATES);
            self::$usingFallback = true;
        }
    }

    private static function normalizeRates($rates)
    {
        $normalized = [];
        foreach (self::$supportedCurrencies as $c) {
            $normalized[$c] = isset($rates[$c]) ? (float)$rates[$c] : (self::FALLBACK_RATES[$c] ?? 1.0);
        }
        return $normalized;
    }

    public static function refreshRates()
    {
        $apiUrls = [
            'https://open.er-api.com/v6/latest/CNY',
            'https://api.exchangerate-api.com/v4/latest/CNY',
        ];
        foreach ($apiUrls as $url) {
            $data = self::fetchUrl($url);
            if ($data && is_array($data)) {
                $extracted = self::extractRates($data);
                if (!empty($extracted)) {
                    self::$rates = self::normalizeRates($extracted);
                    self::$usingFallback = false;
                    self::saveCache();
                    return true;
                }
            }
        }
        self::$rates = self::normalizeRates(self::FALLBACK_RATES);
        self::$usingFallback = true;
        self::saveCache();
        return false;
    }

    private static function fetchUrl($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res && $code >= 200 && $code < 300) {
                return @json_decode($res, true);
            }
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
            $res = @file_get_contents($url, false, $ctx);
            if ($res) {
                return @json_decode($res, true);
            }
        }
        return null;
    }

    private static function extractRates($data)
    {
        if (isset($data['rates']) && is_array($data['rates'])) {
            return $data['rates'];
        }
        return null;
    }

    private static function saveCache()
    {
        if (!self::$cacheFile) return;
        $dir = dirname(self::$cacheFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents(self::$cacheFile, json_encode(self::$rates, JSON_UNESCAPED_UNICODE));
    }

    public static function convert($amount, $from = null, $to = null)
    {
        $fromCode = $from ?? self::$baseCurrency;
        $toCode = $to ?? self::$currency;
        $num = (float)$amount;
        if ($fromCode === $toCode) return $num;
        $fromRate = self::$rates[$fromCode] ?? (self::FALLBACK_RATES[$fromCode] ?? 1.0);
        $toRate = self::$rates[$toCode] ?? (self::FALLBACK_RATES[$toCode] ?? 1.0);
        if (!$fromRate) return 0;
        $inBase = $num / $fromRate;
        return round($inBase * $toRate, 4);
    }

    public static function format($amount, $to = null, $lang = null)
    {
        $code = $to ?? self::$currency;
        $num = self::convert($amount, self::$baseCurrency, $code);
        $localeMap = ['zh-CN' => 'zh_CN', 'en-US' => 'en_US', 'ja-JP' => 'ja_JP'];
        $locale = $lang ? ($localeMap[$lang] ?? 'zh_CN') : 'zh_CN';
        if (class_exists('NumberFormatter')) {
            $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $res = @$fmt->formatCurrency($num, $code);
            if ($res !== false) return $res;
        }
        $symbol = self::getSymbol($code);
        $decimals = ($code === 'JPY') ? 0 : 2;
        return $symbol . number_format($num, $decimals, '.', ',');
    }

    public static function detectCurrencyFromRequest()
    {
        if (isset($_GET['currency']) && in_array($_GET['currency'], self::$supportedCurrencies)) {
            return $_GET['currency'];
        }
        if (isset($_COOKIE['currency']) && in_array($_COOKIE['currency'], self::$supportedCurrencies)) {
            return $_COOKIE['currency'];
        }
        return self::$baseCurrency;
    }
}
