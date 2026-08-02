<?php

use PsiClinic\Domain\Documents;
use PsiClinic\Support\Icons;

$pageTitle = 'Documentos';
?>
<div class="page-head">
    <div>
        <h1>Documentos</h1>
        <p class="page-head__subtitle">Archivos adjuntos a las historias clinicas.</p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div class="card">
        <div class="card__head"><h2 class="card__title">Repositorio</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Titulo</th><th>Paciente</th><th>Categoria</th><th>Tamano</th><th>Subido por</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td class="fw-600"><?= e($document['title']) ?></td>
                        <td class="text-sm text-soft"><?= e($document['first_name'] . ' ' . $document['last_name']) ?></td>
                        <td><span class="badge"><?= e(Documents::CATEGORIES[$document['category']] ?? $document['category']) ?></span></td>
                        <td class="text-sm text-soft"><?= e(Documents::humanSize((int) $document['size_bytes'])) ?></td>
                        <td class="text-sm text-soft"><?= e($document['uploaded_by_name'] ?: '-') ?></td>
                        <td class="text-right">
                            <a class="btn btn--ghost btn--sm" href="/documentos/<?= (int) $document['id'] ?>"><?= Icons::render('download', 15) ?></a>
                            <button class="btn btn--ghost btn--sm" type="submit" form="delete-document-<?= (int) $document['id'] ?>">
                                <?= Icons::render('trash', 15) ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($documents === []): ?>
                    <tr><td colspan="6"><div class="empty"><div class="empty__title">Sin documentos</div></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">Subir documento</h2></div>
        <div class="card__body">
            <form method="post" action="/documentos" enctype="multipart/form-data">
                <?= csrf() ?>
                <div class="field mb-2">
                    <label for="patient_id">Paciente</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Selecciona un paciente</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>"><?= e($patient['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field mb-2">
                    <label for="title">Titulo</label>
                    <input id="title" name="title" required>
                </div>
                <div class="field mb-2">
                    <label for="category">Categoria</label>
                    <select id="category" name="category">
                        <?php foreach (Documents::CATEGORIES as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field mb-2">
                    <label for="document">Archivo</label>
                    <input id="document" name="document" type="file" required>
                </div>
                <button class="btn btn--primary btn--block" type="submit"><?= Icons::render('plus', 16) ?> Subir</button>
            </form>
        </div>
    </div>
</div>

<?php foreach ($documents as $document): ?>
    <form id="delete-document-<?= (int) $document['id'] ?>" method="post" action="/documentos/<?= (int) $document['id'] ?>"
          data-confirm="Eliminar el documento de forma permanente?">
        <?= csrf() ?><?= method('DELETE') ?>
    </form>
<?php endforeach; ?>
