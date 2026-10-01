<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Documents
{
    public const CATEGORIES = [
        'general' => 'General',
        'report' => 'Clinical report',
        'referral' => 'Referral',
        'administrative' => 'Administrative document',
        'external' => 'External document',
    ];

    public const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    /** Extensions a file of each accepted type may carry in its original name. */
    public const ALLOWED_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'text/plain' => ['txt'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    /**
     * The detected type must be on the list, the name's extension must agree
     * with it (no "report.php" that happens to be plain text) and images must
     * really decode as images.
     */
    public static function isAllowed(string $mime, string $originalName, string $path): bool
    {
        if (!array_key_exists($mime, self::ALLOWED_MIME)) {
            return false;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS[$mime], true)) {
            return false;
        }

        if (str_starts_with($mime, 'image/')) {
            return self::isRealImage($mime, $path);
        }

        return true;
    }

    /** Header of the image is coherent: right type, sane dimensions, PNG header chunk in place. */
    private static function isRealImage(string $mime, string $path): bool
    {
        $info = @getimagesize($path);
        if (!is_array($info) || ($info['mime'] ?? '') !== $mime) {
            return false;
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width < 1 || $height < 1 || $width > 20000 || $height > 20000) {
            return false;
        }

        if ($mime === 'image/png') {
            $head = (string) @file_get_contents($path, false, null, 0, 16);

            return substr($head, 12, 4) === 'IHDR';
        }

        return true;
    }

    /**
     * Name shown to people and used in Content-Disposition: no directories,
     * no control characters, at most 180 characters.
     */
    public static function cleanFileName(string $name): string
    {
        $name = str_replace('\\', '/', mb_scrub($name, 'UTF-8'));
        $slash = strrpos($name, '/');
        $name = $slash === false ? $name : substr($name, $slash + 1);
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $name) ?? '', " .\t");

        if ($name === '') {
            return 'document';
        }
        if (mb_strlen($name) <= 180) {
            return $name;
        }

        // Too long: shorten the name but keep the extension.
        $extension = mb_substr(pathinfo($name, PATHINFO_EXTENSION), 0, 10);

        return mb_substr($name, 0, 179 - mb_strlen($extension)) . '.' . $extension;
    }

    public static function recent(?int $patientId = null, int $limit = 50): array
    {
        $filter = $patientId === null ? '' : ' WHERE d.patient_id = :id';
        $params = $patientId === null ? [] : ['id' => $patientId];

        return Database::all(
            'SELECT d.*, p.first_name, p.last_name, p.record_number, u.full_name AS uploaded_by_name
             FROM documents d
             JOIN patients p ON p.id = d.patient_id
             LEFT JOIN users u ON u.id = d.uploaded_by' . $filter . '
             ORDER BY d.created_at DESC
             LIMIT ' . max(1, min($limit, 200)),
            $params
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM documents WHERE id = :id', ['id' => $id]);
    }

    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes = (int) round($bytes / 1024);
            $index++;
        }

        return $bytes . ' ' . $units[$index];
    }
}
