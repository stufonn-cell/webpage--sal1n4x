<?php

use PsiClinic\Domain\Instruments;
use PsiClinic\Support\Icons;

$pageTitle = 'Aplicar ' . $instrument['code'];
?>
<div class="page-head">
    <div>
        <h1><?= e($instrument['code']) ?> · <?= e($instrument['name']) ?></h1>
        <p class="page-head__subtitle"><?= e($instrument['window']) ?> · <?= count($instrument['items']) ?> items · rango 0 a <?= Instruments::maxScore($instrument['code']) ?></p>
    </div>
    <a class="btn btn--ghost" href="/evaluaciones/catalogo">Cambiar instrumento</a>
</div>

<form method="post" action="/evaluaciones" data-likert-form>
    <?= csrf() ?>
    <input type="hidden" name="instrument_code" value="<?= e($instrument['code']) ?>">

    <div class="card mb-2">
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="patient_id">Paciente</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Selecciona un paciente</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>"<?= (int) $selectedPatient === (int) $patient['id'] ? ' selected' : '' ?>>
                                <?= e($patient['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="instrument_switch">Instrumento</label>
                    <select id="instrument_switch" data-reload-on-change="instrumento">
                        <?php foreach ($instruments as $code => $definition): ?>
                            <option value="<?= e($code) ?>"<?= $code === $instrument['code'] ? ' selected' : '' ?>>
                                <?= e($code) ?> · <?= e($definition['domain']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field field--full">
                    <label>Progreso</label>
                    <div class="progress"><div class="progress__bar" data-likert-progress style="width:0"></div></div>
                    <span class="field-hint"><span data-likert-counter>0 de <?= count($instrument['items']) ?></span> items respondidos</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__head">
            <h2 class="card__title"><?= e($instrument['window']) ?>: con que frecuencia le han afectado los siguientes problemas</h2>
        </div>
        <div class="card__body">
            <div class="likert">
                <?php foreach ($instrument['items'] as $index => $item): ?>
                    <div class="likert__item">
                        <div class="likert__question">
                            <span class="likert__number"><?= $index + 1 ?></span>
                            <span><?= e($item) ?></span>
                        </div>
                        <div class="likert__options">
                            <?php foreach ($instrument['scale'] as $label => $value): ?>
                                <label class="likert__option">
                                    <input type="radio" name="answers[<?= $index ?>]" value="<?= (int) $value ?>" required>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__body">
            <div class="field mb-2">
                <label for="clinician_notes">Observaciones del profesional</label>
                <textarea id="clinician_notes" name="clinician_notes" placeholder="Condiciones de aplicacion, actitud del paciente, aclaraciones"></textarea>
            </div>
            <div class="form-actions">
                <a class="btn btn--ghost" href="/evaluaciones">Cancelar</a>
                <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Corregir y guardar</button>
            </div>
        </div>
    </div>
</form>
