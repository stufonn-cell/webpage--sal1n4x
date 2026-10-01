<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

/**
 * An expected API error. The message is shown to the user as is, so it must
 * never contain technical details.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $message,
        private readonly array $fields = [],
        private readonly array $headers = []
    ) {
        parent::__construct($message, $status);
    }

    public static function badRequest(string $message = "We couldn't read the information you sent. Please reload the page and try again."): self
    {
        return new self(400, $message);
    }

    public static function notFound(string $message = "We couldn't find what you're looking for."): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = "You don't have permission to do this."): self
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

    public static function payloadTooLarge(string $message = 'What you sent is too large. Please shorten it and try again.'): self
    {
        return new self(413, $message);
    }

    /** 429 with the Retry-After header (seconds) the client should honour. */
    public static function tooManyRequests(string $message, int $retryAfter): self
    {
        return new self(429, $message, [], ['Retry-After' => (string) max(1, $retryAfter)]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    /** Extra response headers, e.g. Retry-After. */
    public function headers(): array
    {
        return $this->headers;
    }

    public function toArray(): array
    {
        $error = ['message' => $this->getMessage()];

        if ($this->fields !== []) {
            $error['fields'] = $this->fields;
        }

        return ['error' => $error];
    }
}
