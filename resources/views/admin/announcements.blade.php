@extends('layouts.app')

@section('title', '公告管理')

@section('content')
<h2>公告管理</h2>

<article>
    <h4>发布新公告</h4>
    <form method="POST" action="{{ route('admin.announcements') }}">
        @csrf
        <label>标题
            <input type="text" name="title" maxlength="100" required>
        </label>
        <label>内容（支持 Markdown：标题、表格、删除线、自动链接；原始 HTML 会被转义）
            <textarea name="content" rows="5" maxlength="20000" required></textarea>
        </label>
        <div class="grid">
            <label><input type="checkbox" name="pinned" value="1"> 置顶</label>
            <label><input type="checkbox" name="published" value="1" checked> 立即发布（不勾选存为草稿）</label>
            <button type="submit">发布</button>
        </div>
    </form>
</article>

@foreach($announcements as $item)
    <details>
        <summary>
            @if($item->pinned)<mark>置顶</mark>@endif
            @unless($item->published)<small>[草稿]</small>@endunless
            <strong>{{ $item->title }}</strong>
            <small>· {{ $item->created_at->format('Y-m-d') }}</small>
        </summary>
        <form method="POST" action="{{ route('admin.announcements.update', $item) }}">
            @csrf
            <label>标题
                <input type="text" name="title" value="{{ $item->title }}" maxlength="100" required>
            </label>
            <label>内容
                <textarea name="content" rows="5" maxlength="20000" required>{{ $item->content }}</textarea>
            </label>
            <div class="grid">
                <label><input type="checkbox" name="pinned" value="1" @checked($item->pinned)> 置顶</label>
                <label><input type="checkbox" name="published" value="1" @checked($item->published)> 已发布</label>
                <button type="submit" class="outline">保存</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.announcements.destroy', $item) }}"
              onsubmit="return confirm('删除公告「{{ $item->title }}」？')">
            @csrf
            @method('DELETE')
            <button type="submit" class="outline secondary" style="padding:.2rem .6rem;font-size:.85em">删除</button>
        </form>
    </details>
    <hr>
@endforeach

{{ $announcements->links('pagination') }}
@endsection
