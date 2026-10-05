<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aspect extends Model
{
    protected $table = 'aspects';

    protected $fillable = [
        'name',
        'slug',
        'color',
        'order',
    ];

    protected $appends = [
        'text_color',
    ];

    public function getTextColorAttribute(): string
    {
        $hex = ltrim($this->color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $yiq >= 128 ? '#000' : '#fff';
    }

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'card_aspect', 'aspect_id', 'card_id');
    }
}
