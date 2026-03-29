<?php

namespace App\Policies;

use App\Models\TagFollow;
use App\Models\User;

class TagFollowPolicy
{
    public function view(User $user, TagFollow $tagFollow): bool
    {
        return (int) $tagFollow->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return (int) $user->id > 0;
    }

    public function update(User $user, TagFollow $tagFollow): bool
    {
        return (int) $tagFollow->user_id === (int) $user->id;
    }

    public function delete(User $user, TagFollow $tagFollow): bool
    {
        return (int) $tagFollow->user_id === (int) $user->id;
    }
}
