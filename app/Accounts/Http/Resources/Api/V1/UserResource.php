<?php

declare(strict_types=1);

namespace App\Accounts\Http\Resources\Api\V1;

use App\Accounts\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'phone_number' => $this->phone_number,
            'is_super_user' => (bool) $this->is_super_user,
            'enabled' => (bool) $this->enabled,
            'driver' => $this->whenLoaded('driver', function () {
                return $this->driver ? [
                    'id' => $this->driver->id,
                    'user_id' => $this->driver->user_id,
                    'enabled' => (bool) $this->driver->enabled,
                ] : null;
            }, function () {
                return $this->driver ? [
                    'id' => $this->driver->id,
                    'user_id' => $this->driver->user_id,
                    'enabled' => (bool) $this->driver->enabled,
                ] : null;
            }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'message' => null,
        ];
    }
}
