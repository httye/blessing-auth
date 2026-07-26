<?php

namespace App\Http\Controllers;

use App\Models\Texture;
use App\Plugins\Hook;
use App\Services\TextureStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class TextureController extends Controller
{
    public function __construct(private readonly TextureStorage $storage)
    {
    }

    /** 皮肤库：公开材质 + 自己的私有材质 */
    public function index(Request $request)
    {
        $query = Texture::query()->with('owner')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->user()) {
            $query->where(function ($q) use ($request) {
                $q->where('public', true)->orWhere('uploader', $request->user()->id);
            });
        } else {
            $query->where('public', true);
        }

        return view('texture.index', [
            'textures' => $query->paginate(24)->withQueryString(),
        ]);
    }

    public function showUploadForm()
    {
        return view('texture.upload');
    }

    public function upload(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', 'in:skin,cape'],
            'file' => ['required', 'file', 'mimes:png', 'max:1024'], // ≤1MB
            'public' => ['nullable', 'boolean'],
        ]);

        // 单用户配额限制
        $maxTexture = option_int('max_textures_per_user', 100);
        if ($maxTexture > 0) {
            $count = Texture::query()->where('uploader', $request->user()->id)->count();
            if ($count >= $maxTexture) {
                return back()->with('error', "你已达到材质上传上限（{$maxTexture} 个）。请删除部分后再上传。");
            }
        }

        try {
            $result = $this->storage->store($data['file'], $data['type']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $texture = Texture::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'hash' => $result['hash'],
            'size' => $result['size'],
            'uploader' => $request->user()->id,
            'public' => $request->boolean('public', true),
        ]);

        Hook::fire('texture.uploaded', $texture);

        return redirect()->route('texture.index')->with('success', '材质上传成功！');
    }

    public function destroy(Request $request, Texture $texture): RedirectResponse
    {
        if ($texture->uploader !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403, '无权删除该材质。');
        }

        $hash = $texture->hash;
        $texture->delete();
        $this->storage->deleteIfUnused($hash);

        return back()->with('success', '材质已删除。');
    }

    /** 提供材质 PNG 文件（Yggdrasil 材质 URL 指向这里） */
    public function raw(string $hash): Response
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $hash), 404);

        $disk = $this->storage->disk();
        abort_unless($disk->exists($hash), 404);

        return response($disk->get($hash), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.$hash.'"',
        ]);
    }
}
