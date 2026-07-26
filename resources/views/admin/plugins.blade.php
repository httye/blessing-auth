@extends('layouts.app')

@section('title', '插件管理')

@section('content')
<h2>插件管理</h2>

<p><small>插件目录：<code>plugins/</code>，放入插件文件夹后刷新本页即可看到。启停即时生效。</small></p>

<div class="table-wrap">
<table>
    <thead>
    <tr><th>插件</th><th>版本</th><th>作者</th><th>描述</th><th>状态</th><th>操作</th></tr>
    </thead>
    <tbody>
    @forelse($plugins as $plugin)
        <tr>
            <td><strong>{{ $plugin->title() }}</strong><br><small><code>{{ $plugin->name }}</code></small></td>
            <td>{{ $plugin->version() }}</td>
            <td>{{ $plugin->author() }}</td>
            <td>{{ $plugin->description() }}
                @if($plugin->dependencies())
                    <br><small>依赖：{{ implode(', ', $plugin->dependencies()) }}</small>
                @endif
            </td>
            <td>{{ $plugin->enabled ? '✔ 已启用' : '— 未启用' }}</td>
            <td>
                <form method="POST" action="{{ route('admin.plugins') }}">
                    @csrf
                    <input type="hidden" name="name" value="{{ $plugin->name }}">
                    <input type="hidden" name="action" value="{{ $plugin->enabled ? 'disable' : 'enable' }}">
                    <button type="submit" class="outline {{ $plugin->enabled ? 'secondary' : '' }}"
                            style="padding:.2rem .6rem;font-size:.85em">
                        {{ $plugin->enabled ? '停用' : '启用' }}
                    </button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="6">plugins/ 目录下没有发现插件。</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
