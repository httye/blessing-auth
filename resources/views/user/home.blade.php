@extends('layouts.app')

@section('title', '用户中心')

@section('content')
<h2>用户中心</h2>

<div class="grid">
    <article>
        <h4>
            @if($user->currentTitle)
                <strong style="color:{{ $user->currentTitle->color }}">[{{ $user->currentTitle->name }}]</strong>
            @endif
            {{ $user->nickname }}
        </h4>
        <p>{{ $user->email }}</p>
        <p>积分：<strong>{{ $user->score }}</strong> <a href="{{ route('user.score') }}"><small>明细</small></a></p>
        <form method="POST" action="{{ route('user.sign') }}">
            @csrf
            <button type="submit" @disabled(! $user->canSignToday())>
                {{ $user->canSignToday() ? '每日签到 +'.option_int('sign_score') : '今日已签到' }}
            </button>
        </form>
        <a href="{{ route('user.profile') }}">账号设置</a>
    </article>

    <article>
        <h4>我的角色（{{ $players->count() }}）</h4>
        @forelse($players as $player)
            <p>
                <strong>{{ $player->name }}</strong><br>
                <small><code>{{ $player->uuid }}</code></small><br>
                <small>皮肤: {{ $player->skin?->name ?? '无' }} / 披风: {{ $player->cape?->name ?? '无' }}</small>
            </p>
        @empty
            <p>还没有角色。</p>
        @endforelse
        <a href="{{ route('player.index') }}" role="button" class="outline">管理角色</a>
    </article>
</div>

<article>
    <h4>外置登录配置</h4>
    <p>在支持 authlib-injector 的启动器中添加认证服务器：</p>
    <pre><code>{{ url('api/yggdrasil') }}</code></pre>
    <p>游戏内登录使用<strong>本站邮箱和密码</strong>（也支持直接用角色名登录）。</p>
</article>
@endsection
