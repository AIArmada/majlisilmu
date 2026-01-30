<?php

use App\Models\EventType;
use App\Models\Topic;

test('it nulls out parent_id of children when parent is deleted in EventType', function () {
    $parent = EventType::factory()->create();
    $child = EventType::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent_id)->toBe($parent->id);

    $parent->delete();

    $child->refresh();
    expect($child->parent_id)->toBeNull();
});

test('it nulls out parent_id of children when parent is deleted in Topic', function () {
    $parent = Topic::factory()->create();
    $child = Topic::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent_id)->toBe($parent->id);

    $parent->delete();

    $child->refresh();
    expect($child->parent_id)->toBeNull();
});
