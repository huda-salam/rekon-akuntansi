<?php

namespace DatabaseFactories;

use AppModelsUser;
use IlluminateDatabaseEloquentFactoriesFactory;
use IlluminateSupportFacadesHash;
use IlluminateSupportStr;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'skpd',
            'skpd_id' => null,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
