<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\ConsentTemplates;
use PsiClinic\Domain\DocumentLanguage;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Present;
use PsiClinic\Support\Signature;

final class ConsentController extends Controller
{
    public function index(Request $request): void
    {
        $this->ok(array_map(
            static fn (array $row): array => Present::consent($row, true),
            Database::all(
                'SELECT c.id, c.patient_id, c.template_code, c.language, c.title, c.status, c.signed_name, c.signed_at,
                        c.created_at, p.first_name, p.last_name, p.record_number
                 FROM consents c
                 JOIN patients p ON p.id = c.patient_id
                 ORDER BY c.created_at DESC
                 LIMIT 100'
            )
        ));
    }

    /**
     * Creates a consent from a template, in English or Spanish. The text is
     * copied into the consent so it never changes after the patient signs.
     */
    public function store(Request $request): void
    {
        $patientId = $request->integer('patient_id');
        $code = $request->string('template_code');
        $requested = $request->string('language');

        if ($requested !== '' && !DocumentLanguage::isValid($requested)) {
            throw HttpException::unprocessable('Choose English or Spanish for the document.', ['language' => 'Choose a language.']);
        }

        $language = DocumentLanguage::resolve($requested);
        $template = ConsentTemplates::get($code, $language);

        if (Patients::find($patientId) === null || $template === null) {
            throw HttpException::unprocessable('Select a patient and a template.');
        }

        $id = Database::insert('consents', [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'template_code' => $code,
            'language' => $language,
            'title' => $template['title'],
            'body' => $template['body'],
            'created_by' => Auth::id(),
        ]);
        AuditLog::record('create', 'consent', $id);

        $this->created(['id' => $id], 'Consent created. The patient can now sign it from their portal.');
    }

    public function show(Request $request, string $id): void
    {
        $consent = $this->findAccessible((int) $id);
        AuditLog::record('view', 'consent', (int) $consent['id']);

        $this->ok(Present::consent($consent, Auth::isStaff()));
    }

    public function sign(Request $request, string $id): void
    {
        $consent = $this->findAccessible((int) $id);

        if ($consent['status'] !== 'pending') {
            throw HttpException::conflict('This consent has already been signed.');
        }

        $signedName = mb_substr($request->string('signed_name'), 0, 160);
        $signature = Signature::fromStrokes($request->array('strokes'));

        $errors = [];
        if ($signedName === '') {
            $errors['signed_name'] = 'Type your full name.';
        }
        if ($signature === '') {
            $errors['strokes'] = 'Draw your signature in the box.';
        }
        if ($errors !== []) {
            throw HttpException::unprocessable('The signature is not complete yet.', $errors);
        }

        Database::update('consents', (int) $consent['id'], [
            'status' => 'signed',
            'signed_name' => $signedName,
            'signature_svg' => $signature,
            'signed_at' => date('Y-m-d H:i:s'),
            'signed_ip' => $request->ip(),
        ]);
        AuditLog::record('sign', 'consent', (int) $consent['id']);

        $this->message('Consent signed. Thank you.');
    }

    /**
     * The team sees any consent; a patient only sees their own. Before, changing
     * the id in the URL was enough to view or sign someone else's.
     */
    private function findAccessible(int $id): array
    {
        $consent = $this->abortIfMissing(Database::first(
            'SELECT c.*, p.first_name, p.last_name, p.record_number, p.document_id
             FROM consents c JOIN patients p ON p.id = c.patient_id WHERE c.id = :id',
            ['id' => $id]
        ), "We couldn't find this consent.");

        if (!Auth::isStaff() && (int) $consent['patient_id'] !== Auth::patientId()) {
            throw HttpException::notFound("We couldn't find this consent.");
        }

        return $consent;
    }
}
