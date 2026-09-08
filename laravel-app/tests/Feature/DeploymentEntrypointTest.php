<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentEntrypointTest extends TestCase
{
    public function test_container_startup_validates_but_never_migrates_or_seeds(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $startup = file_get_contents(base_path('docker/start.sh'));
        $virtualHost = file_get_contents(base_path('docker/apache-vhost.conf'));

        $this->assertStringContainsString('CMD ["docker/start.sh"]', $dockerfile);
        $this->assertStringContainsString('FROM php:8.4-apache', $dockerfile);
        $this->assertStringContainsString('exec apache2-foreground', $startup);
        $this->assertStringContainsString('DocumentRoot /app/public', $virtualHost);
        $this->assertStringContainsString('deployment:preflight', $startup);
        $this->assertStringNotContainsString('artisan serve', $dockerfile.$startup);
        $this->assertStringNotContainsString('key:generate', $dockerfile.$startup);
        $this->assertStringNotContainsString('migrate', $dockerfile.$startup);
        $this->assertStringNotContainsString('db:seed', $dockerfile.$startup);
    }

    public function test_automated_deployment_requires_trusted_host_data_and_safety_orchestration(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/laravel-auto-deploy.yml'));

        $this->assertStringContainsString('branches: ["main"]', $workflow);
        $this->assertStringContainsString('DEPLOY_KNOWN_HOSTS', $workflow);
        $this->assertStringContainsString('StrictHostKeyChecking=yes', $workflow);
        $this->assertStringContainsString('deploy-laravel.sh', $workflow);
        $this->assertStringNotContainsString('ssh-keyscan', $workflow);
    }
}
