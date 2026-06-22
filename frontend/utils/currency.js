const BASE = 'CNY';
const SUPPORTED = ['CNY', 'USD', 'JPY', 'EUR', 'HKD'];
const TTL = 3600 * 1000;

const FALLBACK_RATES = { CNY: 1.0, USD: 0.14, JPY: 21.0, EUR: 0.13, HKD: 1.09 };
const SYMBOLS = { CNY: '¥', USD: '$', JPY: '¥', EUR: '€', HKD: 'HK$' };
const LOCALES = { 'zh-CN': 'zh-CN', 'en-US': 'en-US', 'ja-JP': 'ja-JP' };

function createCurrency() {
  let current = BASE;
  let rates = { ...FALLBACK_RATES };
  let usingFallback = true;
  let lastUpdate = 0;

  function detect() {
    const url = new URL(window.location.href);
    const q = url.searchParams.get('currency');
    if (q && SUPPORTED.includes(q)) return q;
    try {
      const c = document.cookie.match(/(?:^|;\s*)currency=([^;]+)/);
      if (c && SUPPORTED.includes(c[1])) return c[1];
    } catch (e) {}
    try {
      const s = localStorage.getItem('currency');
      if (s && SUPPORTED.includes(s)) return s;
    } catch (e) {}
    return BASE;
  }

  function setCookie(name, value, days = 30) {
    const d = new Date(Date.now() + days * 86400000).toUTCString();
    document.cookie = `${name}=${value}; expires=${d}; path=/`;
  }

  function getCurrency() { return current; }
  function setCurrency(c) {
    if (!SUPPORTED.includes(c)) return false;
    current = c;
    try { localStorage.setItem('currency', c); } catch (e) {}
    setCookie('currency', c);
    return true;
  }
  function getSupportedCurrencies() { return SUPPORTED.slice(); }
  function getBaseCurrency() { return BASE; }
  function getSymbol(c) { return SYMBOLS[c] || ''; }
  function getRates() { return { ...rates }; }
  function isUsingFallback() { return usingFallback; }

  function convert(amount, from, to) {
    const f = from || BASE;
    const t = to || current;
    if (f === t) return Number(amount) || 0;
    const inBase = (Number(amount) || 0) / (rates[f] || 1);
    return Number((inBase * (rates[t] || 1)).toFixed(4));
  }

  function format(amount, currencyCode, lang) {
    const code = currencyCode || current;
    const n = Number(amount) || 0;
    const locale = LOCALES[lang] || LOCALES['zh-CN'];
    try {
      return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: code,
        minimumFractionDigits: code === 'JPY' ? 0 : 2,
        maximumFractionDigits: code === 'JPY' ? 0 : 2,
      }).format(n);
    } catch (e) {
      return (SYMBOLS[code] || code + ' ') + n.toFixed(code === 'JPY' ? 0 : 2);
    }
  }

  function loadCache() {
    try {
      const raw = localStorage.getItem('exchange_rates');
      const ts = Number(localStorage.getItem('exchange_rates_ts') || '0');
      if (raw && Date.now() - ts < TTL) {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          rates = { ...FALLBACK_RATES, ...parsed };
          usingFallback = false;
          lastUpdate = ts;
          return true;
        }
      }
    } catch (e) {}
    return false;
  }

  function saveCache() {
    try {
      localStorage.setItem('exchange_rates', JSON.stringify(rates));
      localStorage.setItem('exchange_rates_ts', String(lastUpdate));
    } catch (e) {}
  }

  async function fetchFromAPI() {
    try {
      const res = await fetch('backend/api/index.php?action=get_rates', { cache: 'no-store' });
      if (!res.ok) return false;
      const j = await res.json();
      if (j && j.code === 0 && j.data && j.data.rates) {
        rates = { ...FALLBACK_RATES, ...j.data.rates };
        usingFallback = false;
        lastUpdate = Date.now();
        saveCache();
        return true;
      }
    } catch (e) {}
    return false;
  }

  async function loadRates(force = false) {
    if (!force && loadCache()) return true;
    const ok = await fetchFromAPI();
    if (!ok) {
      rates = { ...FALLBACK_RATES };
      usingFallback = true;
    }
    return true;
  }

  async function init() {
    current = detect();
    try { localStorage.setItem('currency', current); } catch (e) {}
    setCookie('currency', current);
    await loadRates();
    return true;
  }

  return { init, convert, format, getCurrency, setCurrency, getSupportedCurrencies, getBaseCurrency, getSymbol, getRates, isUsingFallback, loadRates };
}

export { createCurrency };
