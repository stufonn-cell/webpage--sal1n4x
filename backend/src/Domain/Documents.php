<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Documents
{
    public const CATEGORIES = [
        'general' => 'General',
        'informe' => 'Informe clínico',
        'remision' => 'Remisión',
        'soporte' => 'Soporte administrativo',
        'externo' => 'Documento externo',
    ];

    public const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

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
