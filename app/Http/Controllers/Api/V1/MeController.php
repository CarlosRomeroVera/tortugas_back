<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /** GET /api/v1/me — para revalidar el token y los roles al abrir la app. */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load('staff');
        $staff = $user->staff;

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'staff' => $staff ? [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'role' => $staff->role,
                    'phone' => $staff->phone,
                    'license_number' => $staff->license_number,
                    'license_expires_on' => $staff->license_expires_on?->toDateString(),
                ] : null,
            ],
        ]);
    }
}
