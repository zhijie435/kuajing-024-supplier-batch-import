import { api } from '../utils/api.js';

const { createApp, ref, computed, onMounted, defineComponent, h, reactive, watch } = Vue;

export const FailDetailModal = defineComponent({
  name: 'FailDetailModal',
  props: {
    visible: { type: Boolean, default: false },
    taskId: { type: [Number, String], default: null },
    t: { type: Function, required: true },
  },
  emits: ['close'],
  setup(props, { emit }) {
    const list = ref([]);
    const loading = ref(false);
    const page = ref(1);
    const pageSize = ref(50);
    const total = ref(0);
    const columnLabels = {
      company_name: '企业全称',
      unified_social_credit_code: '统一社会信用代码',
      legal_person: '法定代表人',
      legal_person_id_card: '法人身份证号',
      registered_capital: '注册资本',
      establish_date: '成立日期',
      business_scope: '经营范围',
      registered_address_province: '省份',
      registered_address_city: '城市',
      registered_address_district: '区县',
      registered_address_detail: '详细地址',
      contact_name: '联系人姓名',
      contact_phone: '联系电话',
      contact_email: '联系邮箱',
    };

    const totalPages = computed(() => Math.ceil(total.value / pageSize.value));

    const loadData = async () => {
      if (!props.taskId) return;
      loading.value = true;
      try {
        const res = await api.getSupplierImportFailDetails({
          task_id: props.taskId,
          page: page.value,
          pageSize: pageSize.value,
        });
        if (res.code === 0) {
          list.value = res.data.list || [];
          total.value = res.data.pagination?.total || 0;
        }
      } finally {
        loading.value = false;
      }
    };

    watch(() => props.visible, (val) => {
      if (val && props.taskId) {
        page.value = 1;
        loadData();
      }
    });

    watch(() => props.taskId, (val) => {
      if (val && props.visible) {
        page.value = 1;
        loadData();
      }
    });

    const handlePageChange = (newPage) => {
      page.value = newPage;
      loadData();
    };

    const handleExport = () => {
      const url = api.getSupplierImportFailExportURL(props.taskId);
      window.location.href = url;
    };

    const handleClose = () => {
      emit('close');
    };

    const renderDataRow = (rowData) => {
      if (!rowData) return null;
      const items = [];
      for (const [key, label] of Object.entries(columnLabels)) {
        const value = rowData[key];
        if (value && String(value).trim()) {
          items.push(h('div', { class: 'fail-data-item', key }, [
            h('span', { class: 'fail-data-label' }, label + '：'),
            h('span', { class: 'fail-data-value' }, String(value)),
          ]));
        }
      }
      return h('div', { class: 'fail-data-row' }, items);
    };

    return () => {
      if (!props.visible) return null;
      return h('div', { class: 'modal-mask', onClick: handleClose }, [
        h('div', { class: 'modal modal-xl', onClick: (e) => e.stopPropagation() }, [
          h('div', { class: 'modal-header' }, [
            h('span', { class: 'modal-title' }, props.t('supplier.import_fail_details')),
            h('div', { style: 'display: flex; gap: 12px; align-items: center;' }, [
              h('button', { class: 'btn btn-sm btn-primary', onClick: handleExport }, [
                h('span', '↓ '),
                h('span', props.t('supplier.import_fail_export')),
              ]),
              h('button', { class: 'modal-close', onClick: handleClose }, '×'),
            ]),
          ]),
          h('div', { class: 'modal-body' }, [
            loading.value && h('div', { style: 'text-align: center; padding: 40px;' }, [
              h('div', { class: 'loading-text' }, [
                h('span', { class: 'loading-spinner' }),
                h('span', props.t('common.loading')),
              ]),
            ]),
            !loading.value && list.value.length === 0 && h('div', { class: 'empty-state' }, [
              h('div', { class: 'empty-state-icon' }, '📭'),
              h('div', { class: 'empty-state-text' }, props.t('common.no_data')),
            ]),
            !loading.value && list.value.length > 0 && h('table', { class: 'fail-table' }, [
              h('thead', null, h('tr', null, [
                h('th', null, props.t('supplier.row_number')),
                h('th', null, '原始数据'),
                h('th', null, props.t('supplier.error_message')),
              ])),
              h('tbody', null, list.value.map((item) => h('tr', { key: item.id }, [
                h('td', { class: 'row-num' }, '第 ' + item.row_number + ' 行'),
                h('td', null, renderDataRow(item.row_data)),
                h('td', { class: 'error-msg' }, item.error_message),
              ]))),
            ]),
          ]),
          list.value.length > 0 && h('div', { class: 'modal-footer', style: 'padding-top: 0; border-top: none;' }, [
            h('div', { class: 'pagination' }, [
              h('span', { class: 'pagination-info' }, '共 ' + total.value + ' 条'),
              h('button', {
                class: 'pagination-btn',
                onClick: () => handlePageChange(page.value - 1),
                disabled: page.value <= 1,
              }, '上一页'),
              ...Array.from({ length: Math.min(totalPages.value, 5) }, (_, i) => {
                let p = i + 1;
                if (totalPages.value > 5) {
                  if (page.value <= 3) {
                    p = i + 1;
                  } else if (page.value >= totalPages.value - 2) {
                    p = totalPages.value - 4 + i;
                  } else {
                    p = page.value - 2 + i;
                  }
                }
                return h('button', {
                  key: p,
                  class: ['pagination-btn', p === page.value ? 'active' : ''].join(' '),
                  onClick: () => handlePageChange(p),
                }, String(p));
              }),
              h('button', {
                class: 'pagination-btn',
                onClick: () => handlePageChange(page.value + 1),
                disabled: page.value >= totalPages.value,
              }, '下一页'),
            ]),
          ]),
          h('div', { class: 'modal-footer' }, [
            h('button', { class: 'btn', onClick: handleClose }, props.t('supplier.close')),
          ]),
        ]),
      ]);
    };
  },
});
