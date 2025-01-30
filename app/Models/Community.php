<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    protected $table = 'communities';

    protected $fillable = [
        'user_id',
        'product_name',
        'product_image',
        'brand',
        'model',
        'description',
        'is_approved',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
