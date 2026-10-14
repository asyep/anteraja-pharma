<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundMessage extends Model
{
    protected $table = 'messages';

    protected $guarded = ['id'];

    protected $hidden = ['chat_id'];
}
