# AuthMe 同步插件

将本站角色与密码同步到 [AuthMeReloaded](https://github.com/AuthMe/AuthMeReloaded) 数据表，玩家在游戏内 `/login <网站密码>` 即可，无需在服务器里二次注册。

适用场景：**离线模式（offline-mode）+ AuthMe** 的服务器。若你的服务器使用 authlib-injector 外置登录，则不需要本插件（Yggdrasil API 已负责认证）。

## 原理

- 本站密码使用 bcrypt 存储，AuthMe 支持 `BCRYPT` 哈希算法
- 插件把「角色名 + 用户密码 bcrypt 哈希」写入 `authme` 表（PHP 的 `$2y$` 前缀转 Java 的 `$2a$`，算法相同）
- AuthMe 配置为 MySQL 数据源直读该表，**全程无明文密码**
- 角色创建/改名/删除、网站改密码、管理员重置密码时自动同步

## 站点侧配置

1. 站点必须使用 **MySQL**（AuthMe 需要能连同一个库；SQLite 无法共享连接）
2. 后台「插件管理」启用 `AuthMe 同步`
3. 已有角色执行一次全量同步：

```bash
php artisan authme:sync
```

表名默认 `authme`，可通过 options 修改：`Option::set('authme-sync.table', 'mc_authme')`。

## AuthMe 服务端配置

`plugins/AuthMe/config.yml` 关键项：

```yaml
DataSource:
    backend: MYSQL
    mySQLHost: <数据库主机>
    mySQLPort: '3306'
    mySQLUsername: <只读读写皆可的账号>
    mySQLPassword: <密码>
    mySQLDatabase: <与站点相同的库>
    mySQLTablename: authme
    mySQLColumnName: username
    mySQLRealName: realname
    mySQLColumnPassword: password
    mySQLColumnEmail: email
    mySQLColumnLogged: isLogged
    mySQLColumnRegisterDate: regdate
    mySQLColumnRegisterIp: regip

settings:
    security:
        passwordHash: BCRYPT
    registration:
        # 关闭游戏内注册，账号一律来自网站
        enabled: false
```

重启服务器后，网站上创建过角色的玩家即可直接 `/login <网站密码>`。

## 注意事项

- **游戏内改密码**（`/changepassword`）会被下一次网站同步覆盖，建议用 AuthMe 权限禁用该命令，统一走网站改密
- 通过 OAuth（微软/GitHub/Gitee）注册的用户密码是随机值，需先在网站「账号设置」中设置密码才能进服
- 删除网站账号会级联删除角色并同步移除 AuthMe 记录
- 数据库账号建议给 AuthMe 单独建一个，只授权 `authme` 表
