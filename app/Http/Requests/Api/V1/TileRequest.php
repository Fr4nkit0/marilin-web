<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Map\SaltaBoundingBox;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class TileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->all(), $this->route()?->parameters() ?? []);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'z' => ['required', 'integer', 'between:0,19'],
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'z.*' => 'Zoom level must be between 0 and 19.',
            'x.*' => 'Tile coordinates out of range for zoom level.',
            'y.*' => 'Tile coordinates out of range for zoom level.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $maxCoord = 2 ** $this->zoom();

                if ($this->tileX() >= $maxCoord || $this->tileY() >= $maxCoord) {
                    $validator->errors()->add('coordinates', 'Tile coordinates out of range for zoom level.');

                    return;
                }

                if (! SaltaBoundingBox::tileOverlaps($this->zoom(), $this->tileX(), $this->tileY())) {
                    $validator->errors()->add('tile', 'Requested map tile is outside Salta bounding box.');
                }
            },
        ];
    }

    public function zoom(): int
    {
        return (int) $this->route('z');
    }

    public function tileX(): int
    {
        return (int) $this->route('x');
    }

    public function tileY(): int
    {
        return (int) $this->route('y');
    }
}
