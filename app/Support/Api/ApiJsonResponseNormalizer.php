<?php

declare(strict_types=1);

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiJsonResponseNormalizer
{
    public function normalize(Request $request, Response $response): Response
    {
        if (! ApiResponseFactory::isApiRequest($request) || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || ! $this->shouldNormalize($payload)) {
            return $response;
        }

        $payload = $this->normalizeErrorPayload($payload, $response->getStatusCode());
        $payload = $this->liftNestedDataMessage($payload);
        $payload = $this->normalizeTopLevelPagination($payload);
        $payload = $this->normalizeMetaPagination($payload);
        $payload = $this->appendRequestId($payload, $request);

        $response->setData($payload);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldNormalize(array $payload): bool
    {
        return array_key_exists('data', $payload)
            || array_key_exists('message', $payload)
            || array_key_exists('error', $payload)
            || array_key_exists('errors', $payload)
            || array_key_exists('meta', $payload)
            || $this->hasPaginationKeys($payload)
            || $this->hasPaginationKeys($this->meta($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeErrorPayload(array $payload, int $status): array
    {
        if ($status < 400) {
            return $payload;
        }

        $message = $this->resolveErrorMessage($payload, $status);
        $validationErrors = is_array($payload['errors'] ?? null) ? $payload['errors'] : null;
        $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];

        $error['code'] = is_string($error['code'] ?? null) && trim((string) $error['code']) !== ''
            ? (string) $error['code']
            : ApiResponseFactory::errorCodeForStatus($validationErrors !== null ? 422 : $status);
        $error['message'] = is_string($error['message'] ?? null) && trim((string) $error['message']) !== ''
            ? (string) $error['message']
            : $message;

        if ($validationErrors !== null) {
            $details = is_array($error['details'] ?? null) ? $error['details'] : [];
            $details['fields'] = $validationErrors;
            $error['details'] = $details;
        }

        $payload['message'] = $message;
        $payload['error'] = $error;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function liftNestedDataMessage(array $payload): array
    {
        if (! is_array($payload['data'] ?? null) || ! array_key_exists('message', $payload['data'])) {
            return $payload;
        }

        $message = $payload['data']['message'];

        if (! is_string($message) || trim($message) === '') {
            return $payload;
        }

        if (! is_string($payload['message'] ?? null) || trim((string) $payload['message']) === '') {
            $payload['message'] = $message;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeTopLevelPagination(array $payload): array
    {
        if (! $this->hasPaginationKeys($payload)) {
            return $payload;
        }

        $meta = $this->meta($payload);
        $meta['pagination'] = $this->paginationData($payload);

        $links = $this->linkData($payload);

        if ($links !== []) {
            $meta['links'] = $links;
        }

        $payload['meta'] = $meta;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeMetaPagination(array $payload): array
    {
        $meta = $this->meta($payload);

        if (! $this->hasPaginationKeys($meta)) {
            return $payload;
        }

        $meta['pagination'] = $this->paginationData($meta);

        $links = $this->linkData($meta);

        if ($links !== []) {
            $meta['links'] = array_merge(is_array($meta['links'] ?? null) ? $meta['links'] : [], $links);
        }

        $payload['meta'] = $meta;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function appendRequestId(array $payload, Request $request): array
    {
        $meta = $this->meta($payload);
        $meta['request_id'] = is_string($meta['request_id'] ?? null) && trim((string) $meta['request_id']) !== ''
            ? (string) $meta['request_id']
            : ApiResponseFactory::requestId($request);

        $payload['meta'] = $meta;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function meta(array $payload): array
    {
        return is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveErrorMessage(array $payload, int $status): string
    {
        $message = $payload['message'] ?? data_get($payload, 'error.message');

        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return ApiResponseFactory::messageForStatus($status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasPaginationKeys(array $payload): bool
    {
        return array_key_exists('current_page', $payload)
            || array_key_exists('last_page', $payload)
            || array_key_exists('per_page', $payload)
            || array_key_exists('total', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int|null>
     */
    private function paginationData(array $payload): array
    {
        return array_filter([
            'page' => is_numeric($payload['current_page'] ?? null) ? (int) $payload['current_page'] : null,
            'per_page' => is_numeric($payload['per_page'] ?? null) ? (int) $payload['per_page'] : null,
            'total' => is_numeric($payload['total'] ?? null) ? (int) $payload['total'] : null,
            'last_page' => is_numeric($payload['last_page'] ?? null) ? (int) $payload['last_page'] : null,
            'from' => is_numeric($payload['from'] ?? null) ? (int) $payload['from'] : null,
            'to' => is_numeric($payload['to'] ?? null) ? (int) $payload['to'] : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function linkData(array $payload): array
    {
        return array_filter([
            'first' => is_string($payload['first_page_url'] ?? null) && $payload['first_page_url'] !== '' ? $payload['first_page_url'] : null,
            'last' => is_string($payload['last_page_url'] ?? null) && $payload['last_page_url'] !== '' ? $payload['last_page_url'] : null,
            'prev' => is_string($payload['prev_page_url'] ?? null) && $payload['prev_page_url'] !== '' ? $payload['prev_page_url'] : null,
            'next' => is_string($payload['next_page_url'] ?? null) && $payload['next_page_url'] !== '' ? $payload['next_page_url'] : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
