@extends('layouts.app')

@section('title', '用户管理')

@section('content')
<h2>用户管理</h2>

<form method="GET" action="{{ route('admin.users') }}">
    <div class="grid">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="搜索邮箱或昵称">
        <button type="submit" class="outline">搜索</button>
    </div>
</form>

<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th>ID</th><th>邮箱</th><th>昵称</th><th>积分</th><th>角色数</th><th>权限</th><th>操作</th>
    </tr>
    </thead>
    <tbody>
    @foreach($users as $user)
        <tr>
            <td>{{ $user->id }}</td>
            <td>{{ $user->email }}</td>
            <td>{{ $user->nickname }}</td>
            <td>
                <form method="POST" action="{{ route('admin.users.score', $user) }}" style="display:flex;gap:.3rem">
                    @csrf
                    <input type="number" name="score" value="{{ $user->score }}" min="0" style="width:6rem;padding:.2rem">
                    <button type="submit" class="outline" style="padding:.2rem .5rem">改</button>
                </form>
            </td>
            <td>{{ $user->players_count }}</td>
            <td>
                @php($me = auth()->user())
                @if($user->id === $me->id || $user->permission >= $me->permission)
                    {{-- 不可操作：自己或权限不低于自己 --}}
                    @switch($user->permission)
                        @case(2) 超级管理员 @break
                        @case(1) 管理员 @break
                        @case(0) 普通 @break
                        @default 封禁中
                    @endswitch
                @else
                    <details>
                        <summary role="button" class="outline" style="padding:.2rem .5rem;font-size:.85em">
                            @switch($user->permission)
                                @case(2) 超级管理员 @break
                                @case(1) 管理员 @break
                                @case(0) 普通 @break
                                @default 封禁中
                            @endswitch
                        </summary>
                        <form method="POST" action="{{ route('admin.users.permission', $user) }}">
                            @csrf
                            <select name="permission">
                                <option value="0" @selected($user->permission === 0)>普通用户</option>
                                @if($me->permission > 1)
                                    <option value="1" @selected($user->permission === 1)>管理员</option>
                                @endif
                                <option value="-1" @selected($user->permission === -1)>封禁</option>
                            </select>
                            <input type="text" name="ban_reason" placeholder="封禁原因（可选）"
                                   value="{{ $user->ban_reason }}" maxlength="255">
                            <input type="number" name="ban_days" placeholder="封禁天数（空/0=永久）" min="0" max="36500">
                            @if($user->ban_until)
                                <small>当前解封时间：{{ $user->ban_until->format('Y-m-d H:i') }}</small>
                            @endif
                            <button type="submit" class="outline">应用</button>
                        </form>
                    </details>
                @endif
            </td>
            <td style="white-space:nowrap">
                <details style="display:inline-block">
                    <summary role="button" class="outline" style="padding:.2rem .5rem;font-size:.85em">重置密码</summary>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}">
                        @csrf
                        <input type="text" name="password" placeholder="新密码（≥8位）" minlength="8" required>
                        <button type="submit" class="outline">确认</button>
                    </form>
                </details>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display:inline"
                      onsubmit="return confirm('删除用户 {{ $user->email }}？其角色和令牌将一并删除。')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="outline secondary" style="padding:.2rem .5rem;font-size:.85em">删除</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

{{ $users->links('pagination') }}
@endsection
