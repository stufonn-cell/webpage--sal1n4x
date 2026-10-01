<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

use PsiClinic\Core\Database;

final class Invoices
{
    public const STATUSES = [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'paid' => 'Paid',
        'void' => 'Void',
    ];

    public const METHODS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'transfer' => 'Bank transfer',
        'insurance' => 'Insurance',
        'other' => 'Other',
    ];

    public static function paginate(string $status = '', int $page = 1, int $perPage = 15): array
    {
        $where = '1 = 1';
        $params = [];

        if (array_key_exists($status, self::STATUSES)) {
            $where = 'i.status = :status';
            $params['status'] = $status;
        }

        $total = (int) Database::value('SELECT COUNT(*) FROM invoices i WHERE ' . $where, $params);
        $offset = Database::offset($page, $perPage);

        return [
            'rows' => Database::all(
                'SELECT i.*, p.first_name, p.last_name, p.record_number,
                        COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id = i.id), 0) AS paid
                 FROM invoices i
                 JOIN patients p ON p.id = i.patient_id
                 WHERE ' . $where . '
                 ORDER BY i.issued_at DESC, i.id DESC' . Database::limit($perPage, $offset),
                $params
            ),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT i.*, p.first_name, p.last_name, p.record_number, p.document_id, p.email
             FROM invoices i
             JOIN patients p ON p.id = i.patient_id
             WHERE i.id = :id',
            ['id' => $id]
        );
    }

    public static function items(int $invoiceId): array
    {
        return Database::all('SELECT * FROM invoice_items WHERE invoice_id = :id', ['id' => $invoiceId]);
    }

    public static function payments(int $invoiceId): array
    {
        return Database::all('SELECT * FROM payments WHERE invoice_id = :id ORDER BY paid_at DESC', ['id' => $invoiceId]);
    }

    public static function balance(int $invoiceId): float
    {
        $total = (float) Database::value('SELECT total FROM invoices WHERE id = :id', ['id' => $invoiceId]);
        $paid = (float) Database::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :id', ['id' => $invoiceId]);

        return round($total - $paid, 2);
    }

    public static function nextNumber(): string
    {
        $year = date('Y');
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM invoices WHERE number LIKE :prefix',
            ['prefix' => 'INV-' . $year . '-%']
        );

        return sprintf('INV-%s-%04d', $year, $count + 1);
    }

    public static function refreshStatus(int $invoiceId): void
    {
        if (self::balance($invoiceId) <= 0.0) {
            Database::update('invoices', $invoiceId, ['status' => 'paid']);
        }
    }
}
