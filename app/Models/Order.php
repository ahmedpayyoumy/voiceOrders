<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id', 'transcript', 'parsed_data', 'status', 'response_log'];

    protected function casts(): array
    {
        return [
            'parsed_data' => 'array',
            'response_log' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
