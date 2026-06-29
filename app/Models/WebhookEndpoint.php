<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    protected $fillable = ['user_id', 'name', 'url', 'method', 'headers', 'is_active', 'transform_prompt'];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
