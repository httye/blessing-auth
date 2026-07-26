@extends('layouts.app')

@section('title', '角色管理')

@section('content')
<h2>角色管理</h2>

<form method="GET" action="{{ route('admin.players') }}">
    <div class="grid">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="搜索角色名">
        <button type="submit" class="outline">搜索</button>
    </div>
</form>

<div class="table-wrap">
<table>
    <thead>
    <tr><th>ID</th><th>角色名</th><th>UUID</th><th>所属用户</th><th>操作</th></tr>
    </thead>
    <tbody>
    @foreach($players as $player)
        <tr>
            <td>{{ $player->id }}</td>
            <td>{{ $player->name }}</td>
            <td><code>{{ $player->uuid }}</code></td>
            <td>{{ $player->user?->email }}</td>
            <td>
                <form method="POST" action="{{ route('admin.players.destroy', $player) }}"
                      onsubmit="return confirm('删除角色 {{ $player->name }}？')">
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

{{ $players->links('pagination') }}
@endsection
