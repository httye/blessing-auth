# Blessing Auth

一个类似 [Blessing Skin](https://github.com/bs-community/blessing-skin-server) 的 Minecraft 认证系统，基于 Laravel 11，兼容 [authlib-injector](https://github.com/yushijinhun/authlib-injector) 外置登录。

## 功能

- 用户注册 / 登录 / 账号管理（第一个注册的用户自动成为**超级管理员**）
- 邮箱验证码注册（后台可开关；SMTP 后台配置；60s 冷却 + IP 限流 + 防爆破）
- 注册邮箱后缀白/黑名单限制（后台配置）
- 公告系统：发布/草稿/置顶，Markdown 渲染（原始 HTML 转义防 XSS），首页展示最新公告
- 多级权限：超级管理员（站点设置/插件/任命管理员）> 管理员（用户/角色/材质/公告）> 普通用户；严格层级校验
- 封禁系统：原因 + 期限（临时/永久），到期自动解封，登录与游戏内认证同步拦截
- 称号系统：彩色称号、积分购买/管理员授予、佩戴展示
- 备份：一键/定时备份数据库 + 材质 + 签名密钥（`php artisan site:backup`），后台下载管理
- OAuth 授权服务器：外部应用（论坛/商店）可「用本站账号登录」，后台管理接入应用
- 第三方登录：微软 / GitHub / Gitee，后台自选启用并配置密钥；支持绑定/解绑与 OAuth 一键注册
- 角色（Player）管理：创建（消耗积分）、改名、删除，UUID 与离线模式算法一致
- 皮肤库：PNG 皮肤/披风上传（尺寸校验、SHA-256 去重存储）、公开/私有、应用到角色
- 每日签到获取积分
- 积分系统：注册奖励、签到、创建角色消耗，全流水记录（`score_logs`），并发安全（行级锁），用户可查明细账单
- 完整 Yggdrasil API：`authenticate` / `refresh` / `validate` / `invalidate` / `signout` / `join` / `hasJoined` / `profile` / 批量查询 / 材质分发
- RSA-4096 材质签名（SHA1withRSA，客户端要求）
- 管理面板：用户（封禁/积分/重置密码/删除）、角色、材质管理、站点设置（后台可改所有站点参数）
- 插件系统：事件/过滤器钩子、插件路由、菜单与视图扩展，后台一键启停（见 [docs/PLUGIN.md](docs/PLUGIN.md)）
- AuthMe 适配：内置 `authme-sync` 插件，离线模式 + AuthMe 服务器可直接用网站密码 `/login`（见 [plugins/authme-sync/README.md](plugins/authme-sync/README.md)）
- 完整 API 文档：[docs/API.md](docs/API.md)

## 环境要求

- PHP >= 8.2（启用 `openssl`、`gd`、`pdo_sqlite` 或 `pdo_mysql` 扩展）
- Composer

## 部署

```bash
composer install --no-dev

cp .env.example .env
php artisan key:generate

# 生成材质签名密钥（必须）
php artisan ygg:generate-keys

# 建表（默认 SQLite；用 MySQL 请先改 .env）
php artisan migrate

# 开发环境运行
php artisan serve
```

生产环境用 Nginx/Caddy 指向 `public/` 目录即可，注意：

- `storage/` 与 `database/` 目录需 Web 进程可写
- `.env` 中 `APP_URL` 必须填公网可访问的地址（材质 URL 与皮肤域名白名单基于它生成）
- 建议启用 HTTPS

## 启动器 / 服务端配置

认证服务器地址（添加到 HMCL、PCL2 等启动器）：

```
https://你的域名/api/yggdrasil
```

Minecraft 服务端（Paper/Spigot 等）通过 authlib-injector 启动：

```bash
java -javaagent:authlib-injector.jar=https://你的域名/api/yggdrasil -jar server.jar
```

游戏内使用**本站邮箱 + 密码**登录（也支持直接用角色名登录，`feature.non_email_login`）。

## 第三方登录配置

管理面板 → 站点设置 → 第三方登录（OAuth）：

1. 到对应平台创建 OAuth 应用：
   - 微软：[Azure Portal](https://portal.azure.com) → 应用注册（受支持账户类型选"个人 Microsoft 账户"）
   - GitHub：Settings → Developer settings → OAuth Apps
   - Gitee：设置 → 第三方应用
2. 回调地址填 `https://你的域名/auth/oauth/<provider>/callback`（provider 为 `microsoft` / `github` / `gitee`）
3. 把 Client ID / Client Secret 填入后台并勾选启用

说明：

- 未绑定过的第三方账号首次登录会自动注册（需开放注册）；密码为随机值，用户应在「账号设置」中设置密码后才能进游戏
- 第三方邮箱与已有账号重复时不会自动合并，需先密码登录再手动绑定
- OAuth 仅用于**网页**登录；游戏内（Yggdrasil）始终使用邮箱/角色名 + 密码

## 自测清单

部署完成后可用 curl 验证：

```bash
BASE=http://localhost:8000/api/yggdrasil

# 1. API 元数据（应返回 meta / skinDomains / signaturePublickey）
curl -s $BASE/

# 2. 登录（先在网页注册账号并创建角色）
curl -s -X POST $BASE/authserver/authenticate \
  -H 'Content-Type: application/json' \
  -d '{"username":"you@example.com","password":"yourpassword","requestUser":true}'
# 应返回 accessToken / availableProfiles

# 3. 验证令牌（有效返回 204）
curl -s -o /dev/null -w '%{http_code}' -X POST $BASE/authserver/validate \
  -H 'Content-Type: application/json' \
  -d '{"accessToken":"<上一步的accessToken>"}'

# 4. 错误密码（应返回 403 + ForbiddenOperationException）
curl -s -X POST $BASE/authserver/authenticate \
  -H 'Content-Type: application/json' \
  -d '{"username":"you@example.com","password":"wrong"}'

# 5. 角色查询（UUID 为角色 UUID，去掉连字符也可以）
curl -s "$BASE/sessionserver/session/minecraft/profile/<uuid>?unsigned=false"

# 6. 批量按名查询
curl -s -X POST $BASE/api/profiles/minecraft \
  -H 'Content-Type: application/json' \
  -d '["YourPlayerName"]'
```

## 目录结构

```
app/
├── Console/Commands/GenerateKeyPair.php   # RSA 密钥生成命令
├── Exceptions/YggdrasilException.php      # Yggdrasil 统一错误格式
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                          # 注册、登录
│   │   ├── Admin/AdminController.php      # 管理面板
│   │   ├── Yggdrasil/                     # Yggdrasil API 四组端点
│   │   ├── PlayerController.php           # 角色管理
│   │   ├── TextureController.php          # 皮肤库与材质分发
│   │   └── UserController.php             # 用户中心
│   └── Middleware/
│       ├── CheckAdmin.php
│       └── YggdrasilHeaders.php           # X-Authlib-Injector-API-Location
├── Models/                                # User / Player / Texture / YggToken
└── Services/
    ├── YggdrasilService.php               # 令牌签发 / 刷新 / 校验
    ├── ProfileSerializer.php              # 角色序列化 + RSA 签名
    └── TextureStorage.php                 # 材质校验与去重存储
```

## 令牌策略

- `accessToken` 有效期 72 小时（可 `validate`），过期后进入暂存期仅可 `refresh`
- 暂存期至 360 小时，之后彻底失效
- 每用户最多 10 个令牌，超出淘汰最旧
- 修改密码 / 被封禁 / 管理员重置密码时吊销全部令牌

以上参数均可在**管理面板 → 站点设置**中修改（存于数据库 `options` 表，优先于 `.env` 默认值，保存即生效，无需重启）。`.env` 中的对应变量仅作为初始默认值。
