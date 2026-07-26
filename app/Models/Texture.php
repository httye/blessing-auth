<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Texture extends Model
{
    protected $fillable = [
        'name',
        'type',
        'hash',
        'size',
        'uploader',
        'public',
    ];

    protected function casts(): array
    {
        return [
            'public' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader');
    }

    public function url(): string
    {
        return url('textures/'.$this->hash);
    }
}
