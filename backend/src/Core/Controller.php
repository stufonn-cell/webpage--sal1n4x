<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Core;

abstract class Controller
{
    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function ok(mixed $data, int $status = 200): void
    {
        Response::json(['data' => $data], $status);
    }

    protected function created(mixed $data, string $message = ''): void
    {
        Response::json(['data' => $data] + ($message === '' ? [] : ['message' => $message]), 201);
    }

    protected function message(string $message, mixed $data = null): void
    {
        Response::json(['message' => $message, 'data' => $data]);
    }

    /**
     * Validates the input and stops the request with a 422 that carries the
     * error for each field, so the form can show it next to the input.
     */
    protected function validate(Request $request, array $rules): array
    {
        $validator = (new Validator($request->all()))->validate($rules);

        if ($validator->fails()) {
            throw HttpException::unprocessable('Please check the highlighted fields in the form.', $validator->errors());
        }

        return $request->all();
    }

    protected function abortIfMissing(mixed $record, string $message = "We couldn't find what you're looking for."): array
    {
        if (!is_array($record)) {
            throw HttpException::notFound($message);
        }

        return $record;
    }

    /**
     * Restricts a value to the keys of a catalog (a database ENUM). This keeps
     * an arbitrary value from reaching MySQL in strict mode and causing a 500.
     */
    protected function oneOf(string $value, array $catalog, string $default): string
    {
        return array_key_exists($value, $catalog) ? $value : $default;
    }
}
