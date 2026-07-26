<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OAuthClient extends Model
{
    protected $table = 'oauth_clients';

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'redirect_uri',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function generate(string $name, string $redirectUri): self
    {
        return static::create([
            'name' => $name,
            'client_id' => Str::random(24),
            'client_secret' => Str::random(48),
            'redirect_uri' => $redirectUri,
            'enabled' => true,
        ]);
    }

    /** @return string[] */
    public function redirectUris(): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $this->redirect_uri))));
    }

    /** 精确匹配回调地址 */
    public function validRedirect(string $uri): bool
    {
        return in_array($uri, $this->redirectUris(), true);
    }
}
