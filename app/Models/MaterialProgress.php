<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialProgress extends Model
{
    protected $guarded = [];

    protected $casts = ['completed' => 'boolean', 'completed_at' => 'datetime'];
}
