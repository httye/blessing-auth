@extends('layouts.app')

@section('title', '备份管理')

@section('content')
<h2>备份管理</h2>

<article>
    <p>备份内容：全部数据表（JSON 导出）、材质文件、Yggdrasil 签名密钥。存放于 <code>storage/backups/</code>。</p>
    <p><small>定时备份：把 <code>php artisan site:backup --keep=10</code> 加入系统 crontab 即可。</small></p>
    <form method="POST" action="{{ route('admin.backups') }}">
        @csrf
        <button type="submit">立即备份</button>
    </form>
</article>

<div class="table-wrap">
<table>
    <thead>
    <tr><th>文件</th><th>大小</th><th>时间</th><th>操作</th></tr>
    </thead>
    <tbody>
    @forelse($backups as $backup)
        <tr>
            <td><code>{{ $backup['name'] }}</code></td>
            <td>{{ round($backup['size'] / 1048576, 2) }} MB</td>
            <td>{{ date('Y-m-d H:i:s', $backup['created_at']) }}</td>
            <td>
                <a href="{{ route('admin.backups.download', ['name' => $backup['name']]) }}" role="button"
                   class="outline" style="padding:.2rem .6rem;font-size:.85em">下载</a>
                <form method="POST" action="{{ route('admin.backups.destroy', ['name' => $backup['name']]) }}"
                      style="display:inline" onsubmit="return confirm('删除该备份？')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="outline secondary" style="padding:.2rem .6rem;font-size:.85em">删除</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="4">暂无备份。</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
