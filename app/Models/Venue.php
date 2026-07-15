<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'description',
        'area',
        'address',
        'phone',
        'lat',
        'lng',
        'congestion_reports',
        'average_congestion',
        'likes_count',
    ];

    protected function casts(): array
    {
        return [
            'congestion_reports' => 'array',
            'average_congestion' => 'float',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
