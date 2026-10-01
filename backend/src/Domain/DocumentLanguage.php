<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

/**
 * Language of the documents the practice hands to patients (informed
 * consents, printed notes, invoices and assessment reports). The interface
 * stays in English; only the documents can also be produced in Spanish.
 */
final class DocumentLanguage
{
    public const LANGUAGES = ['en' => 'English', 'es' => 'Spanish (Español)'];

    public const DEFAULT = 'en';

    public static function isValid(string $language): bool
    {
        return array_key_exists($language, self::LANGUAGES);
    }

    /** The requested language, or the practice default when it is missing or unknown. */
    public static function resolve(string $language): string
    {
        if (self::isValid($language)) {
            return $language;
        }

        $default = Settings::get('document_language', self::DEFAULT);

        return self::isValid($default) ? $default : self::DEFAULT;
    }
}
