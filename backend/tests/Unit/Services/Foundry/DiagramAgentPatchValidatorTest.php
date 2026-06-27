<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Foundry;

use App\Exceptions\FoundryException;
use App\Services\Foundry\DiagramAgentPatchValidator;
use PHPUnit\Framework\TestCase;

class DiagramAgentPatchValidatorTest extends TestCase
{
    private DiagramAgentPatchValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DiagramAgentPatchValidator();
    }

    public function test_parses_message_and_valid_operations(): void
    {
        $content = json_encode([
            'message' => 'Adding a customer table.',
            'patch' => ['operations' => [
                ['op' => 'add_table', 'name' => 'Customer', 'columns' => [['name' => 'id', 'key' => 'PK']]],
            ]],
            'warnings' => ['Heads up'],
        ]);

        $result = $this->validator->parse($content, false);

        $this->assertSame('Adding a customer table.', $result['message']);
        $this->assertCount(1, $result['operations']);
        $this->assertSame('add_table', $result['operations'][0]['op']);
        $this->assertContains('Heads up', $result['warnings']);
    }

    public function test_strips_markdown_code_fence(): void
    {
        $content = "```json\n{\"message\":\"hi\",\"patch\":{\"operations\":[]}}\n```";

        $result = $this->validator->parse($content, false);

        $this->assertSame('hi', $result['message']);
        $this->assertSame([], $result['operations']);
    }

    public function test_drops_unknown_operations_with_warning(): void
    {
        $content = json_encode([
            'message' => 'ok',
            'operations' => [
                ['op' => 'drop_database'],
                ['op' => 'add_table', 'name' => 'Order'],
            ],
        ]);

        $result = $this->validator->parse($content, false);

        $this->assertCount(1, $result['operations']);
        $this->assertSame('add_table', $result['operations'][0]['op']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_drops_destructive_operations_unless_allowed(): void
    {
        $content = json_encode([
            'operations' => [
                ['op' => 'delete_table', 'table' => 'Customer'],
            ],
        ]);

        $blocked = $this->validator->parse($content, false);
        $this->assertSame([], $blocked['operations']);
        $this->assertNotEmpty($blocked['warnings']);

        $allowed = $this->validator->parse($content, true);
        $this->assertCount(1, $allowed['operations']);
        $this->assertSame('delete_table', $allowed['operations'][0]['op']);
    }

    public function test_drops_operations_missing_required_fields(): void
    {
        $content = json_encode([
            'operations' => [
                ['op' => 'add_column', 'table' => 'Customer'], // missing name
                ['op' => 'add_relationship', 'from' => ['table' => 'A', 'column' => 'id'], 'to' => ['table' => 'B', 'column' => 'a_id']],
            ],
        ]);

        $result = $this->validator->parse($content, false);

        $this->assertCount(1, $result['operations']);
        $this->assertSame('add_relationship', $result['operations'][0]['op']);
    }

    public function test_accepts_metadata_apiname_as_name(): void
    {
        $content = json_encode([
            'operations' => [
                ['op' => 'add_value_type', 'apiName' => 'emailAddress', 'type' => 'string'],
            ],
        ]);

        $result = $this->validator->parse($content, false);

        $this->assertCount(1, $result['operations']);
        $this->assertSame('emailAddress', $result['operations'][0]['name']);
    }

    public function test_throws_on_non_json(): void
    {
        $this->expectException(FoundryException::class);
        $this->validator->parse('I cannot help with that.', false);
    }
}
