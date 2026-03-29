<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsAdmin(?User $user = null): User
    {
        $adminUser = $user ?? User::factory()->admin()->create();
        $this->actingAs($adminUser);

        return $adminUser;
    }
}
