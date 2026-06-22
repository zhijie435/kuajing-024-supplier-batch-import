<?php

class I18n
{
    private static $lang = 'zh-CN';
    private static $defaultLang = 'zh-CN';
    private static $fallbackLang = 'en-US';
    private static $supportedLangs = ['zh-CN', 'en-US', 'ja-JP'];
    private static $translations = [];
    private static $initialized = false;

    public static function init($config = [])
    {
        if (self::$initialized) {
            return;
        }
        if (isset($config['default_lang'])) {
            self::$defaultLang = $config['default_lang'];
            self::$lang = $config['default_lang'];
        }
        if (isset($config['fallback_lang'])) {
            self::$fallbackLang = $config['fallback_lang'];
        }
        if (isset($config['supported_langs'])) {
            self::$supportedLangs = $config['supported_langs'];
        }
        self::loadTranslations();
        self::$initialized = true;
    }

    private static function loadTranslations()
    {
        $langsDir = __DIR__ . '/../langs/';
        foreach (self::$supportedLangs as $lang) {
            $file = $langsDir . $lang . '.php';
            if (file_exists($file)) {
                self::$translations[$lang] = require $file;
            } else {
                self::$translations[$lang] = [];
            }
        }
    }

    public static function setLang($lang)
    {
        if (in_array($lang, self::$supportedLangs)) {
            self::$lang = $lang;
            return true;
        }
        return false;
    }

    public static function getLang()
    {
        return self::$lang;
    }

    public static function getSupportedLangs()
    {
        return self::$supportedLangs;
    }

    public static function getDefaultLang()
    {
        return self::$defaultLang;
    }

    public static function getFallbackLang()
    {
        return self::$fallbackLang;
    }

    private static function getByPath($arr, $path)
    {
        if (!is_array($arr) || empty($path)) return null;
        $parts = explode('.', $path);
        $curr = $arr;
        foreach ($parts as $p) {
            if (!is_array($curr) || !array_key_exists($p, $curr)) return null;
            $curr = $curr[$p];
        }
        return is_string($curr) ? $curr : null;
    }

    private static function formatTemplate($str, $params)
    {
        if (!$str || !is_array($params) || empty($params)) return $str;
        $keys = array_keys($params);
        $wrapped = [];
        foreach ($keys as $k) $wrapped[] = '{' . $k . '}';
        return str_replace($wrapped, array_values($params), $str);
    }

    public static function t($key, $params = [], $lang = null)
    {
        $targetLang = $lang ?? self::$lang;
        $chain = [];
        $chain[] = $targetLang;
        if (self::$fallbackLang !== $targetLang) {
            $chain[] = self::$fallbackLang;
        }
        if (self::$defaultLang !== $targetLang && self::$defaultLang !== self::$fallbackLang) {
            $chain[] = self::$defaultLang;
        }
        foreach ($chain as $l) {
            $val = self::getByPath(self::$translations[$l] ?? [], $key);
            if ($val !== null) {
                return self::formatTemplate($val, $params);
            }
        }
        return self::formatTemplate($key, $params);
    }

    public static function getAllTranslations()
    {
        $merged = [];
        $mergeFn = function ($base, $extra) use (&$mergeFn) {
            if (!is_array($extra)) return $base;
            foreach ($extra as $k => $v) {
                if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                    $base[$k] = $mergeFn($base[$k], $v);
                } elseif (!isset($base[$k])) {
                    $base[$k] = $v;
                }
            }
            return $base;
        };
        $merged = $mergeFn($merged, self::$translations[self::$defaultLang] ?? []);
        if (self::$fallbackLang !== self::$defaultLang) {
            $merged = $mergeFn($merged, self::$translations[self::$fallbackLang] ?? []);
        }
        if (self::$lang !== self::$defaultLang && self::$lang !== self::$fallbackLang) {
            $merged = $mergeFn($merged, self::$translations[self::$lang] ?? []);
        }
        return $merged;
    }

    public static function detectLangFromRequest()
    {
        if (isset($_GET['lang']) && in_array($_GET['lang'], self::$supportedLangs)) {
            return $_GET['lang'];
        }
        if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], self::$supportedLangs)) {
            return $_COOKIE['lang'];
        }
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            $list = explode(',', $accept);
            foreach ($list as $tag) {
                $tag = trim(explode(';', $tag)[0]);
                foreach (self::$supportedLangs as $l) {
                    if (stripos($l, $tag) === 0 || stripos($tag, substr($l, 0, 2)) === 0) {
                        return $l;
                    }
                }
            }
        }
        return self::$defaultLang;
    }
}
