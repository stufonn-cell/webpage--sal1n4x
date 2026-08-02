<?php

use PsiClinic\Domain\Appointments;
use PsiClinic\Support\Icons;

$pageTitle = 'Agenda';
$weekdays = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
$today = date('Y-m-d');
$totalWeek = array_sum(array_map('count', $week['days']));
?>
<div class="page-head">
    <div>
        <h1>Agenda semanal</h1>
        <p class="page-head__subtitle">
            <?= e(formatDate($week['start']->format('Y-m-d'))) ?> al <?= e(formatDate($week['start']->modify('+6 days')->format('Y-m-d'))) ?>
            · <?= $totalWeek ?> citas · jornada <?= e($workStart) ?> a <?= e($workEnd) ?>
        </p>
    </div>
    <div class="flex gap-1">
        <a class="btn btn--icon" href="/agenda?semana=<?= e($previous) ?>"><?= Icons::render('chevron-left', 17) ?></a>
        <a class="btn" href="/agenda">Hoy</a>
        <a class="btn btn--icon" href="/agenda?semana=<?= e($next) ?>"><?= Icons::render('chevron-right', 17) ?></a>
        <a class="btn btn--primary" href="/agenda/nueva"><?= Icons::render('plus', 16) ?> Nueva cita</a>
    </div>
</div>

<div class="calendar">
    <?php $index = 0; ?>
    <?php foreach ($week['days'] as $date => $events): ?>
        <div class="calendar__day<?= $date === $today ? ' calendar__day--today' : '' ?>">
            <div class="calendar__head">
                <div class="calendar__weekday"><?= e($weekdays[$index]) ?></div>
                <div class="calendar__date"><?= e(date('d', strtotime($date))) ?>
                    <span class="text-xs text-muted"><?= e(date('M', strtotime($date))) ?></span>
                </div>
            </div>
            <div class="calendar__body">
                <?php foreach ($events as $event): ?>
                    <a class="event event--<?= e($event['status']) ?>" href="/agenda/<?= (int) $event['id'] ?>/editar">
                        <span class="event__time"><?= e(date('H:i', strtotime((string) $event['starts_at']))) ?></span>
                        <span class="event__name"><?= e($event['first_name'] . ' ' . $event['last_name']) ?></span>
                        <span class="text-xs text-muted" style="display:block"><?= e(Appointments::MODALITIES[$event['modality']]) ?></span>
                    </a>
                <?php endforeach; ?>
                <a class="btn btn--ghost btn--sm" href="/agenda/nueva?fecha=<?= e($date) ?>" style="margin-top:auto;justify-content:center">
                    <?= Icons::render('plus', 14) ?>
                </a>
            </div>
        </div>
        <?php $index++; ?>
    <?php endforeach; ?>
</div>

<div class="chart-legend mt-2">
    <?php foreach (Appointments::STATUSES as $key => $label): ?>
        <span><i style="background:<?= e(['scheduled' => '#3f5ff2', 'confirmed' => '#2f7bd9', 'completed' => '#17a673', 'cancelled' => '#d94848', 'no_show' => '#d98207'][$key]) ?>"></i><?= e($label) ?></span>
    <?php endforeach; ?>
</div>
