<?php

declare(strict_types=1);

namespace App\Support;

final class DiagramSchema
{
    private const TRANSIENT_ELEMENT_KEYS = [
        'computedPosition',
        'dimensions',
        'dragging',
        'events',
        'handleBounds',
        'initialized',
        'isParent',
        'resizing',
        'selected',
    ];

    private const TRANSIENT_DATA_KEYS = [
        'editing',
        'modalPosition',
        'showModal',
        'showOptionsModal',
    ];

    /**
     * Remove Vue Flow runtime state that is recalculated when an element is rendered.
     *
     * @param  array<int, mixed>|null  $schema
     * @return array<int, mixed>|null
     */
    public static function withoutRuntimeState(?array $schema): ?array
    {
        if ($schema === null) {
            return null;
        }

        return array_map(static function (mixed $element): mixed {
            if (! is_array($element)) {
                return $element;
            }

            foreach (self::TRANSIENT_ELEMENT_KEYS as $key) {
                unset($element[$key]);
            }

            if (is_array($element['data'] ?? null)) {
                foreach (self::TRANSIENT_DATA_KEYS as $key) {
                    unset($element['data'][$key]);
                }
            }

            return $element;
        }, $schema);
    }
}
