<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class PasswordSetupTest extends TestCase
{
    public function test_password_setup_requires_authentication(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/password/setup',
            []
        );

        $response->assertStatus(401);
    }

    public function test_password_setup_route_uses_sanctum(): void
    {
        $route = app('router')
            ->getRoutes()
            ->match(
                Request::create(
                    '/api/v1/auth/password/setup',
                    'POST'
                )
            );

        $this->assertContains(
            'auth:sanctum',
            $route->gatherMiddleware()
        );
    }
}
