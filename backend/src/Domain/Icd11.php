<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

/**
 * ICD-11 MMS 2025-01 catalog, adopted in Colombia by Resolution 1442 of 2024.
 * Code and title only, plus the WHO ICD-10 equivalent used for dual coding
 * during the transition (RIPS still requires ICD-10).
 *
 * Titles are shown in English. The Spanish title of the official release is
 * kept in the table so a search in either language finds the code.
 *
 * Source: World Health Organization, ICD-11 for Mortality and Morbidity
 * Statistics, release 2025-01. Licence CC BY-ND 3.0 IGO.
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

    /** Loads the catalog from database/data. Returns the number of rows loaded. */
    public static function import(string $directory): int
    {
        $equivalences = [];
        $map = fopen($directory . '/icd11-to-icd10.tsv', 'rb');
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

        $catalog = fopen($directory . '/icd11-' . self::RELEASE . '.tsv', 'rb');
        if ($catalog === false) {
            throw new \RuntimeException('The ICD-11 catalog file was not found.');
        }

        fgets($catalog);
        $rows = [];
        $total = 0;

        while (($line = fgets($catalog)) !== false) {
            [$code, $titleEs, $titleEn, $chapter, $leaf] = array_pad(explode("\t", rtrim($line, "\r\n")), 5, '');
            if ($code === '' || ($titleEn === '' && $titleEs === '')) {
                continue;
            }
            $rows[] = [
                $code,
                mb_substr($titleEs !== '' ? $titleEs : $titleEn, 0, 400),
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
     * Searches by code or by words of the title. Mental health codes
     * (chapter 06) come first, then exact and prefix code matches.
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
            $where[] = '(title_en LIKE :' . $key . 'n OR title_es LIKE :' . $key . 'e OR code LIKE :' . $key . 'c)';
            $like = Database::like($word);
            $params[$key . 'n'] = $like;
            $params[$key . 'e'] = $like;
            $params[$key . 'c'] = Database::like(strtoupper($word), 'prefix');
        }

        $params['exact'] = strtoupper($term);
        $params['prefix'] = Database::like(strtoupper($term), 'prefix');

        return array_map([self::class, 'present'], Database::all(
            'SELECT code, title_en AS title, chapter, is_leaf, icd10_code
             FROM icd11_codes
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY (code = :exact) DESC, (code LIKE :prefix) DESC,
                      (chapter = "' . self::MENTAL_HEALTH_CHAPTER . '") DESC, is_leaf DESC, CHAR_LENGTH(title_en)' .
            Database::limit($limit, 0, 50),
            $params
        ));
    }

    public static function find(string $code): ?array
    {
        $row = Database::first(
            'SELECT code, title_en AS title, chapter, is_leaf, icd10_code FROM icd11_codes WHERE code = :code',
            ['code' => strtoupper(trim($code))]
        );

        return $row === null ? null : self::present($row);
    }

    private static function present(array $row): array
    {
        return [
            'code' => (string) $row['code'],
            'title' => (string) $row['title'],
            'chapter' => (string) $row['chapter'],
            'is_leaf' => (int) $row['is_leaf'] === 1,
            'icd10_code' => $row['icd10_code'] !== null ? (string) $row['icd10_code'] : null,
        ];
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
