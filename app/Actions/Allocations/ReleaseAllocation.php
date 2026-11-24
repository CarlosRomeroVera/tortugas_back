<?php

namespace App\Actions\Allocations;

use App\Enums\AllocationStatus;
use App\Models\Allocation;

/** Libera una ocupación (no se borra, para conservar el historial). */
final class ReleaseAllocation
{
    public function handle(?Allocation $allocation): void
    {
        if ($allocation && $allocation->status === AllocationStatus::Active) {
            $allocation->update(['status' => AllocationStatus::Released]);
        }
    }
}
