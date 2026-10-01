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
use PsiClinic\Core\OutboundUrl;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Rips;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\MuvClient;
use PsiClinic\Support\Present;

/**
 * RIPS reports without invoice: preview of a period, JSON generation,
 * download for the Ministry's local validator and submission to its Docker API.
 */
final class RipsController extends Controller
{
    /**
     * Builds the validator client from (url, verifyTls). Tests swap it for a
     * client with a fake transport; in production it stays null.
     */
    public static ?\Closure $clientFactory = null;

    public function index(Request $request): void
    {
        $this->ok([
            'reports' => Database::all(
                'SELECT r.id, r.note_number, r.period_start, r.period_end, r.status, r.cuv, r.users_count,
                        r.services_count, r.created_at, r.sent_at, c.full_name AS created_by_name, s.full_name AS sent_by_name
                 FROM rips_reports r
                 LEFT JOIN users c ON c.id = r.created_by
                 LEFT JOIN users s ON s.id = r.sent_by
                 ORDER BY r.id DESC LIMIT 100'
            ),
            'configIssues' => Rips::configIssues(Settings::all()),
            'nextNoteNumber' => Rips::nextNoteNumber(),
            'environment' => Settings::get('rips_environment', 'test'),
            'validatorConfigured' => Settings::get('rips_validator_url') !== '',
        ]);
    }

    public function preview(Request $request): void
    {
        [$from, $to] = $this->period($request);
        $result = Rips::build($from, $to, '—');
        unset($result['rips']);

        $this->ok($result);
    }

    public function store(Request $request): void
    {
        [$from, $to] = $this->period($request);
        $config = Rips::configIssues(Settings::all());
        if ($config !== []) {
            throw HttpException::unprocessable($config[0]);
        }

        $id = Database::transaction(static function () use ($from, $to): ?int {
            $noteNumber = Rips::nextNoteNumber();
            $result = Rips::build($from, $to, $noteNumber);

            if ($result['readyCount'] === 0) {
                return null;
            }

            $id = Database::insert('rips_reports', [
                'note_number' => $noteNumber,
                'period_start' => $from,
                'period_end' => $to,
                'payload' => json_encode($result['rips'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'users_count' => $result['usersCount'],
                'services_count' => $result['readyCount'],
                'created_by' => Auth::id(),
            ]);

            foreach ($result['items'] as $item) {
                if ($item['issues'] === []) {
                    Database::insert('rips_report_items', ['report_id' => $id, 'appointment_id' => $item['appointment_id']]);
                }
            }

            return $id;
        });

        if ($id === null) {
            throw HttpException::unprocessable('There are no complete consultations to report in that period. Review the missing data.');
        }

        AuditLog::record('create', 'rips_report', $id);
        $this->created(['id' => $id], 'RIPS generated. You can now download it or send it to the validator.');
    }

    public function show(Request $request, string $id): void
    {
        $report = $this->abortIfMissing(Database::first(
            'SELECT r.*, c.full_name AS created_by_name, s.full_name AS sent_by_name
             FROM rips_reports r
             LEFT JOIN users c ON c.id = r.created_by
             LEFT JOIN users s ON s.id = r.sent_by
             WHERE r.id = :id',
            ['id' => (int) $id]
        ), 'We could not find this report.');

        $this->ok([
            'payload' => json_decode((string) $report['payload'], true),
            'validation_result' => json_decode((string) ($report['validation_result'] ?? 'null'), true),
            'validatorConfigured' => Settings::get('rips_validator_url') !== '',
        ] + Present::row($report, []));
    }

    /** Downloads the JSON to load it into the Ministry's local validator. */
    public function download(Request $request, string $id): void
    {
        $report = $this->find((int) $id);
        AuditLog::record('download', 'rips_report', (int) $report['id']);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
            header(sprintf('Content-Disposition: attachment; filename="RIPS_RS_%s.json"', preg_replace('/\D+/', '', (string) $report['note_number'])));
        }
        echo json_encode(json_decode((string) $report['payload'], true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** Sends the report to the MUV Docker API. The password is never stored. */
    public function send(Request $request, string $id): void
    {
        $report = $this->find((int) $id);

        if ($report['status'] === 'validated') {
            throw HttpException::conflict('The Ministry already validated this RIPS.');
        }

        $url = Settings::get('rips_validator_url');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw HttpException::unprocessable('Add the address of the validator Docker API in Settings → RIPS.');
        }

        // Checked again right before the call (the address could have been
        // saved before this check existed, or its DNS could have changed):
        // the server never calls cloud metadata or link-local addresses.
        $problem = OutboundUrl::problem($url);
        if ($problem !== null) {
            throw HttpException::unprocessable($problem . ' Review the validator address in Settings → RIPS.');
        }

        $this->validate($request, [
            'document_type' => 'required|in:' . implode(',', array_keys(Rips::DOCUMENT_TYPES)),
            'document_number' => 'required|max:20',
            'password' => 'required|max:200',
        ]);

        $verifyTls = Settings::get('rips_validator_verify_tls', '1') === '1';
        $client = self::$clientFactory !== null ? (self::$clientFactory)($url, $verifyTls) : new MuvClient($url, $verifyTls);
        $token = $client->login(
            $request->string('document_type'),
            $request->string('document_number'),
            (string) $request->input('password'),
            preg_replace('/\D+/', '', Settings::get('rips_reporter_id')) ?? ''
        );

        $result = $client->sendWithoutInvoice($token, json_decode((string) $report['payload'], true));

        Database::update('rips_reports', (int) $report['id'], [
            'status' => $result['accepted'] ? 'validated' : 'rejected',
            'cuv' => $result['cuv'],
            'validation_result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sent_by' => Auth::id(),
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLog::record($result['accepted'] ? 'send:validated' : 'send:rejected', 'rips_report', (int) $report['id']);

        $this->message(
            $result['accepted']
                ? 'The Ministry validated the RIPS and issued the CUV.'
                : 'The validator rejected the RIPS. Review the messages, fix the data, delete this report and generate it again.',
            $result
        );
    }

    /**
     * Deletes a report that was not validated, so its appointments can be
     * reported again after fixing the data. A RIPS with a CUV cannot be deleted.
     */
    public function destroy(Request $request, string $id): void
    {
        $report = $this->find((int) $id);

        if ($report['status'] === 'validated') {
            throw HttpException::conflict('A RIPS validated by the Ministry cannot be deleted.');
        }

        Database::delete('rips_reports', (int) $report['id']);
        AuditLog::record('delete', 'rips_report', (int) $report['id']);

        $this->message('Report deleted. Its consultations are available for a new RIPS.');
    }

    private function find(int $id): array
    {
        return $this->abortIfMissing(Database::first('SELECT * FROM rips_reports WHERE id = :id', ['id' => $id]), 'We could not find this report.');
    }

    /** @return array{0:string,1:string} */
    private function period(Request $request): array
    {
        $this->validate($request, ['from' => 'required|date', 'to' => 'required|date']);

        $from = date('Y-m-d', strtotime($request->string('from')));
        $to = date('Y-m-d', strtotime($request->string('to')));

        if ($from > $to) {
            throw HttpException::unprocessable('The start date is after the end date.', ['from' => 'Check the period.']);
        }

        return [$from, $to];
    }
}
