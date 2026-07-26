@extends('layouts.app')

@section('title', '站点设置')

@section('content')
<h2>站点设置</h2>

<form method="POST" action="{{ route('admin.settings') }}">
    @csrf

    <article>
        <h4>基本设置</h4>
        <label>站点名称
            <input type="text" name="site_name" value="{{ old('site_name', $options['site_name']) }}" maxlength="100" required>
        </label>
        <label>站点简介
            <input type="text" name="site_description" value="{{ old('site_description', $options['site_description']) }}" maxlength="255">
        </label>
        <label>
            <input type="hidden" name="allow_register" value="0">
            <input type="checkbox" name="allow_register" value="1" @checked(old('allow_register', $options['allow_register']))>
            开放注册
        </label>
        <label>
            <input type="hidden" name="email_verification" value="0">
            <input type="checkbox" name="email_verification" value="1" @checked(old('email_verification', $options['email_verification']))>
            注册需邮箱验证码（需先配置下方 SMTP）
        </label>
        <div class="grid">
            <label>邮箱后缀限制
                <select name="email_domain_mode">
                    <option value="off" @selected($options['email_domain_mode'] === 'off')>不限制</option>
                    <option value="whitelist" @selected($options['email_domain_mode'] === 'whitelist')>白名单（仅允许列表内）</option>
                    <option value="blacklist" @selected($options['email_domain_mode'] === 'blacklist')>黑名单（拒绝列表内）</option>
                </select>
            </label>
            <label>后缀列表（逗号分隔）
                <input type="text" name="email_domains" value="{{ old('email_domains', $options['email_domains']) }}"
                       placeholder="qq.com, 163.com, gmail.com" maxlength="1000">
            </label>
        </div>
    </article>

    <article>
        <h4>SMTP 邮件</h4>
        <div class="grid">
            <label>SMTP 主机
                <input type="text" name="mail_host" value="{{ option('mail.host', '') }}" placeholder="smtp.example.com">
            </label>
            <label>端口
                <input type="number" name="mail_port" value="{{ option('mail.port', 465) }}" min="1" max="65535">
            </label>
            <label>加密
                <select name="mail_encryption">
                    <option value="ssl" @selected(option('mail.encryption', 'ssl') === 'ssl')>SSL</option>
                    <option value="tls" @selected(option('mail.encryption') === 'tls')>TLS</option>
                    <option value="none" @selected(option('mail.encryption') === 'none')>无</option>
                </select>
            </label>
        </div>
        <div class="grid">
            <label>用户名
                <input type="text" name="mail_username" value="{{ option('mail.username', '') }}" autocomplete="off">
            </label>
            <label>密码 / 授权码
                <input type="password" name="mail_password" value=""
                       placeholder="{{ option('mail.password') ? '已设置，留空不修改' : '未设置' }}"
                       autocomplete="new-password">
            </label>
            <label>发件地址
                <input type="email" name="mail_from" value="{{ option('mail.from', '') }}" placeholder="no-reply@example.com">
            </label>
        </div>
        <small>留空则使用 .env 中的 MAIL_* 配置。</small>
    </article>

    <article>
        <h4>积分设置</h4>
        <div class="grid">
            <label>注册赠送积分
                <input type="number" name="initial_score" value="{{ old('initial_score', $options['initial_score']) }}" min="0" required>
            </label>
            <label>创建角色消耗
                <input type="number" name="player_cost" value="{{ old('player_cost', $options['player_cost']) }}" min="0" required>
            </label>
            <label>每日签到获得
                <input type="number" name="sign_score" value="{{ old('sign_score', $options['sign_score']) }}" min="0" required>
            </label>
        </div>
    </article>

    <article>
        <h4>Yggdrasil 令牌</h4>
        <div class="grid">
            <label>令牌有效期（小时）
                <input type="number" name="token_valid_hours" value="{{ old('token_valid_hours', $options['token_valid_hours']) }}" min="1" max="8760" required>
            </label>
            <label>彻底失效期（小时）
                <input type="number" name="token_expire_hours" value="{{ old('token_expire_hours', $options['token_expire_hours']) }}" min="1" max="8760" required>
            </label>
            <label>每用户令牌上限
                <input type="number" name="tokens_limit" value="{{ old('tokens_limit', $options['tokens_limit']) }}" min="1" max="100" required>
            </label>
        </div>
        <small>有效期内可 validate；有效期至彻底失效期之间仅可 refresh。修改立即对全部现有令牌生效。</small>
    </article>

    <article>
        <h4>材质域名白名单</h4>
        <label>skinDomains（逗号分隔，留空自动使用 APP_URL 域名）
            <input type="text" name="skin_domains" value="{{ old('skin_domains', $options['skin_domains']) }}"
                   placeholder="example.com, .cdn.example.com" maxlength="500">
        </label>
    </article>

    <article>
        <h4>存储与站点</h4>
        <div class="grid">
            <label>材质存储后端
                <select name="texture_storage_driver">
                    <option value="local" @selected($options['texture_storage_driver'] === 'local')>本地磁盘</option>
                    <option value="s3" @selected($options['texture_storage_driver'] === 's3')>S3 兼容（R2/OSS/MinIO）</option>
                    <option value="webdav" @selected($options['texture_storage_driver'] === 'webdav')>WebDAV（OpenList/OneDrive）</option>
                </select>
            </label>
            <label>每用户材质上限（0 = 不限）
                <input type="number" name="max_textures" value="{{ old('max_textures', $options['max_textures']) }}" min="0" max="10000" required>
            </label>
            <label>站点语言
                <select name="site_language">
                    <option value="zh_CN" @selected($options['site_language'] === 'zh_CN')>简体中文</option>
                    <option value="en" @selected($options['site_language'] === 'en')>English</option>
                </select>
            </label>
        </div>
        <small>S3/WebDAV 连接参数在 <code>.env</code> 中配置（AWS_* / WEBDAV_*）。</small>
    </article>

    <article>
        <h4>第三方登录（OAuth）</h4>
        <p><small>回调地址填：<code>{{ url('auth/oauth/{provider}/callback') }}</code>（把 <code>{provider}</code> 换成对应名称）。Secret 留空表示保持不变。</small></p>

        @foreach($oauthProviders as $p)
            @php($prefix = "oauth_{$p->name}")
            <details @if(option_bool("oauth.{$p->name}.enabled")) open @endif>
                <summary><strong>{{ $p->title }}</strong>
                    <small>{{ $p->configured() ? '（已启用）' : '' }}</small>
                </summary>
                <label>
                    <input type="hidden" name="{{ $prefix }}_enabled" value="0">
                    <input type="checkbox" name="{{ $prefix }}_enabled" value="1"
                           @checked(option_bool("oauth.{$p->name}.enabled"))>
                    启用 {{ $p->title }} 登录
                </label>
                <div class="grid">
                    <label>Client ID
                        <input type="text" name="{{ $prefix }}_client_id"
                               value="{{ option("oauth.{$p->name}.client_id", '') }}" autocomplete="off">
                    </label>
                    <label>Client Secret
                        <input type="password" name="{{ $prefix }}_client_secret" value=""
                               placeholder="{{ option("oauth.{$p->name}.client_secret") ? '已设置，留空不修改' : '未设置' }}"
                               autocomplete="new-password">
                    </label>
                </div>
                <small>回调地址：<code>{{ route('oauth.callback', ['provider' => $p->name]) }}</code></small>
            </details>
        @endforeach
    </article>

    <button type="submit">保存设置</button>
</form>
@endsection
