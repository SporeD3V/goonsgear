<?php

namespace App\Policies;

use App\Models\StockAlertSubscription;
use App\Models\User;

class StockAlertSubscriptionPolicy
{
    public function view(User $user, StockAlertSubscription $stockAlertSubscription): bool
    {
        return (int) $stockAlertSubscription->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return (int) $user->id > 0;
    }

    public function update(User $user, StockAlertSubscription $stockAlertSubscription): bool
    {
        return (int) $stockAlertSubscription->user_id === (int) $user->id;
    }

    public function delete(User $user, StockAlertSubscription $stockAlertSubscription): bool
    {
        return (int) $stockAlertSubscription->user_id === (int) $user->id;
    }
}
