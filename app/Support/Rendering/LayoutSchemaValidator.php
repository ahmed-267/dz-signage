<?php

namespace App\Support\Rendering;

use App\Support\Widgets\WidgetConfigValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class LayoutSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $schema): array
    {
        $validator = Validator::make($schema, [
            'schemaVersion' => ['required', 'integer', 'in:'.LayoutSchema::SCHEMA_VERSION],
            'canvas' => ['required', 'array'],
            'canvas.width' => ['required', 'integer', 'min:1'],
            'canvas.height' => ['required', 'integer', 'min:1'],
            'canvas.orientation' => ['required', 'string'],
            'canvas.background' => ['required', 'array'],
            'theme' => ['required', 'string'],
            // Empty canvases are valid — `required` rejects empty arrays.
            'elements' => ['present', 'array'],
            'elements.*.id' => ['required', 'string'],
            'elements.*.type' => ['required', 'string'],
            'elements.*.x' => ['required', 'numeric'],
            'elements.*.y' => ['required', 'numeric'],
            'elements.*.width' => ['required', 'numeric'],
            'elements.*.height' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(
                collect($validator->errors()->messages())
                    ->mapWithKeys(fn (array $messages, string $key) => ["schema.$key" => $messages])
                    ->all(),
            );
        }

        self::validateBrandBindings($schema);

        return WidgetConfigValidator::validateSchema($schema);
    }

    /**
     * @param  array<string, mixed>  $schema
     *
     * @throws ValidationException
     */
    private static function validateBrandBindings(array $schema): void
    {
        $allowed = [
            'brand.business_name',
            'brand.tagline',
            'brand.logo',
            'brand.primary_color',
            'brand.secondary_color',
            'brand.accent_color',
            'brand.background_color',
            'brand.text_color',
            'brand.heading_font',
            'brand.body_font',
        ];

        $errors = [];

        $canvasBinding = $schema['canvas']['background']['brandBinding'] ?? null;
        if ($canvasBinding !== null && $canvasBinding !== '') {
            if (! is_string($canvasBinding) || ! in_array($canvasBinding, $allowed, true)) {
                $errors['schema.canvas.background.brandBinding'] = ['Invalid brandBinding value.'];
            }
        }

        $elements = $schema['elements'] ?? [];
        if (! is_array($elements)) {
            return;
        }

        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            $binding = $element['props']['brandBinding'] ?? null;
            if ($binding === null || $binding === '') {
                continue;
            }

            if (! is_string($binding) || ! in_array($binding, $allowed, true)) {
                $errors["schema.elements.$index.props.brandBinding"] = ['Invalid brandBinding value.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
