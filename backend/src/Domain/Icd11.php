<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;
use PsiClinic\Core\Lang;

/**
 * Catalogo CIE-11 MMS 2025-01, adoptado en Colombia por la Resolucion 1442
 * de 2024. Solo codigo y nombre (espanol e ingles), mas la equivalencia
 * CIE-10 de la OMS para la codificacion dual durante la transicion.
 *
 * Fuente: Organizacion Mundial de la Salud, ICD-11 for Mortality and
 * Morbidity Statistics, release 2025-01. Licencia CC BY-ND 3.0 IGO.
 */
final class Icd11
{
    public const RELEASE = '2025-01';
    public const MENTAL_HEALTH_CHAPTER = '06';
    private const BATCH = 500;

    public static function isLoaded(): bool
    {
        return (int) Database::value('SELECT COUNT(*) FROM icd11_codes') > 0;
    }

    /** Carga el catalogo desde database/data. Devuelve las filas cargadas. */
    public static function import(string $directory): int
    {
        $equivalences = [];
        $map = fopen($directory . '/cie11-a-cie10.tsv', 'rb');
        if ($map !== false) {
            fgets($map);
            while (($line = fgets($map)) !== false) {
                [$icd11, $icd10] = array_pad(explode("\t", rtrim($line, "\r\n")), 2, '');
                if ($icd11 !== '' && $icd10 !== '') {
                    $equivalences[$icd11] = $icd10;
                }
            }
            fclose($map);
        }

        $catalog = fopen($directory . '/cie11-' . self::RELEASE . '.tsv', 'rb');
        if ($catalog === false) {
            throw new \RuntimeException('No se encontro el archivo del catalogo CIE-11.');
        }

        fgets($catalog);
        $rows = [];
        $total = 0;

        while (($line = fgets($catalog)) !== false) {
            [$code, $titleEs, $titleEn, $chapter, $leaf] = array_pad(explode("\t", rtrim($line, "\r\n")), 5, '');
            if ($code === '' || $titleEs === '') {
                continue;
            }
            $rows[] = [
                $code,
                mb_substr($titleEs, 0, 400),
                mb_substr($titleEn !== '' ? $titleEn : $titleEs, 0, 400),
                $chapter,
                $leaf === '1' ? 1 : 0,
                $equivalences[$code] ?? null,
            ];

            if (count($rows) === self::BATCH) {
                $total += self::insert($rows);
                $rows = [];
            }
        }
        fclose($catalog);

        return $total + self::insert($rows);
    }

    /**
     * Busqueda por codigo o por palabras del nombre, en espanol e ingles. Los
     * codigos de salud mental (capitulo 06) aparecen primero. El titulo se
     * devuelve en el idioma de la peticion.
     */
    public static function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $where = [];
        $params = [];
        $words = array_slice(preg_split('/\s+/', $term) ?: [], 0, 5);

        foreach ($words as $index => $word) {
            $key = 'w' . $index;
            $where[] = '(title_es LIKE :' . $key . 'e OR title_en LIKE :' . $key . 'n OR code LIKE :' . $key . 'c)';
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $params[$key . 'e'] = $like;
            $params[$key . 'n'] = $like;
            $params[$key . 'c'] = addcslashes(strtoupper($word), '%_\\') . '%';
        }

        $params['exact'] = strtoupper($term);
        $params['prefix'] = addcslashes(strtoupper($term), '%_\\') . '%';

        return Database::all(
            'SELECT code, ' . self::titleColumn() . ' AS title, chapter, is_leaf, icd10_code
             FROM icd11_codes
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY (code = :exact) DESC, (code LIKE :prefix) DESC,
                      (chapter = "' . self::MENTAL_HEALTH_CHAPTER . '") DESC, is_leaf DESC, CHAR_LENGTH(' . self::titleColumn() . ')
             LIMIT ' . max(1, min($limit, 50)),
            $params
        );
    }

    public static function find(string $code): ?array
    {
        return Database::first(
            'SELECT code, ' . self::titleColumn() . ' AS title, chapter, is_leaf, icd10_code FROM icd11_codes WHERE code = :code',
            ['code' => $code]
        );
    }

    private static function titleColumn(): string
    {
        return Lang::isEnglish() ? 'title_en' : 'title_es';
    }

    private static function insert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($rows as $i => $row) {
            $placeholders[] = sprintf('(:c%1$d, :s%1$d, :n%1$d, :h%1$d, :l%1$d, :e%1$d)', $i);
            $params['c' . $i] = $row[0];
            $params['s' . $i] = $row[1];
            $params['n' . $i] = $row[2];
            $params['h' . $i] = $row[3];
            $params['l' . $i] = $row[4];
            $params['e' . $i] = $row[5];
        }

        Database::run(
            'INSERT INTO icd11_codes (code, title_es, title_en, chapter, is_leaf, icd10_code) VALUES ' . implode(', ', $placeholders) . '
             ON DUPLICATE KEY UPDATE title_es = VALUES(title_es), title_en = VALUES(title_en), chapter = VALUES(chapter),
                                     is_leaf = VALUES(is_leaf), icd10_code = VALUES(icd10_code)',
            $params
        );

        return count($rows);
    }
}
