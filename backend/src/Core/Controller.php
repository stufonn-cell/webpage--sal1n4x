<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
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
        Response::json(['data' => $data] + ($message === '' ? [] : ['message' => Lang::t($message)]), 201);
    }

    protected function message(string $message, mixed $data = null): void
    {
        Response::json(['message' => Lang::t($message), 'data' => $data]);
    }

    /**
     * Valida la entrada y corta la peticion con un 422 que lleva el error de
     * cada campo, para que el formulario lo muestre junto al input.
     */
    protected function validate(Request $request, array $rules): array
    {
        $validator = (new Validator($request->all()))->validate($rules);

        if ($validator->fails()) {
            throw HttpException::unprocessable('Revisa los campos marcados en el formulario.', $validator->errors());
        }

        return $request->all();
    }

    protected function abortIfMissing(mixed $record, string $message = 'No encontramos lo que buscas.'): array
    {
        if (!is_array($record)) {
            throw HttpException::notFound($message);
        }

        return $record;
    }

    /**
     * Restringe un valor a las claves de un catalogo (ENUM de la base). Evita
     * que un valor arbitrario llegue a MySQL en modo estricto y produzca un 500.
     */
    protected function oneOf(string $value, array $catalog, string $default): string
    {
        return array_key_exists($value, $catalog) ? $value : $default;
    }
}
