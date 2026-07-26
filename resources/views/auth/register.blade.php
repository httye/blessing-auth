@extends('layouts.app')

@section('title', '注册')

@section('content')
<article style="max-width: 480px; margin: 0 auto;">
    <h2>注册</h2>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label>邮箱
            <input type="email" name="email" id="reg-email" value="{{ old('email') }}" required autofocus>
        </label>

        @if(option_bool('email_verification'))
            <label>邮箱验证码
                <fieldset role="group">
                    <input type="text" name="captcha" inputmode="numeric" maxlength="6"
                           placeholder="6 位数字" autocomplete="one-time-code" required>
                    <button type="button" id="send-code" class="outline">发送验证码</button>
                </fieldset>
            </label>
            <small id="code-tip"></small>
        @endif

        <label>昵称
            <input type="text" name="nickname" value="{{ old('nickname') }}" maxlength="50" required>
        </label>
        <label>密码（至少 8 位）
            <input type="password" name="password" minlength="8" required>
        </label>
        <label>确认密码
            <input type="password" name="password_confirmation" minlength="8" required>
        </label>
        <button type="submit">注册</button>
    </form>
    <small>已有账号？<a href="{{ route('login') }}">直接登录</a></small>
</article>

@if(option_bool('email_verification'))
<script>
(function () {
    var btn = document.getElementById('send-code');
    var tip = document.getElementById('code-tip');
    var cooldown = 0, timer = null;

    function tick() {
        if (cooldown > 0) {
            btn.disabled = true;
            btn.textContent = cooldown + 's';
            cooldown--;
        } else {
            btn.disabled = false;
            btn.textContent = '发送验证码';
            clearInterval(timer);
        }
    }

    btn.addEventListener('click', function () {
        var email = document.getElementById('reg-email').value;
        if (!email) { tip.textContent = '请先填写邮箱。'; return; }

        btn.disabled = true;
        tip.textContent = '发送中……';

        fetch('{{ route('register.code') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email: email })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              tip.textContent = res.data.message ||
                  (res.data.errors && Object.values(res.data.errors)[0][0]) || '发送失败。';
              if (res.ok) {
                  cooldown = 60;
                  timer = setInterval(tick, 1000);
                  tick();
              } else {
                  btn.disabled = false;
              }
          })
          .catch(function () { tip.textContent = '网络错误。'; btn.disabled = false; });
    });
})();
</script>
@endif
@endsection
