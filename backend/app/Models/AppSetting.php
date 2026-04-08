<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function getAppConfig(): array
    {
        $settings = static::whereIn('key', ['min_version', 'update_url', 'force_update'])
            ->pluck('value', 'key');

        return [
            'min_version' => $settings->get('min_version', '1.0.0'),
            'update_url' => $settings->get('update_url', ''),
            'force_update' => filter_var($settings->get('force_update', 'false'), FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
