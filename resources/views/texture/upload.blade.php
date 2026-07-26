@extends('layouts.app')

@section('title', '上传材质')

@section('content')
<article style="max-width: 560px; margin: 0 auto;">
    <h2>上传材质</h2>
    <form method="POST" action="{{ route('texture.upload') }}" enctype="multipart/form-data">
        @csrf
        <label>材质名称
            <input type="text" name="name" value="{{ old('name') }}" maxlength="50" required>
        </label>
        <label>类型
            <select name="type" required>
                <option value="skin" @selected(old('type')==='skin')>皮肤（64x32 / 64x64）</option>
                <option value="cape" @selected(old('type')==='cape')>披风（64x32）</option>
            </select>
        </label>
        <label>PNG 文件（≤ 1MB）
            <input type="file" name="file" accept="image/png" required>
        </label>
        <label>
            <input type="checkbox" name="public" value="1" checked> 公开到皮肤库
        </label>
        <button type="submit">上传</button>
    </form>
</article>
@endsection
