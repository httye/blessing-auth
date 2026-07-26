<?php

namespace App\Http\Controllers\Yggdrasil;

use App\Exceptions\YggdrasilException;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\User;
use App\Services\ProfileSerializer;
use App\Services\YggdrasilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly YggdrasilService $ygg,
        private readonly ProfileSerializer $serializer,
    ) {
    }

    public function authenticate(Request $request): JsonResponse
    {
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');
        $clientToken = $request->input('clientToken');

        if ($username === '' || $password === '') {
            throw YggdrasilException::invalidCredentials();
        }

        ['user' => $user, 'token' => $token] = $this->ygg->authenticate($username, $password, $clientToken);

        $availableProfiles = $user->players
            ->map(fn (Player $p) => $this->profileStub($p))
            ->values()
            ->all();

        $response = [
            'accessToken' => $token->access_token,
            'clientToken' => $token->client_token,
            'availableProfiles' => $availableProfiles,
        ];

        if ($token->player_uuid !== null) {
            $selected = $user->players->firstWhere('uuid', $token->player_uuid);
            if ($selected) {
                $response['selectedProfile'] = $this->profileStub($selected);
            }
        }

        if ($request->boolean('requestUser')) {
            $response['user'] = $this->serializeUser($user);
        }

        return response()->json($response);
    }

    public function refresh(Request $request): JsonResponse
    {
        $accessToken = (string) $request->input('accessToken', '');
        $clientToken = $request->input('clientToken');
        $selectedProfile = $request->input('selectedProfile');

        ['user' => $user, 'token' => $token] = $this->ygg->refresh($accessToken, $clientToken, $selectedProfile);

        $response = [
            'accessToken' => $token->access_token,
            'clientToken' => $token->client_token,
        ];

        if ($token->player_uuid !== null) {
            $selected = $user->players->firstWhere('uuid', $token->player_uuid);
            if ($selected) {
                $response['selectedProfile'] = $this->profileStub($selected);
            }
        }

        if ($request->boolean('requestUser')) {
            $response['user'] = $this->serializeUser($user);
        }

        return response()->json($response);
    }

    public function validateToken(Request $request): Response
    {
        $this->ygg->validateToken(
            (string) $request->input('accessToken', ''),
            $request->input('clientToken'),
        );

        return response()->noContent();
    }

    public function invalidate(Request $request): Response
    {
        $this->ygg->invalidate((string) $request->input('accessToken', ''));

        return response()->noContent();
    }

    public function signout(Request $request): Response
    {
        $this->ygg->signout(
            (string) $request->input('username', ''),
            (string) $request->input('password', ''),
        );

        return response()->noContent();
    }

    /** 不含 properties 的简单 profile（authenticate/refresh 响应用） */
    private function profileStub(Player $player): array
    {
        return [
            'id' => $player->undashedUuid(),
            'name' => $player->name,
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => md5((string) $user->id),
            'properties' => [
                ['name' => 'preferredLanguage', 'value' => 'zh_CN'],
            ],
        ];
    }
}
