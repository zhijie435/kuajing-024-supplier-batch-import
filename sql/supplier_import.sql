-- 供应商批量导入相关表结构
-- 导入任务表
CREATE TABLE IF NOT EXISTS `supplier_import_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL COMMENT '服务器存储路径',
  `total_rows` int(11) NOT NULL DEFAULT 0 COMMENT '总行数',
  `success_count` int(11) NOT NULL DEFAULT 0 COMMENT '成功数量',
  `fail_count` int(11) NOT NULL DEFAULT 0 COMMENT '失败数量',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待处理 1处理中 2已完成 3失败',
  `operator` varchar(100) NOT NULL DEFAULT 'system' COMMENT '操作人',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商导入任务表';

-- 导入行记录表
CREATE TABLE IF NOT EXISTS `supplier_import_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT '任务ID',
  `row_number` int(11) NOT NULL COMMENT 'Excel行号',
  `row_data` text NOT NULL COMMENT '行原始数据(JSON)',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待处理 1成功 2失败',
  `supplier_id` int(11) DEFAULT NULL COMMENT '关联的供应商ID',
  `error_message` varchar(500) DEFAULT NULL COMMENT '错误信息',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商导入行记录';

-- 导入失败明细表
CREATE TABLE IF NOT EXISTS `supplier_import_fail_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT '任务ID',
  `row_number` int(11) NOT NULL COMMENT 'Excel行号',
  `row_data` text NOT NULL COMMENT '行原始数据(JSON)',
  `error_message` varchar(1000) NOT NULL COMMENT '错误信息',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商导入失败明细';

-- 操作记录表
CREATE TABLE IF NOT EXISTS `supplier_operation_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL COMMENT '关联任务ID',
  `operator` varchar(100) NOT NULL DEFAULT 'system' COMMENT '操作人',
  `operation_type` varchar(50) NOT NULL COMMENT '操作类型: download_template, upload_file, process_import, view_fail, export_fail, refresh_history',
  `operation_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '操作状态: 0失败 1成功',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注/详情',
  `ip_address` varchar(50) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_operator` (`operator`),
  KEY `idx_operation_type` (`operation_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商操作记录表';
