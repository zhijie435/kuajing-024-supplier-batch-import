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

async function post(action, params = {}) {
  try {
    const formData = new FormData();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== null) {
        formData.append(k, v);
      }
    }
    const res = await fetch(buildURL(action), {
      method: 'POST',
      body: formData,
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  } catch (e) {
    return { code: 1, message: e.message };
  }
}

async function upload(action, file, extraParams = {}) {
  try {
    const formData = new FormData();
    formData.append('file', file);
    for (const [k, v] of Object.entries(extraParams)) {
      if (v !== undefined && v !== null) {
        formData.append(k, v);
      }
    }
    const res = await fetch(buildURL(action), {
      method: 'POST',
      body: formData,
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return await res.json();
  } catch (e) {
    return { code: 1, message: e.message };
  }
}

function downloadURL(action, params = {}) {
  return buildURL(action, params);
}

export const api = {
  getMeta: () => request('get_meta'),
  getTranslations: () => request('get_translations'),
  translate: (key, params) => request('translate', { key, params: JSON.stringify(params || {}) }),
  getRates: () => request('get_rates'),
  convert: (amount, from, to, format = false) => request('convert', { amount, from, to, format: format ? '1' : '0' }),
  refreshRates: () => request('refresh_rates'),
  getCourses: () => request('get_courses'),

  getSupplierList: (params) => request('supplier_list', params),
  getSupplierImportTemplateURL: () => downloadURL('supplier_import_template'),
  uploadSupplierImport: (file, operator) => upload('supplier_import_upload', file, { operator }),
  processSupplierImport: (taskId) => post('supplier_import_process', { task_id: taskId }),
  getSupplierImportTasks: (params) => request('supplier_import_tasks', params),
  getSupplierImportFailDetails: (params) => request('supplier_import_fail_details', params),
  getSupplierImportFailExportURL: (taskId) => downloadURL('supplier_import_fail_export', { task_id: taskId }),
};
