<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrganizationUnit extends Model
{
    use Auditable;

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * This unit's id plus every descendant unit's id, at any depth --
     * a user is considered part of a unit if they're directly in it or in
     * any sub-unit under it. Depth-first, cycle-safe (parent_id changes
     * always go through Filament's own record, so a cycle can't normally
     * happen, but a corrupted/manual DB edit shouldn't be able to hang
     * this in an infinite loop either).
     */
    public function descendantIds(): Collection
    {
        $ids = collect([$this->id]);
        $seen = collect([$this->id]);
        $frontier = collect([$this->id]);

        while ($frontier->isNotEmpty()) {
            $childIds = OrganizationUnit::whereIn('parent_id', $frontier)->pluck('id')->diff($seen);
            if ($childIds->isEmpty()) {
                break;
            }
            $ids = $ids->merge($childIds);
            $seen = $seen->merge($childIds);
            $frontier = $childIds;
        }

        return $ids->values();
    }

    /**
     * Every user in this unit or any sub-unit beneath it.
     */
    public function allUsers()
    {
        return User::whereIn('organization_unit_id', $this->descendantIds());
    }
}
