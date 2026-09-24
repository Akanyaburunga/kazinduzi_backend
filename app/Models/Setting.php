<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Read a setting's value, falling back to $default when absent.
     */
    public static function get(string $key, $default = null)
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Write (create or update) a setting value.
     */
    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Remove a setting so it reverts to its default.
     */
    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }
}