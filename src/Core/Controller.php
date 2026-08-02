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
    protected function view(string $template, array $data = [], string $layout = 'app'): void
    {
        Response::html(View::render($template, $data, $layout));
    }

    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function redirect(string $path): void
    {
        Response::redirect($path);
    }

    protected function back(Request $request, array $errors = []): void
    {
        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($request->all());
        }

        Response::redirect((string) ($_SERVER['HTTP_REFERER'] ?? '/dashboard'));
    }

    protected function validate(Request $request, array $rules): array
    {
        $validator = (new Validator($request->all()))->validate($rules);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Session::flashInput($request->all());
            Session::flash('error', 'Revisa los campos marcados en el formulario.');
            Response::redirect((string) ($_SERVER['HTTP_REFERER'] ?? '/dashboard'));
        }

        return $request->all();
    }

    protected function abortIfMissing(mixed $record): array
    {
        if (!is_array($record)) {
            http_response_code(404);
            Response::html(View::render('errors/404', [], 'blank'), 404);
            exit;
        }

        return $record;
    }
}
