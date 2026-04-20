<?php

declare(strict_types=1);

namespace App\Support\ApiDocumentation;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

final class ApiRequestBodyExamplesExtension extends OperationExtension
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const array EXAMPLES = [
        'post auth/login' => [
            'login' => 'superadmin@majlisilmu.my',
            'password' => 'password',
            'device_name' => 'OpenClaw Production',
        ],
        'post auth/register' => [
            'name' => 'OpenClaw User',
            'email' => 'openclaw@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'OpenClaw iPhone',
        ],
        'put account-settings' => [
            'name' => 'Super Admin',
            'email' => 'superadmin@majlisilmu.my',
            'phone' => '+60111222333',
            'timezone' => 'Asia/Kuala_Lumpur',
            'daily_prayer_institution_id' => null,
            'friday_prayer_institution_id' => null,
        ],
        'put notification-settings' => [
            'settings' => [
                'locale' => 'ms',
                'timezone' => 'Asia/Kuala_Lumpur',
                'digest_delivery_time' => '08:00:00',
                'digest_weekly_day' => 1,
                'preferred_channels' => ['in_app', 'push', 'email'],
                'fallback_channels' => ['in_app', 'email'],
                'fallback_strategy' => 'next_available',
                'urgent_override' => true,
            ],
            'families' => [
                'event_updates' => [
                    'enabled' => true,
                    'cadence' => 'instant',
                    'channels' => ['in_app', 'email'],
                ],
            ],
            'triggers' => [
                'checkin_open' => [
                    'enabled' => true,
                    'channels' => ['push', 'in_app'],
                ],
            ],
        ],
        'post notification-destinations/push' => [
            'installation_id' => 'ios-installation-123',
            'platform' => 'ios',
            'fcm_token' => 'fcm-token-abc123',
            'app_version' => '2.4.0',
            'device_label' => 'Nadia iPhone 15',
            'locale' => 'ms',
            'timezone' => 'Asia/Kuala_Lumpur',
            'last_seen_at' => '2026-04-16T09:30:00Z',
        ],
        'put notification-destinations/push/{installation}' => [
            'platform' => 'ios',
            'fcm_token' => 'fcm-token-updated-xyz789',
            'app_version' => '2.4.1',
            'device_label' => 'Nadia iPhone 15 Pro',
            'locale' => 'ms',
            'timezone' => 'Asia/Kuala_Lumpur',
            'last_seen_at' => '2026-04-16T10:45:00Z',
        ],
        'post events/{event}/registrations' => [
            'name' => 'API Guest',
            'email' => 'guest@example.com',
            'phone' => '+60112223344',
        ],
        'post membership-claims/{subjectType}/{subject}' => [
            'justification' => 'I am part of the mosque committee and can help manage event records for this institution.',
            'evidence' => ['committee-letter.pdf', 'staff-pass.jpg'],
        ],
        'post institution-workspace/{institutionId}/members' => [
            'email' => 'member@example.com',
            'role_id' => 'institution_admin',
        ],
        'put institution-workspace/{institutionId}/members/{memberId}' => [
            'role_id' => 'institution_editor',
        ],
        'post saved-searches' => [
            'name' => 'Kuliah Maghrib Kuala Lumpur',
            'query' => 'muamalat',
            'filters' => [
                'language_codes' => ['ms'],
                'event_type' => ['kuliah_ceramah'],
                'age_group' => ['all_ages'],
            ],
            'radius_km' => 25,
            'lat' => 3.139,
            'lng' => 101.6869,
            'notify' => 'daily',
        ],
        'put saved-searches/{savedSearch}' => [
            'name' => 'Kuliah Maghrib KL',
            'filters' => [
                'language_codes' => ['ms', 'en'],
                'event_type' => ['forum'],
                'age_group' => ['youth'],
            ],
            'notify' => 'instant',
        ],
        'post reports' => [
            'entity_type' => 'event',
            'entity_id' => '019d1da4-cf8e-71f9-8c27-f60ef378d6f4',
            'category' => 'wrong_info',
            'description' => 'The start time on the poster does not match the event description.',
            'evidence' => ['poster-screenshot.jpg', 'venue-notice.pdf'],
        ],
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $operation->requestBodyObject instanceof RequestBodyObject) {
            return;
        }

        $example = $this->resolveExample($routeInfo, $operation);

        if (! is_array($example)) {
            return;
        }

        $requestBodyObject = ExampleRequestBodyObject::fromRequestBodyObject($operation->requestBodyObject);

        foreach ($requestBodyObject->content as $mediaType => $schema) {
            $requestBodyObject->setContentExample((string) $mediaType, $example);

            $resolvedSchema = $schema instanceof Reference ? $schema->resolve() : $schema;

            if ($resolvedSchema instanceof Schema) {
                $resolvedSchema->type->example($example);
            }
        }

        $operation->addRequestBodyObject($requestBodyObject);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveExample(RouteInfo $routeInfo, Operation $operation): ?array
    {
        $method = strtolower($operation->method);
        $candidates = array_unique([
            $this->normalizedOperationPath($operation),
            $this->normalizedRoutePath($routeInfo),
            $this->canonicalizeWildcardNames($this->normalizedOperationPath($operation)),
            $this->canonicalizeWildcardNames($this->normalizedRoutePath($routeInfo)),
        ]);

        foreach ($candidates as $path) {
            if ($path === '') {
                continue;
            }

            $example = self::EXAMPLES["{$method} {$path}"] ?? null;

            if (is_array($example)) {
                return $example;
            }
        }

        return null;
    }

    private function normalizedRoutePath(RouteInfo $routeInfo): string
    {
        $uri = trim($routeInfo->route->uri(), '/');
        $apiPath = trim((string) config('scramble.api_path', 'api/v1'), '/');

        if ($apiPath !== '' && Str::startsWith($uri, $apiPath.'/')) {
            return Str::after($uri, $apiPath.'/');
        }

        return $uri;
    }

    private function normalizedOperationPath(Operation $operation): string
    {
        $path = trim($operation->path, '/');
        $apiPath = trim((string) config('scramble.api_path', 'api/v1'), '/');

        if ($apiPath !== '' && Str::startsWith($path, $apiPath.'/')) {
            return Str::after($path, $apiPath.'/');
        }

        return $path;
    }

    private function canonicalizeWildcardNames(string $path): string
    {
        return str_replace(['{saved_search}'], ['{savedSearch}'], $path);
    }
}
