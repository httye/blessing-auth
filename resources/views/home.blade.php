@extends('layouts.app')

@section('title', '首页')

@section('content')
<hgroup>
    <h1>{{ option('site_name', config('app.name')) }}</h1>
    <p>{{ option('site_description', '一个兼容 authlib-injector 的 Minecraft 外置登录认证系统') }}</p>
</hgroup>

@if($announcements->isNotEmpty())
    <article>
        <h3>公告</h3>
        @foreach($announcements as $item)
            <p>
                @if($item->pinned)<mark>置顶</mark>@endif
                <a href="{{ route('news.show', $item) }}">{{ $item->title }}</a>
                <small>{{ $item->created_at->format('m-d') }}</small>
            </p>
        @endforeach
        <small><a href="{{ route('news.index') }}">全部公告 »</a></small>
    </article>
@endif

<article>
    <h3>如何使用</h3>
    <ol>
        <li>注册账号并创建游戏角色</li>
        <li>在皮肤库上传或挑选喜欢的皮肤，应用到角色</li>
        <li>启动器添加认证服务器：<code>{{ url('api/yggdrasil') }}</code></li>
        <li>使用本站邮箱和密码登录游戏</li>
    </ol>
    @guest
        <a href="{{ route('register') }}" role="button">立即注册</a>
    @endguest
</article>
@endsection
