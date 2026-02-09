<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_changes()
    {
        $this->assertFalse(Schema::hasColumn('events', 'speaker_id'), 'speaker_id should not exist in events table');
    }

    public function test_speaker_post_nominal_logic()
    {
        $speaker = Speaker::factory()->create([
            'qualifications' => [
                ['degree' => 'PhD', 'institution' => 'Oxford'],
                ['degree' => 'MA', 'institution' => 'Cairo'],
            ],
        ]);

        // post_nominal is cast to array, not string
        $this->assertEquals(['PhD', 'MA'], $speaker->post_nominal);
    }

    public function test_speaker_avatar_url_behavior()
    {
        $speaker = Speaker::factory()->create();

        // Should be null by default as no media attached
        $this->assertNull($speaker->avatar_url);

        // We can't really test setting it because the column is gone and the accessor is read-only for media
    }

    public function test_relationships()
    {
        $speaker = Speaker::factory()->create();
        $event = Event::factory()->create();
        $institution = Institution::factory()->create();

        $event->speakers()->attach($speaker);
        $institution->speakers()->attach($speaker);

        $this->assertTrue($event->speakers->contains($speaker));
        $this->assertTrue($institution->speakers->contains($speaker));
        $this->assertTrue($speaker->institutions->contains($institution));
    }

    public function test_event_card_image_url_uses_speaker_fallback()
    {
        $speaker = Speaker::factory()->create();
        // Since we removed avatar_url column, we verify default behavior.
        // We expect default placeholder if no media is attached.

        $event = Event::factory()->create();
        $event->speakers()->attach($speaker);

        $event->update(['institution_id' => null]);

        $this->assertEquals(asset('images/placeholders/event.png'), $event->card_image_url);
    }
}
