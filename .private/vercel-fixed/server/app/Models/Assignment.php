<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $guarded = ['id'];

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
