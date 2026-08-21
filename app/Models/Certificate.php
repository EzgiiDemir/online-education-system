<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $casts = ['issued_at' => 'datetime'];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
