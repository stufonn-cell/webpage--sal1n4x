<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

final class Validator
{
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

    private function apply(string $field, string $rule): void
    {
        $parameter = null;
        if (str_contains($rule, ':')) {
            [$rule, $parameter] = explode(':', $rule, 2);
        }

        $value = $this->data[$field] ?? null;
        $value = is_string($value) ? trim($value) : $value;

        match ($rule) {
            'required' => $this->check($field, $value !== null && $value !== '' && $value !== [], 'Este campo es obligatorio.'),
            'email' => $this->check($field, $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false, 'Revisa el correo; parece incompleto.'),
            'numeric' => $this->check($field, $value === null || $value === '' || is_numeric($value), 'Escribe solo números.'),
            'date' => $this->check($field, $value === null || $value === '' || strtotime((string) $value) !== false, 'Revisa la fecha.'),
            'min' => $this->check($field, $value === null || $value === '' || mb_strlen((string) $value) >= (int) $parameter, __('Debe tener al menos %d caracteres.', (int) $parameter)),
            'max' => $this->check($field, $value === null || $value === '' || mb_strlen((string) $value) <= (int) $parameter, __('No debe superar %d caracteres.', (int) $parameter)),
            'in' => $this->check($field, $value === null || $value === '' || in_array((string) $value, explode(',', (string) $parameter), true), 'Elige una de las opciones.'),
            'confirmed' => $this->check($field, ($this->data[$field . '_confirmation'] ?? null) === $value, 'La confirmación no coincide.'),
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
