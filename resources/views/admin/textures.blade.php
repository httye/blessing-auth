@extends('layouts.app')

@section('title', '材质管理')

@section('content')
<h2>材质管理</h2>

<form method="GET" action="{{ route('admin.textures') }}">
    <div class="grid">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="搜索材质名">
        <button type="submit" class="outline">搜索</button>
    </div>
</form>

<div class="table-wrap">
<table>
    <thead>
    <tr><th>ID</th><th>预览</th><th>名称</th><th>类型</th><th>上传者</th><th>公开</th><th>操作</th></tr>
    </thead>
    <tbody>
    @foreach($textures as $texture)
        <tr>
            <td>{{ $texture->id }}</td>
            <td><img class="texture-preview" src="{{ $texture->url() }}" alt=""></td>
            <td>{{ $texture->name }}</td>
            <td>{{ $texture->type === 'skin' ? '皮肤' : '披风' }}</td>
            <td>{{ $texture->owner?->email }}</td>
            <td>{{ $texture->public ? '是' : '否' }}</td>
            <td>
                <form method="POST" action="{{ route('admin.textures.destroy', $texture) }}"
                      onsubmit="return confirm('删除材质 {{ $texture->name }}？')">
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

{{ $textures->links('pagination') }}
@endsection
