<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

/**
 * Error esperado de la API. El mensaje se muestra tal cual al usuario, asi que
 * nunca debe contener detalles tecnicos.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $message,
        private readonly array $fields = []
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = 'No encontramos lo que buscas.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'No tienes permiso para realizar esta acción.'): self
    {
        return new self(403, $message);
    }

    public static function unprocessable(string $message, array $fields = []): self
    {
        return new self(422, $message, $fields);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function toArray(): array
    {
        $error = ['message' => Lang::t($this->getMessage())];

        if ($this->fields !== []) {
            $error['fields'] = Lang::all($this->fields);
        }

        return ['error' => $error];
    }
}
