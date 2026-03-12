<?php

use App\Enums\EventParticipantRole;
use App\Models\SavedSearch;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('Saved Search API Endpoints', function () {
    it('requires authentication', function () {
        $this->getJson('/api/v1/saved-searches')
            ->assertUnauthorized();
    });

    describe('Authenticated endpoints', function () {
        beforeEach(function () {
            $this->user = User::factory()->create();
            Sanctum::actingAs($this->user);
        });

        describe('GET /api/v1/saved-searches', function () {
            it('returns user saved searches', function () {
                SavedSearch::factory()->count(3)->create(['user_id' => $this->user->id]);
                SavedSearch::factory()->count(2)->create(); // Other user's searches

                $response = $this->getJson('/api/v1/saved-searches');

                $response->assertOk()
                    ->assertJsonCount(3, 'data');
            });
        });

        describe('POST /api/v1/saved-searches', function () {
            it('creates a saved search', function () {
                $imamSpeaker = Speaker::factory()->create();

                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Kuliah Maghrib',
                    'query' => 'maghrib',
                    'filters' => [
                        'language' => 'malay',
                        'key_person_roles' => [EventParticipantRole::Imam->value],
                        'imam_ids' => [$imamSpeaker->id],
                    ],
                    'notify' => 'daily',
                ]);

                $response->assertCreated()
                    ->assertJsonPath('data.name', 'Kuliah Maghrib')
                    ->assertJsonPath('data.filters.key_person_roles.0', EventParticipantRole::Imam->value)
                    ->assertJsonPath('data.filters.imam_ids.0', $imamSpeaker->id);

                $this->assertDatabaseHas('saved_searches', [
                    'user_id' => $this->user->id,
                    'name' => 'Kuliah Maghrib',
                ]);
            });

            it('validates required fields', function () {
                $response = $this->postJson('/api/v1/saved-searches', []);

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['name', 'notify']);
            });

            it('validates notify options', function () {
                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Test Search',
                    'notify' => 'invalid',
                ]);

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['notify']);
            });

            it('validates participant role options', function () {
                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Role Search',
                    'filters' => [
                        'key_person_roles' => ['invalid-role'],
                    ],
                    'notify' => 'daily',
                ]);

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['filters.key_person_roles.0']);
            });

            it('enforces max 10 saved searches per user', function () {
                SavedSearch::factory()->count(10)->create(['user_id' => $this->user->id]);

                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Eleventh Search',
                    'notify' => 'off',
                ]);

                $response->assertStatus(422)
                    ->assertJsonPath('error.code', 'validation_error');
            });

            it('requires lat/lng when radius_km is provided', function () {
                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Nearby Search',
                    'radius_km' => 50,
                    'notify' => 'weekly',
                ]);

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['lat', 'lng']);
            });

            it('accepts radius up to 1000 km when coordinates are provided', function () {
                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Wide Nearby Search',
                    'radius_km' => 1000,
                    'lat' => 3.1390,
                    'lng' => 101.6869,
                    'notify' => 'daily',
                ]);

                $response->assertCreated()
                    ->assertJsonPath('data.radius_km', 1000);
            });

            it('rejects non-numeric geography filter ids', function () {
                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Geography Filter Test',
                    'filters' => [
                        'state_id' => (string) Str::uuid(),
                    ],
                    'notify' => 'daily',
                ]);

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['filters.state_id']);
            });

            it('rejects non-numeric subdistrict filter ids', function () {
                $response = $this->postJson('/api/v1/saved-searches', [
                    'name' => 'Subdistrict Filter Test',
                    'filters' => [
                        'subdistrict_id' => (string) Str::uuid(),
                    ],
                    'notify' => 'daily',
                ]);

                $response->assertUnprocessable()
                    ->assertJsonValidationErrors(['filters.subdistrict_id']);
            });
        });

        describe('GET /api/v1/saved-searches/{id}', function () {
            it('returns a specific saved search', function () {
                $search = SavedSearch::factory()->create(['user_id' => $this->user->id]);

                $response = $this->getJson("/api/v1/saved-searches/{$search->id}");

                $response->assertOk()
                    ->assertJsonPath('data.id', $search->id);
            });

            it('denies access to other users searches', function () {
                $otherSearch = SavedSearch::factory()->create();

                $response = $this->getJson("/api/v1/saved-searches/{$otherSearch->id}");

                $response->assertForbidden();
            });
        });

        describe('PUT /api/v1/saved-searches/{id}', function () {
            it('updates a saved search', function () {
                $search = SavedSearch::factory()->create([
                    'user_id' => $this->user->id,
                    'name' => 'Old Name',
                ]);

                $response = $this->putJson("/api/v1/saved-searches/{$search->id}", [
                    'name' => 'New Name',
                    'notify' => 'weekly',
                ]);

                $response->assertOk()
                    ->assertJsonPath('data.name', 'New Name');
            });

            it('prevents updating other users searches', function () {
                $otherSearch = SavedSearch::factory()->create();

                $response = $this->putJson("/api/v1/saved-searches/{$otherSearch->id}", [
                    'name' => 'Hacked',
                ]);

                $response->assertForbidden();
            });
        });

        describe('DELETE /api/v1/saved-searches/{id}', function () {
            it('deletes a saved search', function () {
                $search = SavedSearch::factory()->create(['user_id' => $this->user->id]);

                $response = $this->deleteJson("/api/v1/saved-searches/{$search->id}");

                $response->assertNoContent();
                $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
            });

            it('prevents deleting other users searches', function () {
                $otherSearch = SavedSearch::factory()->create();

                $response = $this->deleteJson("/api/v1/saved-searches/{$otherSearch->id}");

                $response->assertForbidden();
                $this->assertDatabaseHas('saved_searches', ['id' => $otherSearch->id]);
            });
        });

        describe('POST /api/v1/saved-searches/{id}/execute', function () {
            it('executes a saved search and returns results', function () {
                $search = SavedSearch::factory()->create([
                    'user_id' => $this->user->id,
                    'query' => 'test',
                    'filters' => ['language' => 'malay'],
                ]);

                $response = $this->postJson("/api/v1/saved-searches/{$search->id}/execute");

                $response->assertOk()
                    ->assertJsonStructure(['data', 'meta']);
            });
        });
    });
});
