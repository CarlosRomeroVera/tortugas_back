<?php

namespace Database\Factories;

use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kind' => AssetKind::Boat,
            'name' => 'Lancha '.$this->faker->unique()->numberBetween(1, 99999),
            'registration' => $this->faker->bothify('####-QR'),
            'capacity' => 12,
            'status' => AssetStatus::Active,
            'engine_hours' => 100,
        ];
    }

    public function vehicle(): static
    {
        return $this->state(fn () => [
            'kind' => AssetKind::Vehicle,
            'name' => 'Van '.$this->faker->unique()->numberBetween(1, 99999),
            'engine_hours' => null,
            'odometer_km' => 50000,
        ]);
    }

    public function capacity(int $capacity): static
    {
        return $this->state(fn () => ['capacity' => $capacity]);
    }

    public function outOfService(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::OutOfService]);
    }
}
