<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#13171f">
    <title>@yield('title', '首页') - {{ option('site_name', config('app.name')) }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        .texture-preview { image-rendering: pixelated; width: 64px; height: 64px; object-fit: cover; }
        .flash { padding: .75rem 1rem; border-radius: .25rem; margin-bottom: 1rem; }
        .flash-success { background: #d1e7dd; color: #0f5132; }
        .flash-error { background: #f8d7da; color: #842029; }
        nav .brand { font-weight: bold; }
        .grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }

        /* ---------- 桌面导航 ---------- */
        .nav-desktop { display: flex; }
        .nav-mobile { display: none; }

        /* ---------- 表格：包一层横向滚动容器 ---------- */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap table { min-width: 640px; }

        /* ---------- 移动端 ---------- */
        @media (max-width: 768px) {
            /* 汉堡菜单 */
            .nav-desktop { display: none; }
            .nav-mobile { display: block; position: relative; }
            .nav-mobile summary {
                list-style: none;
                cursor: pointer;
                padding: .5rem .8rem;
                font-size: 1.4rem;
                line-height: 1;
                user-select: none;
            }
            .nav-mobile summary::-webkit-details-marker { display: none; }
            .nav-mobile[open] summary::before {
                content: '';
                position: fixed; inset: 0; z-index: 98;
            }
            .nav-mobile .menu {
                position: absolute; right: 0; top: 100%;
                z-index: 99;
                min-width: 12rem;
                background: var(--pico-card-background-color, #fff);
                border: 1px solid var(--pico-muted-border-color, #ddd);
                border-radius: .5rem;
                box-shadow: 0 6px 24px rgba(0,0,0,.15);
                padding: .4rem 0;
            }
            .nav-mobile .menu a,
            .nav-mobile .menu button {
                display: block;
                width: 100%;
                text-align: left;
                padding: .7rem 1.1rem;   /* ≥44px 触控目标 */
                border: none;
                background: none;
                color: inherit;
                text-decoration: none;
                font-size: 1rem;
            }
            .nav-mobile .menu a:active,
            .nav-mobile .menu button:active { background: rgba(0,0,0,.06); }
            .nav-mobile .menu hr { margin: .3rem 0; }

            /* 多列 grid 全部折叠为单列（Pico 默认 grid 不带断点折叠） */
            .grid { grid-template-columns: 1fr !important; }

            /* 卡片网格改两列，小屏更紧凑 */
            .grid-cards { grid-template-columns: repeat(2, 1fr); gap: .6rem; }

            /* 触控目标：按钮不小于 44px 高 */
            button, [role="button"], input[type="submit"] {
                min-height: 44px;
            }

            /* 行内小操作按钮例外（表格里的） */
            .table-wrap button, .table-wrap [role="button"] { min-height: 36px; }

            /* 表单控件 16px 起，避免 iOS 聚焦自动放大 */
            input, select, textarea { font-size: 16px !important; }

            /* 长内容截断 */
            code { word-break: break-all; }

            h1 { font-size: 1.6rem; }
            h2 { font-size: 1.35rem; }

            main.container { padding-top: .5rem; }
        }
    </style>
    {!! \App\Plugins\Hook::headHtml() !!}
    <style>
        /* 3D 皮肤预览 canvas */
        .skin-preview { image-rendering: auto; cursor: pointer; background: transparent; }
    </style>
</head>
<body>
<nav class="container">
    <ul>
        <li><a href="{{ route('home') }}" class="brand contrast">{{ option('site_name', config('app.name')) }}</a></li>
    </ul>

    {{-- 桌面导航 --}}
    <ul class="nav-desktop">
        <li><a href="{{ route('news.index') }}">公告</a></li>
        @auth
            <li><a href="{{ route('user.home') }}">用户中心</a></li>
            <li><a href="{{ route('player.index') }}">我的角色</a></li>
            <li><a href="{{ route('texture.index') }}">皮肤库</a></li>
            <li><a href="{{ route('title.index') }}">称号</a></li>
            @foreach(\App\Plugins\Hook::menu('user') as $item)
                <li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></li>
            @endforeach
            @if(auth()->user()->isAdmin())
                <li><a href="{{ route('admin.index') }}">管理面板</a></li>
                @foreach(\App\Plugins\Hook::menu('admin') as $item)
                    <li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></li>
                @endforeach
            @endif
            <li>
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="outline secondary">退出</button>
                </form>
            </li>
        @else
            <li><a href="{{ route('login') }}">登录</a></li>
            <li><a href="{{ route('register') }}" role="button">注册</a></li>
        @endauth
    </ul>

    {{-- 移动端汉堡菜单（纯 CSS/HTML，无 JS 依赖） --}}
    <details class="nav-mobile">
        <summary aria-label="菜单">&#9776;</summary>
        <div class="menu">
            <a href="{{ route('news.index') }}">公告</a>
            @auth
                <a href="{{ route('user.home') }}">用户中心</a>
                <a href="{{ route('player.index') }}">我的角色</a>
                <a href="{{ route('texture.index') }}">皮肤库</a>
                <a href="{{ route('title.index') }}">称号</a>
                @foreach(\App\Plugins\Hook::menu('user') as $item)
                    <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                @endforeach
                @if(auth()->user()->isAdmin())
                    <hr>
                    <a href="{{ route('admin.index') }}">管理面板</a>
                    @foreach(\App\Plugins\Hook::menu('admin') as $item)
                        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                    @endforeach
                @endif
                <hr>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">退出登录</button>
                </form>
            @else
                <a href="{{ route('login') }}">登录</a>
                <a href="{{ route('register') }}">注册</a>
            @endauth
        </div>
    </details>
</nav>

<main class="container">
    @if(session('success'))
        <div class="flash flash-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash flash-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="flash flash-error">
            <ul style="margin:0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<footer class="container">
    <hr>
    <small>
        Powered by Blessing Auth ·
        Yggdrasil API: <code>{{ url('api/yggdrasil') }}</code>
    </small>
</footer>
@stack('scripts')
</body>
</html>
