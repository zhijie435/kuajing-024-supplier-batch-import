import { createI18n } from '../utils/i18n.js';
import { api } from '../utils/api.js';
import { ImportModal } from '../components/ImportModal.js';
import { FailDetailModal } from '../components/FailDetailModal.js';
import { ImportHistoryModal } from '../components/ImportHistoryModal.js';

const { createApp, ref, computed, onMounted, reactive } = Vue;

const i18n = createI18n();

async function bootstrap() {
  await i18n.init();
  const t = i18n.t.bind(i18n);

  createApp({
    components: { ImportModal, FailDetailModal, ImportHistoryModal },
    setup() {
      const list = ref([]);
      const loading = ref(false);
      const page = ref(1);
      const pageSize = ref(20);
      const total = ref(0);
      const keyword = ref('');

      const showImport = ref(false);
      const showHistory = ref(false);
      const showFailDetail = ref(false);
      const currentTaskId = ref(null);

      const totalPages = computed(() => Math.ceil(total.value / pageSize.value));
      const visiblePages = computed(() => {
        const pages = [];
        const tp = totalPages.value;
        if (tp <= 5) {
          for (let i = 1; i <= tp; i++) pages.push(i);
        } else if (page.value <= 3) {
          for (let i = 1; i <= 5; i++) pages.push(i);
        } else if (page.value >= tp - 2) {
          for (let i = tp - 4; i <= tp; i++) pages.push(i);
        } else {
          for (let i = page.value - 2; i <= page.value + 2; i++) pages.push(i);
        }
        return pages;
      });

      const statusMap = {
        0: { text: '待审核', class: 'tag tag-warning' },
        1: { text: '已通过', class: 'tag tag-success' },
        2: { text: '已拒绝', class: 'tag tag-error' },
      };

      const getStatusText = (status) => statusMap[status]?.text || t('supplier.status_' + status) || '-';
      const getStatusClass = (status) => statusMap[status]?.class || 'tag tag-default';

      const loadData = async () => {
        loading.value = true;
        try {
          const res = await api.getSupplierList({
            page: page.value,
            pageSize: pageSize.value,
            keyword: keyword.value,
          });
          if (res.code === 0) {
            list.value = res.data.list || [];
            total.value = res.data.pagination?.total || 0;
          }
        } finally {
          loading.value = false;
        }
      };

      const handleSearch = () => {
        page.value = 1;
        loadData();
      };

      const handleRefresh = () => {
        loadData();
      };

      const handlePageChange = (newPage) => {
        if (newPage < 1 || newPage > totalPages.value) return;
        page.value = newPage;
        loadData();
      };

      const handleImportSuccess = (result) => {
        loadData();
      };

      const handleViewFail = (task) => {
        currentTaskId.value = task.id;
        showHistory.value = false;
        showFailDetail.value = true;
      };

      onMounted(() => {
        loadData();
      });

      return {
        list,
        loading,
        page,
        pageSize,
        total,
        keyword,
        totalPages,
        visiblePages,
        showImport,
        showHistory,
        showFailDetail,
        currentTaskId,
        t,
        getStatusText,
        getStatusClass,
        handleSearch,
        handleRefresh,
        handlePageChange,
        handleImportSuccess,
        handleViewFail,
      };
    },
  }).mount('#app');
}

bootstrap();
