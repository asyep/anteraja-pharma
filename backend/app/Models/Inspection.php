<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['packaging' => 'boolean', 'seal' => 'boolean', 'label' => 'boolean', 'passed' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
