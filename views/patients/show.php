<?php

use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\Documents;
use PsiClinic\Domain\Instruments;
use PsiClinic\Domain\Invoices;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Chart;
use PsiClinic\Support\Icons;

$pageTitle = Patients::fullName($patient);
$patientId = (int) $patient['id'];
$risk = (string) $patient['risk_level'];
?>
<div class="page-head">
    <div class="flex-center" style="gap:1rem">
        <span class="avatar avatar--lg"><?= e(initials(Patients::fullName($patient))) ?></span>
        <div>
            <h1 class="mb-0"><?= e(Patients::fullName($patient)) ?></h1>
            <p class="page-head__subtitle">
                <?= e($patient['record_number']) ?> · <?= e(ageFrom($patient['birth_date'])) ?> anos ·
                <?= e(Patients::GENDERS[$patient['gender']]) ?>
            </p>
            <div class="flex-center flex-wrap mt-1" style="gap:.4rem">
                <span class="badge badge--<?= $patient['status'] === 'active' ? 'brand' : '' ?>"><?= e(Patients::STATUSES[$patient['status']]) ?></span>
                <span class="badge badge--<?= $risk === 'high' ? 'danger' : ($risk === 'moderate' ? 'warning' : ($risk === 'low' ? 'info' : 'success')) ?>">
                    Riesgo: <?= e(Patients::RISK_LEVELS[$risk]) ?>
                </span>
                <?php if ($patient['psychologist_name']): ?>
                    <span class="badge"><?= e($patient['psychologist_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="flex gap-1 flex-wrap">
        <a class="btn" href="/notas/nueva?paciente=<?= $patientId ?>"><?= Icons::render('notes', 16) ?> Nota de sesion</a>
        <a class="btn" href="/agenda/nueva"><?= Icons::render('calendar', 16) ?> Agendar</a>
        <a class="btn btn--primary" href="/evaluaciones/nueva?paciente=<?= $patientId ?>"><?= Icons::render('clipboard', 16) ?> Aplicar instrumento</a>
        <a class="btn btn--ghost btn--icon" href="/pacientes/<?= $patientId ?>/editar"><?= Icons::render('edit', 17) ?></a>
    </div>
</div>

<div class="tabs" data-tabs="patient">
    <button class="tab is-active" data-tab="resumen">Resumen</button>
    <button class="tab" data-tab="notas">Notas (<?= count($notes) ?>)</button>
    <button class="tab" data-tab="evaluaciones">Evaluaciones (<?= count($assessments) ?>)</button>
    <button class="tab" data-tab="diagnosticos">Diagnosticos (<?= count($diagnoses) ?>)</button>
    <button class="tab" data-tab="citas">Citas</button>
    <button class="tab" data-tab="documentos">Documentos</button>
    <button class="tab" data-tab="administrativo">Administrativo</button>
</div>

<section class="tab-panel is-active" data-tab-panel="resumen" data-tab-group="patient">
    <div class="grid grid--sidebar">
        <div>
            <div class="card mb-2">
                <div class="card__head"><h2 class="card__title">Ficha clinica</h2></div>
                <div class="card__body">
                    <dl class="definition">
                        <dt>Motivo de consulta</dt><dd><?= e($patient['reason_for_consult'] ?: 'No registrado') ?></dd>
                        <dt>Antecedentes</dt><dd><?= e($patient['relevant_history'] ?: 'No registrados') ?></dd>
                        <dt>Medicacion</dt><dd><?= e($patient['current_medication'] ?: 'Ninguna registrada') ?></dd>
                        <dt>Remitido por</dt><dd><?= e($patient['referred_by'] ?: '-') ?></dd>
                        <dt>Contacto</dt><dd><?= e($patient['phone'] ?: '-') ?> · <?= e($patient['email'] ?: '-') ?></dd>
                        <dt>Emergencia</dt><dd><?= e($patient['emergency_contact_name'] ?: '-') ?> <?= e($patient['emergency_contact_phone'] ?: '') ?></dd>
                        <dt>Direccion</dt><dd><?= e(trim(($patient['address'] ?: '') . ' ' . ($patient['city'] ?: ''))) ?: '-' ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($series !== []): ?>
                <div class="card mb-2">
                    <div class="card__head"><h2 class="card__title">Evolucion en instrumentos</h2></div>
                    <div class="card__body">
                        <?php foreach ($series as $code => $points): ?>
                            <div class="mb-3">
                                <div class="flex-between mb-1">
                                    <h3 class="mb-0"><?= e($code) ?> <span class="text-xs text-muted"><?= e(Instruments::get($code)['domain'] ?? '') ?></span></h3>
                                    <span class="badge badge--brand"><?= count($points) ?> aplicaciones</span>
                                </div>
                                <?= Chart::line(
                                    array_map(static fn (array $row): array => [
                                        'label' => formatDate((string) $row['administered_at']),
                                        'value' => (int) $row['total_score'],
                                    ], $points),
                                    Instruments::maxScore((string) $code),
                                    620
                                ) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($moodSeries !== []): ?>
                <div class="card">
                    <div class="card__head"><h2 class="card__title">Estado de animo reportado en sesion</h2></div>
                    <div class="card__body">
                        <?= Chart::line(
                            array_map(static fn (array $row): array => [
                                'label' => formatDate((string) $row['session_date']),
                                'value' => (int) $row['mood_score'],
                            ], $moodSeries),
                            10,
                            620
                        ) ?>
                        <p class="text-xs text-muted mt-1">Escala de 0 a 10 registrada por el profesional al cierre de cada sesion.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div class="card mb-2">
                <div class="card__head"><h2 class="card__title">Linea de tiempo</h2></div>
                <div class="card__body">
                    <?php if ($timeline === []): ?>
                        <p class="text-sm text-muted mb-0">Sin actividad registrada.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($timeline as $event): ?>
                                <div class="timeline__item">
                                    <span class="timeline__dot timeline__dot--<?= e($event['type']) ?>"></span>
                                    <a class="timeline__title" href="<?= e($event['link']) ?>"><?= e($event['title']) ?></a>
                                    <div class="timeline__meta"><?= e(formatDate($event['at'])) ?> · <?= e($event['meta']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card__head"><h2 class="card__title">Portal del paciente</h2></div>
                <div class="card__body">
                    <?php if ($portalAccount !== null): ?>
                        <p class="text-sm mb-1">Acceso activo con el usuario <strong><?= e($portalAccount['username']) ?></strong>.</p>
                        <p class="text-xs text-muted mb-0">Ultimo ingreso: <?= e(formatDateTime($portalAccount['last_login_at'])) ?></p>
                    <?php else: ?>
                        <p class="text-sm text-soft">Genera credenciales para que el paciente responda cuestionarios y firme consentimientos.</p>
                        <form method="post" action="/pacientes/<?= $patientId ?>/acceso-portal">
                            <?= csrf() ?>
                            <button class="btn btn--accent btn--block" type="submit"><?= Icons::render('link', 16) ?> Crear acceso</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="tab-panel" data-tab-panel="notas" data-tab-group="patient">
    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Notas de sesion</h2>
            <a class="btn btn--primary btn--sm" href="/notas/nueva?paciente=<?= $patientId ?>"><?= Icons::render('plus', 15) ?> Nueva</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Sesion</th><th>Fecha</th><th>Formato</th><th>Intervenciones</th><th>Animo</th><th>Riesgo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($notes as $note): ?>
                    <tr>
                        <td class="fw-600">#<?= (int) $note['session_number'] ?></td>
                        <td class="text-sm"><?= e(formatDate((string) $note['session_date'])) ?></td>
                        <td class="text-sm"><span class="badge"><?= e(strtoupper((string) $note['format'])) ?></span></td>
                        <td class="text-sm text-soft"><?= e($note['interventions'] ?: '-') ?></td>
                        <td class="text-sm"><?= $note['mood_score'] === null ? '-' : (int) $note['mood_score'] . '/10' ?></td>
                        <td class="text-sm"><?= e(Patients::RISK_LEVELS[$note['risk_level']]) ?></td>
                        <td><span class="badge badge--<?= (int) $note['is_locked'] === 1 ? 'success' : 'warning' ?>"><?= (int) $note['is_locked'] === 1 ? 'Firmada' : 'Borrador' ?></span></td>
                        <td class="text-right"><a class="btn btn--ghost btn--sm" href="/notas/<?= (int) $note['id'] ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($notes === []): ?>
                    <tr><td colspan="8"><div class="empty"><div class="empty__title">Sin notas registradas</div></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="tab-panel" data-tab-panel="evaluaciones" data-tab-group="patient">
    <div class="grid grid--sidebar">
        <div class="card">
            <div class="card__head"><h2 class="card__title">Aplicaciones registradas</h2></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Instrumento</th><th>Fecha</th><th>Puntaje</th><th>Severidad</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($assessments as $assessment): ?>
                        <tr>
                            <td class="fw-600"><?= e($assessment['instrument_code']) ?></td>
                            <td class="text-sm"><?= e(formatDate($assessment['administered_at'] ?: $assessment['created_at'])) ?></td>
                            <td class="text-sm"><?= $assessment['total_score'] === null ? '-' : (int) $assessment['total_score'] ?></td>
                            <td class="text-sm"><?= e($assessment['severity'] ?: '-') ?></td>
                            <td><span class="badge badge--<?= $assessment['status'] === 'completed' ? 'success' : 'warning' ?>"><?= $assessment['status'] === 'completed' ? 'Completada' : 'Pendiente' ?></span></td>
                            <td class="text-right">
                                <?php if ($assessment['status'] === 'completed'): ?>
                                    <a class="btn btn--ghost btn--sm" href="/evaluaciones/<?= (int) $assessment['id'] ?>">Ver</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($assessments === []): ?>
                        <tr><td colspan="6"><div class="empty"><div class="empty__title">Sin evaluaciones</div></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">Asignar cuestionario</h2></div>
            <div class="card__body">
                <p class="text-sm text-soft">El paciente lo respondera desde su portal y el sistema lo corregira automaticamente.</p>
                <form method="post" action="/evaluaciones/<?= $patientId ?>/asignar">
                    <?= csrf() ?>
                    <div class="field mb-2">
                        <label for="instrument_code">Instrumento</label>
                        <select id="instrument_code" name="instrument_code">
                            <?php foreach ($instruments as $code => $instrument): ?>
                                <option value="<?= e($code) ?>"><?= e($code) ?> · <?= e($instrument['domain']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn--primary btn--block" type="submit"><?= Icons::render('clipboard', 16) ?> Asignar al portal</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="tab-panel" data-tab-panel="diagnosticos" data-tab-group="patient">
    <div class="grid grid--sidebar">
        <div class="card">
            <div class="card__head"><h2 class="card__title">Diagnosticos</h2></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Codigo</th><th>Descripcion</th><th>Sistema</th><th>Estado</th><th>Inicio</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($diagnoses as $diagnosis): ?>
                        <tr>
                            <td class="fw-600"><?= e($diagnosis['code']) ?></td>
                            <td class="text-sm"><?= e($diagnosis['title']) ?></td>
                            <td class="text-sm"><span class="badge"><?= e(strtoupper((string) $diagnosis['system'])) ?></span></td>
                            <td class="text-sm"><?= e($diagnosis['status']) ?></td>
                            <td class="text-sm text-soft"><?= e(formatDate($diagnosis['onset_date'])) ?></td>
                            <td class="text-right">
                                <form method="post" action="/pacientes/<?= $patientId ?>/diagnosticos/<?= (int) $diagnosis['id'] ?>" data-confirm="Eliminar este diagnostico?">
                                    <?= csrf() ?><?= method('DELETE') ?>
                                    <button class="btn btn--ghost btn--sm" type="submit"><?= Icons::render('trash', 15) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($diagnoses === []): ?>
                        <tr><td colspan="6"><div class="empty"><div class="empty__title">Sin diagnosticos registrados</div></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">Agregar diagnostico</h2></div>
            <div class="card__body">
                <form method="post" action="/pacientes/<?= $patientId ?>/diagnosticos">
                    <?= csrf() ?>
                    <div class="field mb-2">
                        <label for="system">Sistema</label>
                        <select id="system" name="system">
                            <option value="icd10">CIE-10</option>
                            <option value="dsm5">DSM-5</option>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="code">Codigo</label>
                        <input id="code" name="code" placeholder="F41.1" required>
                    </div>
                    <div class="field mb-2">
                        <label for="title">Descripcion</label>
                        <input id="title" name="title" placeholder="Trastorno de ansiedad generalizada" required>
                    </div>
                    <div class="field mb-2">
                        <label for="onset_date">Fecha de inicio</label>
                        <input id="onset_date" name="onset_date" type="date">
                    </div>
                    <button class="btn btn--primary btn--block" type="submit"><?= Icons::render('plus', 16) ?> Agregar</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="tab-panel" data-tab-panel="citas" data-tab-group="patient">
    <div class="card">
        <div class="card__head"><h2 class="card__title">Historial de citas</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Fecha</th><th>Modalidad</th><th>Tipo</th><th>Estado</th><th>Tarifa</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td class="text-sm"><?= e(formatDateTime((string) $appointment['starts_at'])) ?></td>
                        <td class="text-sm"><?= e(Appointments::MODALITIES[$appointment['modality']]) ?></td>
                        <td class="text-sm text-soft"><?= e($appointment['session_type'] ?: '-') ?></td>
                        <td><span class="badge"><?= e(Appointments::STATUSES[$appointment['status']]) ?></span></td>
                        <td class="text-sm"><?= e(formatMoney($appointment['fee'])) ?></td>
                        <td class="text-right"><a class="btn btn--ghost btn--sm" href="/agenda/<?= (int) $appointment['id'] ?>/editar"><?= Icons::render('edit', 15) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($appointments === []): ?>
                    <tr><td colspan="6"><div class="empty"><div class="empty__title">Sin citas registradas</div></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="tab-panel" data-tab-panel="documentos" data-tab-group="patient">
    <div class="grid grid--sidebar">
        <div class="card">
            <div class="card__head"><h2 class="card__title">Documentos adjuntos</h2></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Titulo</th><th>Categoria</th><th>Tamano</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td class="fw-600"><?= e($document['title']) ?></td>
                            <td class="text-sm"><span class="badge"><?= e(Documents::CATEGORIES[$document['category']] ?? $document['category']) ?></span></td>
                            <td class="text-sm text-soft"><?= e(Documents::humanSize((int) $document['size_bytes'])) ?></td>
                            <td class="text-sm text-soft"><?= e(formatDate((string) $document['created_at'])) ?></td>
                            <td class="text-right"><a class="btn btn--ghost btn--sm" href="/documentos/<?= (int) $document['id'] ?>"><?= Icons::render('download', 15) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($documents === []): ?>
                        <tr><td colspan="5"><div class="empty"><div class="empty__title">Sin documentos</div></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">Adjuntar documento</h2></div>
            <div class="card__body">
                <form method="post" action="/documentos" enctype="multipart/form-data">
                    <?= csrf() ?>
                    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
                    <div class="field mb-2">
                        <label for="doc_title">Titulo</label>
                        <input id="doc_title" name="title" required>
                    </div>
                    <div class="field mb-2">
                        <label for="doc_category">Categoria</label>
                        <select id="doc_category" name="category">
                            <?php foreach (Documents::CATEGORIES as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="document">Archivo</label>
                        <input id="document" name="document" type="file" required>
                        <span class="field-hint">PDF, imagen o documento de texto.</span>
                    </div>
                    <button class="btn btn--primary btn--block" type="submit"><?= Icons::render('plus', 16) ?> Subir</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="tab-panel" data-tab-panel="administrativo" data-tab-group="patient">
    <div class="grid grid--sidebar">
        <div class="card">
            <div class="card__head">
                <h2 class="card__title">Facturas</h2>
                <a class="btn btn--sm" href="/facturacion/nueva">Nueva factura</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Numero</th><th>Emision</th><th>Total</th><th>Pagado</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td class="fw-600"><?= e($invoice['number']) ?></td>
                            <td class="text-sm"><?= e(formatDate((string) $invoice['issued_at'])) ?></td>
                            <td class="text-sm"><?= e(formatMoney($invoice['total'])) ?></td>
                            <td class="text-sm"><?= e(formatMoney($invoice['paid'])) ?></td>
                            <td><span class="badge badge--<?= $invoice['status'] === 'paid' ? 'success' : 'warning' ?>"><?= e(Invoices::STATUSES[$invoice['status']]) ?></span></td>
                            <td class="text-right"><a class="btn btn--ghost btn--sm" href="/facturacion/<?= (int) $invoice['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($invoices === []): ?>
                        <tr><td colspan="6"><div class="empty"><div class="empty__title">Sin facturas</div></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">Consentimientos</h2></div>
            <div class="card__body card__body--flush">
                <?php foreach ($consents as $consent): ?>
                    <a class="list-row" href="/consentimientos/<?= (int) $consent['id'] ?>">
                        <?= Icons::render('clipboard', 16) ?>
                        <span>
                            <span class="fw-600 text-sm"><?= e($consent['title']) ?></span>
                            <span class="text-xs text-muted" style="display:block"><?= e(formatDate((string) $consent['created_at'])) ?></span>
                        </span>
                        <span class="list-row__meta">
                            <span class="badge badge--<?= $consent['status'] === 'signed' ? 'success' : 'warning' ?>"><?= $consent['status'] === 'signed' ? 'Firmado' : 'Pendiente' ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
                <?php if ($consents === []): ?>
                    <div class="empty"><p class="text-sm mb-0">Sin consentimientos generados.</p>
                        <a class="btn btn--sm mt-1" href="/consentimientos">Generar</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
