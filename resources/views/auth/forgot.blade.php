@extends('layouts.app')

@section('title', '找回密码')

@section('content')
<article style="max-width: 480px; margin: 0 auto;">
    <h2>找回密码</h2>
    <form method="POST" action="{{ route('password.reset') }}">
        @csrf
        <label>注册邮箱
            <input type="email" name="email" id="fp-email" value="{{ old('email') }}" required autofocus>
        </label>
        <label>邮箱验证码
            <fieldset role="group">
                <input type="text" name="captcha" inputmode="numeric" maxlength="6"
                       placeholder="6 位数字" required>
                <button type="button" id="send-code" class="outline">发送验证码</button>
            </fieldset>
        </label>
        <small id="code-tip"></small>
        <label>新密码（至少 8 位）
            <input type="password" name="password" minlength="8" required>
        </label>
        <label>确认新密码
            <input type="password" name="password_confirmation" minlength="8" required>
        </label>
        <button type="submit">重置密码并登录</button>
    </form>
    <small><a href="{{ route('login') }}">返回登录</a></small>
</article>

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
        var email = document.getElementById('fp-email').value;
        if (!email) { tip.textContent = '请先填写邮箱。'; return; }

        btn.disabled = true;
        tip.textContent = '发送中……';

        fetch('{{ route('password.code') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email: email })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              tip.textContent = res.data.message || '发送失败。';
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
@endsection
