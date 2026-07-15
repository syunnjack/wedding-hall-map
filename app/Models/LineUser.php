<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineUser extends Model
{
    protected $fillable = [
        'line_user_id',
        'display_name',
    ];

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
