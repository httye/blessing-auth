@extends('layouts.app')

@section('title', '审计日志')

@section('content')
<h2>审计日志</h2>

<p>管理员的所有关键操作记录，按时间倒序排列（保存 90 天）。</p>

<div class="table-wrap">
<table>
    <thead>
    <tr><th>时间</th><th>操作者</th><th>操作</th><th>目标</th><th>详情</th><th>IP</th></tr>
    </thead>
    <tbody>
    @forelse($logs as $log)
        <tr>
            <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
            <td>{{ $log->operator?->nickname ?? $log->operator?->email ?? '—' }}</td>
            <td><code>{{ $log->action }}</code></td>
            <td>{{ $log->targetUser?->email ?? '—' }}</td>
            <td>{{ $log->detail ?? '' }}</td>
            <td><code>{{ $log->ip }}</code></td>
        </tr>
    @empty
        <tr><td colspan="6">暂无记录。</td></tr>
    @endforelse
    </tbody>
</table>
</div>

{{ $logs->links('pagination') }}
@endsection
