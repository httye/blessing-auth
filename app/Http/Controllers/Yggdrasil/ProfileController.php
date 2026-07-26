<?php

namespace App\Http\Controllers\Yggdrasil;

use App\Exceptions\YggdrasilException;
use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /** POST /api/profiles/minecraft — 批量按角色名查询 */
    public function search(Request $request): JsonResponse
    {
        $names = $request->json()->all();

        if (! is_array($names)) {
            throw YggdrasilException::illegalArgument('Request body must be a JSON array.');
        }

        $names = array_values(array_filter($names, 'is_string'));

        if (count($names) > 5) {
            throw YggdrasilException::illegalArgument('Not more than 5 profile names per call is allowed.');
        }

        $profiles = Player::query()
            ->whereIn('name', $names)
            ->get()
            ->map(fn (Player $p) => [
                'id' => $p->undashedUuid(),
                'name' => $p->name,
            ])
            ->values();

        return response()->json($profiles);
    }
}
