# Blessing Auth API 文档

本文档描述 Blessing Auth 提供的全部 HTTP API，含 Yggdrasil 认证协议（兼容 [authlib-injector](https://github.com/yushijinhun/authlib-injector)）与材质分发。

- 基础地址：`https://<你的域名>`，下文记为 `{BASE}`
- Yggdrasil 根：`{BASE}/api/yggdrasil`，下文记为 `{YGG}`
- 除特别说明外，请求与响应均为 `application/json; charset=utf-8`
- 所有 Yggdrasil 响应都带 `X-Authlib-Injector-API-Location` 头，指向 `{YGG}`

## 错误格式

所有 Yggdrasil 错误响应统一为：

```json
{
  "error": "ForbiddenOperationException",
  "errorMessage": "Invalid credentials. Invalid username or password."
}
```

| HTTP 状态 | error | 场景 |
|---|---|---|
| 403 | ForbiddenOperationException | 密码错误、令牌无效、账号被封禁 |
| 400 | IllegalArgumentException | 参数缺失/非法（如 serverId 缺失、批量查询超 5 个） |

## UUID 格式约定

- 协议中的角色 UUID 一律为**无连字符小写十六进制**（32 位），如 `df24818be0e64d0b96faacba0e97e1b4`
- `profile/{uuid}` 端点同时接受带/不带连字符的形式
- 角色 UUID 生成算法与离线模式一致：`md5("OfflinePlayer:" + name)` 设置 UUIDv3 版本位

---

## 1. API 元数据

### `GET {YGG}/`

authlib-injector 首次连接时调用，返回服务器信息。

**响应 200**

```json
{
  "meta": {
    "serverName": "Blessing Auth",
    "implementationName": "blessing-auth",
    "implementationVersion": "1.0.0",
    "links": {
      "homepage": "https://example.com",
      "register": "https://example.com/auth/register"
    },
    "feature.non_email_login": true
  },
  "skinDomains": ["example.com"],
  "signaturePublickey": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
}
```

| 字段 | 说明 |
|---|---|
| `feature.non_email_login` | `true`：支持直接用角色名登录 |
| `skinDomains` | 材质域名白名单；以 `.` 开头表示匹配所有子域名 |
| `signaturePublickey` | RSA-4096 公钥（PEM），客户端用它验证材质签名 |

---

## 2. 认证服务器（authserver）

### `POST {YGG}/authserver/authenticate`

登录，签发令牌。

**请求体**

```json
{
  "username": "user@example.com",
  "password": "secret",
  "clientToken": "客户端生成的随机串（可选）",
  "requestUser": true,
  "agent": { "name": "Minecraft", "version": 1 }
}
```

- `username`：邮箱，或角色名（`feature.non_email_login`）
- `clientToken`：可选；不传则服务端生成 UUID
- `requestUser`：可选；为 `true` 时响应附带 `user` 对象
- `agent`：可选，忽略

**响应 200**

```json
{
  "accessToken": "32位hex",
  "clientToken": "与请求一致或服务端生成",
  "availableProfiles": [
    { "id": "df24818be0e64d0b96faacba0e97e1b4", "name": "Steve" }
  ],
  "selectedProfile": { "id": "df24818be0e64d0b96faacba0e97e1b4", "name": "Steve" },
  "user": {
    "id": "用户标识",
    "properties": [ { "name": "preferredLanguage", "value": "zh_CN" } ]
  }
}
```

`selectedProfile` 出现条件：用角色名登录，或该账号仅有一个角色。

**失败 403** — 用户不存在 / 密码错误 / 被封禁。

**令牌生命周期**

| 阶段 | 时间（默认，后台可改） | validate | refresh | 进服 |
|---|---|---|---|---|
| 有效 | 0 – 72h | 204 | ✔ | ✔ |
| 暂存 | 72h – 360h | 403 | ✔ | ✘ |
| 失效 | > 360h | 403 | 403 | ✘ |

每用户最多 10 个令牌，超出时淘汰最旧。修改密码、被封禁、管理员重置密码会吊销全部令牌。

### `POST {YGG}/authserver/refresh`

刷新令牌。旧令牌立即失效，新令牌继承 `clientToken`。

**请求体**

```json
{
  "accessToken": "旧令牌",
  "clientToken": "可选；传了则必须与旧令牌匹配",
  "requestUser": false,
  "selectedProfile": { "id": "df24818be0e64d0b96faacba0e97e1b4", "name": "Steve" }
}
```

- `selectedProfile`：可选，为尚未绑定角色的令牌指定角色
  - 令牌已绑定角色时再传 → 400 IllegalArgumentException
  - 指定不属于该用户的角色 → 403

**响应 200**：结构同 authenticate（无 `availableProfiles`）。

### `POST {YGG}/authserver/validate`

**请求体**：`{ "accessToken": "...", "clientToken": "可选" }`

**响应**：有效 `204 No Content`；无效/过期 `403`。

### `POST {YGG}/authserver/invalidate`

吊销单个令牌。**请求体**：`{ "accessToken": "..." }`

**响应**：始终 `204`（无论令牌是否存在）。

### `POST {YGG}/authserver/signout`

吊销该用户的**全部**令牌，用密码而非令牌鉴权。

**请求体**：`{ "username": "...", "password": "..." }`

**响应**：成功 `204`；密码错误 `403`。

---

## 3. 会话服务器（sessionserver）

Minecraft 多人游戏进服握手：

```
客户端 join(serverId) → 服务端 hasJoined(username, serverId) → 返回签名档案
```

### `POST {YGG}/sessionserver/session/minecraft/join`

游戏客户端在连接服务器时调用。

**请求体**

```json
{
  "accessToken": "...",
  "selectedProfile": "df24818be0e64d0b96faacba0e97e1b4",
  "serverId": "服务器握手生成的标识"
}
```

**响应**：成功 `204`。记录 30 秒内有效。

**失败 403**：令牌无效 / 未绑定角色 / `selectedProfile` 与令牌绑定的角色不符。
**失败 400**：`serverId` 缺失。

### `GET {YGG}/sessionserver/session/minecraft/hasJoined?username={name}&serverId={id}&ip={ip}`

游戏服务端校验客户端是否完成 join。`ip` 可选，传了则额外校验客户端 IP 一致。

**响应 200**：完整签名档案（见下方"角色档案结构"，properties 必带 `signature`）。
**响应 204**：校验失败（无记录 / 用户名不符 / IP 不符 / 超时）。

### `GET {YGG}/sessionserver/session/minecraft/profile/{uuid}?unsigned={true|false}`

查询角色档案。`unsigned` 默认 `true`；`unsigned=false` 时附带 RSA 签名。

**响应 200**：角色档案。**响应 204**：角色不存在。

**角色档案结构**

```json
{
  "id": "df24818be0e64d0b96faacba0e97e1b4",
  "name": "Steve",
  "properties": [
    {
      "name": "textures",
      "value": "<base64>",
      "signature": "<base64，unsigned=false 时出现>"
    }
  ]
}
```

`value` base64 解码后：

```json
{
  "timestamp": 1753500000000,
  "profileId": "df24818be0e64d0b96faacba0e97e1b4",
  "profileName": "Steve",
  "textures": {
    "SKIN": { "url": "https://example.com/textures/<sha256>" },
    "CAPE": { "url": "https://example.com/textures/<sha256>" }
  }
}
```

- 无皮肤/披风时对应键不出现
- 签名算法：SHA1withRSA，对 `value` 的 base64 字符串签名
- 纤细手臂（Alex）模型时 `SKIN` 带 `"metadata": {"model": "slim"}`（可由插件注入）

---

## 4. 角色查询

### `POST {YGG}/api/profiles/minecraft`

按名批量查询（服务端白名单等场景用）。

**请求体**：`["Steve", "Alex", "Notch"]`（字符串数组，≤5 个）

**响应 200**

```json
[
  { "id": "df24818be0e64d0b96faacba0e97e1b4", "name": "Steve" },
  { "id": "…", "name": "Alex" }
]
```

- 不存在的名字直接省略
- 超过 5 个 → 400 IllegalArgumentException

---

## 5. OAuth 授权服务器（外部应用接入）

让论坛、商店等外部应用「用本站账号登录」。授权码模式，管理员在后台「接入应用」创建 client。

### 流程

```
外部应用 → GET {BASE}/oauth/authorize?client_id=&redirect_uri=&response_type=code&state=
         ← 用户登录并同意 → 302 redirect_uri?code=...&state=...
外部应用 → POST {BASE}/oauth/token（code 换 access_token）
外部应用 → GET {BASE}/oauth/userinfo（Bearer token 取用户资料）
```

### `GET /oauth/authorize`

参数：`client_id`、`redirect_uri`（必须与登记完全一致）、`response_type=code`、`state`（建议）。
用户未登录会先跳登录页；已授权过的应用直接发码跳回。授权码 5 分钟有效，一次性。

### `POST /oauth/token`

表单参数：`client_id`、`client_secret`、`code`、`redirect_uri`。

**响应 200**

```json
{
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 2592000,
  "scope": "profile"
}
```

错误：`invalid_client`（401，密钥错误）、`invalid_grant`（400，code 无效/过期/参数不匹配）。

### `GET /oauth/userinfo`

请求头：`Authorization: Bearer <access_token>`

**响应 200**

```json
{
  "id": 1,
  "email": "user@example.com",
  "nickname": "Steve",
  "score": 1000,
  "is_admin": false,
  "title": { "name": "元老", "color": "#e0a800" },
  "players": [ { "name": "Steve", "uuid": "..." } ],
  "registered_at": "2026-07-26T12:00:00+08:00"
}
```

令牌 30 天有效；应用被停用或用户被封禁时立即失效（401 invalid_token）。

## 6. 材质分发

### `GET {BASE}/textures/{hash}`

- `hash`：64 位 sha256 十六进制
- **响应 200**：`image/png`，`Cache-Control: public, max-age=31536000, immutable`
- **响应 404**：hash 格式非法或文件不存在

材质按内容寻址，同一 URL 内容永不变化，可放心永久缓存 / 挂 CDN（记得把 CDN 域名加进后台"材质域名白名单"）。

---

## 7. 接入示例

**启动器（HMCL / PCL2 等）**：添加认证服务器 `{YGG}`，用邮箱（或角色名）+ 密码登录。

**游戏服务端**：

```bash
java -javaagent:authlib-injector.jar={YGG} -jar server.jar
```

**curl 冒烟测试**：见项目 README「自测清单」一节。
