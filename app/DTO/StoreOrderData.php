<?php

namespace App\DTO;

final readonly class StoreOrderData
{
    /**
     * @param list<OrderItemData> $items
     */
    public function __construct(public array $items)
    {
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_map(
            static fn (OrderItemData $item): int => $item->productId,
            $this->items,
        );
    }

    /**
     * @param array{items: list<array{product_id: int, quantity: int}>} $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            array_map(
                static fn (array $item): OrderItemData => new OrderItemData(
                    productId: (int) $item['product_id'],
                    quantity: (int) $item['quantity'],
                ),
                $validated['items'],
            ),
        );
    }
}
