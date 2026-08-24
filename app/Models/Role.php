<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A workspace a user can be granted access to (learner/instructor/manager/
 * admin). See User::roles() -- users.role stays the single "primary" role
 * for backward-compatible routing; this table is what makes holding more
 * than one role possible at all.
 */
class Role extends Model
{
    protected $guarded = [];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
