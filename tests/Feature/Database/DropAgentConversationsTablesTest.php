<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;

function dropAgentConversationsTablesMigration(): object
{
    return require database_path('migrations/2026_09_28_150257_drop_agent_conversations_tables.php');
}

test('a migrated database has no agent conversation tables', function () {
    /** @var TestCase $this */
    expect(Schema::hasTable('agent_conversations'))->toBeFalse();
    expect(Schema::hasTable('agent_conversation_messages'))->toBeFalse();
});

describe('down()', function () {
    test('recreates both tables, and up() drops them again', function () {
        /** @var TestCase $this */
        $migration = dropAgentConversationsTablesMigration();

        $migration->down();

        expect(Schema::hasTable('agent_conversations'))->toBeTrue();
        expect(Schema::hasColumns('agent_conversation_messages', [
            'id', 'conversation_id', 'user_id', 'agent', 'role', 'content',
            'attachments', 'tool_calls', 'tool_results', 'usage', 'meta',
        ]))->toBeTrue();

        $migration->up();

        expect(Schema::hasTable('agent_conversations'))->toBeFalse();
        expect(Schema::hasTable('agent_conversation_messages'))->toBeFalse();
    });
});
