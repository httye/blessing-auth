@extends('layouts.app')

@section('title', '皮肤库')

@section('content')
<h2>皮肤库</h2>

<div class="grid">
    <div>
        <a href="{{ route('texture.index') }}" role="button" class="outline @if(!request('type')) contrast @endif">全部</a>
        <a href="{{ route('texture.index', ['type' => 'skin']) }}" role="button" class="outline @if(request('type')==='skin') contrast @endif">皮肤</a>
        <a href="{{ route('texture.index', ['type' => 'cape']) }}" role="button" class="outline @if(request('type')==='cape') contrast @endif">披风</a>
    </div>
    <div style="text-align:right">
        <a href="{{ route('texture.upload') }}" role="button">上传材质</a>
    </div>
</div>

<div class="grid-cards" style="margin-top:1rem">
    @forelse($textures as $texture)
        <article>
            <img class="texture-preview" src="{{ $texture->url() }}" alt="{{ $texture->name }}" style="width:100%;height:120px">
            <strong>{{ $texture->name }}</strong><br>
            <small>{{ $texture->type === 'skin' ? '皮肤' : '披风' }} · {{ $texture->owner?->nickname }}
                @unless($texture->public) · 私有 @endunless
            </small>

            @auth
                <details>
                    <summary role="button" class="outline" style="padding:.2rem .6rem;font-size:.85em">应用到角色</summary>
                    @foreach(auth()->user()->players as $player)
                        <form method="POST" action="{{ route('player.texture', $player) }}">
                            @csrf
                            <input type="hidden" name="texture_id" value="{{ $texture->id }}">
                            <button type="submit" class="outline" style="width:100%;margin-bottom:.3rem">{{ $player->name }}</button>
                        </form>
                    @endforeach
                </details>

                @if($texture->uploader === auth()->id() || auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('texture.destroy', $texture) }}"
                          onsubmit="return confirm('确定删除？')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="outline secondary" style="padding:.2rem .6rem;font-size:.85em">删除</button>
                    </form>
                @endif
            @endauth
        </article>
    @empty
        <p>暂无材质。</p>
    @endforelse
</div>

{{ $textures->links('pagination') }}
@endsection
