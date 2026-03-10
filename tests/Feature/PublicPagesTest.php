<?php

use App\Enums\ContactCategory;
use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\Tag;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('loads public index pages', function () {
    $this->get('/')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/events')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/institutions')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/speakers')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/submit-event')->assertSuccessful()->assertSee('Hantar Majlis');
    $this->get('/submit-event/success')->assertSuccessful()->assertSee(__('Event Submitted!'));
});

it('loads public detail pages', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $series = Series::factory()->create([
        'visibility' => 'public',
    ]);
    $series->events()->attach($event->id, [
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'order_column' => 1,
    ]);

    $this->get(route('events.show', $event))->assertSuccessful()->assertSee($event->title);
    $this->get(route('institutions.show', $institution))->assertSuccessful()->assertSee($institution->name);
    $this->get(route('speakers.show', $speaker))->assertSuccessful()->assertSee($speaker->name);
    $this->get(route('series.show', $series))
        ->assertSuccessful()
        ->assertSee($series->title)
        ->assertSee($event->title);
});

it('renders noindex robots metadata for moderation-only or non-public detail pages', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::findOrCreate('moderator', 'web');

    $moderator = \App\Models\User::factory()->create();
    $moderator->assignRole('moderator');

    $pendingInstitution = Institution::factory()->create([
        'status' => 'pending',
        'is_active' => true,
    ]);

    $pendingSpeaker = Speaker::factory()->create([
        'status' => 'pending',
        'is_active' => true,
    ]);

    $privateSeries = Series::factory()->create([
        'visibility' => 'private',
        'is_active' => true,
    ]);

    $pendingReference = Reference::factory()->create([
        'status' => 'pending',
        'is_active' => true,
    ]);

    $this->actingAs($moderator);

    $this->get(route('institutions.show', $pendingInstitution))
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->get(route('speakers.show', $pendingSpeaker))
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->get(route('series.show', $privateSeries))
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->get(route('references.show', $pendingReference))
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('renders optimized seo metadata on public listing pages', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('<title>Majlis Ilmu - Cari Kuliah & Majlis Ilmu di Malaysia</title>', false)
        ->assertSee('<meta name="description" content="Platform terbesar untuk mencari kuliah, ceramah, tazkirah, dan majlis ilmu di seluruh Malaysia. Cari yang berdekatan dengan anda.">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/default-mosque-hero.png').'">', false)
        ->assertSee('<meta property="og:image:width" content="1024">', false)
        ->assertSee('<meta property="og:image:height" content="1024">', false);

    $this->get(route('events.index'))
        ->assertSuccessful()
        ->assertSee('<title>Kuliah &amp; Majlis Ilmu Akan Datang di Malaysia - Majlis Ilmu</title>', false)
        ->assertSee('Terokai kuliah, ceramah, kelas, dan majlis ilmu akan datang di seluruh Malaysia.', false);

    $this->get(route('institutions.index'))
        ->assertSuccessful()
        ->assertSee('<title>Direktori Institusi Islam di Malaysia - Majlis Ilmu</title>', false)
        ->assertSee('Terokai masjid, surau, pusat pengajian, dan institusi penganjur majlis ilmu di seluruh Malaysia.', false);

    $this->get(route('speakers.index'))
        ->assertSuccessful()
        ->assertSee('<title>Direktori Penceramah Islam - Majlis Ilmu</title>', false)
        ->assertSee('Cari profil penceramah, ustaz, dan pendakwah serta semak majlis ilmu mereka yang akan datang di seluruh Malaysia.', false);
});

it('renders optimized seo metadata on public detail pages', function () {
    $event = Event::factory()->create([
        'title' => 'Kuliah Fiqh Munakahat',
        'description' => 'Kupasan fiqh munakahat untuk keluarga Muslim, termasuk panduan asas, adab, dan soal jawab bersama penceramah jemputan.',
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Al-Hidayah Taman Melawati',
        'description' => 'Pusat komuniti Islam yang aktif menganjurkan kuliah, kelas, dan program ilmu untuk masyarakat setempat.',
        'status' => 'verified',
    ]);

    $speaker = Speaker::factory()->create([
        'name' => 'Ahmad Fauzi',
        'bio' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Penceramah yang aktif mengendalikan kuliah aqidah, tafsir, dan pembinaan keluarga di seluruh negara.',
                ]],
            ]],
        ],
        'status' => 'verified',
        'is_active' => true,
    ]);

    $series = Series::factory()->create([
        'title' => 'Siri Tafsir Juz Amma',
        'description' => 'Siri pengajian berkala yang menghimpunkan tadabbur ayat-ayat pilihan daripada Juz Amma untuk masyarakat umum.',
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $reference = Reference::factory()->create([
        'title' => 'Riyadus Salihin Edisi Syarah',
        'description' => 'Rujukan hadis dan adab yang sering digunakan dalam kuliah pengajian umum serta sesi pembelajaran mingguan.',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->get(route('events.show', $event))
        ->assertSuccessful()
        ->assertSee('<title>Kuliah Fiqh Munakahat - Majlis Ilmu</title>', false)
        ->assertSee(Str::limit($event->description_text, 160), false);

    $this->get(route('institutions.show', $institution))
        ->assertSuccessful()
        ->assertSee('<title>Masjid Al-Hidayah Taman Melawati - Majlis Ilmu</title>', false)
        ->assertSee('Pusat komuniti Islam yang aktif menganjurkan kuliah, kelas, dan program ilmu untuk masyarakat setempat.', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/placeholders/institution.png').'">', false)
        ->assertSee('<meta property="og:image:alt" content="Profil institusi Masjid Al-Hidayah Taman Melawati">', false);

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('<title>'.$speaker->formatted_name.' - Majlis Ilmu</title>', false)
        ->assertSee('Penceramah yang aktif mengendalikan kuliah aqidah, tafsir, dan pembinaan keluarga di seluruh negara.', false);

    $this->get(route('series.show', $series))
        ->assertSuccessful()
        ->assertSee('<title>Siri Tafsir Juz Amma - Majlis Ilmu</title>', false)
        ->assertSee('Siri pengajian berkala yang menghimpunkan tadabbur ayat-ayat pilihan daripada Juz Amma untuk masyarakat umum.', false);

    $this->get(route('references.show', $reference))
        ->assertSuccessful()
        ->assertSee('<title>Riyadus Salihin Edisi Syarah - Majlis Ilmu</title>', false)
        ->assertSee('Rujukan hadis dan adab yang sering digunakan dalam kuliah pengajian umum serta sesi pembelajaran mingguan.', false);
});

it('loads institution detail page with upcoming event type enum collection', function () {
    $institution = Institution::factory()->create(['status' => 'verified']);
    $eventType = \App\Enums\EventType::KuliahCeramah;
    $event = Event::factory()
        ->for($institution)
        ->create([
            'status' => 'approved',
            'visibility' => EventVisibility::Public,
            'starts_at' => now()->addDay(),
            'event_type' => [$eventType],
            'title' => 'Institution Upcoming Event',
        ]);

    $this->get(route('institutions.show', $institution))
        ->assertSuccessful()
        ->assertSee($institution->name)
        ->assertSee($event->title)
        ->assertSee($eventType->getLabel());
});

it('hides unverified speakers and institutions from public pages', function () {
    $institution = Institution::factory()->create(['status' => 'pending']);
    $speaker = Speaker::factory()->create(['status' => 'pending']);

    $this->get(route('institutions.show', $institution))->assertNotFound();
    $this->get(route('speakers.show', $speaker))->assertNotFound();
});

it('updates submit event age group without error', function () {
    Livewire::test('pages.submit-event.create')
        ->set('data.age_group', [EventAgeGroup::Children->value])
        ->assertSet('data.age_group', [EventAgeGroup::Children->value]);
});

it('records guest submissions without a submitter id', function () {
    $title = 'Guest Submission '.uniqid();
    $email = 'guest@example.com';

    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $institution = Institution::factory()->create(['status' => 'verified']);
    Livewire::test('pages.submit-event.create')
        ->set('data.title', $title)
        ->set('data.description', 'Test event description')
        ->set('data.event_date', now()->addDay()->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.speakers', [$speaker->id])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.submitter_name', 'Guest User')
        ->set('data.submitter_email', $email)
        ->set('data.visibility', EventVisibility::Public->value)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::query()->where('title', $title)->first();

    expect($event)->not->toBeNull();
    expect($event?->submitter_id)->toBeNull();

    $submission = EventSubmission::query()->where('event_id', $event->id)->first();

    expect($submission)->not->toBeNull();
    expect($submission->submitted_by)->toBeNull();
    expect($submission->contacts()->where('category', ContactCategory::Email->value)->where('value', $email)->exists())->toBeTrue();
});
