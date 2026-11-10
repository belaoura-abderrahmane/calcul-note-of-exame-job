<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ScoringSetting extends Model
{
    protected $fillable = ['rules'];

    protected $casts = [
        'rules' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('scoring.rules'));
    }

    /** القواعد الحالية (من قاعدة البيانات أو القيم الافتراضية) */
    public static function rules(): array
    {
        return Cache::rememberForever('scoring.rules', function () {
            $defaults = config('scoring');

            try {
                $stored = static::query()->first()?->rules;
            } catch (\Throwable) {
                $stored = null;
            }

            return $stored ? array_replace($defaults, $stored) : $defaults;
        });
    }
}
