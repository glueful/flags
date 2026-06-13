<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Services;

use InvalidArgumentException;

/**
 * Structural validation for flag and rule write payloads.
 *
 * Throws InvalidArgumentException on the first invalid field; controllers
 * map that to a 422 validation envelope.
 */
final class FlagPayloadValidator
{
    private const KEY_PATTERN = '/\A[a-z0-9._-]{1,160}\z/';
    private const STATUSES = ['active', 'archived'];
    private const RULE_TYPES = ['user', 'tenant', 'role', 'scope', 'attribute', 'environment', 'percentage'];
    private const OPERATORS = ['in', 'not_in'];
    private const SUBJECTS = ['user', 'tenant', 'custom'];

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function validateCreate(array $payload): array
    {
        if (!array_key_exists('key', $payload)) {
            throw new InvalidArgumentException('Missing required flag field: key.');
        }

        $key = $this->validateKey($payload['key']);

        return [
            'key' => $key,
            'name' => array_key_exists('name', $payload) ? $this->validateName($payload['name']) : $key,
            'description' => $this->nullableString($payload['description'] ?? null, 'description'),
            'enabled' => $this->validateBool($payload['enabled'] ?? false, 'enabled'),
            'default_value' => $this->validateBool($payload['default_value'] ?? false, 'default_value'),
            'status' => $this->validateStatus($payload['status'] ?? 'active'),
        ];
    }

    /**
     * Validates a partial update; only whitelisted fields pass through.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function validatePatch(array $payload): array
    {
        if (array_key_exists('key', $payload)) {
            throw new InvalidArgumentException('key is immutable.');
        }

        $validated = [];

        if (array_key_exists('name', $payload)) {
            $validated['name'] = $this->validateName($payload['name']);
        }

        if (array_key_exists('description', $payload)) {
            $validated['description'] = $this->nullableString($payload['description'], 'description');
        }

        if (array_key_exists('enabled', $payload)) {
            $validated['enabled'] = $this->validateBool($payload['enabled'], 'enabled');
        }

        if (array_key_exists('default_value', $payload)) {
            $validated['default_value'] = $this->validateBool($payload['default_value'], 'default_value');
        }

        if (array_key_exists('status', $payload)) {
            $validated['status'] = $this->validateStatus($payload['status']);
        }

        return $validated;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function validateRule(array $payload): array
    {
        if (!array_key_exists('type', $payload)) {
            throw new InvalidArgumentException('Missing required rule field: type.');
        }

        $type = $this->whitelist($payload['type'], self::RULE_TYPES, 'type');
        $subject = array_key_exists('subject', $payload) && $payload['subject'] !== null
            ? $this->whitelist($payload['subject'], self::SUBJECTS, 'subject')
            : null;
        $value = $payload['value'] ?? null;
        $this->validateValueShape($type, $subject, $value);

        $percentage = null;
        if (array_key_exists('percentage', $payload) && $payload['percentage'] !== null) {
            $percentage = $this->validatePercentage($payload['percentage']);
        }
        if ($type === 'percentage' && $percentage === null) {
            throw new InvalidArgumentException('percentage rules require a percentage between 0 and 100.');
        }

        return [
            'type' => $type,
            'operator' => $this->whitelist($payload['operator'] ?? 'in', self::OPERATORS, 'operator'),
            'value' => $value,
            'priority' => $this->validateInt($payload['priority'] ?? 0, 'priority'),
            'percentage' => $percentage,
            'subject' => $subject,
            'enabled' => $this->validateBool($payload['enabled'] ?? true, 'enabled'),
        ];
    }

    private function validateKey(mixed $value): string
    {
        if (!is_string($value) || preg_match(self::KEY_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('key must match [a-z0-9._-] and be 1-160 characters.');
        }

        return $value;
    }

    private function validateName(mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new InvalidArgumentException('name must be a non-empty string.');
        }

        $name = trim((string) $value);
        if (strlen($name) > 255) {
            throw new InvalidArgumentException('name must be 255 characters or fewer.');
        }

        return $name;
    }

    private function nullableString(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a string or null.', $field));
        }

        return (string) $value;
    }

    private function validateStatus(mixed $value): string
    {
        return $this->whitelist($value, self::STATUSES, 'status');
    }

    private function validateBool(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) (int) $value;
        }

        throw new InvalidArgumentException(sprintf('%s must be a boolean.', $field));
    }

    private function validateInt(mixed $value, string $field): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/\A-?\d+\z/', $value) === 1)) {
            throw new InvalidArgumentException(sprintf('%s must be an integer.', $field));
        }

        return (int) $value;
    }

    private function validatePercentage(mixed $value): int
    {
        $percentage = $this->validateInt($value, 'percentage');
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('percentage must be between 0 and 100.');
        }

        return $percentage;
    }

    private function validateValueShape(string $type, ?string $subject, mixed $value): void
    {
        if ($type === 'attribute') {
            $key = is_array($value) ? ($value['key'] ?? null) : null;
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException(
                    'attribute rules require value to be an object with a non-empty "key".'
                );
            }
        }

        if ($subject === 'custom') {
            $attribute = is_array($value) ? ($value['attribute'] ?? null) : null;
            if (!is_string($attribute) || $attribute === '') {
                throw new InvalidArgumentException(
                    'custom-subject rules require value to be an object with a non-empty "attribute".'
                );
            }
        }

        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('value must be JSON-encodable.');
        }
    }

    /**
     * @param list<string> $allowed
     */
    private function whitelist(mixed $value, array $allowed, string $field): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf('%s must be one of: %s.', $field, implode(', ', $allowed))
            );
        }

        return $value;
    }
}
