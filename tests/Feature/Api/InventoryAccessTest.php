<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_products(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_guests_cannot_create_products(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Guest Product',
            'price' => 1,
            'stock' => 1,
        ])->assertUnauthorized();
    }

    public function test_any_authenticated_user_can_view_products(): void
    {
        Product::query()->create([
            'name' => 'Nordic Chair',
            'price' => 125.50,
            'stock' => 10,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Nordic Chair');
    }

    public function test_any_authenticated_user_can_create_products(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/products', [
                'name' => 'Nordic Shelf',
                'price' => 85.75,
                'stock' => 12,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Product created successfully.')
            ->assertJsonPath('product.name', 'Nordic Shelf');

        $this->assertDatabaseHas('products', [
            'name' => 'Nordic Shelf',
            'price' => 85.75,
            'stock' => 12,
        ]);
    }

    public function test_any_authenticated_user_can_update_products(): void
    {
        $product = Product::query()->create([
            'name' => 'Nordic Table',
            'price' => 199,
            'stock' => 4,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/products/'.$product->id, [
                'name' => 'Nordic Table Pro',
                'price' => 225,
                'stock' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Product updated successfully.')
            ->assertJsonPath('product.name', 'Nordic Table Pro');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nordic Table Pro',
            'price' => 225,
            'stock' => 7,
        ]);
    }

    public function test_any_authenticated_user_can_delete_products(): void
    {
        $product = Product::query()->create([
            'name' => 'Nordic Stool',
            'price' => 49,
            'stock' => 8,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_any_authenticated_user_can_create_orders(): void
    {
        $product = Product::query()->create([
            'name' => 'Nordic Desk',
            'price' => 250.00,
            'stock' => 5,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('order.total_price', 500);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total_price' => 500,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 250,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 3,
        ]);
    }

    public function test_order_creation_fails_when_stock_is_insufficient(): void
    {
        $product = Product::query()->create([
            'name' => 'Nordic Lamp',
            'price' => 45.00,
            'stock' => 1,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
        ]);
    }

    public function test_order_creation_fails_when_product_is_not_found(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/orders', [
                'items' => [
                    [
                        'product_id' => 999999,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'One or more selected products were not found.')
            ->assertJsonValidationErrors('items.0.product_id')
            ->assertJsonFragment(['One or more selected products were not found.']);

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
        ]);
    }

    public function test_any_authenticated_user_can_update_orders(): void
    {
        $firstProduct = Product::query()->create([
            'name' => 'Nordic Desk',
            'price' => 250.00,
            'stock' => 5,
        ]);

        $secondProduct = Product::query()->create([
            'name' => 'Nordic Lamp',
            'price' => 100.00,
            'stock' => 4,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $order = $user->orders()->create([
            'total_price' => 500,
        ]);

        $order->items()->create([
            'product_id' => $firstProduct->id,
            'quantity' => 2,
            'price' => 250,
        ]);

        $firstProduct->decrement('stock', 2);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/orders/'.$order->id, [
                'items' => [
                    [
                        'product_id' => $secondProduct->id,
                        'quantity' => 3,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Order updated successfully.')
            ->assertJsonPath('order.total_price', 300);

        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'product_id' => $firstProduct->id,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'quantity' => 3,
            'price' => 100,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $firstProduct->id,
            'stock' => 5,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $secondProduct->id,
            'stock' => 1,
        ]);
    }

    public function test_any_authenticated_user_can_delete_orders(): void
    {
        $product = Product::query()->create([
            'name' => 'Nordic Chair',
            'price' => 80.00,
            'stock' => 2,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $order = $user->orders()->create([
            'total_price' => 160,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 80,
        ]);

        $product->decrement('stock', 2);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('message', 'Order deleted successfully.');

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 2,
        ]);
    }

}