@extends('layouts.app')

@section('title', $announcement->title)

@section('content')
<article>
    <hgroup>
        <h2>@if($announcement->pinned)<mark>置顶</mark> @endif{{ $announcement->title }}</h2>
        <p><small>{{ $announcement->user?->nickname }} 发布于 {{ $announcement->created_at->format('Y-m-d H:i') }}
            @if($announcement->updated_at->ne($announcement->created_at))
                · 更新于 {{ $announcement->updated_at->format('Y-m-d H:i') }}
            @endif
        </small></p>
    </hgroup>
    <div class="md-content">{!! $announcement->renderedContent() !!}</div>
</article>
<a href="{{ route('news.index') }}">« 返回公告列表</a>
@endsection
