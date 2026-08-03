<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Workbench\App\Models\Category;
use Workbench\App\Models\Role;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $roles = collect(['Admin', 'Editor', 'Support', 'Analyst', 'Viewer'])
            ->map(fn (string $name): Role => Role::query()->create(['name' => $name]));
        $password = Hash::make('password');

        foreach (range(1, 100) as $number) {
            $label = Str::padLeft((string) $number, 3, '0');
            $user = User::query()->create([
                'name' => "User {$label}",
                'email' => "user{$label}@example.test",
                'password' => $password,
            ]);

            $user->roles()->attach($roles->get(($number - 1) % $roles->count()));
        }

        foreach (range(1, 25) as $chain) {
            $parentId = null;

            foreach (range(1, 4) as $level) {
                $parentId = Category::query()->create([
                    'name' => "Category {$chain}.{$level}",
                    'parent_id' => $parentId,
                ])->getKey();
            }
        }
    }
}
