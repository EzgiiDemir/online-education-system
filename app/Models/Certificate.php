<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $guarded = [];

    protected $casts = ['issued_at' => 'datetime'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
