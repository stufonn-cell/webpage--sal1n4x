<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Feature;

use PsiClinic\Core\Database;
use PsiClinic\Domain\Invoices;

final class BillingTest extends FeatureTestCase
{
    public function testInvoiceNumbersFollowTheYearlySequence(): void
    {
        $year = date('Y');

        $this->assertSame(sprintf('FAC-%s-0001', $year), Invoices::nextNumber());

        $this->createInvoice($this->createPatient(), 240000.0);

        $this->assertSame(sprintf('FAC-%s-0002', $year), Invoices::nextNumber());
    }

    public function testTheBalanceEqualsTheTotalWithoutPayments(): void
    {
        $invoiceId = $this->createInvoice($this->createPatient(), 240000.0);

        $this->assertSame(240000.0, Invoices::balance($invoiceId));
    }

    public function testPartialPaymentsReduceTheBalance(): void
    {
        $invoiceId = $this->createInvoice($this->createPatient(), 240000.0);

        $this->pay($invoiceId, 100000.0);

        $this->assertSame(140000.0, Invoices::balance($invoiceId));
        Invoices::refreshStatus($invoiceId);
        $this->assertSame('issued', Invoices::find($invoiceId)['status'], 'Un pago parcial no cierra la factura');
    }

    public function testTheInvoiceIsMarkedAsPaidWhenTheBalanceReachesZero(): void
    {
        $invoiceId = $this->createInvoice($this->createPatient(), 240000.0);

        $this->pay($invoiceId, 100000.0);
        $this->pay($invoiceId, 140000.0);
        Invoices::refreshStatus($invoiceId);

        $this->assertSame(0.0, Invoices::balance($invoiceId));
        $this->assertSame('paid', Invoices::find($invoiceId)['status']);
    }

    public function testOverpaymentsLeaveANegativeBalance(): void
    {
        $invoiceId = $this->createInvoice($this->createPatient(), 100000.0);

        $this->pay($invoiceId, 120000.0);
        Invoices::refreshStatus($invoiceId);

        $this->assertSame(-20000.0, Invoices::balance($invoiceId));
        $this->assertSame('paid', Invoices::find($invoiceId)['status']);
    }

    public function testItemsAreStoredWithTheirComputedAmount(): void
    {
        $invoiceId = $this->createInvoice($this->createPatient(), 360000.0, 3);

        $items = Invoices::items($invoiceId);

        $this->assertCount(1, $items);
        $this->assertSame(360000.0, (float) $items[0]['amount']);
        $this->assertSame(3.0, (float) $items[0]['quantity']);
    }

    public function testListingReportsThePaidAmountPerInvoice(): void
    {
        $patientId = $this->createPatient();
        $invoiceId = $this->createInvoice($patientId, 240000.0);
        $this->pay($invoiceId, 90000.0);

        $row = Invoices::paginate()['rows'][0];

        $this->assertSame(90000.0, (float) $row['paid']);
        $this->assertSame(240000.0, (float) $row['total']);
    }

    public function testFilteringByStatus(): void
    {
        $patientId = $this->createPatient();
        $paidInvoice = $this->createInvoice($patientId, 100000.0);
        $this->pay($paidInvoice, 100000.0);
        Invoices::refreshStatus($paidInvoice);
        $this->createInvoice($patientId, 200000.0);

        $this->assertCount(1, Invoices::paginate('paid')['rows']);
        $this->assertCount(1, Invoices::paginate('issued')['rows']);
        $this->assertCount(2, Invoices::paginate('')['rows']);
    }

    public function testDeletingAnInvoiceRemovesItsItemsAndPayments(): void
    {
        $invoiceId = $this->createInvoice($this->createPatient(), 100000.0);
        $this->pay($invoiceId, 50000.0);

        Database::delete('invoices', $invoiceId);

        $this->assertCount(0, Invoices::items($invoiceId));
        $this->assertCount(0, Invoices::payments($invoiceId));
    }

    public function testInvoiceNumbersAreUnique(): void
    {
        $patientId = $this->createPatient();
        $number = Invoices::nextNumber();

        Database::insert('invoices', $this->invoicePayload($patientId, 100000.0, $number));

        $this->assertThrows(
            fn () => Database::insert('invoices', $this->invoicePayload($patientId, 100000.0, $number))
        );
    }

    private function createInvoice(int $patientId, float $total, float $quantity = 2.0): int
    {
        $invoiceId = Database::insert('invoices', $this->invoicePayload($patientId, $total, Invoices::nextNumber()));

        Database::insert('invoice_items', [
            'invoice_id' => $invoiceId,
            'description' => 'Sesion de psicoterapia individual',
            'quantity' => $quantity,
            'unit_price' => $total / $quantity,
            'amount' => $total,
        ]);

        return $invoiceId;
    }

    private function invoicePayload(int $patientId, float $total, string $number): array
    {
        return [
            'uuid' => uuid(),
            'patient_id' => $patientId,
            'number' => $number,
            'issued_at' => date('Y-m-d'),
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'status' => 'issued',
        ];
    }

    private function pay(int $invoiceId, float $amount): void
    {
        Database::insert('payments', [
            'invoice_id' => $invoiceId,
            'paid_at' => date('Y-m-d'),
            'amount' => $amount,
            'method' => 'transfer',
        ]);
    }
}
