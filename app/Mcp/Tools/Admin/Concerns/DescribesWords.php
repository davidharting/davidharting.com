<?php

namespace App\Mcp\Tools\Admin\Concerns;

use App\Models\Word;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;

/**
 * The word fields add-word and edit-word share: how senses are validated and
 * described to a caller, and how a stored word is returned.
 */
trait DescribesWords
{
    /**
     * Validation rules for the senses list. Present means "replace them all".
     *
     * @return array<string, list<string>>
     */
    protected function sensesRules(): array
    {
        return [
            'senses' => ['sometimes', 'array', 'list'],
            'senses.*' => ['array:part_of_speech,definition,examples'],
            'senses.*.part_of_speech' => ['sometimes', 'nullable', 'string', 'max:50'],
            'senses.*.definition' => ['required', 'string', 'filled'],
            'senses.*.examples' => ['sometimes', 'array', 'list'],
            'senses.*.examples.*' => ['string', 'filled'],
        ];
    }

    protected function sensesSchema(JsonSchema $schema): ArrayType
    {
        return $schema->array()
            ->items($schema->object([
                'part_of_speech' => $schema->string()->nullable()->description('For example noun, verb or adjective.'),
                'definition' => $schema->string()->required()->description('The dictionary definition of this sense.'),
                'examples' => $schema->array()->items($schema->string())->description('Example sentences using the word in this sense.'),
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function wordResult(Word $word): array
    {
        return [
            'word_id' => $word->id,
            'word' => $word->word,
            'short_definition' => $word->short_definition,
            'senses' => $word->senses,
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    protected function wordOutputSchema(JsonSchema $schema): array
    {
        return [
            'word_id' => $schema->integer()->description('The internal id of the word, for edit-word.'),
            'word' => $schema->string()->description('The word as stored.'),
            'short_definition' => $schema->string()->nullable()->description('David\'s plain-words definition, if one is stored.'),
            'senses' => $this->sensesSchema($schema)->description('The dictionary senses David is learning the word in.'),
        ];
    }
}
