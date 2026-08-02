<?php

use PsiClinic\Domain\Documents;
use PsiClinic\Support\Icons;

$pageTitle = 'Mis documentos';
?>
<div class="page-head">
    <div>
        <h1>Mis documentos</h1>
        <p class="page-head__subtitle">Informes y soportes compartidos por tu profesional.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Titulo</th><th>Categoria</th><th>Tamano</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($documents as $document): ?>
                <tr>
                    <td class="fw-600"><?= e($document['title']) ?></td>
                    <td><span class="badge"><?= e(Documents::CATEGORIES[$document['category']] ?? $document['category']) ?></span></td>
                    <td class="text-sm text-soft"><?= e(Documents::humanSize((int) $document['size_bytes'])) ?></td>
                    <td class="text-sm text-soft"><?= e(formatDate((string) $document['created_at'])) ?></td>
                    <td class="text-right">
                        <a class="btn btn--sm" href="/documentos/<?= (int) $document['id'] ?>"><?= Icons::render('download', 15) ?> Descargar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($documents === []): ?>
                <tr><td colspan="5"><div class="empty"><div class="empty__title">Sin documentos disponibles</div></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
