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
use PsiClinic\Domain\Invoices;
use PsiClinic\Domain\Patients;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Present;

final class BillingController extends Controller
{
    /** Bounds of the DECIMAL columns (10,2 for money, 8,2 for quantities). */
    private const MAX_AMOUNT = 99999999.99;
    private const MAX_QUANTITY = 9999.0;
    private const MAX_ITEMS = 100;

    public function index(Request $request): void
    {
        $this->ok(Present::page(Invoices::paginate($request->string('status'), max(1, $request->integer('page', 1)))));
    }

    public function store(Request $request): void
    {
        $this->validate($request, [
            'patient_id' => 'required|integer',
            'issued_at' => 'required|date',
            'due_at' => 'date',
            'tax_rate' => 'numeric',
            'notes' => 'max:2000',
        ]);

        if (Patients::find($request->integer('patient_id')) === null) {
            throw HttpException::unprocessable('Choose a patient.', ['patient_id' => 'Choose a patient.']);
        }

        $items = [];
        $subtotal = 0.0;

        foreach (array_slice($request->array('items'), 0, self::MAX_ITEMS) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $description = is_scalar($item['description'] ?? null) ? mb_substr(trim((string) $item['description']), 0, 200) : '';
            if ($description === '') {
                continue;
            }

            $quantity = round(max(0.0, min(self::MAX_QUANTITY, self::number($item['quantity'] ?? 1))), 2);
            $price = round(max(0.0, min(self::MAX_AMOUNT, self::number($item['unit_price'] ?? 0))), 2);
            $amount = round($quantity * $price, 2);
            $subtotal += $amount;

            if ($subtotal > self::MAX_AMOUNT) {
                throw HttpException::unprocessable('The invoice total is too large.', ['items' => 'Split it into several invoices.']);
            }

            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $price,
                'amount' => $amount,
            ];
        }

        if ($items === []) {
            throw HttpException::unprocessable('Add at least one line item to the invoice.', ['items' => 'Add at least one line item.']);
        }

        $rate = max(0.0, min(100.0, self::number($request->input('tax_rate', 0))));
        $tax = round($subtotal * ($rate / 100), 2);

        if ($subtotal + $tax > self::MAX_AMOUNT) {
            throw HttpException::unprocessable('The invoice total is too large.', ['items' => 'Split it into several invoices.']);
        }

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
        $this->created(['id' => $invoiceId], 'Invoice created.');
    }

    public function show(Request $request, string $id): void
    {
        $invoice = $this->abortIfMissing(Invoices::find((int) $id), "We couldn't find this invoice.");

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
        $invoice = $this->abortIfMissing(Invoices::find((int) $id), "We couldn't find this invoice.");

        if ($invoice['status'] === 'void') {
            throw HttpException::conflict("This invoice has been voided and can't take payments.");
        }

        $this->validate($request, ['amount' => 'required|numeric', 'paid_at' => 'date', 'reference' => 'max:80']);

        $amount = round(self::number($request->input('amount', 0)), 2);
        if ($amount <= 0) {
            throw HttpException::unprocessable('The amount must be greater than zero.', ['amount' => 'The amount must be greater than zero.']);
        }
        if ($amount > self::MAX_AMOUNT) {
            throw HttpException::unprocessable('The amount is too large.', ['amount' => 'Check the amount.']);
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

        $this->message('Payment recorded.', ['balance' => Invoices::balance((int) $invoice['id'])]);
    }

    /** A finite number from user input; anything else (lists, text, INF) counts as 0. */
    private static function number(mixed $value): float
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return 0.0;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : 0.0;
    }
}
