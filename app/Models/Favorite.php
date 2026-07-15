<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $fillable = [
        'line_user_id',
        'venue_id',
    ];

    public function lineUser()
    {
        return $this->belongsTo(LineUser::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
