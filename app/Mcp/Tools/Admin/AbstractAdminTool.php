<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Models\User;
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
    protected function authorizeAdmin(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasApplicationAdminAccess(), 403);

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
            return Response::error(ValidationMessages::from($exception));
        } catch (HttpExceptionInterface $exception) {
            return Response::error($this->httpExceptionMessage($exception));
        } catch (Throwable $exception) {
            report($exception);

            return Response::error('Unexpected server error.');
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
}
