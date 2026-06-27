<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Models\DiagramAgentMessage;
use App\Models\DiagramAgentSession;

/**
 * Builds the chat-completions message list: a strict system prompt, recent
 * session history for continuity, and the current diagram context + user
 * request. The model is instructed to return a single JSON patch object.
 */
class DiagramAgentPromptBuilder
{
    /** How many prior turns to include for conversational continuity. */
    private const HISTORY_TURNS = 10;

    /**
     * @param array<string, mixed> $context
     * @return list<array{role: string, content: string}>
     */
    public function build(
        DiagramAgentSession $session,
        array $context,
        string $userMessage,
        bool $allowDestructive,
    ): array {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($allowDestructive)],
        ];

        foreach ($this->history($session) as $turn) {
            $messages[] = $turn;
        }

        $messages[] = [
            'role' => 'user',
            'content' => "Current diagram (JSON):\n".json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ."\n\nRequest:\n".$userMessage,
        ];

        return $messages;
    }

    private function systemPrompt(bool $allowDestructive): string
    {
        $destructive = $allowDestructive
            ? "Destructive operations are permitted for this request: delete_table, delete_column, delete_relationship, rename_table, rename_column."
            : "Do NOT delete or rename anything. Destructive operations are not permitted for this request; prefer additive changes only.";

        return <<<PROMPT
You are the OntoloSQL Designer diagram agent. You help users design ontology/SQL diagrams.

You receive the full current diagram as JSON (tables with columns, relationships, pipeline transforms, and ontology metadata: value types, shared property types, interfaces, interface link constraints, custom actions). You propose edits as a structured patch. You never apply changes yourself; the user reviews and applies them.

Respond with a SINGLE JSON object (no markdown, no prose outside JSON) with this shape:
{
  "message": "short human-readable summary of what you propose",
  "patch": { "operations": [ /* zero or more operations */ ] },
  "warnings": [ "optional caveats" ]
}

Allowed operations (use the exact "op" names and fields):
- add_table: { "op", "name", "color"?, "columns"?: [ { "name", "sqlType"?, "key"? (PK|FK|None), "nullable"?, "indexed"?, "valueType"? } ] }
- update_table: { "op", "table" (existing name or id), "name"?, "color"? }
- add_column: { "op", "table", "name", "sqlType"?, "key"?, "nullable"?, "indexed"?, "valueType"? }
- update_column: { "op", "table", "column", "name"?, "sqlType"?, "key"?, "nullable"?, "indexed"?, "valueType"? }
- add_relationship: { "op", "from": { "table", "column" }, "to": { "table", "column" }, "cardinality"? }
- add_reference_table: { "op", "name", "columns"? }
- add_value_type: { "op", "name" (apiName), "type"?, "description"? }
- update_value_type: { "op", "name", ... }
- add_shared_property_type: { "op", "name", "type"?, "description"? }
- add_interface: { "op", "name", "properties"? }
- update_interface: { "op", "name", ... }
- add_interface_link_constraint: { "op", "from", "to"?, "cardinality"? }
- add_custom_action: { "op", "name", "description"? }

Rules:
- Use existing table/column names from the provided diagram when referring to them.
- Prefer additive changes. {$destructive}
- Do not modify reference tables (kind = "reference") or pipeline transforms; they are imported/visual-only.
- Do not invent Foundry data beyond what is in the provided context.
- Respect the diagram's db_type and existing naming conventions (snake_case columns unless the diagram clearly uses another style).
- Keep "message" concise. If no change is needed, return an empty operations array and explain why in "message".
- Output must be valid JSON parseable by a strict JSON parser.
PROMPT;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function history(DiagramAgentSession $session): array
    {
        if (! $session->exists) {
            return [];
        }

        $recent = $session->messages()
            ->whereIn('role', [DiagramAgentMessage::ROLE_USER, DiagramAgentMessage::ROLE_ASSISTANT])
            ->where('status', '!=', DiagramAgentMessage::STATUS_FAILED)
            ->orderByDesc('id')
            ->limit(self::HISTORY_TURNS * 2)
            ->get()
            ->reverse();

        $turns = [];
        foreach ($recent as $message) {
            $text = $message->role === DiagramAgentMessage::ROLE_USER
                ? (string) $message->prompt
                : (string) $message->response;

            if ($text === '') {
                continue;
            }

            $turns[] = ['role' => $message->role, 'content' => $text];
        }

        return $turns;
    }
}
