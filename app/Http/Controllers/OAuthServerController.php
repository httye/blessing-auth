<?php

namespace App\Http\Controllers;

use App\Models\OAuthClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OAuth 2.0 授权服务器（授权码模式）。
 * 让论坛、商店等外部应用支持「用本站账号登录」。
 *
 * 端点：
 *   GET  /oauth/authorize   授权页（需登录）
 *   POST /oauth/authorize   用户同意/拒绝
 *   POST /oauth/token       code 换 access_token
 *   GET  /oauth/userinfo    用户信息（Bearer）
 */
class OAuthServerController extends Controller
{
    protected const CODE_TTL = 300;        // 授权码 5 分钟
    protected const TOKEN_TTL_DAYS = 30;   // 访问令牌 30 天
    protected const REFRESH_TTL_DAYS = 90; // 刷新令牌 90 天

    public function authorize(Request $request)
    {
        $client = $this->resolveClient($request);

        if (is_string($client)) {
            return response($client, 400);
        }

        $redirectUri = (string) $request->query('redirect_uri');

        // pkce 挑战（S256）
        $codeChallenge = $request->input('code_challenge');
        $codeChallengeMethod = $request->input('code_challenge_method', 'plain');

        if ($codeChallenge !== null && $codeChallengeMethod !== 'S256' && $codeChallengeMethod !== 'plain') {
            return response('不支持的 code_challenge_method（仅支持 S256/plain）', 400);
        }

        // 已授权过的应用直接发码跳回，跳过授权页
        if (DB::table('oauth_authorizations')
            ->where('client_id', $client->id)
            ->where('uid', $request->user()->id)
            ->exists()) {
            return $this->issueCodeAndRedirect($request, $client, $redirectUri);
        }

        return view('oauth.authorize', [
            'client' => $client,
            'redirectUri' => $redirectUri,
            'state' => (string) $request->query('state', ''),
        ]);
    }

    public function approve(Request $request)
    {
        $client = $this->resolveClient($request);

        if (is_string($client)) {
            return response($client, 400);
        }

        $redirectUri = (string) $request->input('redirect_uri');

        if ($request->input('action') !== 'approve') {
            return redirect()->away($redirectUri.'?'.http_build_query(array_filter([
                'error' => 'access_denied',
                'state' => $request->input('state'),
            ])));
        }

        DB::table('oauth_authorizations')->insertOrIgnore([
            'client_id' => $client->id,
            'uid' => $request->user()->id,
            'authorized_at' => now(),
        ]);

        return $this->issueCodeAndRedirect($request, $client, $redirectUri);
    }

    public function token(Request $request): JsonResponse
    {
        $clientId = (string) $request->input('client_id');
        $clientSecret = (string) $request->input('client_secret');
        $code = (string) $request->input('code');
        $redirectUri = (string) $request->input('redirect_uri');

        // 判断请求类型
        $grantType = $request->input('grant_type', 'authorization_code');
        $refreshToken = (string) $request->input('refresh_token');

        if ($grantType === 'refresh_token') {
            return $this->refreshToken($clientId, $clientSecret, $refreshToken);
        }

        $client = OAuthClient::query()
            ->where('client_id', $clientId)
            ->where('enabled', true)
            ->first();

        if ($client === null || ! hash_equals($client->client_secret, $clientSecret)) {
            return response()->json(['error' => 'invalid_client'], 401);
        }

        $record = Cache::pull('oauth_server:code:'.$code); // pull：授权码一次性

        if ($record === null
            || $record['client_id'] !== $client->id
            || $record['redirect_uri'] !== $redirectUri) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        // PKCE 校验（S256/plain）
        $codeChallenge = $record['code_challenge'] ?? null;
        $codeVerifier = (string) $request->input('code_verifier', '');
        if ($codeChallenge) {
            $method = $record['code_challenge_method'] ?? 'plain';
            $expected = $method === 'S256'
                ? rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=')
                : $codeVerifier;

            if (! hash_equals($expected, $codeChallenge)) {
                return response()->json(['error' => 'invalid_grant'], 400);
            }
        }

        $token = Str::random(64);
        $refreshToken = Str::random(64);

        DB::table('oauth_access_tokens')->insert([
            'token' => hash('sha256', $token), // 存哈希，防库泄露
            'client_id' => $client->id,
            'uid' => $record['uid'],
            'scope' => 'profile',
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('oauth_refresh_tokens')->insert([
            'token' => hash('sha256', $refreshToken),
            'client_id' => $client->id,
            'uid' => $record['uid'],
            'expires_at' => now()->addDays(self::REFRESH_TTL_DAYS),
            'created_at' => now(),
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_TTL_DAYS * 86400,
            'scope' => 'profile',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * 刷新令牌：用 refresh_token 换新的 access_token + refresh_token（旋转式，旧的吊销）。
     */
    protected function refreshToken(string $clientId, string $clientSecret, string $refreshToken): JsonResponse
    {
        $client = OAuthClient::query()
            ->where('client_id', $clientId)
            ->where('enabled', true)
            ->first();

        if ($client === null || ! hash_equals($client->client_secret, $clientSecret)) {
            return response()->json(['error' => 'invalid_client'], 401);
        }

        $row = DB::table('oauth_refresh_tokens')
            ->where('token', hash('sha256', $refreshToken))
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        // 旋转：吊销旧刷新令牌，签发新的
        DB::table('oauth_refresh_tokens')->where('id', $row->id)->delete();

        $newToken = Str::random(64);
        $newRefreshToken = Str::random(64);

        DB::table('oauth_access_tokens')->insert([
            'token' => hash('sha256', $newToken),
            'client_id' => $client->id,
            'uid' => $row->uid,
            'scope' => 'profile',
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('oauth_refresh_tokens')->insert([
            'token' => hash('sha256', $newRefreshToken),
            'client_id' => $client->id,
            'uid' => $row->uid,
            'expires_at' => now()->addDays(self::REFRESH_TTL_DAYS),
            'created_at' => now(),
        ]);

        return response()->json([
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_TTL_DAYS * 86400,
            'scope' => 'profile',
            'refresh_token' => $newRefreshToken,
        ]);
    }

    public function userinfo(Request $request): JsonResponse
    {
        $bearer = (string) $request->bearerToken();

        if ($bearer === '') {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $row = DB::table('oauth_access_tokens')
            ->join('oauth_clients', 'oauth_clients.id', '=', 'oauth_access_tokens.client_id')
            ->where('oauth_clients.enabled', true)
            ->where('oauth_access_tokens.token', hash('sha256', $bearer))
            ->where('oauth_access_tokens.expires_at', '>', now())
            ->select('oauth_access_tokens.*')
            ->first();

        if ($row === null) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $user = \App\Models\User::query()->with(['currentTitle', 'players'])->find($row->uid);

        if ($user === null || $user->isBanned()) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'nickname' => $user->nickname,
            'score' => $user->score,
            'is_admin' => $user->isAdmin(),
            'title' => $user->currentTitle?->only(['name', 'color']),
            'players' => $user->players->map(fn ($p) => [
                'name' => $p->name,
                'uuid' => $p->uuid,
            ])->values(),
            'registered_at' => $user->created_at?->toIso8601String(),
        ]);
    }

    protected function issueCodeAndRedirect(Request $request, OAuthClient $client, string $redirectUri)
    {
        $code = Str::random(48);

        $payload = [
            'client_id' => $client->id,
            'uid' => $request->user()->id,
            'redirect_uri' => $redirectUri,
        ];

        // 缓存 code_challenge 供 token 端点校验
        $codeChallenge = $request->input('code_challenge', $request->query('code_challenge'));
        if ($codeChallenge) {
            $payload['code_challenge'] = $codeChallenge;
            $payload['code_challenge_method'] = $request->input('code_challenge_method', $request->query('code_challenge_method', 'plain'));
        }

        Cache::put('oauth_server:code:'.$code, $payload, self::CODE_TTL);

        return redirect()->away($redirectUri.'?'.http_build_query(array_filter([
            'code' => $code,
            'state' => $request->input('state', $request->query('state')),
        ])));
    }

    /** 校验 client_id / redirect_uri / response_type，失败返回错误文本（不能重定向到未验证的 URI） */
    protected function resolveClient(Request $request): OAuthClient|string
    {
        $client = OAuthClient::query()
            ->where('client_id', (string) $request->input('client_id', $request->query('client_id')))
            ->where('enabled', true)
            ->first();

        if ($client === null) {
            return '无效的 client_id。';
        }

        $redirectUri = (string) $request->input('redirect_uri', $request->query('redirect_uri'));

        if (! $client->validRedirect($redirectUri)) {
            return 'redirect_uri 与应用登记的回调地址不匹配。';
        }

        $responseType = $request->input('response_type', $request->query('response_type', 'code'));

        if ($responseType !== 'code') {
            return '仅支持 response_type=code。';
        }

        return $client;
    }
}
