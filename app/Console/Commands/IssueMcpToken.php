<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'mcp:token',
    description: 'Issue a Sanctum bearer token for the admin MCP server.'
)]
class IssueMcpToken extends Command
{
    protected $signature = 'mcp:token {email : The admin user email} {name=opencode-mcp : The token name}';

    protected $description = 'Issue a Sanctum bearer token for the admin MCP server.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->argument('name');

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->components->error("User [{$email}] was not found.");

            return self::FAILURE;
        }

        if (! $user->hasApplicationAdminAccess()) {
            $this->components->error("User [{$email}] does not have application admin access.");

            return self::FAILURE;
        }

        $token = $user->createToken($name)->plainTextToken;

        $this->line('Bearer '.$token);

        return self::SUCCESS;
    }
}
