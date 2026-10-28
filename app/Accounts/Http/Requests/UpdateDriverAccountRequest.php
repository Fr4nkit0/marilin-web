<?php

declare(strict_types=1);

namespace App\Accounts\Http\Requests;

use App\Accounts\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return DriverAccountRules::profile($user);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return DriverAccountRules::messages();
    }
}
