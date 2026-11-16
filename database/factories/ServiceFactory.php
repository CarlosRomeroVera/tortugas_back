<?php

namespace Database\Factories;

use App\Enums\AssetKind;
use App\Enums\Currency;
use App\Enums\ServiceCategory;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Snorkel '.$this->faker->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => ServiceCategory::Snorkel,
            'asset_kind' => AssetKind::Boat,
            'is_shared' => true,
            'duration_minutes' => 120,
            'buffer_minutes' => 30,
            'default_capacity' => null,
            'price_adult_cents' => 100000,
            'price_child_cents' => 50000,
            'currency' => Currency::MXN,
            'is_active' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_shared' => false, 'category' => ServiceCategory::PrivateBoat]);
    }
}
