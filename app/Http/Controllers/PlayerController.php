<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Texture;
use App\Plugins\Hook;
use App\Services\InsufficientScoreException;
use App\Services\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        return view('player.index', [
            'players' => $request->user()->players()->with(['skin', 'cape'])->get(),
            'cost' => option_int('player_cost'),
        ]);
    }

    public function store(Request $request, ScoreService $score): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:16', 'regex:/^[A-Za-z0-9_]+$/', 'unique:players,name'],
        ], [
            'name.regex' => '角色名只能包含字母、数字和下划线。',
            'name.unique' => '该角色名已被占用。',
        ]);

        $user = $request->user();
        $cost = option_int('player_cost');

        try {
            DB::transaction(function () use ($user, $data, $cost, $score) {
                if ($cost > 0) {
                    $score->deduct($user, $cost, '创建角色 '.$data['name']);
                }
                $player = Player::create([
                    'uid' => $user->id,
                    'name' => $data['name'],
                    'uuid' => Player::offlineUuid($data['name']),
                ]);
                Hook::fire('player.created', $player);
            });
        } catch (InsufficientScoreException) {
            return back()->with('error', "积分不足，创建角色需要 {$cost} 积分。");
        }

        return back()->with('success', '角色创建成功！');
    }

    public function rename(Request $request, Player $player): RedirectResponse
    {
        $this->authorizeOwner($request, $player);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:16', 'regex:/^[A-Za-z0-9_]+$/', 'unique:players,name'],
        ], [
            'name.regex' => '角色名只能包含字母、数字和下划线。',
            'name.unique' => '该角色名已被占用。',
        ]);

        // 改名保留 UUID 不变，与正版行为一致
        $old = $player->name;
        $player->name = $data['name'];
        $player->save();
        $player->touchLastModified();
        Hook::fire('player.renamed', $player, $old);

        return back()->with('success', '角色已改名。');
    }

    public function setTexture(Request $request, Player $player): RedirectResponse
    {
        $this->authorizeOwner($request, $player);

        $data = $request->validate([
            'texture_id' => ['required', 'integer', 'exists:textures,id'],
        ]);

        $texture = Texture::findOrFail($data['texture_id']);

        if (! $texture->public && $texture->uploader !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403, '无权使用该材质。');
        }

        if ($texture->type === 'cape') {
            $player->tid_cape = $texture->id;
        } else {
            $player->tid_skin = $texture->id;
        }

        $player->save();
        $player->touchLastModified();

        return back()->with('success', '材质已应用到角色 '.$player->name.'。');
    }

    public function clearTexture(Request $request, Player $player): RedirectResponse
    {
        $this->authorizeOwner($request, $player);

        $type = $request->validate([
            'type' => ['required', 'in:skin,cape'],
        ])['type'];

        if ($type === 'cape') {
            $player->tid_cape = null;
        } else {
            $player->tid_skin = null;
        }

        $player->save();
        $player->touchLastModified();

        return back()->with('success', '已移除材质。');
    }

    public function destroy(Request $request, Player $player): RedirectResponse
    {
        $this->authorizeOwner($request, $player);

        $player->delete();
        Hook::fire('player.deleted', $player);

        return redirect()->route('player.index')->with('success', '角色已删除。');
    }

    private function authorizeOwner(Request $request, Player $player): void
    {
        if ($player->uid !== $request->user()->id) {
            abort(403, '这不是你的角色。');
        }
    }
}
