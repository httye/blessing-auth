@extends('layouts.app')

@section('title', '授权确认')

@section('content')
<article style="max-width: 480px; margin: 0 auto; text-align: center;">
    <h3>授权请求</h3>
    <p><strong>{{ $client->name }}</strong> 请求使用你的 {{ option('site_name', config('app.name')) }} 账号登录。</p>
    <p><small>对方将获得：昵称、邮箱、角色列表、称号、积分等基本资料（只读）。</small></p>

    <form method="POST" action="{{ route('oauth.server.approve') }}">
        @csrf
        <input type="hidden" name="client_id" value="{{ $client->client_id }}">
        <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
        <input type="hidden" name="state" value="{{ $state }}">
        <div class="grid">
            <button type="submit" name="action" value="approve">同意授权</button>
            <button type="submit" name="action" value="deny" class="secondary outline">拒绝</button>
        </div>
    </form>
    <small>当前登录：{{ auth()->user()->email }}</small>
</article>
@endsection
