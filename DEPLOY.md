# 供应商注册KYB部署文档

## 一、项目概述

本项目是在线课程教务系统的供应商资质认证（KYB - Know Your Business）模块，支持供应商企业注册、资料提交、证照上传、审核管理等全流程功能。

### 功能模块
- 企业基本信息填写与验证
- 资质证照上传（营业执照、法人身份证正反面、其他证照）
- 三步式注册流程引导
- 管理员审核（通过/拒绝）
- 多语言支持（zh-CN / en-US / ja-JP）
- 多币种支持

---

## 二、系统要求

### 后端环境
- PHP >= 7.4
- MySQL >= 5.7 或 MariaDB >= 10.3
- PHP 扩展：pdo_mysql、mbstring、fileinfo、gd

### 前端环境
- 现代浏览器（Chrome、Firefox、Safari、Edge）
- Vue 3（CDN 引入，无需构建）

---

## 三、环境变量配置

所有环境变量均有默认值，可根据部署环境按需配置。

### 3.1 数据库配置

| 环境变量 | 说明 | 默认值 | 示例 |
|---------|------|--------|------|
| `DB_HOST` | 数据库主机地址 | `localhost` | `127.0.0.1` |
| `DB_NAME` | 数据库名称 | `course_edu` | `course_prod` |
| `DB_USER` | 数据库用户名 | `root` | `db_user` |
| `DB_PASS` | 数据库密码 | （空） | `your_password` |

### 3.2 文件上传配置

| 环境变量 | 说明 | 默认值 | 示例 |
|---------|------|--------|------|
| `UPLOAD_MAX_SIZE` | 单文件最大大小（字节） | `10485760`（10MB） | `20971520`（20MB） |
| `UPLOAD_DIR` | 上传文件存储目录（绝对路径） | `backend/uploads/` | `/data/uploads/kyb/` |
| `UPLOAD_BASE_URL` | 上传文件访问基础路径 | `backend/uploads/` | `/uploads/kyb/` |
| `UPLOAD_ALLOWED_MIME_TYPES` | 允许的 MIME 类型（JSON） | `{"image/jpeg":"jpg","image/png":"png","image/gif":"gif","application/pdf":"pdf"}` | - |
| `UPLOAD_ALLOWED_EXTENSIONS` | 允许的文件扩展名（JSON数组） | `["jpg","jpeg","png","gif","pdf"]` | - |
| `UPLOAD_ALLOWED_TYPES` | 允许的上传类型分类（JSON数组） | `["business_license","legal_person_id_front","legal_person_id_back","other"]` | - |

### 3.3 KYB 业务配置

| 环境变量 | 说明 | 默认值 | 示例 |
|---------|------|--------|------|
| `KYB_REQUIRED_FIELDS` | 必填企业资料字段（JSON数组） | `["company_name","unified_social_credit_code","legal_person","contact_name","contact_phone"]` | - |
| `KYB_REQUIRED_CERTS` | 必填证照字段（JSON数组） | `["business_license","legal_person_id_front","legal_person_id_back"]` | - |

### 3.4 配置方式示例

#### Nginx + PHP-FPM
在 nginx 配置文件中设置：

```nginx
location ~ \.php$ {
    fastcgi_param DB_HOST localhost;
    fastcgi_param DB_NAME course_edu;
    fastcgi_param DB_USER root;
    fastcgi_param DB_PASS your_password;
    fastcgi_param UPLOAD_MAX_SIZE 10485760;
    fastcgi_param UPLOAD_DIR /var/www/course/backend/uploads/;
    fastcgi_param UPLOAD_BASE_URL /uploads/;
    # ... 其他配置
}
```

#### Apache (.htaccess)
```apache
SetEnv DB_HOST localhost
SetEnv DB_NAME course_edu
SetEnv DB_USER root
SetEnv DB_PASS your_password
```

#### 命令行运行
```bash
export DB_HOST=localhost
export DB_NAME=course_edu
export DB_USER=root
export DB_PASS=your_password
export UPLOAD_MAX_SIZE=10485760
php -S localhost:8000 -t .
```

---

## 四、部署步骤

### 4.1 数据库初始化

1. 创建数据库
```sql
CREATE DATABASE course_edu DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. 执行 SQL 脚本
```bash
mysql -u root -p course_edu < sql/supplier_kyb.sql
```

3. 验证表结构
```sql
SHOW TABLES LIKE 'supplier_kyb';
DESC supplier_kyb;
```

### 4.2 后端部署

1. 上传代码到服务器
2. 配置 Web 服务器（Nginx/Apache）指向项目根目录
3. 设置上传目录权限
```bash
mkdir -p backend/uploads
chmod 755 backend/uploads
chown www-data:www-data backend/uploads
```

4. 配置环境变量（参见 3. 环境变量配置）
5. 验证 PHP 环境
```bash
php -v
php -m | grep pdo_mysql
```

### 4.3 前端部署

前端为纯静态页面，可直接部署：

1. 将 `frontend/` 目录下的文件部署到 Web 服务器
2. 确保 `API_BASE` 路径正确（默认 `../backend/api`）
3. 如部署到不同域名，需配置 CORS

---

## 五、验收命令

### 5.1 环境检查

```bash
# 检查 PHP 版本和扩展
php -v
php -m | grep -E "pdo_mysql|mbstring|fileinfo|gd"

# 检查 MySQL 连接
mysql -h localhost -u root -p -e "SELECT VERSION();"

# 检查上传目录权限
ls -la backend/uploads/
test -w backend/uploads/ && echo "上传目录可写" || echo "上传目录不可写"
```

### 5.2 接口验证

使用 curl 进行接口验收测试：

#### 1. 注册接口测试
```bash
# 测试提交企业注册信息
curl -X POST http://localhost/backend/api/register.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token" \
  -d '{
    "company_name": "测试科技有限公司",
    "unified_social_credit_code": "91110108MA01ABCD23",
    "legal_person": "张法定",
    "contact_name": "李联系",
    "contact_phone": "13800138001",
    "contact_email": "contact@example.com",
    "registered_address_province": "北京市",
    "registered_address_city": "北京市",
    "registered_address_district": "海淀区",
    "registered_address_detail": "中关村大街1号",
    "business_license": "backend/uploads/business_license_test.jpg",
    "legal_person_id_front": "backend/uploads/id_front_test.jpg",
    "legal_person_id_back": "backend/uploads/id_back_test.jpg"
  }' | jq .
```

**预期结果**：
- `code` 为 200
- `data.status` 为 0（待审核）
- `data.is_new` 为 true

#### 2. 列表查询测试
```bash
curl "http://localhost/backend/api/list.php?page=1&pageSize=10" \
  -H "Authorization: Bearer your_token" | jq .
```

**预期结果**：
- `code` 为 200
- `data.list` 为数组
- `data.pagination` 包含分页信息

#### 3. 详情查询测试
```bash
curl "http://localhost/backend/api/detail.php?id=1" \
  -H "Authorization: Bearer your_token" | jq .
```

**预期结果**：
- `code` 为 200
- `data` 包含完整的企业信息和证照信息

#### 4. 审核接口测试（管理员）
```bash
# 审核通过
curl -X POST http://localhost/backend/api/review.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer admin_token" \
  -d '{"id": 1, "status": 1, "remark": "资料齐全，审核通过"}' | jq .

# 审核拒绝
curl -X POST http://localhost/backend/api/review.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer admin_token" \
  -d '{"id": 2, "status": 2, "remark": "营业执照不清晰，请重新上传"}' | jq .
```

**预期结果**：
- `code` 为 200
- `data.status` 为对应审核状态（1 通过 / 2 拒绝）

#### 5. 文件上传测试
```bash
# 上传营业执照
curl -X POST http://localhost/backend/api/upload.php \
  -H "Authorization: Bearer your_token" \
  -F "type=business_license" \
  -F "file=@/path/to/license.jpg" | jq .
```

**预期结果**：
- `code` 为 200
- `data.url` 包含文件访问路径
- `data.size` 为文件大小

### 5.3 前端页面验证

启动本地开发服务器进行验证：

```bash
# 方式一：PHP 内置服务器（推荐，前后端一起测试）
php -S localhost:8000 -t .

# 方式二：仅前端静态页面（需配合后端）
cd frontend && python3 -m http.server 8080
```

访问地址：
- 注册页面：http://localhost:8000/frontend/index.html
- 列表页面：http://localhost:8000/frontend/list.html
- 详情页面：http://localhost:8000/frontend/detail.html?id=1

### 5.4 单元测试

运行 KYB 模块单元测试：

```bash
# 打开测试页面
open http://localhost:8000/frontend/tests/kyb_test.html

# 或使用命令行工具（需安装浏览器驱动）
# 可集成到 CI/CD 流程
```

测试套件包含：
- Suite 1: 企业资料表单验证（8 用例）
- Suite 2: 证照上传功能验证
- Suite 3: 审核流程状态闭环
- Suite 4: 列表查询与筛选
- Suite 5: 权限控制验证

---

## 六、企业资料字段说明

### 6.1 企业基本信息

| 字段名 | 类型 | 必填 | 说明 |
|-------|------|------|------|
| `company_name` | varchar(200) | 是 | 企业全称 |
| `unified_social_credit_code` | varchar(50) | 是 | 统一社会信用代码（18位） |
| `legal_person` | varchar(50) | 是 | 法定代表人姓名 |
| `legal_person_id_card` | varchar(30) | 否 | 法人身份证号 |
| `registered_capital` | varchar(50) | 否 | 注册资本 |
| `establish_date` | date | 否 | 成立日期 |
| `business_scope` | text | 否 | 经营范围 |

### 6.2 注册地址

| 字段名 | 类型 | 必填 | 说明 |
|-------|------|------|------|
| `registered_address_province` | varchar(20) | 否 | 省份 |
| `registered_address_city` | varchar(20) | 否 | 城市 |
| `registered_address_district` | varchar(20) | 否 | 区县 |
| `registered_address_detail` | varchar(200) | 否 | 详细地址 |

### 6.3 联系信息

| 字段名 | 类型 | 必填 | 说明 |
|-------|------|------|------|
| `contact_name` | varchar(50) | 是 | 联系人姓名 |
| `contact_phone` | varchar(20) | 是 | 联系电话（手机号） |
| `contact_email` | varchar(100) | 否 | 联系邮箱 |

---

## 七、证照上传说明

### 7.1 必填证照

| 证照类型 | 字段名 | 说明 |
|---------|--------|------|
| 营业执照 | `business_license` | 企业营业执照正本 |
| 法人身份证正面 | `legal_person_id_front` | 身份证人像面 |
| 法人身份证反面 | `legal_person_id_back` | 身份证国徽面 |

### 7.2 其他证照（选填）

`other_certificates` 字段存储 JSON 数组，每个元素包含：
- `name`: 证照名称
- `url`: 证照文件路径
- `original_name`: 原始文件名

### 7.3 上传限制

- 支持格式：JPG、PNG、GIF、PDF
- 单文件大小：默认 10MB（可通过 `UPLOAD_MAX_SIZE` 配置）
- 存储方式：本地文件系统
- 文件命名：`{type}_{YYYYMMDDHHMMSS}_{uniqid}.{ext}`

---

## 八、审核状态说明

| 状态值 | 状态名 | 说明 |
|-------|--------|------|
| 0 | 待审核 | 提交后初始状态 |
| 1 | 审核通过 | 管理员审核通过 |
| 2 | 审核拒绝 | 管理员审核拒绝 |

状态流转：
```
待审核(0) → 审核通过(1)
待审核(0) → 审核拒绝(2) → 修改后重提交 → 待审核(0)
```

> 注意：已通过审核的记录不可修改，如需修改需联系管理员。

---

## 九、常见问题

### Q1: 上传文件时提示"上传目录不可写"
A: 检查 `backend/uploads/` 目录权限，确保 Web 服务器用户有写入权限。

### Q2: 统一社会信用代码验证失败
A: 确保输入的是 18 位正确格式的统一社会信用代码，支持大写字母和数字。

### Q3: 手机号格式验证失败
A: 目前仅支持中国大陆手机号（1 开头的 11 位数字）。

### Q4: 审核通过后无法修改资料
A: 已通过审核的企业资料锁定，如需修改请联系管理员重新审核。

### Q5: 如何配置跨域访问
A: 在 `backend/config/common.php` 中已设置 `Access-Control-Allow-Origin: *`，如需限制域名请修改对应 header。

### Q6: 上传文件大小限制如何修改
A: 设置环境变量 `UPLOAD_MAX_SIZE`（单位：字节），同时需确保 PHP 配置 `upload_max_filesize` 和 `post_max_size` 足够大。

---

## 十、目录结构

```
.
├── backend/                    # 后端代码
│   ├── api/                    # API 接口
│   │   ├── register.php        # 注册/提交接口
│   │   ├── list.php            # 列表查询接口
│   │   ├── detail.php          # 详情查询接口
│   │   ├── review.php          # 审核接口
│   │   └── upload.php          # 文件上传接口
│   ├── classes/                # 工具类
│   ├── config/                 # 配置文件
│   │   └── common.php          # 公共配置和函数
│   ├── langs/                  # 多语言包
│   └── uploads/                # 上传文件目录（需可写）
├── frontend/                   # 前端代码
│   ├── index.html              # 注册页面
│   ├── list.html               # 列表页面
│   ├── detail.html             # 详情页面
│   ├── css/                    # 样式文件
│   ├── js/                     # JavaScript
│   ├── locales/                # 前端多语言
│   ├── utils/                  # 工具函数
│   └── tests/                  # 测试文件
├── sql/                        # 数据库脚本
│   └── supplier_kyb.sql        # KYB 表结构
├── DEPLOY.md                   # 本文档
└── test.html                   # 综合测试页面
```
