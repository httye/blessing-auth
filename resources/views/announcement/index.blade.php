@extends('layouts.app')

@section('title', '公告')

@section('content')
<h2>公告</h2>

@forelse($announcements as $item)
    <article>
        <header>
            @if($item->pinned)<mark>置顶</mark>@endif
            <a href="{{ route('news.show', $item) }}"><strong>{{ $item->title }}</strong></a>
        </header>
        <p>{{ $item->excerpt() }}</p>
        <footer><small>{{ $item->user?->nickname }} · {{ $item->created_at->format('Y-m-d') }}</small></footer>
    </article>
@empty
    <p>暂无公告。</p>
@endforelse

{{ $announcements->links('pagination') }}
@endsection
