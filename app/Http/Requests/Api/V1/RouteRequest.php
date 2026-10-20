<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Map\SaltaBoundingBox;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $origin = $this->origin();
                $destination = $this->destination();

                if ($origin === null || $destination === null) {
                    $validator->errors()->add('coordinates', 'Origin and destination coordinates are required.');

                    return;
                }

                if (! SaltaBoundingBox::contains(...$origin) || ! SaltaBoundingBox::contains(...$destination)) {
                    $validator->errors()->add('coordinates', 'Coordinates are outside Salta bounding box.');
                }
            },
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public function origin(): ?array
    {
        return $this->point('origin', 'origin_lat', 'origin_lng');
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public function destination(): ?array
    {
        return $this->point('destination', 'destination_lat', 'destination_lng');
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function point(string $combined, string $latKey, string $lngKey): ?array
    {
        if ($this->filled($combined) && is_string($this->input($combined))) {
            $parts = explode(',', $this->string($combined)->toString());

            return count($parts) === 2 ? [(float) trim($parts[0]), (float) trim($parts[1])] : null;
        }

        if ($this->filled($latKey) && $this->filled($lngKey)) {
            return [(float) $this->input($latKey), (float) $this->input($lngKey)];
        }

        return null;
    }
}
