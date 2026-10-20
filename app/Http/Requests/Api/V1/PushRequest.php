<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PushRequest extends FormRequest
{
    /**
     * Entities a push may carry.
     *
     * @var array<int, string>
     */
    private const array ENTITIES = ['customers', 'route_stops', 'visits', 'container_movements'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the envelope is validated here; each record is validated on its own so one invalid record never rejects the batch.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_fill_keys(self::ENTITIES, ['nullable', 'array']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function records(): array
    {
        $records = [];

        foreach (self::ENTITIES as $entity) {
            $records[$entity] = array_values((array) $this->input($entity, []));
        }

        return $records;
    }
}
