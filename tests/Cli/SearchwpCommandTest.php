<?php

namespace Threespot\Wp\Tests\Cli;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Threespot\Wp\Cli\SearchwpCommand;

class SearchwpCommandTest extends TestCase
{
    private const ENGINES = [
        'default' => [
            'label' => 'Default',
            'settings' => ['stemming' => true, 'adminengine' => false],
            'sources' => [
                'post.page' => [
                    'attributes' => ['title' => 300, 'content' => 150],
                    'rules' => [],
                    'options' => [],
                ],
            ],
        ],
    ];

    public function test_encode_is_readable_json_ending_in_a_newline(): void
    {
        $json = SearchwpCommand::encodeEngines(['default' => ['label' => 'Café / Default', 'sources' => []]]);

        $this->assertStringContainsString("\n    \"default\": {", $json);
        $this->assertStringContainsString('Café / Default', $json);
        $this->assertStringEndsWith("}\n", $json);
    }

    public function test_decode_round_trips_encode(): void
    {
        $this->assertSame(self::ENGINES, SearchwpCommand::decodeEngines(SearchwpCommand::encodeEngines(self::ENGINES)));
    }

    public function test_decode_rejects_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        SearchwpCommand::decodeEngines('{"default": ');
    }

    public function test_decode_rejects_anything_but_named_engines(): void
    {
        $cases = [
            '[]' => 'no engines',
            '"default"' => 'no engines',
            '[{"sources": {}}]' => 'engine names',
            '{"default": "x"}' => '"default" has no sources',
            '{"default": {"label": "Default"}}' => '"default" has no sources',
        ];

        foreach ($cases as $json => $message) {
            try {
                SearchwpCommand::decodeEngines($json);
                $this->fail("Expected an exception for $json");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $json);
            }
        }
    }
}
