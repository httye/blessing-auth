@extends('layouts.app')

@section('title', '登录')

@section('content')
<article style="max-width: 480px; margin: 0 auto;">
    <h2>登录</h2>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label>邮箱
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label>密码
            <input type="password" name="password" required>
        </label>
        <label>
            <input type="checkbox" name="remember" value="1"> 记住我
        </label>
        <button type="submit">登录</button>
    </form>
    <small>没有账号？<a href="{{ route('register') }}">注册一个</a> · <a href="{{ route('password.forgot') }}">找回密码</a></small>

    @php($oauthProviders = \App\Services\OAuth\ProviderRegistry::enabled())
    @if($oauthProviders)
        <hr>
        <p style="text-align:center"><small>或使用第三方账号登录</small></p>
        <div class="grid">
            @foreach($oauthProviders as $p)
                <a href="{{ route('oauth.redirect', ['provider' => $p->name]) }}"
                   role="button" class="outline">{{ $p->title }}</a>
            @endforeach
        </div>
    @endif
</article>
@endsection
