<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;


class ProductTest extends TestCase
{
   use DatabaseTransactions;

    protected bool $seed = false;

    private User $admin;
    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
       
        // Create admin user
        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        // Create regular user
        $this->user = User::factory()->create([
            'is_admin' => false,
        ]);

        // Create category
        $this->category = Category::create([
            'name'        => 'Watches',
            'slug'        => 'watches',
            'description' => 'Luxury watches',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | List Products
    |--------------------------------------------------------------------------
    */

    public function test_anyone_can_list_products(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Rolex Submariner',
            'price'       => 5000,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price', 'stock', 'category'],
                ],
            ]);
    }

    public function test_inactive_products_are_not_listed(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Hidden Product',
            'price'       => 100,
            'stock'       => 5,
            'is_active'   => false,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertNotContains('Hidden Product', $names);
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Rolex',
            'price'       => 5000,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        $response = $this->getJson("/api/products?category_id={$this->category->id}");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_products_can_be_filtered_by_price_range(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Cheap Watch',
            'price'       => 100,
            'stock'       => 5,
            'is_active'   => true,
        ]);

        Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Expensive Watch',
            'price'       => 9000,
            'stock'       => 2,
            'is_active'   => true,
        ]);

        $response = $this->getJson('/api/products?min_price=50&max_price=500');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Cheap Watch', $names);
        $this->assertNotContains('Expensive Watch', $names);
    }

    public function test_products_can_be_searched(): void
    {
        Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Rolex Submariner',
            'price'       => 5000,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        $response = $this->getJson('/api/products?search=Rolex');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Rolex Submariner', $names);
    }

    /*
    |--------------------------------------------------------------------------
    | Single Product
    |--------------------------------------------------------------------------
    */

    public function test_anyone_can_view_single_product(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Rolex Submariner',
            'price'       => 5000,
            'stock'       => 10,
            'is_active'   => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'product' => ['id', 'name', 'price', 'stock', 'category'],
            ])
            ->assertJsonPath('product.name', 'Rolex Submariner');
    }

    public function test_inactive_product_returns_404(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Hidden Product',
            'price'       => 100,
            'stock'       => 5,
            'is_active'   => false,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(404);
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $response = $this->getJson('/api/products/999');

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Product (Admin)
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_product(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/products', [
                'category_id' => $this->category->id,
                'name'        => 'New Watch',
                'price'       => 1500,
                'stock'       => 20,
                'brand'       => 'Omega',
                'is_active'   => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('product.name', 'New Watch')
            ->assertJsonPath('product.price', '1500.00');

        $this->assertDatabaseHas('products', ['name' => 'New Watch']);
    }

    public function test_regular_user_cannot_create_product(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/products', [
                'category_id' => $this->category->id,
                'name'        => 'New Watch',
                'price'       => 1500,
                'stock'       => 20,
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_create_product(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'name'        => 'New Watch',
            'price'       => 1500,
            'stock'       => 20,
        ]);

        $response->assertStatus(401);
    }

    public function test_product_creation_requires_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/products', [
                'category_id' => $this->category->id,
                'price'       => 1500,
                'stock'       => 20,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_product_creation_requires_valid_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/products', [
                'category_id' => 999,
                'name'        => 'New Watch',
                'price'       => 1500,
                'stock'       => 20,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Product (Admin)
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_product(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Old Name',
            'price'       => 1000,
            'stock'       => 5,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'name'  => 'Updated Name',
                'price' => 1200,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('product.name', 'Updated Name')
            ->assertJsonPath('product.price', '1200.00');

        $this->assertDatabaseHas('products', ['name' => 'Updated Name']);
    }

    public function test_regular_user_cannot_update_product(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Watch',
            'price'       => 1000,
            'stock'       => 5,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Product (Admin)
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_product(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'To Delete',
            'price'       => 500,
            'stock'       => 3,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_regular_user_cannot_delete_product(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name'        => 'Watch',
            'price'       => 500,
            'stock'       => 3,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(403);
    }
}
