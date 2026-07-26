@extends('layouts.app')

@section('title', '接入应用')

@section('content')
<h2>接入应用（OAuth 服务器）</h2>

<article>
    <p>让论坛、商店等外部应用支持「用本站账号登录」。对接文档见 <code>docs/API.md</code> 的 OAuth 服务器章节。</p>
    <h4>创建应用</h4>
    <form method="POST" action="{{ route('admin.oauth-clients') }}">
        @csrf
        <div class="grid">
            <label>应用名称
                <input type="text" name="name" maxlength="100" placeholder="例如：社区论坛" required>
            </label>
            <label>回调地址（多个每行一个）
                <textarea name="redirect_uri" rows="2" placeholder="https://forum.example.com/oauth/callback" required></textarea>
            </label>
        </div>
        <button type="submit">创建</button>
    </form>
</article>

@foreach($clients as $client)
    <article>
        <header>
            <strong>{{ $client->name }}</strong>
            @unless($client->enabled)<mark>已停用</mark>@endunless
        </header>
        <p>
            client_id: <code>{{ $client->client_id }}</code><br>
            client_secret:
            <details style="display:inline-block">
                <summary style="display:inline;cursor:pointer"><small>点击显示</small></summary>
                <code>{{ $client->client_secret }}</code>
            </details>
        </p>
        <p><small>回调地址：</small><br>
            @foreach($client->redirectUris() as $uri)
                <code>{{ $uri }}</code><br>
            @endforeach
        </p>
        <form method="POST" action="{{ route('admin.oauth-clients.toggle', $client) }}" style="display:inline">
            @csrf
            <button type="submit" class="outline" style="padding:.2rem .6rem;font-size:.85em">
                {{ $client->enabled ? '停用' : '启用' }}
            </button>
        </form>
        <form method="POST" action="{{ route('admin.oauth-clients.destroy', $client) }}" style="display:inline"
              onsubmit="return confirm('删除应用「{{ $client->name }}」？其全部令牌将失效。')">
            @csrf
            @method('DELETE')
            <button type="submit" class="outline secondary" style="padding:.2rem .6rem;font-size:.85em">删除</button>
        </form>
    </article>
@endforeach
@endsection
