<?php

namespace App\Policies;

use App\Models\SizeProfile;
use App\Models\User;

class SizeProfilePolicy
{
    public function view(User $user, SizeProfile $sizeProfile): bool
    {
        return (int) $sizeProfile->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return (int) $user->id > 0;
    }

    public function update(User $user, SizeProfile $sizeProfile): bool
    {
        return (int) $sizeProfile->user_id === (int) $user->id;
    }

    public function delete(User $user, SizeProfile $sizeProfile): bool
    {
        return (int) $sizeProfile->user_id === (int) $user->id;
    }
}
