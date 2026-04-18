<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Models\User;
use App\Support\Mcp\McpAuthenticatedUserResolver;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Support\ValidationMessages;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class AbstractAdminTool extends Tool
{
    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validateArguments(Request $request, array $rules): array
    {
        $this->rejectUnexpectedArguments($request, array_keys($rules));

        return $request->validate($rules);
    }

    protected function authorizeAdmin(Request $request): User
    {
        $user = app(McpAuthenticatedUserResolver::class)->resolve($request->user());

        abort_unless($user instanceof User && $user->hasApplicationAdminAccess(), 403);

        auth()->setUser($user);

        return $user;
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    protected function structuredResponse(callable $callback): ResponseFactory|Response
    {
        try {
            return Response::structured($callback());
        } catch (ValidationException $exception) {
            return $this->errorResponse(
                ValidationMessages::from($exception),
                'validation_error',
                ['errors' => $exception->errors()],
            );
        } catch (HttpExceptionInterface $exception) {
            return $this->errorResponse(
                $this->httpExceptionMessage($exception),
                $this->httpExceptionCode($exception),
                ['status' => $exception->getStatusCode()],
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse('Unexpected server error.', 'server_error');
        }
    }

    protected function httpExceptionMessage(HttpExceptionInterface $exception): string
    {
        return match ($exception->getStatusCode()) {
            401 => 'Unauthenticated.',
            403 => 'Forbidden.',
            404 => 'Resource not found.',
            422 => $exception->getMessage() !== '' ? $exception->getMessage() : 'The given data was invalid.',
            default => $exception->getMessage() !== '' ? $exception->getMessage() : 'Request failed.',
        };
    }

    protected function httpExceptionCode(HttpExceptionInterface $exception): string
    {
        return match ($exception->getStatusCode()) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            422 => 'invalid_request',
            default => 'request_failed',
        };
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    protected function rejectUnexpectedArguments(Request $request, array $allowedKeys): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), $allowedKeys));

        if ($unexpected === []) {
            return;
        }

        throw ValidationException::withMessages([
            'arguments' => [
                'Unexpected argument(s): '.implode(', ', $unexpected).'.',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function errorResponse(string $message, string $code, array $details = []): ResponseFactory
    {
        $structured = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $structured['error']['details'] = $details;
        }

        return Response::make(Response::error($message))
            ->withStructuredContent($structured);
    }
}
