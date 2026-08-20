<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialProgress extends Model
{
    protected $guarded = [];

    protected $casts = ['completed' => 'boolean', 'completed_at' => 'datetime', 'last_heartbeat_at' => 'datetime'];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
