import { zhCN } from '../locales/zh-CN.js';
import { enUS } from '../locales/en-US.js';
import { jaJP } from '../locales/ja-JP.js';

const DEFAULT_LANG = 'zh-CN';
const FALLBACK_LANG = 'en-US';
const SUPPORTED_LANGS = ['zh-CN', 'en-US', 'ja-JP'];

function createI18n() {
  const localeData = {
    'zh-CN': zhCN,
    'en-US': enUS,
    'ja-JP': jaJP,
  };
  let currentLang = DEFAULT_LANG;
  const fallbackMap = new Map();

  function getByPath(obj, path) {
    if (!obj || !path) return undefined;
    const parts = path.split('.');
    let curr = obj;
    for (const p of parts) {
      if (curr == null || typeof curr !== 'object') return undefined;
      curr = curr[p];
    }
    return typeof curr === 'string' ? curr : undefined;
  }

  function format(str, params) {
    if (!str || !params) return str;
    return str.replace(/\{(\w+)\}/g, (_, k) => (params[k] !== undefined ? params[k] : `{${k}}`));
  }

  function detectLang() {
    const url = new URL(window.location.href);
    const q = url.searchParams.get('lang');
    if (q && SUPPORTED_LANGS.includes(q)) return q;
    try {
      const c = document.cookie.match(/(?:^|;\s*)lang=([^;]+)/);
      if (c && SUPPORTED_LANGS.includes(c[1])) return c[1];
    } catch (e) {}
    try {
      const s = localStorage.getItem('lang');
      if (s && SUPPORTED_LANGS.includes(s)) return s;
    } catch (e) {}
    const nav = navigator.language || 'zh-CN';
    for (const l of SUPPORTED_LANGS) {
      if (nav.startsWith(l.slice(0, 2))) return l;
    }
    return DEFAULT_LANG;
  }

  function setCookie(name, value, days = 30) {
    const d = new Date(Date.now() + days * 86400000).toUTCString();
    document.cookie = `${name}=${value}; expires=${d}; path=/`;
  }

  function getLang() { return currentLang; }
  function setLang(lang) {
    if (!SUPPORTED_LANGS.includes(lang)) return false;
    currentLang = lang;
    try { localStorage.setItem('lang', lang); } catch (e) {}
    setCookie('lang', lang);
    return true;
  }
  function getSupportedLangs() { return SUPPORTED_LANGS.slice(); }
  function getDefaultLang() { return DEFAULT_LANG; }
  function getFallbackLang() { return FALLBACK_LANG; }

  function t(key, params) {
    fallbackMap.delete(key);
    const chain = [];
    if (currentLang !== DEFAULT_LANG && currentLang !== FALLBACK_LANG) chain.push(currentLang);
    if (currentLang !== FALLBACK_LANG && FALLBACK_LANG !== DEFAULT_LANG) chain.push(FALLBACK_LANG);
    chain.push(DEFAULT_LANG);
    for (const lang of chain) {
      const v = getByPath(localeData[lang], key);
      if (v !== undefined) {
        if (lang !== currentLang) fallbackMap.set(key, lang);
        return format(v, params);
      }
    }
    fallbackMap.set(key, '__KEY__');
    return format(key, params);
  }

  function getFallbackSource(key) { return fallbackMap.get(key) || null; }
  function isFallback(key) { return fallbackMap.has(key); }

  function getAllTranslations() {
    const merged = {};
    function merge(target, src) {
      if (!src || typeof src !== 'object') return;
      for (const k of Object.keys(src)) {
        if (typeof src[k] === 'object' && src[k] !== null && !Array.isArray(src[k])) {
          if (!target[k] || typeof target[k] !== 'object') target[k] = {};
          merge(target[k], src[k]);
        } else if (target[k] === undefined) {
          target[k] = src[k];
        }
      }
    }
    merge(merged, localeData[DEFAULT_LANG] || {});
    if (FALLBACK_LANG !== DEFAULT_LANG) merge(merged, localeData[FALLBACK_LANG] || {});
    if (currentLang !== DEFAULT_LANG && currentLang !== FALLBACK_LANG) merge(merged, localeData[currentLang] || {});
    return merged;
  }

  async function init() {
    currentLang = detectLang();
    try { localStorage.setItem('lang', currentLang); } catch (e) {}
    setCookie('lang', currentLang);
    return true;
  }

  return { init, t, getLang, setLang, getSupportedLangs, getDefaultLang, getFallbackLang, getAllTranslations, getFallbackSource, isFallback };
}

export { createI18n };
