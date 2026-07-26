<?php

namespace App\Http\Controllers;

use App\Models\Title;
use App\Plugins\Hook;
use App\Services\InsufficientScoreException;
use App\Services\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TitleController extends Controller
{
    /** 称号中心：已拥有 + 可购买 */
    public function index(Request $request)
    {
        $user = $request->user();
        $owned = $user->titles()->get();
        $ownedIds = $owned->pluck('id')->all();

        return view('title.index', [
            'user' => $user,
            'owned' => $owned,
            'shop' => Title::query()
                ->where('purchasable', true)
                ->whereNotIn('id', $ownedIds)
                ->orderBy('price')
                ->get(),
        ]);
    }

    public function buy(Request $request, Title $title, ScoreService $score): RedirectResponse
    {
        $user = $request->user();

        if (! $title->purchasable) {
            return back()->with('error', '该称号不可购买。');
        }

        if ($user->titles()->where('title_id', $title->id)->exists()) {
            return back()->with('error', '你已拥有该称号。');
        }

        try {
            DB::transaction(function () use ($user, $title, $score) {
                if ($title->price > 0) {
                    $score->deduct($user, $title->price, '购买称号「'.$title->name.'」');
                }
                $user->titles()->attach($title->id, ['acquired_at' => now()]);
                Hook::fire('title.acquired', $user, $title);
            });
        } catch (InsufficientScoreException) {
            return back()->with('error', "积分不足，购买需要 {$title->price} 积分。");
        }

        return back()->with('success', '称号「'.$title->name.'」已入手！');
    }

    /** 佩戴 / 取下 */
    public function wear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $titleId = $data['title_id'] ?? null;

        if ($titleId !== null && ! $user->titles()->where('title_id', $titleId)->exists()) {
            return back()->with('error', '你没有该称号。');
        }

        $user->update(['current_title_id' => $titleId]);

        return back()->with('success', $titleId === null ? '已取下称号。' : '称号已佩戴。');
    }
}
