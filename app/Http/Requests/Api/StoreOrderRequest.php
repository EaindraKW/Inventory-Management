<?php

namespace App\Http\Requests\Api;

use App\DTO\StoreOrderData;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'One or more selected products were not found.',
        ];
    }

    public function toDto(): StoreOrderData
    {
        /** @var array{items: list<array{product_id: int, quantity: int}>} $validated */
        $validated = $this->validated();

        return StoreOrderData::fromValidated($validated);
    }
}
