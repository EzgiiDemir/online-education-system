<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialVersion extends Model
{
    protected $guarded = [];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function replacedBy()
    {
        return $this->belongsTo(User::class, 'replaced_by');
    }
}
