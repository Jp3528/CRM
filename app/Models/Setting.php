<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'is_public', 'description'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }
}
