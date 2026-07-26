@extends('layouts.app')

@section('title', '账号设置')

@section('content')
<h2>账号设置</h2>

<div class="grid">
    <article>
        <h4>修改昵称</h4>
        <form method="POST" action="{{ route('user.nickname') }}">
            @csrf
            <label>新昵称
                <input type="text" name="nickname" value="{{ $user->nickname }}" maxlength="50" required>
            </label>
            <button type="submit">保存</button>
        </form>
    </article>

    <article>
        <h4>修改邮箱</h4>
        <form method="POST" action="{{ route('user.email') }}">
            @csrf
            <label>新邮箱
                <input type="email" name="email" value="{{ $user->email }}" required>
            </label>
            <label>当前密码
                <input type="password" name="current_password" required>
            </label>
            <button type="submit">保存</button>
        </form>
    </article>

    <article>
        <h4>修改密码</h4>
        <form method="POST" action="{{ route('user.password') }}">
            @csrf
            <label>当前密码
                <input type="password" name="current_password" required>
            </label>
            <label>新密码（至少 8 位）
                <input type="password" name="password" minlength="8" required>
            </label>
            <label>确认新密码
                <input type="password" name="password_confirmation" minlength="8" required>
            </label>
            <button type="submit">保存</button>
        </form>
        <small>修改密码后，所有游戏登录令牌将失效。</small>
    </article>
</div>

@php($oauthProviders = \App\Services\OAuth\ProviderRegistry::enabled())
@if($oauthProviders)
    <article>
        <h4>第三方账号绑定</h4>
        @php($identities = $user->oauthIdentities->keyBy('provider'))
        <div class="table-wrap">
        <table>
            <tbody>
            @foreach($oauthProviders as $p)
                @php($bound = $identities->get($p->name))
                <tr>
                    <td><strong>{{ $p->title }}</strong></td>
                    <td>
                        @if($bound)
                            已绑定：{{ $bound->provider_nickname ?? $bound->provider_email ?? $bound->provider_user_id }}
                        @else
                            未绑定
                        @endif
                    </td>
                    <td>
                        @if($bound)
                            <form method="POST" action="{{ route('oauth.unbind', ['provider' => $p->name]) }}"
                                  onsubmit="return confirm('确定解绑 {{ $p->title }}？')">
                                @csrf
                                <button type="submit" class="outline secondary" style="padding:.2rem .6rem;font-size:.85em">解绑</button>
                            </form>
                        @else
                            <a href="{{ route('oauth.redirect', ['provider' => $p->name]) }}"
                               role="button" class="outline" style="padding:.2rem .6rem;font-size:.85em">绑定</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <small>绑定后可用第三方账号一键登录本站。游戏内登录仍需邮箱/角色名 + 密码。</small>
    </article>
@endif
@endsection
