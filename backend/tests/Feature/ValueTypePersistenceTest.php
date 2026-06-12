<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Enums\DiagramAccess;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValueTypePersistenceTest extends TestCase
{

    public function test_owner_can_save_and_load_value_types(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $user->id,
            'db_type' => 'ontology',
        ]);
        $valueTypes = [$this->emailValueType()];
        Sanctum::actingAs($user);

        $this->putJson("/api/diagrams/{$diagram->id}", [
            'schema' => [],
            'value_types' => $valueTypes,
        ])->assertOk();

        $this->assertSame('emailAddress', $diagram->fresh()->value_types[0]['apiName']);
        $this->assertSame('regex', $diagram->fresh()->value_types[0]['constraints'][0]['type']);
        $this->getJson("/api/diagrams/{$diagram->id}")
            ->assertOk()
            ->assertJsonPath('data.value_types.0.apiName', 'emailAddress');
    }

    public function test_invalid_value_type_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $user->id,
            'db_type' => 'ontology',
        ]);
        Sanctum::actingAs($user);
        $invalid = $this->emailValueType();
        $invalid['constraints'][0]['regexPattern'] = '[';

        $this->putJson("/api/diagrams/{$diagram->id}", [
            'schema' => [],
            'value_types' => [$invalid],
        ])->assertUnprocessable();
    }

    public function test_schema_cannot_reference_a_missing_value_type(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $user->id,
            'db_type' => 'ontology',
        ]);
        Sanctum::actingAs($user);

        $this->putJson("/api/diagrams/{$diagram->id}", [
            'schema' => [[
                'id' => 'email',
                'type' => 'row',
                'data' => ['valueTypeId' => 'missing'],
            ]],
            'value_types' => [],
        ])->assertUnprocessable();
    }

    public function test_shared_writer_can_save_value_types(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $writer = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'db_type' => 'ontology',
            'share_access' => DiagramAccess::WRITE,
        ]);
        Sanctum::actingAs($writer);

        $this->patchJson("/api/diagrams/shared/{$diagram->share_token}", [
            'schema' => [['id' => 'table', 'type' => 'table', 'label' => 'users']],
            'value_types' => [$this->emailValueType()],
        ])->assertOk();

        $this->assertSame('emailAddress', $diagram->fresh()->value_types[0]['apiName']);
    }

    /** @return array<string, mixed> */
    private function emailValueType(): array
    {
        return [
            'id' => 'email-type',
            'apiName' => 'emailAddress',
            'displayName' => 'Email Address',
            'description' => '',
            'version' => '1.0.0',
            'baseType' => ['type' => 'string'],
            'constraints' => [[
                'id' => 'email-regex',
                'type' => 'regex',
                'regexPattern' => '^[^@]+@[^@]+$',
                'usePartialMatch' => false,
                'failureMessage' => 'Invalid email',
            ]],
        ];
    }
}
