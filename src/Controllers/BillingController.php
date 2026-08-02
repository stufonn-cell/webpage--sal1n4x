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
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Domain\AuditLog;
use PsiClinic\Domain\Invoices;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;

final class BillingController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('billing/index', [
            'result' => Invoices::paginate((string) $request->input('estado', ''), max(1, $request->integer('page', 1))),
            'status' => (string) $request->input('estado', ''),
            'currency' => Settings::get('currency', 'COP'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('billing/form', [
            'patients' => Patients::options(),
            'number' => Invoices::nextNumber(),
            'defaultFee' => Settings::get('default_fee', '0'),
            'currency' => Settings::get('currency', 'COP'),
        ]);
    }

    public function store(Request $request): void
    {
        $this->validate($request, [
            'patient_id' => 'required|numeric',
            'issued_at' => 'required|date',
        ]);

        $descriptions = $request->array('description');
        $quantities = $request->array('quantity');
        $prices = $request->array('unit_price');

        $items = [];
        $subtotal = 0.0;

        foreach ($descriptions as $index => $description) {
            $description = trim((string) $description);
            if ($description === '') {
                continue;
            }

            $quantity = (float) ($quantities[$index] ?? 1);
            $price = (float) ($prices[$index] ?? 0);
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
            Session::flash('error', 'Agrega al menos un concepto a la factura.');
            $this->back($request);
        }

        $tax = round($subtotal * ((float) $request->input('tax_rate', 0) / 100), 2);

        $invoiceId = Database::transaction(static function () use ($request, $items, $subtotal, $tax): int {
            $id = Database::insert('invoices', [
                'uuid' => uuid(),
                'patient_id' => $request->integer('patient_id'),
                'number' => Invoices::nextNumber(),
                'issued_at' => (string) $request->input('issued_at', date('Y-m-d')),
                'due_at' => $request->input('due_at') ?: null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => round($subtotal + $tax, 2),
                'status' => 'issued',
                'notes' => (string) $request->input('notes', ''),
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                Database::insert('invoice_items', $item + ['invoice_id' => $id]);
            }

            return $id;
        });

        AuditLog::record('create', 'invoice', $invoiceId);
        Session::flash('success', 'Factura generada.');
        $this->redirect('/facturacion/' . $invoiceId);
    }

    public function show(Request $request, string $id): void
    {
        $invoice = $this->abortIfMissing(Invoices::find((int) $id));

        $this->view('billing/show', [
            'invoice' => $invoice,
            'items' => Invoices::items((int) $invoice['id']),
            'payments' => Invoices::payments((int) $invoice['id']),
            'balance' => Invoices::balance((int) $invoice['id']),
            'currency' => Settings::get('currency', 'COP'),
        ]);
    }

    public function storePayment(Request $request, string $id): void
    {
        $invoice = $this->abortIfMissing(Invoices::find((int) $id));
        $amount = (float) $request->input('amount', 0);

        if ($amount <= 0) {
            Session::flash('error', 'El monto del pago debe ser mayor que cero.');
            $this->back($request);
        }

        Database::insert('payments', [
            'invoice_id' => (int) $invoice['id'],
            'paid_at' => (string) $request->input('paid_at', date('Y-m-d')),
            'amount' => $amount,
            'method' => (string) $request->input('method', 'transfer'),
            'reference' => (string) $request->input('reference', ''),
            'created_by' => Auth::id(),
        ]);

        Invoices::refreshStatus((int) $invoice['id']);
        AuditLog::record('payment', 'invoice', (int) $invoice['id']);

        Session::flash('success', 'Pago registrado.');
        $this->redirect('/facturacion/' . (int) $invoice['id']);
    }
}
