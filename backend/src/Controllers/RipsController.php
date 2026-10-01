<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Controllers;

use PsiClinic\Core\Auth;
use PsiClinic\Core\Controller;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Request;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Rips;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\MuvClient;
use PsiClinic\Support\Present;

/**
 * Reportes RIPS sin factura: vista previa de un periodo, generacion del JSON,
 * descarga para el Validador Local y envio al API Docker del Ministerio.
 */
final class RipsController extends Controller
{
    public function index(Request $request): void
    {
        $this->ok([
            'reports' => Database::all(
                'SELECT r.id, r.num_nota, r.period_start, r.period_end, r.status, r.cuv, r.users_count,
                        r.services_count, r.created_at, r.sent_at, c.full_name AS created_by_name, s.full_name AS sent_by_name
                 FROM rips_reports r
                 LEFT JOIN users c ON c.id = r.created_by
                 LEFT JOIN users s ON s.id = r.sent_by
                 ORDER BY r.id DESC LIMIT 100'
            ),
            'configIssues' => Rips::configIssues(Settings::all()),
            'nextNumNota' => Rips::nextNumNota(),
            'environment' => Settings::get('rips_ambiente', 'pruebas'),
            'muvConfigured' => Settings::get('rips_muv_url') !== '',
            'catalogs' => [
                'documentTypes' => Present::options(Rips::DOCUMENT_TYPES),
                'userTypes' => Present::options(Rips::USER_TYPES),
                'sexes' => Present::options(Rips::SEXES),
                'zones' => Present::options(Rips::ZONES),
                'purposes' => Present::options(Rips::PURPOSES),
                'causes' => Present::options(Rips::CAUSES),
            ],
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
            $numNota = Rips::nextNumNota();
            $result = Rips::build($from, $to, $numNota);

            if ($result['readyCount'] === 0) {
                return null;
            }

            $id = Database::insert('rips_reports', [
                'num_nota' => $numNota,
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
            throw HttpException::unprocessable('No hay consultas completas para reportar en ese periodo. Revisa los datos que faltan.');
        }

        AuditLog::record('create', 'rips_report', $id);
        $this->created(['id' => $id], 'RIPS generado. Ya puedes descargarlo o enviarlo al validador.');
    }

    public function show(Request $request, string $id): void
    {
        $report = $this->find((int) $id);

        $this->ok(Present::row($report, []) + [
            'payload' => json_decode((string) $report['payload'], true),
            'validation_result' => json_decode((string) ($report['validation_result'] ?? 'null'), true),
        ]);
    }

    /** Descarga el JSON para cargarlo en el Validador Local (cliente-servidor). */
    public function download(Request $request, string $id): void
    {
        $report = $this->find((int) $id);
        AuditLog::record('download', 'rips_report', (int) $report['id']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header(sprintf('Content-Disposition: attachment; filename="RIPS_RS_%s.json"', preg_replace('/\D+/', '', (string) $report['num_nota'])));
        echo json_encode(json_decode((string) $report['payload'], true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** Envia al API Docker del MUV. La contrasena no se guarda en ningun lugar. */
    public function send(Request $request, string $id): void
    {
        $report = $this->find((int) $id);

        if ($report['status'] === 'validated') {
            throw HttpException::conflict('Este RIPS ya fue validado por el Ministerio.');
        }

        $url = Settings::get('rips_muv_url');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw HttpException::unprocessable('Configura la dirección del API Docker del validador en Configuración → RIPS.');
        }

        $this->validate($request, [
            'document_type' => 'required|in:' . implode(',', array_keys(Rips::DOCUMENT_TYPES)),
            'document_number' => 'required|max:20',
            'password' => 'required|max:200',
        ]);

        $client = new MuvClient($url, Settings::get('rips_muv_verify_tls', '1') === '1');
        $token = $client->login(
            $request->string('document_type'),
            $request->string('document_number'),
            (string) $request->input('password'),
            preg_replace('/\D+/', '', Settings::get('rips_obligado_documento')) ?? ''
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
            $result['accepted'] ? 'El Ministerio validó el RIPS y entregó el CUV.' : 'El validador rechazó el RIPS. Revisa los mensajes, corrige y vuelve a generarlo.',
            $result
        );
    }

    /**
     * Elimina un reporte no validado para liberar sus citas y generarlo de
     * nuevo tras corregir. Un RIPS con CUV no se puede borrar.
     */
    public function destroy(Request $request, string $id): void
    {
        $report = $this->find((int) $id);

        if ($report['status'] === 'validated') {
            throw HttpException::conflict('Un RIPS validado por el Ministerio no se puede eliminar.');
        }

        Database::delete('rips_reports', (int) $report['id']);
        AuditLog::record('delete', 'rips_report', (int) $report['id']);

        $this->message('Reporte eliminado. Sus consultas quedan disponibles para un nuevo RIPS.');
    }

    private function find(int $id): array
    {
        return $this->abortIfMissing(Database::first('SELECT * FROM rips_reports WHERE id = :id', ['id' => $id]), 'No encontramos este reporte.');
    }

    /** @return array{0:string,1:string} */
    private function period(Request $request): array
    {
        $this->validate($request, ['from' => 'required|date', 'to' => 'required|date']);

        $from = date('Y-m-d', strtotime($request->string('from')));
        $to = date('Y-m-d', strtotime($request->string('to')));

        if ($from > $to) {
            throw HttpException::unprocessable('La fecha inicial es posterior a la final.', ['from' => 'Revisa el periodo.']);
        }

        return [$from, $to];
    }
}
