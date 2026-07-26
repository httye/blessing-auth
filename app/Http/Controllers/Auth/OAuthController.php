<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OAuthIdentity;
use App\Models\User;
use App\Plugins\Hook;
use App\Services\OAuth\Provider;
use App\Services\OAuth\ProviderRegistry;
use App\Services\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    /** 跳转到提供商授权页 */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $p = $this->resolveProvider($provider);

        $state = Str::random(40);
        $request->session()->put('oauth.state', $state);
        $request->session()->put('oauth.provider', $p->name);
        // 已登录用户发起 = 绑定操作；游客发起 = 登录操作
        $request->session()->put('oauth.binding', Auth::check());

        $query = http_build_query([
            'client_id' => $p->clientId(),
            'redirect_uri' => $this->redirectUri($p),
            'response_type' => 'code',
            'scope' => $p->scope,
            'state' => $state,
        ]);

        return redirect()->away($p->authUrl.'?'.$query);
    }

    /** 提供商回调 */
    public function callback(Request $request, string $provider, ScoreService $score): RedirectResponse
    {
        $p = $this->resolveProvider($provider);

        // state 校验（防 CSRF）
        $state = $request->session()->pull('oauth.state');
        $binding = (bool) $request->session()->pull('oauth.binding', false);
        $request->session()->forget('oauth.provider');

        if (! $state || $request->query('state') !== $state) {
            return redirect()->route('login')->with('error', '登录状态校验失败，请重试。');
        }

        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('login')->with('error', '授权被取消或失败。');
        }

        // code 换 token，再换用户信息
        $remote = $this->fetchRemoteUser($p, (string) $request->query('code'));

        if ($remote === null) {
            return redirect()->route('login')->with('error', '获取第三方账号信息失败，请重试。');
        }

        $identity = OAuthIdentity::query()
            ->where('provider', $p->name)
            ->where('provider_user_id', $remote['id'])
            ->first();

        // 场景一：已登录用户绑定
        if ($binding && Auth::check()) {
            if ($identity !== null && $identity->uid !== Auth::id()) {
                return redirect()->route('user.profile')->with('error', '该第三方账号已被其他用户绑定。');
            }

            OAuthIdentity::updateOrCreate(
                ['uid' => Auth::id(), 'provider' => $p->name],
                [
                    'provider_user_id' => $remote['id'],
                    'provider_email' => $remote['email'] ?? null,
                    'provider_nickname' => $remote['nickname'] ?? null,
                ],
            );

            return redirect()->route('user.profile')->with('success', $p->title.' 绑定成功。');
        }

        // 场景二：已有绑定 → 直接登录
        if ($identity !== null) {
            $user = $identity->user;

            if ($user->isBanned()) {
                return redirect()->route('login')->with('error', '该账号已被封禁。');
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();
            Hook::fire('auth.login', $user);

            return redirect()->intended(route('user.home'));
        }

        // 场景三：未绑定 → 自动注册（需开放注册）
        if (! option_bool('allow_register')) {
            return redirect()->route('login')->with('error', '该第三方账号未绑定本站用户，且本站已关闭注册。');
        }

        $email = $remote['email'] ?? null;

        // 第三方邮箱已存在本站账号时，出于安全不自动合并（邮箱未必已验证）
        if ($email && User::query()->where('email', $email)->exists()) {
            return redirect()->route('login')->with(
                'error',
                "邮箱 {$email} 已注册过本站账号，请先用密码登录，再到「账号设置」中绑定 {$p->title}。"
            );
        }

        $user = User::create([
            'email' => $email ?? ($p->name.'_'.$remote['id'].'@oauth.local'),
            'nickname' => $remote['nickname'] ?? ($p->title.'用户'),
            'password' => Str::random(32), // 随机密码，用户可稍后修改
            'score' => 0,
            'permission' => User::query()->count() === 0
                ? User::PERMISSION_SUPER
                : User::PERMISSION_NORMAL,
        ]);

        OAuthIdentity::create([
            'uid' => $user->id,
            'provider' => $p->name,
            'provider_user_id' => $remote['id'],
            'provider_email' => $email,
            'provider_nickname' => $remote['nickname'] ?? null,
        ]);

        if (($initial = option_int('initial_score')) > 0) {
            $score->grant($user, $initial, '注册奖励（'.$p->title.'）');
        }

        Hook::fire('auth.registered', $user);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('user.home')
            ->with('success', '已通过 '.$p->title.' 创建账号。请尽快在「账号设置」中设置密码，游戏内登录需要用到。');
    }

    /** 解绑（需已设置密码的账号至少保留一种登录方式） */
    public function unbind(Request $request, string $provider): RedirectResponse
    {
        $identity = OAuthIdentity::query()
            ->where('uid', $request->user()->id)
            ->where('provider', $provider)
            ->first();

        if ($identity === null) {
            return back()->with('error', '未绑定该第三方账号。');
        }

        $identity->delete();

        return back()->with('success', '已解绑。');
    }

    protected function resolveProvider(string $name): Provider
    {
        $p = ProviderRegistry::get($name);

        abort_if($p === null || ! $p->configured(), 404, '该登录方式未启用。');

        return $p;
    }

    protected function redirectUri(Provider $p): string
    {
        return route('oauth.callback', ['provider' => $p->name]);
    }

    /** @return array{id: string, email: ?string, nickname: ?string}|null */
    protected function fetchRemoteUser(Provider $p, string $code): ?array
    {
        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($p->tokenUrl, array_merge([
                    'client_id' => $p->clientId(),
                    'client_secret' => $p->clientSecret(),
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri($p),
                    'grant_type' => 'authorization_code',
                ], $p->extraTokenParams));

            $accessToken = $tokenResponse->json('access_token');

            if (! $accessToken) {
                return null;
            }

            $userResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->get($p->userUrl);

            $raw = $userResponse->json();

            if (! is_array($raw)) {
                return null;
            }

            $mapped = ($p->mapUser)($raw);

            return isset($mapped['id']) && $mapped['id'] !== '' ? $mapped : null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("OAuth {$p->name} 回调失败：{$e->getMessage()}");

            return null;
        }
    }
}
