<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\MediaWritingAgentTool;
use App\Ai\Tools\RequestConfirmation;
use App\Models\Media;
use App\Models\MediaEventType;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Contracts\ConversationStore;

test('happy path: user logs a finished book', function () {
    /** @var TestCase $this */
    expect(config('ai.providers.anthropic.api_key'))
        ->not->toBeEmpty('Set ANTHROPIC_API_KEY to run evals');

    $conversationId = app(ConversationStore::class)
        ->storeConversation(null, 'I finished reading Dune');

    $user = new class
    {
        public ?int $id = null;
    };

    // Turn 1: agent identifies the book, checks library, requests confirmation
    $confirmationTool = new RequestConfirmation;
    $agent = (new MediaTrackingAgent(confirmationTool: $confirmationTool))
        ->continue($conversationId, $user);
    $agent->prompt('I just finished reading Dune by Frank Herbert');

    expect($confirmationTool->wasRequested())->toBeTrue('Agent should have called RequestConfirmation');

    // Turn 2: user confirms — agent executes the plan
    $writingTool = new MediaWritingAgentTool;
    $agent = (new MediaTrackingAgent(writingTool: $writingTool))
        ->continue($conversationId, $user);
    $agent->prompt('The user confirmed. Execute the plan.');

    // Assert the media record was created
    $media = Media::where('title', 'like', '%Dune%')->first();
    expect($media)->not->toBeNull('A Dune media record should exist in the database');

    // Assert a finished event was created
    $this->assertDatabaseHas('media_events', [
        'media_id' => $media->id,
        'media_event_type_id' => MediaEventType::where('name', 'finished')->value('id'),
    ]);
});
