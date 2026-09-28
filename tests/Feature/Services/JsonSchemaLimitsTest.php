<?php

use App\Services\Ai\JsonSchema;

/**
 * Claude rechaza con un 400 los esquemas con más de 16 uniones (anyOf o
 * `type` con varios tipos) y los que usan restricciones que no soporta.
 */
function schemaViolations(array $node, int &$unions, array &$unsupported, string $path = '$'): void
{
    if (isset($node['anyOf']) || (isset($node['type']) && is_array($node['type']))) {
        $unions++;
    }

    foreach (['minimum', 'maximum', 'multipleOf', 'minLength', 'maxLength'] as $keyword) {
        if (array_key_exists($keyword, $node)) {
            $unsupported[] = "{$path}.{$keyword}";
        }
    }

    foreach ($node as $key => $child) {
        if (is_array($child)) {
            schemaViolations($child, $unions, $unsupported, "{$path}.{$key}");
        }
    }
}

it('mantiene cada esquema dentro de los límites de la salida estructurada de Claude', function (string $class): void {
    /** @var JsonSchema $schema */
    $schema = $class::structure();
    $unions = 0;
    $unsupported = [];

    schemaViolations($schema->toOutputFormat()['schema'], $unions, $unsupported);

    expect($unions)->toBeLessThanOrEqual(16)
        ->and($unsupported)->toBe([]);
})->with(array_map(
    fn (string $file): string => 'App\\Services\\Ai\\Schemas\\'.pathinfo($file, PATHINFO_FILENAME),
    glob(dirname(__DIR__, 3).'/app/Services/Ai/Schemas/*.php'),
));
