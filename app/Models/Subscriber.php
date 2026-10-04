<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['email', 'token', 'is_active', 'ip_address'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
