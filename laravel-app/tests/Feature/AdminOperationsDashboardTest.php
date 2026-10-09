<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminSessionVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_management_page_is_visible_without_two_factor_but_writes_remain_locked(): void
    {
        $this->authenticateAdmin(twoFactor: false);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Business overview')->assertSee('Sales & orders', false);
        $this->get(route('admin.sales'))->assertOk()->assertSee('Sales & orders', false)->assertSee('Order data is fully visible');
        $this->get(route('admin.customers'))->assertOk()->assertSee('Customers');
        $this->get(route('admin.products'))->assertOk()->assertSee('Product Registry');
        $this->get(route('admin.storefront'))->assertOk()->assertSee('Storefront CMS');

        $this->post(route('admin.categories.store'), ['name' => 'Must Stay Locked'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseMissing('categories', ['name' => 'Must Stay Locked']);
    }

    public function test_dashboard_sales_and_customer_tables_report_business_data(): void
    {
        $this->authenticateAdmin();
        $customer = User::factory()->create([
            'name' => 'Amina Rahman',
            'phone' => '01700000000',
            'district' => 'Dhaka',
        ]);
        $category = Category::create(['name' => 'Lighting', 'slug' => 'lighting']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Operations Lamp',
            'slug' => 'operations-lamp',
            'sku' => 'OPS-LAMP',
            'price' => '2500.00',
            'discount_amount' => '0.00',
            'stock' => 3,
            'status' => 'active',
            'is_featured' => false,
        ]);
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-OPS-1001',
            'status' => 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_method' => 'pathao',
            'subtotal' => '2500.00',
            'shipping_fee' => '0.00',
            'total' => '2500.00',
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'district' => 'Dhaka',
            'address' => 'Test delivery address in Dhaka',
            'placed_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => '2500.00',
        ]);

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('ORD-OPS-1001')->assertSee('Amina Rahman')->assertSee('2,500.00');
        $this->get(route('admin.sales', ['status' => 'processing']))->assertOk()
            ->assertSee('ORD-OPS-1001')->assertSee('Operations Lamp')->assertSee('2,500.00');
        $this->get(route('admin.customers'))->assertOk()
            ->assertSee('Amina Rahman')->assertSee('01700000000')->assertSee('2,500.00');
    }

    private function authenticateAdmin(bool $twoFactor = true): Admin
    {
        $admin = Admin::create([
            'admin_id' => 'ADM-1700-O',
            'name' => 'Operations Admin',
            'email' => 'operations-admin@example.test',
            'password' => 'Admin-Password-42!',
            'status' => 'active',
            'session_version' => Str::random(64),
            'two_factor_enabled' => $twoFactor,
        ]);
        $this->actingAs($admin, 'admin')->withSession([
            AdminSessionVersion::SESSION_KEY => $admin->session_version,
        ]);

        return $admin;
    }
}
