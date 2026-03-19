<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class DeviceDefinitionPolicy
{
    /**
     * Determine if the user can view device definitions.
     */
    public function view(User $user): Response
    {
        if ($user->cannot('device_definition:read')) {
            return Response::denyWithStatus(404);
        }

        return Response::allow();
    }
}
