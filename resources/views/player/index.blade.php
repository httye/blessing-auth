@extends('layouts.app')

@section('title', '我的角色')

@section('content')
<h2>我的角色</h2>

<article>
    <h4>创建新角色（消耗 {{ $cost }} 积分）</h4>
    <form method="POST" action="{{ route('player.store') }}">
        @csrf
        <div class="grid">
            <input type="text" name="name" placeholder="角色名（3-16位字母数字下划线）"
                   pattern="[A-Za-z0-9_]{3,16}" required>
            <button type="submit">创建</button>
        </div>
    </form>
</article>

@forelse($players as $player)
    <article>
        <header>
            <strong>{{ $player->name }}</strong>
            <small style="float:right"><code>{{ $player->uuid }}</code></small>
        </header>

        <div class="grid">
            <div>
                <p>皮肤：{{ $player->skin?->name ?? '未设置' }}
                    @if($player->skin)
                        <img class="texture-preview" src="{{ $player->skin->url() }}" alt="skin">
                    @endif
                </p>
                <p>披风：{{ $player->cape?->name ?? '未设置' }}
                    @if($player->cape)
                        <img class="texture-preview" src="{{ $player->cape->url() }}" alt="cape">
                    @endif
                </p>
                <small>去 <a href="{{ route('texture.index') }}">皮肤库</a> 挑选材质应用到该角色</small>
            </div>

            <div style="text-align:center">
                @if($player->tid_skin)
                    <canvas class="skin-preview" data-url="{{ $player->skin->url() }}"
                            data-cape="{{ $player->tid_cape && $player->cape ? $player->cape->url() : '' }}"
                            width="300" height="340"></canvas>
                @else
                    <p><small>应用皮肤后可预览 3D 效果</small></p>
                @endif
            </div>

            <div>
                <form method="POST" action="{{ route('player.rename', $player) }}">
                    @csrf
                    <div class="grid">
                        <input type="text" name="name" placeholder="新角色名" pattern="[A-Za-z0-9_]{3,16}" required>
                        <button type="submit" class="outline">改名</button>
                    </div>
                </form>

                <div class="grid">
                    @if($player->tid_skin)
                        <form method="POST" action="{{ route('player.texture.clear', $player) }}">
                            @csrf
                            <input type="hidden" name="type" value="skin">
                            <button type="submit" class="outline secondary">移除皮肤</button>
                        </form>
                    @endif
                    @if($player->tid_cape)
                        <form method="POST" action="{{ route('player.texture.clear', $player) }}">
                            @csrf
                            <input type="hidden" name="type" value="cape">
                            <button type="submit" class="outline secondary">移除披风</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('player.destroy', $player) }}"
                          onsubmit="return confirm('确定删除角色 {{ $player->name }}？')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="outline" style="color:#842029;border-color:#842029">删除角色</button>
                    </form>
                </div>
            </div>
        </div>
    </article>
@empty
    <p>还没有角色，创建一个吧！</p>
@endforelse
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/skinview3d@3.0.1/bundles/skinview3d.bundle.min.js"></script>
<script>
document.querySelectorAll('.skin-preview').forEach(function (canvas) {
    var skinUrl = canvas.dataset.url;
    var capeUrl = canvas.dataset.cape || undefined;

    var skinViewer = new skinview3d.SkinViewer({
        canvas: canvas,
        width: 300,
        height: 340,
        skin: skinUrl,
    });

    if (capeUrl) {
        skinViewer.loadCape(capeUrl);
    }

    // 行走动画
    skinViewer.animations.add(new skinview3d.WalkingAnimation());
    skinViewer.animations.speed = 0.6;

    // 初始视角
    skinViewer.camera.rotation.x = -0.2;
    skinViewer.playerWrapper.rotation.y = 3.8;

    // 鼠标悬停时暂停
    canvas.addEventListener('mouseenter', function () {
        skinViewer.animations.paused = true;
    });
    canvas.addEventListener('mouseleave', function () {
        skinViewer.animations.paused = false;
    });
});
</script>
@endpush
