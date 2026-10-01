<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\Request;

final class SearchController extends Controller
{
    public function index(Request $request): void
    {
        $term = $request->string('q');

        if (mb_strlen($term) < 2) {
            $this->json(['results' => []]);

            return;
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';

        $patients = Database::all(
            'SELECT id, record_number, first_name, last_name
             FROM patients
             WHERE first_name LIKE :s1 OR last_name LIKE :s2 OR record_number LIKE :s3 OR document_id LIKE :s4
                OR CONCAT(first_name, " ", last_name) LIKE :s5
             ORDER BY last_name LIMIT 6',
            ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like, 's5' => $like]
        );

        $results = [];

        foreach ($patients as $patient) {
            $results[] = [
                'group' => __('Pacientes'),
                'label' => $patient['first_name'] . ' ' . $patient['last_name'],
                'meta' => (string) $patient['record_number'],
                'url' => '/app/pacientes/' . (int) $patient['id'],
            ];
        }

        foreach (Database::all(
            'SELECT n.id, n.session_date, n.session_number, p.first_name, p.last_name
             FROM clinical_notes n
             JOIN patients p ON p.id = n.patient_id
             WHERE n.subjective LIKE :s1 OR n.assessment LIKE :s2 OR n.plan LIKE :s3
             ORDER BY n.id DESC LIMIT 5',
            ['s1' => $like, 's2' => $like, 's3' => $like]
        ) as $note) {
            $results[] = [
                'group' => __('Notas'),
                'label' => __('Sesión %d · %s %s', (int) $note['session_number'], $note['first_name'], $note['last_name']),
                'meta' => (string) $note['session_date'],
                'url' => '/app/notas/' . (int) $note['id'],
            ];
        }

        // Las etiquetas viajan como texto plano; la interfaz las escapa al pintarlas.
        $this->json(['results' => $results]);
    }
}
