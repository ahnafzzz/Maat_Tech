<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminLoginHttpsTest extends TestCase
{
    public function test_production_requests_force_https_for_generated_urls(): void
    {
        config()->set('app.url', 'http://localhost');
        $this->app['config']->set('app.env', 'production');

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';

        $request = Request::create('https://example.com/admin/login', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'example.com',
        ]);
        $this->app->instance('request', $request);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->assertSame('https://example.com', URL::to('/'));
    }
}
