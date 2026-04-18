<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

it('registers MCP OAuth clients without using Passport schema introspection', function (): void {
    config()->set('mcp.redirect_domains', [
        'https://majlisilmu.test',
        'https://chatgpt.com',
        'http://localhost',
    ]);

    config()->set('mcp.custom_schemes', ['vscode']);

    $this->instance(ClientRepository::class, new class extends ClientRepository
    {
        public function createAuthorizationCodeGrantClient(
            string $name,
            array $redirectUris,
            bool $confidential = true,
            ?Authenticatable $user = null,
            bool $enableDeviceFlow = false
        ): Client {
            throw new RuntimeException('ClientRepository should not be used by MCP registration.');
        }
    });

    $response = $this->postJson('/oauth/mcp/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => ['https://chatgpt.com/connector/oauth/callback-123'],
    ]);

    $response->assertOk()
        ->assertJsonPath('scope', 'mcp:use')
        ->assertJsonPath('token_endpoint_auth_method', 'none');

    $clientId = $response->json('client_id');

    expect($clientId)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('oauth_clients', [
        'id' => $clientId,
        'name' => 'ChatGPT',
        'revoked' => false,
    ]);
});
