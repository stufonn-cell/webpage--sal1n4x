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
use PsiClinic\Domain\Invoices;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class BillingController extends Controller
{
    public function index(Request $request): void
    {
        $this->ok(Present::page(Invoices::paginate($request->string('status'), max(1, $request->integer('page', 1)))));
    }

    public function store(Request $request): void
    {
        $this->validate($request, [
            'patient_id' => 'required|numeric',
            'issued_at' => 'required|date',
            'due_at' => 'date',
            'tax_rate' => 'numeric',
            'notes' => 'max:2000',
        ]);

        if (Patients::find($request->integer('patient_id')) === null) {
            throw HttpException::unprocessable('Elige un paciente.', ['patient_id' => 'Elige un paciente.']);
        }

        $items = [];
        $subtotal = 0.0;

        foreach ($request->array('items') as $item) {
            if (!is_array($item)) {
                continue;
            }

            $description = mb_substr(trim((string) ($item['description'] ?? '')), 0, 200);
            if ($description === '') {
                continue;
            }

            $quantity = max(0.0, (float) ($item['quantity'] ?? 1));
            $price = max(0.0, (float) ($item['unit_price'] ?? 0));
            $amount = round($quantity * $price, 2);
            $subtotal += $amount;

            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $price,
                'amount' => $amount,
            ];
        }

        if ($items === []) {
            throw HttpException::unprocessable('Agrega al menos un concepto a la factura.', ['items' => 'Agrega al menos un concepto.']);
        }

        $rate = max(0.0, min(100.0, (float) $request->input('tax_rate', 0)));
        $tax = round($subtotal * ($rate / 100), 2);

        $invoiceId = Database::transaction(static function () use ($request, $items, $subtotal, $tax): int {
            $id = Database::insert('invoices', [
                'uuid' => uuid(),
                'patient_id' => $request->integer('patient_id'),
                'number' => Invoices::nextNumber(),
                'issued_at' => $request->string('issued_at'),
                'due_at' => $request->string('due_at') ?: null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => round($subtotal + $tax, 2),
                'status' => 'issued',
                'notes' => $request->string('notes'),
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                Database::insert('invoice_items', $item + ['invoice_id' => $id]);
            }

            return $id;
        });

        AuditLog::record('create', 'invoice', $invoiceId);
        $this->created(['id' => $invoiceId], 'Factura generada.');
    }

    public function show(Request $request, string $id): void
    {
        $invoice = $this->abortIfMissing(Invoices::find((int) $id), 'No encontramos esta factura.');

        $this->ok([
            'invoice' => Present::row($invoice),
            'items' => Invoices::items((int) $invoice['id']),
            'payments' => Invoices::payments((int) $invoice['id']),
            'balance' => Invoices::balance((int) $invoice['id']),
            'currency' => Settings::get('currency', 'COP'),
            'clinic' => [
                'name' => Settings::get('clinic_name', 'PsiClinic'),
                'address' => Settings::get('clinic_address'),
                'email' => Settings::get('clinic_email'),
                'phone' => Settings::get('clinic_phone'),
            ],
        ]);
    }

    public function storePayment(Request $request, string $id): void
    {
        $invoice = $this->abortIfMissing(Invoices::find((int) $id), 'No encontramos esta factura.');

        if ($invoice['status'] === 'void') {
            throw HttpException::conflict('La factura está anulada y no admite pagos.');
        }

        $this->validate($request, ['amount' => 'required|numeric', 'paid_at' => 'date', 'reference' => 'max:120']);

        $amount = round((float) $request->input('amount', 0), 2);
        if ($amount <= 0) {
            throw HttpException::unprocessable('El monto debe ser mayor que cero.', ['amount' => 'El monto debe ser mayor que cero.']);
        }

        Database::insert('payments', [
            'invoice_id' => (int) $invoice['id'],
            'paid_at' => $request->string('paid_at') ?: date('Y-m-d'),
            'amount' => $amount,
            'method' => $this->oneOf($request->string('method'), Invoices::METHODS, 'transfer'),
            'reference' => $request->string('reference'),
            'created_by' => Auth::id(),
        ]);

        Invoices::refreshStatus((int) $invoice['id']);
        AuditLog::record('payment', 'invoice', (int) $invoice['id']);

        $this->message('Pago registrado.', ['balance' => Invoices::balance((int) $invoice['id'])]);
    }
}
