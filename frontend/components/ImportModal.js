import { api } from '../utils/api.js';

const { createApp, ref, computed, onMounted, defineComponent, h, reactive, onUnmounted } = Vue;

const Toast = {
  instances: [],
  show(message, type = 'info', duration = 3000) {
    const id = Date.now() + Math.random();
    const el = document.createElement('div');
    el.className = `status-toast ${type}`;
    el.id = `toast-${id}`;
    const icons = { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' };
    el.innerHTML = `<span class="status-toast-icon">${icons[type] || 'ℹ'}</span><span>${message}</span>`;
    document.body.appendChild(el);
    this.instances.push(id);
    setTimeout(() => {
      const target = document.getElementById(`toast-${id}`);
      if (target) {
        target.style.opacity = '0';
        target.style.transform = 'translateX(-50%) translateY(-20px)';
        target.style.transition = 'all 0.3s ease';
        setTimeout(() => target.remove(), 300);
      }
      this.instances = this.instances.filter((i) => i !== id);
    }, duration);
  },
};

export const ImportModal = defineComponent({
  name: 'ImportModal',
  props: {
    visible: { type: Boolean, default: false },
    t: { type: Function, required: true },
  },
  emits: ['close', 'success'],
  setup(props, { emit }) {
    const file = ref(null);
    const fileName = ref('');
    const uploading = ref(false);
    const processing = ref(false);
    const downloadingTemplate = ref(false);
    const step = ref(1);
    const taskId = ref(null);
    const result = reactive({
      total: 0,
      success_count: 0,
      fail_count: 0,
    });
    const errorMessage = ref('');
    const dragOver = ref(false);
    const validationError = ref('');
    const statusMessage = ref('');
    const statusType = ref('info');

    const resetState = () => {
      file.value = null;
      fileName.value = '';
      uploading.value = false;
      processing.value = false;
      downloadingTemplate.value = false;
      step.value = 1;
      taskId.value = null;
      result.total = 0;
      result.success_count = 0;
      result.fail_count = 0;
      errorMessage.value = '';
      validationError.value = '';
      statusMessage.value = '';
      statusType.value = 'info';
    };

    const showStatus = (message, type = 'info', duration = 3000) => {
      statusMessage.value = message;
      statusType.value = type;
      if (duration > 0) {
        setTimeout(() => {
          if (statusMessage.value === message) {
            statusMessage.value = '';
          }
        }, duration);
      }
    };

    const handleClose = () => {
      if (!processing.value && !uploading.value) {
        resetState();
        emit('close');
      }
    };

    const handleDownloadTemplate = async () => {
      if (downloadingTemplate.value) return;
      downloadingTemplate.value = true;
      showStatus(props.t('supplier.template_downloading'), 'info', 0);
      Toast.show(props.t('supplier.template_downloading'), 'info', 2000);
      try {
        const url = api.getSupplierImportTemplateURL('admin');
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showStatus(props.t('supplier.template_download_success'), 'success', 3000);
        Toast.show(props.t('supplier.template_download_success'), 'success', 3000);
      } catch (e) {
        showStatus(props.t('supplier.template_download_fail'), 'error', 5000);
        Toast.show(props.t('supplier.template_download_fail'), 'error', 3000);
      } finally {
        setTimeout(() => {
          downloadingTemplate.value = false;
        }, 1000);
      }
    };

    const validateFile = (f) => {
      if (!f) {
        return props.t('supplier.import_select_file');
      }
      const ext = f.name.split('.').pop().toLowerCase();
      if (ext !== 'csv') {
        return '仅支持CSV格式文件';
      }
      const maxSize = 10 * 1024 * 1024;
      if (f.size > maxSize) {
        return '文件大小不能超过10MB';
      }
      if (f.size === 0) {
        return '文件内容不能为空';
      }
      return '';
    };

    const handleFileSelect = (e) => {
      const selectedFile = e.target.files && e.target.files[0];
      if (selectedFile) {
        const err = validateFile(selectedFile);
        if (err) {
          validationError.value = err;
          Toast.show(err, 'error', 3000);
          e.target.value = '';
          return;
        }
        validationError.value = '';
        file.value = selectedFile;
        fileName.value = selectedFile.name;
      }
    };

    const handleDrop = (e) => {
      e.preventDefault();
      dragOver.value = false;
      const droppedFile = e.dataTransfer.files && e.dataTransfer.files[0];
      if (droppedFile) {
        const err = validateFile(droppedFile);
        if (err) {
          validationError.value = err;
          Toast.show(err, 'error', 3000);
          return;
        }
        validationError.value = '';
        file.value = droppedFile;
        fileName.value = droppedFile.name;
      }
    };

    const handleDragOver = (e) => {
      e.preventDefault();
      dragOver.value = true;
    };

    const handleDragLeave = () => {
      dragOver.value = false;
    };

    const removeFile = () => {
      file.value = null;
      fileName.value = '';
      validationError.value = '';
      const fileInput = document.getElementById('import-file-input');
      if (fileInput) fileInput.value = '';
    };

    const handleConfirmImport = async () => {
      if (!file.value) {
        validationError.value = props.t('supplier.import_select_file');
        Toast.show(props.t('supplier.import_select_file'), 'warning', 3000);
        return;
      }
      const err = validateFile(file.value);
      if (err) {
        validationError.value = err;
        Toast.show(err, 'error', 3000);
        return;
      }
      validationError.value = '';
      uploading.value = true;
      errorMessage.value = '';
      showStatus(props.t('supplier.import_processing'), 'info', 0);
      try {
        const uploadRes = await api.uploadSupplierImport(file.value, 'admin');
        if (uploadRes.code !== 0) {
          errorMessage.value = uploadRes.message || '文件上传失败';
          Toast.show(uploadRes.message || '文件上传失败', 'error', 3000);
          uploading.value = false;
          showStatus('', 'info');
          return;
        }
        taskId.value = uploadRes.data.task_id;
        uploading.value = false;
        step.value = 2;
        processing.value = true;
        const processRes = await api.processSupplierImport(taskId.value, 'admin');
        processing.value = false;
        if (processRes.code !== 0) {
          errorMessage.value = processRes.message || '导入处理失败';
          Toast.show(processRes.message || '导入处理失败', 'error', 3000);
          step.value = 1;
          showStatus('', 'info');
          return;
        }
        result.total = processRes.data.total || 0;
        result.success_count = processRes.data.success_count || 0;
        result.fail_count = processRes.data.fail_count || 0;
        step.value = 3;
        const successMsg = result.fail_count === 0
          ? props.t('supplier.import_success')
          : `导入完成，成功${result.success_count}条，失败${result.fail_count}条`;
        showStatus(successMsg, result.fail_count === 0 ? 'success' : 'warning', 5000);
        Toast.show(successMsg, result.fail_count === 0 ? 'success' : 'warning', 4000);
        emit('success', processRes.data);
      } catch (e) {
        uploading.value = false;
        processing.value = false;
        errorMessage.value = e.message || '导入过程发生错误';
        Toast.show(e.message || '导入过程发生错误', 'error', 3000);
        showStatus('', 'info');
      }
    };

    const successRate = computed(() => {
      if (result.total === 0) return 0;
      return Math.round((result.success_count / result.total) * 100);
    });

    return () => {
      if (!props.visible) return null;
      return h('div', { class: 'modal-mask', onClick: handleClose }, [
        h('div', { class: 'modal modal-lg', onClick: (e) => e.stopPropagation() }, [
          h('div', { class: 'modal-header' }, [
            h('span', { class: 'modal-title' }, props.t('supplier.import_title')),
            h('button', { class: 'modal-close', onClick: handleClose, disabled: processing.value || uploading.value }, '×'),
          ]),
          h('div', { class: 'modal-body' }, [
            statusMessage.value && h('div', { class: `modal-status-bar ${statusType.value}` }, [
              h('span', statusType.value === 'success' ? '✓ ' : statusType.value === 'error' ? '✕ ' : statusType.value === 'warning' ? '⚠ ' : 'ℹ '),
              h('span', statusMessage.value),
            ]),
            errorMessage.value && h('div', { class: 'alert alert-error' }, errorMessage.value),
            validationError.value && h('div', { class: 'alert alert-warning' }, validationError.value),
            step.value === 1 && h('div', [
              h('div', { class: 'alert alert-info' }, props.t('supplier.import_file_tip')),
              h('div', { style: 'margin-bottom: 16px; text-align: center;' }, [
                h('button', {
                  class: 'btn btn-primary',
                  onClick: handleDownloadTemplate,
                  disabled: downloadingTemplate.value,
                }, [
                  h('span', downloadingTemplate.value ? '⟳ ' : '↓ '),
                  h('span', downloadingTemplate.value ? props.t('supplier.template_downloading') : props.t('supplier.import_template')),
                ]),
              ]),
              !file.value ? h('div', {
                class: ['upload-area', dragOver.value ? 'dragover' : ''].join(' '),
                onDrop: handleDrop,
                onDragover: handleDragOver,
                onDragleave: handleDragLeave,
                onClick: () => document.getElementById('import-file-input').click(),
              }, [
                h('div', { class: 'upload-icon' }, '📄'),
                h('div', { class: 'upload-text' }, props.t('supplier.import_upload')),
                h('div', { class: 'upload-hint' }, props.t('supplier.import_file_tip')),
                h('input', {
                  id: 'import-file-input',
                  type: 'file',
                  accept: '.csv',
                  style: 'display: none',
                  onChange: handleFileSelect,
                }),
              ]) : h('div', { class: 'upload-file-info' }, [
                h('span', { class: 'upload-file-name' }, '📄 ' + fileName.value),
                h('span', { class: 'upload-file-remove', onClick: removeFile }, '✕ 删除'),
              ]),
            ]),
            step.value === 2 && h('div', { style: 'padding: 40px 0; text-align: center;' }, [
              h('div', { class: 'loading-spinner', style: 'width: 40px; height: 40px; margin: 0 auto 20px;' }),
              h('div', { class: 'loading-text', style: 'justify-content: center; font-size: 16px;' }, props.t('supplier.import_processing')),
            ]),
            step.value === 3 && h('div', [
              h('div', { style: 'text-align: center; margin-bottom: 16px;' }, [
                h('span', { style: 'font-size: 32px;' }, result.fail_count === 0 ? '🎉' : '⚠️'),
              ]),
              h('div', { class: 'info-row' }, [
                h('div', { class: 'info-item' }, [
                  h('span', { class: 'info-label' }, '任务ID:'),
                  h('span', { class: 'info-value' }, String(taskId.value)),
                ]),
                h('div', { class: 'info-item' }, [
                  h('span', { class: 'info-label' }, '文件:'),
                  h('span', { class: 'info-value' }, fileName.value),
                ]),
              ]),
              h('div', { class: 'result-summary' }, [
                h('div', { class: 'result-item' }, [
                  h('div', { class: 'result-number' }, result.total),
                  h('div', { class: 'result-label' }, props.t('supplier.import_total')),
                ]),
                h('div', { class: 'result-item' }, [
                  h('div', { class: 'result-number success' }, result.success_count),
                  h('div', { class: 'result-label' }, props.t('supplier.import_success_count')),
                ]),
                h('div', { class: 'result-item' }, [
                  h('div', { class: 'result-number failed' }, result.fail_count),
                  h('div', { class: 'result-label' }, props.t('supplier.import_fail_count')),
                ]),
              ]),
              h('div', { class: 'progress-container' }, [
                h('div', { class: 'progress-label' }, [
                  h('span', '导入成功率'),
                  h('span', successRate.value + '%'),
                ]),
                h('div', { class: 'progress-bar' }, [
                  h('div', {
                    class: 'progress-fill',
                    style: { width: successRate.value + '%' },
                  }),
                ]),
              ]),
            ]),
          ]),
          h('div', { class: 'modal-footer' }, [
            step.value < 3 && h('button', {
              class: 'btn',
              onClick: handleClose,
              disabled: processing.value || uploading.value,
            }, props.t('common.cancel')),
            step.value === 1 && h('button', {
              class: 'btn btn-primary',
              onClick: handleConfirmImport,
              disabled: !file.value || uploading.value || processing.value,
            }, uploading.value ? '上传中...' : props.t('supplier.import_confirm')),
            step.value === 3 && h('button', {
              class: 'btn btn-primary',
              onClick: handleClose,
            }, props.t('supplier.close')),
          ]),
        ]),
      ]);
    };
  },
});
