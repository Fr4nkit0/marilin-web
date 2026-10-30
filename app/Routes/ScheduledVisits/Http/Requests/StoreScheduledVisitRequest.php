<?php

declare(strict_types=1);

namespace App\Routes\ScheduledVisits\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduledVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Whether the date may be scheduled (not past, customer with a driver) is VisitScheduler's rule, not the form's.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
