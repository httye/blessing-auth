@extends('layouts.app')

@section('title', '我的称号')

@section('content')
<h2>我的称号</h2>

<article>
    <h4>已拥有（{{ $owned->count() }}）</h4>
    @if($owned->isEmpty())
        <p>还没有称号，去下面的商店看看吧。</p>
    @else
        <form method="POST" action="{{ route('title.wear') }}">
            @csrf
            <div class="grid-cards">
                @foreach($owned as $title)
                    <label style="border:1px solid #ddd;border-radius:.5rem;padding:.8rem;cursor:pointer">
                        <input type="radio" name="title_id" value="{{ $title->id }}"
                               @checked($user->current_title_id === $title->id)>
                        <strong style="color:{{ $title->color }}">[{{ $title->name }}]</strong><br>
                        <small>获得于 {{ \Illuminate\Support\Carbon::parse($title->pivot->acquired_at)->format('Y-m-d') }}</small>
                    </label>
                @endforeach
                <label style="border:1px dashed #bbb;border-radius:.5rem;padding:.8rem;cursor:pointer">
                    <input type="radio" name="title_id" value="" @checked($user->current_title_id === null)>
                    不佩戴称号
                </label>
            </div>
            <button type="submit" style="margin-top:1rem">保存佩戴</button>
        </form>
    @endif
</article>

<article>
    <h4>称号商店</h4>
    @if($shop->isEmpty())
        <p>暂无可购买的称号。</p>
    @else
        <div class="grid-cards">
            @foreach($shop as $title)
                <article style="text-align:center">
                    <strong style="color:{{ $title->color }}">[{{ $title->name }}]</strong>
                    <p>{{ $title->price }} 积分</p>
                    <form method="POST" action="{{ route('title.buy', $title) }}"
                          onsubmit="return confirm('花费 {{ $title->price }} 积分购买「{{ $title->name }}」？')">
                        @csrf
                        <button type="submit" class="outline" @disabled($user->score < $title->price)>
                            {{ $user->score >= $title->price ? '购买' : '积分不足' }}
                        </button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
    <small>当前积分：{{ $user->score }}</small>
</article>
@endsection
