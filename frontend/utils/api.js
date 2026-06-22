function buildURL(action, params = {}) {
  const u = new URL('backend/api/index.php', window.location.href);
  u.searchParams.set('action', action);
  try {
    const lang = localStorage.getItem('lang');
    if (lang) u.searchParams.set('lang', lang);
  } catch (e) {}
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null) u.searchParams.set(k, String(v));
  }
  return u.toString();
}

async function request(action, params = {}) {
  try {
    const res = await fetch(buildURL(action, params), {
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  } catch (e) {
    return { code: 1, message: e.message };
  }
}

export const api = {
  getMeta: () => request('get_meta'),
  getTranslations: () => request('get_translations'),
  translate: (key, params) => request('translate', { key, params: JSON.stringify(params || {}) }),
  getRates: () => request('get_rates'),
  convert: (amount, from, to, format = false) => request('convert', { amount, from, to, format: format ? '1' : '0' }),
  refreshRates: () => request('refresh_rates'),
  getCourses: () => request('get_courses'),
};
