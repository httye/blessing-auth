<?php

namespace App\Http\Controllers\Yggdrasil;

use App\Exceptions\YggdrasilException;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Plugins\Hook;
use App\Services\ProfileSerializer;
use App\Services\YggdrasilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SessionController extends Controller
{
    public function __construct(
        private readonly YggdrasilService $ygg,
        private readonly ProfileSerializer $serializer,
    ) {
    }

    /** 客户端进服：记录 serverId → 角色 的映射 */
    public function join(Request $request): Response
    {
        $accessToken = (string) $request->input('accessToken', '');
        $selectedProfile = (string) $request->input('selectedProfile', '');
        $serverId = (string) $request->input('serverId', '');

        if ($serverId === '') {
            throw YggdrasilException::illegalArgument('serverId is missing.');
        }

        $token = $this->ygg->validateToken($accessToken, null);

        if ($token->player_uuid === null) {
            throw YggdrasilException::invalidToken();
        }

        $player = Player::query()->where('uuid', $token->player_uuid)->first();

        if ($player === null || $player->undashedUuid() !== $selectedProfile) {
            throw YggdrasilException::invalidToken();
        }

        // 30 秒内有效，供服务端 hasJoined 校验
        Cache::put('ygg:join:'.$serverId, [
            'player_id' => $player->id,
            'ip' => $request->ip(),
        ], now()->addSeconds(30));

        return response()->noContent();
    }

    /** 服务端校验：返回带签名的完整档案 */
    public function hasJoined(Request $request): JsonResponse|Response
    {
        $username = (string) $request->query('username', '');
        $serverId = (string) $request->query('serverId', '');
        $ip = $request->query('ip');

        $record = Cache::get('ygg:join:'.$serverId);

        if ($record === null) {
            return response()->noContent();
        }

        $player = Player::query()->with(['skin', 'cape'])->find($record['player_id']);

        if ($player === null || $player->name !== $username) {
            return response()->noContent();
        }

        if ($ip !== null && $ip !== $record['ip']) {
            return response()->noContent();
        }

        Cache::forget('ygg:join:'.$serverId);

        Hook::fire('ygg.joined', $player, $serverId);

        return response()->json($this->serializer->serialize($player, signed: true));
    }

    /** 角色档案查询 */
    public function profile(Request $request, string $uuid): JsonResponse|Response
    {
        $dashed = $this->dashUuid($uuid);

        if ($dashed === null) {
            return response()->noContent();
        }

        $player = Player::query()->with(['skin', 'cape'])->where('uuid', $dashed)->first();

        if ($player === null) {
            return response()->noContent();
        }

        $signed = $request->query('unsigned', 'true') === 'false';

        return response()->json($this->serializer->serialize($player, $signed));
    }

    private function dashUuid(string $uuid): ?string
    {
        $hex = strtolower(str_replace('-', '', $uuid));

        if (! preg_match('/^[0-9a-f]{32}$/', $hex)) {
            return null;
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
