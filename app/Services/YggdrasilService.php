<?php

namespace App\Services;

use App\Exceptions\YggdrasilException;
use App\Models\Player;
use App\Models\User;
use App\Models\YggToken;
use App\Plugins\Hook;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class YggdrasilService
{
    /**
     * 用邮箱（或唯一角色名）+ 密码认证，签发新令牌。
     *
     * @return array{user: User, token: YggToken}
     */
    public function authenticate(string $username, string $password, ?string $clientToken): array
    {
        $user = $this->resolveUser($username);

        if ($user === null || $user->isBanned() || ! Hash::check($password, $user->password)) {
            Hook::fire('ygg.auth.failed', $username);
            throw YggdrasilException::invalidCredentials();
        }

        // 超出上限时淘汰最旧令牌
        $limit = option_int('tokens_limit');
        $count = $user->yggTokens()->count();
        if ($count >= $limit) {
            $user->yggTokens()
                ->orderBy('created_at')
                ->limit($count - $limit + 1)
                ->delete();
        }

        // 若用户名就是角色名，则自动选定该角色
        $selectedUuid = null;
        if (! str_contains($username, '@')) {
            $selectedUuid = $user->players()->where('name', $username)->value('uuid');
        } elseif ($user->players()->count() === 1) {
            $selectedUuid = $user->players()->value('uuid');
        }

        $token = YggToken::create([
            'access_token' => bin2hex(random_bytes(16)),
            'client_token' => $clientToken ?: Str::uuid()->toString(),
            'owner' => $user->id,
            'player_uuid' => $selectedUuid,
            'created_at' => now(),
        ]);

        Hook::fire('ygg.authenticated', $user, $token);

        return ['user' => $user, 'token' => $token];
    }

    /**
     * 刷新令牌。旧令牌吊销，签发同 clientToken 的新令牌。
     *
     * @param  array{id?: string, name?: string}|null  $selectedProfile
     * @return array{user: User, token: YggToken}
     */
    public function refresh(string $accessToken, ?string $clientToken, ?array $selectedProfile): array
    {
        $old = YggToken::query()->where('access_token', $accessToken)->first();

        if ($old === null || ! $old->isRefreshable()) {
            throw YggdrasilException::invalidToken();
        }

        if ($clientToken !== null && $old->client_token !== $clientToken) {
            throw YggdrasilException::invalidToken();
        }

        $user = $old->user;
        $playerUuid = $old->player_uuid;

        // 处理 selectedProfile：只能绑定自己名下且尚未选定角色的令牌
        if ($selectedProfile !== null) {
            if ($playerUuid !== null) {
                throw new YggdrasilException('Access token already has a profile assigned.', 'IllegalArgumentException', 400);
            }

            $undashed = $selectedProfile['id'] ?? '';
            $player = $user->players()
                ->get()
                ->first(fn (Player $p) => $p->undashedUuid() === $undashed);

            if ($player === null) {
                throw YggdrasilException::invalidToken();
            }

            $playerUuid = $player->uuid;
        }

        $new = YggToken::create([
            'access_token' => bin2hex(random_bytes(16)),
            'client_token' => $old->client_token,
            'owner' => $user->id,
            'player_uuid' => $playerUuid,
            'created_at' => now(),
        ]);

        $old->delete();

        return ['user' => $user, 'token' => $new];
    }

    /** validate：令牌有效返回 YggToken，无效抛 403 */
    public function validateToken(string $accessToken, ?string $clientToken): YggToken
    {
        $token = YggToken::query()->where('access_token', $accessToken)->first();

        if ($token === null || ! $token->isValid()) {
            throw YggdrasilException::invalidToken();
        }

        if ($clientToken !== null && $token->client_token !== $clientToken) {
            throw YggdrasilException::invalidToken();
        }

        return $token;
    }

    public function invalidate(string $accessToken): void
    {
        // 规范：无论令牌是否有效都返回 204
        YggToken::query()->where('access_token', $accessToken)->delete();
    }

    public function signout(string $username, string $password): void
    {
        $user = $this->resolveUser($username);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw YggdrasilException::invalidCredentials();
        }

        $user->yggTokens()->delete();
    }

    /** 支持邮箱登录；无 @ 时按角色名反查用户（non_email_login 特性） */
    private function resolveUser(string $username): ?User
    {
        if (str_contains($username, '@')) {
            return User::query()->where('email', $username)->first();
        }

        return Player::query()->where('name', $username)->first()?->user;
    }
}
