<?php

namespace App\Services\Ai;

/**
 * Un esquema con nombre, listo para la salida estructurada de Claude.
 *
 * Cada objeto declara `additionalProperties: false` y lista **todas** sus
 * propiedades en `required`; los campos opcionales se expresan con cadena
 * vacía. Claude no admite restricciones numéricas (`minimum`/`maximum`), así
 * que los rangos van en la descripción y se acotan en el DTO que lee la
 * respuesta. Los helpers construyen los nodos ya conformes.
 */
final readonly class JsonSchema
{
    /**
     * @param  array<string, mixed>  $schema
     */
    private function __construct(
        public string $name,
        public array $schema,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $properties
     */
    public static function object(string $name, array $properties): self
    {
        return new self($name, self::shape($properties));
    }

    /**
     * Objeto anidado con todas sus claves obligatorias.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    public static function shape(array $properties, ?string $description = null): array
    {
        return array_filter([
            'type' => 'object',
            'description' => $description,
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Cadena obligatoria.
     *
     * @return array<string, mixed>
     */
    public static function string(string $description): array
    {
        return ['type' => 'string', 'description' => $description];
    }

    /**
     * Campo opcional. Claude admite como máximo 16 uniones (`anyOf`) por
     * esquema y el CV tiene más campos opcionales que eso, así que el hueco se
     * expresa con cadena vacía: `Cast::nullableString` la convierte en null.
     *
     * @return array<string, mixed>
     */
    public static function nullableString(string $description): array
    {
        return [
            'type' => 'string',
            'description' => $description.' Devolvé una cadena vacía si el dato no aparece en la fuente; no lo inventes.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function integer(string $description): array
    {
        return ['type' => 'integer', 'description' => $description];
    }

    /**
     * @return array<string, mixed>
     */
    public static function enum(string $description, array $values): array
    {
        return ['type' => 'string', 'description' => $description, 'enum' => $values];
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    public static function array(string $description, array $items): array
    {
        return ['type' => 'array', 'description' => $description, 'items' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    public static function stringArray(string $description): array
    {
        return self::array($description, ['type' => 'string']);
    }

    /**
     * Payload tal como lo espera `output_config.format` de la Messages API.
     *
     * @return array<string, mixed>
     */
    public function toOutputFormat(): array
    {
        return ['type' => 'json_schema', 'schema' => $this->schema];
    }
}
