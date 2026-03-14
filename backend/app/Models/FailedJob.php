<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'failed_at' => 'datetime',
    ];

    public function getJobNameAttribute(): string
    {
        $class = json_decode($this->payload, true)['data']['commandName'] ?? 'Unknown';

        return basename(str_replace('\\', '/', $class));
    }
}
