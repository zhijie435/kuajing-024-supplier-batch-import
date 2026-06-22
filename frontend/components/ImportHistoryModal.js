import { api } from '../utils/api.js';

const { ref, computed, onMounted, defineComponent, h, watch } = Vue;

export const ImportHistoryModal = defineComponent({
  name: 'ImportHistoryModal',
  props: {
    visible: { type: Boolean, default: false },
    t: { type: Function, required: true },
  },
  emits: ['close', 'view-fail'],
  setup(props, { emit }) {
    const list = ref([]);
    const loading = ref(false);
    const page = ref(1);
    const pageSize = ref(20);
    const total = ref(0);

    const totalPages = computed(() => Math.ceil(total.value / pageSize.value));

    const statusMap = {
      0: { text: '待处理', class: 'tag tag-default' },
      1: { text: '处理中', class: 'tag tag-warning' },
      2: { text: '已完成', class: 'tag tag-success' },
      3: { text: '失败', class: 'tag tag-error' },
    };

    const getStatusTag = (status) => {
      const s = statusMap[status] || statusMap[0];
      return h('span', { class: s.class }, s.text);
    };

    const loadData = async () => {
      loading.value = true;
      try {
        const res = await api.getSupplierImportTasks({
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
      if (val) {
        page.value = 1;
        loadData();
      }
    });

    const handlePageChange = (newPage) => {
      page.value = newPage;
      loadData();
    };

    const handleClose = () => {
      emit('close');
    };

    const handleViewFail = (task) => {
      emit('view-fail', task);
    };

    const handleRefresh = () => {
      loadData();
    };

    return () => {
      if (!props.visible) return null;
      return h('div', { class: 'modal-mask', onClick: handleClose }, [
        h('div', { class: 'modal modal-lg', onClick: (e) => e.stopPropagation() }, [
          h('div', { class: 'modal-header' }, [
            h('span', { class: 'modal-title' }, props.t('supplier.import_history')),
            h('div', { style: 'display: flex; gap: 12px; align-items: center;' }, [
              h('button', { class: 'btn btn-sm', onClick: handleRefresh }, [
                h('span', '↻ '),
                h('span', props.t('supplier.refresh')),
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
            !loading.value && list.value.length > 0 && h('table', { class: 'data-table' }, [
              h('thead', null, h('tr', null, [
                h('th', null, 'ID'),
                h('th', null, '文件名'),
                h('th', null, props.t('supplier.import_total')),
                h('th', null, props.t('supplier.import_success_count')),
                h('th', null, props.t('supplier.import_fail_count')),
                h('th', null, props.t('supplier.status')),
                h('th', null, props.t('supplier.operator')),
                h('th', null, props.t('supplier.created_at')),
                h('th', null, '操作'),
              ])),
              h('tbody', null, list.value.map((item) => h('tr', { key: item.id }, [
                h('td', null, String(item.id)),
                h('td', null, item.file_name),
                h('td', null, String(item.total_rows)),
                h('td', null, [
                  h('span', { style: 'color: #52c41a; font-weight: 500;' }, String(item.success_count)),
                ]),
                h('td', null, [
                  (item.fail_count > 0)
                    ? h('button', {
                        class: 'link-btn',
                        style: 'color: #ff4d4f; font-weight: 500;',
                        onClick: () => handleViewFail(item),
                      }, String(item.fail_count))
                    : h('span', null, String(item.fail_count)),
                ]),
                h('td', null, getStatusTag(item.status)),
                h('td', null, item.operator || '-'),
                h('td', null, item.created_at || '-'),
                h('td', null, item.fail_count > 0 ? [
                  h('button', {
                    class: 'link-btn',
                    onClick: () => handleViewFail(item),
                  }, props.t('supplier.import_fail_details')),
                ] : h('span', { style: 'color: #999;' }, '-')),
              ]))),
            ]),
          ]),
          list.value.length > 0 && h('div', { class: 'modal-footer' }, [
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
        ]),
      ]);
    };
  },
});
