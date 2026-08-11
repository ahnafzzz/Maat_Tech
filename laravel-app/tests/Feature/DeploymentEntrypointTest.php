<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentEntrypointTest extends TestCase
{
    public function test_deployment_entrypoint_migrates_without_wiping_data_and_seeds_catalog(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertStringContainsString('php artisan migrate --force', $dockerfile);
        $this->assertStringContainsString('php artisan db:seed --force', $dockerfile);
        $this->assertStringNotContainsString('migrate:fresh', $dockerfile);
    }
}
