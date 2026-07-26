<?php

namespace App\Http\Controllers\Yggdrasil;

use App\Http\Controllers\Controller;
use App\Services\ProfileSerializer;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function __construct(private readonly ProfileSerializer $serializer)
    {
    }

    /** GET /api/yggdrasil — authlib-injector API 元数据 */
    public function index(): JsonResponse
    {
        $domains = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) option('skin_domains'))
        )));

        if ($domains === []) {
            $domains = [parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost'];
        }

        return response()->json([
            'meta' => [
                'serverName' => option('site_name', config('app.name')),
                'implementationName' => 'blessing-auth',
                'implementationVersion' => '1.0.0',
                'links' => [
                    'homepage' => url('/'),
                    'register' => route('register'),
                ],
                'feature.non_email_login' => true,
            ],
            'skinDomains' => $domains,
            'signaturePublickey' => $this->serializer->publicKeyPem(),
        ]);
    }
}
