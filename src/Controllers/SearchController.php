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
        $term = trim((string) $request->input('q', ''));

        if (mb_strlen($term) < 2) {
            $this->json(['results' => []]);

            return;
        }

        $like = '%' . $term . '%';

        $patients = Database::all(
            'SELECT id, record_number, first_name, last_name
             FROM patients
             WHERE first_name LIKE :s OR last_name LIKE :s OR record_number LIKE :s OR document_id LIKE :s
             ORDER BY last_name LIMIT 6',
            ['s' => $like]
        );

        $results = [];

        foreach ($patients as $patient) {
            $results[] = [
                'group' => 'Pacientes',
                'label' => $patient['first_name'] . ' ' . $patient['last_name'],
                'meta' => (string) $patient['record_number'],
                'url' => '/pacientes/' . (int) $patient['id'],
            ];
        }

        foreach (Database::all(
            'SELECT n.id, n.session_date, n.session_number, p.first_name, p.last_name
             FROM clinical_notes n
             JOIN patients p ON p.id = n.patient_id
             WHERE n.subjective LIKE :s OR n.assessment LIKE :s OR n.plan LIKE :s
             ORDER BY n.id DESC LIMIT 5',
            ['s' => $like]
        ) as $note) {
            $results[] = [
                'group' => 'Notas',
                'label' => sprintf('Sesion %d - %s %s', (int) $note['session_number'], $note['first_name'], $note['last_name']),
                'meta' => (string) $note['session_date'],
                'url' => '/notas/' . (int) $note['id'],
            ];
        }

        $this->json(['results' => $results]);
    }
}
