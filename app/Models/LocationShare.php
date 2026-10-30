<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A person's last shared position. Exists only while they choose to share. */
class LocationShare extends Model
{
    protected $fillable = ['user_id', 'couple_space_id', 'lat', 'lng', 'accuracy', 'sealed', 'located_at'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'accuracy' => 'integer', 'located_at' => 'datetime'];
    }

    /** Great-circle distance in metres. */
    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $a = deg2rad($lat2 - $lat1);
        $b = deg2rad($lng2 - $lng1);
        $h = sin($a / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($b / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($h))));
    }
}
