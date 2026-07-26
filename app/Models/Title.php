<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Title extends Model
{
    protected $fillable = [
        'name',
        'color',
        'price',
        'purchasable',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'purchasable' => 'boolean',
        ];
    }

    public function holders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_titles', 'title_id', 'uid')
            ->withPivot('acquired_at');
    }
}
