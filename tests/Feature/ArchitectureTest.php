<?php

use Symfony\Component\Finder\Finder;

/*
 * MySQL no impide traslapes por sí solo: toda ocupación debe pasar por AllocateAsset.
 * Esta prueba falla si alguien crea una Allocation por otro camino.
 */
it('solo AllocateAsset crea ocupaciones', function () {
    $offenders = collect((new Finder)->files()->in([app_path(), database_path('seeders')])->name('*.php'))
        ->reject(fn ($file) => str_ends_with($file->getPathname(), 'Actions/Allocations/AllocateAsset.php'))
        ->filter(fn ($file) => preg_match(
            '/Allocation::(create|insert|forceCreate|firstOrCreate|updateOrCreate)|allocations?\(\)->(create|insert|save)|new Allocation\b/',
            $file->getContents(),
        ))
        ->map(fn ($file) => str_replace(base_path().'/', '', $file->getPathname()))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
