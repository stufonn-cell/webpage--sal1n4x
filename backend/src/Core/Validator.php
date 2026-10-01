<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Validator
{
    /** Rules that only make sense for a single text or number value. */
    private const SCALAR_RULES = ['email', 'numeric', 'integer', 'date', 'min', 'max', 'in'];

    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function validate(array $rules): self
    {
        foreach ($rules as $field => $ruleSet) {
            foreach (explode('|', $ruleSet) as $rule) {
                $this->apply($field, $rule);
            }
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Calendar date as the interface sends it: YYYY-MM-DD, optionally followed
     * by a time (HH:MM or HH:MM:SS). Relative words such as "tomorrow" are not
     * dates: MySQL would reject them.
     */
    public static function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', $value, $parts) !== 1) {
            return false;
        }

        [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        if ($year < 1000 || !checkdate($month, $day, $year)) {
            return false;
        }

        return (int) ($parts[4] ?? 0) <= 23 && (int) ($parts[5] ?? 0) <= 59 && (int) ($parts[6] ?? 0) <= 59;
    }

    private function apply(string $field, string $rule): void
    {
        $parameter = null;
        if (str_contains($rule, ':')) {
            [$rule, $parameter] = explode(':', $rule, 2);
        }

        $value = $this->data[$field] ?? null;
        $value = is_string($value) ? trim($value) : $value;

        // A list or an object where text is expected is refused instead of
        // being silently turned into "Array".
        if (in_array($rule, self::SCALAR_RULES, true) && $value !== null && !is_scalar($value)) {
            $this->check($field, false, 'Please check this field.');

            return;
        }

        $empty = $value === null || $value === '';

        match ($rule) {
            'required' => $this->check($field, $value !== null && $value !== '' && $value !== [], 'This field is required.'),
            'email' => $this->check($field, $empty || filter_var($value, FILTER_VALIDATE_EMAIL) !== false, 'Please check the email; it looks incomplete.'),
            'numeric' => $this->check($field, $empty || (is_numeric($value) && is_finite((float) $value)), 'Use numbers only.'),
            'integer' => $this->check($field, $empty || filter_var($value, FILTER_VALIDATE_INT) !== false, 'Use whole numbers only.'),
            'date' => $this->check($field, $empty || self::isDate((string) $value), 'Please check the date.'),
            'min' => $this->check($field, $empty || mb_strlen((string) $value) >= (int) $parameter, sprintf('Must be at least %d characters.', (int) $parameter)),
            'max' => $this->check($field, $empty || mb_strlen((string) $value) <= (int) $parameter, sprintf('Must be %d characters or fewer.', (int) $parameter)),
            'in' => $this->check($field, $empty || in_array((string) $value, explode(',', (string) $parameter), true), 'Choose one of the options.'),
            'confirmed' => $this->check($field, ($this->data[$field . '_confirmation'] ?? null) === $value, "The confirmation doesn't match."),
            default => null,
        };
    }

    private function check(string $field, bool $passes, string $message): void
    {
        if (!$passes && !isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
