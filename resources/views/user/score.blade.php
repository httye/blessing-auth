@extends('layouts.app')

@section('title', '积分明细')

@section('content')
<h2>积分明细</h2>

<article>
    <p>当前余额：<strong style="font-size:1.5em">{{ $user->score }}</strong> 积分</p>
</article>

<div class="table-wrap">
<table>
    <thead>
    <tr><th>时间</th><th>变动</th><th>余额</th><th>原因</th></tr>
    </thead>
    <tbody>
    @forelse($logs as $log)
        <tr>
            <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
            <td style="color:{{ $log->delta >= 0 ? '#0f5132' : '#842029' }}">
                {{ $log->delta >= 0 ? '+' : '' }}{{ $log->delta }}
            </td>
            <td>{{ $log->balance_after }}</td>
            <td>{{ $log->reason }}</td>
        </tr>
    @empty
        <tr><td colspan="4">暂无积分记录。</td></tr>
    @endforelse
    </tbody>
</table>
</div>

{{ $logs->links('pagination') }}
@endsection
