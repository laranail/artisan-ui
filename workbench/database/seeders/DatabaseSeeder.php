<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Workbench\App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Two users, so both sides of the Access ability can be seen. Workbench only; the
     * password is a fixture, printed in CONTRIBUTING.md.
     *
     * Idempotent: testbench seeds after migrating (the `seeders` key) and the build seeds
     * again, so it must be safe to run twice.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(['email' => 'admin@example.test'], ['name' => 'Admin', 'password' => 'workbench-password', 'is_admin' => true]);
        User::query()->updateOrCreate(['email' => 'member@example.test'], ['name' => 'Member', 'password' => 'workbench-password', 'is_admin' => false]);
    }
}
