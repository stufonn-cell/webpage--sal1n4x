<?php

use PsiClinic\Support\Icons;

$pageTitle = (string) $assessment['instrument_code'];
?>
<div class="page-head">
    <div>
        <h1><?= e($instrument['name']) ?></h1>
        <p class="page-head__subtitle"><?= e($instrument['window']) ?> · <?= count($instrument['items']) ?> preguntas · toma unos 3 minutos</p>
    </div>
    <a class="btn btn--ghost" href="/portal/cuestionarios">Volver</a>
</div>

<form method="post" action="/portal/cuestionarios/<?= (int) $assessment['id'] ?>" data-likert-form>
    <?= csrf() ?>

    <div class="card mb-2">
        <div class="card__body">
            <p class="text-sm text-soft"><?= e($instrument['description']) ?></p>
            <div class="progress"><div class="progress__bar" data-likert-progress style="width:0"></div></div>
            <span class="field-hint"><span data-likert-counter>0 de <?= count($instrument['items']) ?></span> respondidas</span>
        </div>
    </div>

    <div class="card mb-2">
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
        <div class="card__body text-center">
            <p class="text-sm text-muted">Tus respuestas solo son visibles para tu profesional tratante.</p>
            <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Enviar respuestas</button>
        </div>
    </div>
</form>
