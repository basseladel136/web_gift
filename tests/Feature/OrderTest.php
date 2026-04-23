<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;  

class OrderTest extends TestCase
{
     use RefreshDatabase;

    protected bool $seed = false;

    private User $admin;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
       

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user  = User::factory()->create(['is_admin' => false]);

        $category = Category::create([
            'name' => 'Watches',
            'slug' => 'watches',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Rolex Submariner',
            'price'       => 1000,
            'stock'       => 10,
            'is_active'   => true,
        ]);
    }

    /**
     * Helper — add product to cart for user.
     */
    private function addToCart(User $user, Product $product, int $quantity = 2): void
    {
        CartItem::create([
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'quantity'   => $quantity,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Place Order
    |--------------------------------------------------------------------------
    */

    public function test_user_can_place_order(): void
    {
        $this->addToCart($this->user, $this->product, 2);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'gift_message' => 'Happy Birthday!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'order' => [
                    'id',
                    'status',
                    'subtotal',
                    'total',
                    'gift_message',
                    'items',
                ],
            ])
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.gift_message', 'Happy Birthday!')
            ->assertJsonPath('order.total', '2000.00');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status'  => 'pending',
        ]);
    }

    public function test_cart_is_cleared_after_order(): void
    {
        $this->addToCart($this->user, $this->product, 2);

        $this->actingAs($this->user)->postJson('/api/orders');

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_stock_is_deducted_after_order(): void
    {
        $this->addToCart($this->user, $this->product, 2);

        $this->actingAs($this->user)->postJson('/api/orders');

        $this->assertDatabaseHas('products', [
            'id'    => $this->product->id,
            'stock' => 8, // 10 - 2
        ]);
    }

    public function test_cannot_place_order_with_empty_cart(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders');

        $response->assertStatus(422);
    }

    public function test_cannot_place_order_with_insufficient_stock(): void
    {
        $this->addToCart($this->user, $this->product, 999);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders');

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_place_order(): void
    {
        $response = $this->postJson('/api/orders');

        $response->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Order with Coupon
    |--------------------------------------------------------------------------
    */

    public function test_user_can_place_order_with_percent_coupon(): void
    {
        $this->addToCart($this->user, $this->product, 2);

        $coupon = Coupon::create([
            'code'       => 'SAVE10',
            'type'       => 'percent',
            'value'      => 10,
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'coupon_code' => 'SAVE10',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('order.discount', '200.00') // 10% of 2000
            ->assertJsonPath('order.total', '1800.00');
    }

    public function test_user_can_place_order_with_fixed_coupon(): void
    {
        $this->addToCart($this->user, $this->product, 2);

        Coupon::create([
            'code'      => 'FLAT100',
            'type'      => 'fixed',
            'value'     => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'coupon_code' => 'FLAT100',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('order.discount', '100.00')
            ->assertJsonPath('order.total', '1900.00');
    }

    public function test_invalid_coupon_returns_error(): void
    {
        $this->addToCart($this->user, $this->product, 2);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'coupon_code' => 'FAKECODE',
            ]);

        $response->assertStatus(422);
    }

    public function test_coupon_usage_count_increments_after_order(): void
    {
        $this->addToCart($this->user, $this->product, 2);

        $coupon = Coupon::create([
            'code'       => 'SAVE10',
            'type'       => 'percent',
            'value'      => 10,
            'is_active'  => true,
            'used_count' => 0,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/orders', ['coupon_code' => 'SAVE10']);

        $this->assertDatabaseHas('coupons', [
            'code'       => 'SAVE10',
            'used_count' => 1,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | List & View Orders
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_own_orders(): void
    {
        $this->addToCart($this->user, $this->product, 1);
        $this->actingAs($this->user)->postJson('/api/orders');

        $response = $this->actingAs($this->user)
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'orders' => [
                    '*' => ['id', 'status', 'total'],
                ],
            ]);
    }

    public function test_user_can_view_single_order(): void
    {
        $this->addToCart($this->user, $this->product, 1);
        $placeResponse = $this->actingAs($this->user)->postJson('/api/orders');
        $orderId = $placeResponse->json('order.id');

        $response = $this->actingAs($this->user)
            ->getJson("/api/orders/{$orderId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order' => ['id', 'status', 'total', 'items'],
            ]);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        $this->addToCart($otherUser, $this->product, 1);
        $placeResponse = $this->actingAs($otherUser)->postJson('/api/orders');
        $orderId = $placeResponse->json('order.id');

        $response = $this->actingAs($this->user)
            ->getJson("/api/orders/{$orderId}");

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Update Order Status
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_order_status(): void
    {
        $this->addToCart($this->user, $this->product, 1);
        $placeResponse = $this->actingAs($this->user)->postJson('/api/orders');
        $orderId = $placeResponse->json('order.id');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/orders/{$orderId}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => 'confirmed',
        ]);
    }

    public function test_admin_cannot_set_invalid_order_status(): void
    {
        $this->addToCart($this->user, $this->product, 1);
        $placeResponse = $this->actingAs($this->user)->postJson('/api/orders');
        $orderId = $placeResponse->json('order.id');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/orders/{$orderId}/status", [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
    }

    public function test_regular_user_cannot_update_order_status(): void
    {
        $this->addToCart($this->user, $this->product, 1);
        $placeResponse = $this->actingAs($this->user)->postJson('/api/orders');
        $orderId = $placeResponse->json('order.id');

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/orders/{$orderId}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertStatus(403);
    }
}
