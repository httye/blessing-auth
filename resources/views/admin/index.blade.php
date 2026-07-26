@extends('layouts.app')

@section('title', '管理面板')

@section('content')
<h2>管理面板</h2>

<div class="grid">
    <article style="text-align:center">
        <h1>{{ $stats['users'] }}</h1>
        <p>用户</p>
        <a href="{{ route('admin.users') }}">管理用户</a>
    </article>
    <article style="text-align:center">
        <h1>{{ $stats['players'] }}</h1>
        <p>角色</p>
        <a href="{{ route('admin.players') }}">管理角色</a>
    </article>
    <article style="text-align:center">
        <h1>{{ $stats['textures'] }}</h1>
        <p>材质</p>
        <a href="{{ route('admin.textures') }}">管理材质</a>
    </article>
</div>

<article style="text-align:center">
    @if(auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.settings') }}" role="button">站点设置</a>
        <a href="{{ route('admin.plugins') }}" role="button" class="outline">插件管理</a>
    @endif
    <a href="{{ route('admin.announcements') }}" role="button" class="outline">公告管理</a>
    <a href="{{ route('admin.titles') }}" role="button" class="outline">称号管理</a>
    @if(auth()->user()->isSuperAdmin())
        <a href="{{ route('admin.backups') }}" role="button" class="outline">备份管理</a>
        <a href="{{ route('admin.oauth-clients') }}" role="button" class="outline">接入应用</a>
        <a href="{{ route('admin.audit') }}" role="button" class="outline">审计日志</a>
    @endif
</article>
@endsection
