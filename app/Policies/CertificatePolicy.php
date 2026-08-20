<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function view(User $user, Certificate $certificate): bool
    {
        return $user->role === 'admin' || $user->id === $certificate->enrollment->user_id;
    }
}
