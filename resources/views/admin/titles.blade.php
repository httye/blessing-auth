@extends('layouts.app')

@section('title', '称号管理')

@section('content')
<h2>称号管理</h2>

<article>
    <h4>创建称号</h4>
    <form method="POST" action="{{ route('admin.titles') }}">
        @csrf
        <div class="grid">
            <label>名称
                <input type="text" name="name" maxlength="30" required>
            </label>
            <label>颜色
                <input type="color" name="color" value="#6c757d">
            </label>
            <label>价格（积分）
                <input type="number" name="price" value="0" min="0" required>
            </label>
            <label style="align-self:end">
                <input type="checkbox" name="purchasable" value="1"> 可在商店购买
            </label>
        </div>
        <button type="submit">创建</button>
    </form>
    <small>不勾选"可购买"的称号只能由管理员授予（如活动奖励、荣誉称号）。</small>
</article>

<div class="table-wrap">
<table>
    <thead>
    <tr><th>预览</th><th>价格</th><th>可购买</th><th>持有人数</th><th>授予 / 删除</th></tr>
    </thead>
    <tbody>
    @foreach($titles as $title)
        <tr>
            <td><strong style="color:{{ $title->color }}">[{{ $title->name }}]</strong></td>
            <td>{{ $title->price }}</td>
            <td>{{ $title->purchasable ? '是' : '否' }}</td>
            <td>{{ $title->holders_count }}</td>
            <td>
                <form method="POST" action="{{ route('admin.titles.grant', $title) }}" style="display:inline-flex;gap:.3rem">
                    @csrf
                    <input type="email" name="email" placeholder="用户邮箱" required style="width:12rem;padding:.2rem">
                    <button type="submit" class="outline" style="padding:.2rem .5rem;font-size:.85em">授予</button>
                </form>
                <form method="POST" action="{{ route('admin.titles.destroy', $title) }}" style="display:inline"
                      onsubmit="return confirm('删除称号「{{ $title->name }}」？持有者将同时失去该称号。')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="outline secondary" style="padding:.2rem .5rem;font-size:.85em">删除</button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
