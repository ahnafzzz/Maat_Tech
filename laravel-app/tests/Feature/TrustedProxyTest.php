<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/request-context', function (Request $request) {
            $request->session()->put('proxy-test', true);

            return response()->json([
                'secure' => $request->isSecure(),
                'ip' => $request->ip(),
                'host' => $request->getHost(),
                'admin_login_url' => route('admin.login'),
                'web_user_id' => Auth::guard('web')->id(),
                'admin_user_id' => Auth::guard('admin')->id(),
            ]);
        });
    }

    public function test_untrusted_forwarded_headers_are_ignored(): void
    {
        config()->set('trustedproxy.proxies', ['10.0.0.0/8']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeaders([
                'X-Forwarded-For' => '192.0.2.99',
                'X-Forwarded-Host' => 'attacker.example.test',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->getJson('http://direct.example.test/_test/request-context')
            ->assertOk()
            ->assertJson([
                'secure' => false,
                'ip' => '203.0.113.10',
                'host' => 'direct.example.test',
                'admin_login_url' => 'http://direct.example.test/admin/login',
            ]);
    }

    public function test_configured_proxy_honors_only_client_ip_port_and_protocol(): void
    {
        config()->set('trustedproxy.proxies', ['10.0.0.0/8']);
        config()->set('session.secure', true);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeaders([
                'X-Forwarded-For' => '192.0.2.44',
                'X-Forwarded-Host' => 'attacker.example.test',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ])
            ->getJson('http://shop.example.test/_test/request-context');

        $response->assertOk()->assertJson([
            'secure' => true,
            'ip' => '192.0.2.44',
            'host' => 'shop.example.test',
            'admin_login_url' => 'https://shop.example.test/admin/login',
        ]);

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
    }

    public function test_direct_https_does_not_require_a_trusted_proxy(): void
    {
        config()->set('trustedproxy.proxies', []);

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTPS' => 'on',
            'SERVER_PORT' => '443',
        ])->withHeaders([
            'X-Forwarded-For' => '192.0.2.99',
            'X-Forwarded-Proto' => 'http',
        ])->getJson('https://direct.example.test/_test/request-context')
            ->assertOk()
            ->assertJson([
                'secure' => true,
                'ip' => '203.0.113.10',
                'host' => 'direct.example.test',
                'admin_login_url' => 'https://direct.example.test/admin/login',
            ]);
    }

    public function test_proxy_handling_preserves_customer_and_admin_guard_separation(): void
    {
        config()->set('trustedproxy.proxies', ['10.0.0.0/8']);

        $password = 'Proxy-Test-Password-42!';
        $user = User::factory()->create(['email' => 'customer@example.test', 'password' => $password]);
        $admin = Admin::create([
            'admin_id' => 'ADM-1234-A',
            'name' => 'Proxy Admin',
            'email' => 'admin@example.test',
            'password' => $password,
            'status' => 'active',
            'session_version' => Str::random(64),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeaders([
                'X-Forwarded-For' => '192.0.2.44',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Proto' => 'https',
            ]);

        $this->post('http://shop.example.test/login', ['email' => $user->email, 'password' => $password])
            ->assertRedirect('https://shop.example.test/dashboard');
        $this->post('http://shop.example.test/admin/login', ['admin_id' => $admin->admin_id, 'password' => $password])
            ->assertRedirect('https://shop.example.test/admin');

        $this->getJson('http://shop.example.test/_test/request-context')
            ->assertOk()
            ->assertJson([
                'web_user_id' => $user->id,
                'admin_user_id' => $admin->id,
            ]);
    }
}
