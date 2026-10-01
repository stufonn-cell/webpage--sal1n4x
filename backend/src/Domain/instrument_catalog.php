<?php

/**
 * PsiClinic - catalogo de instrumentos psicometricos.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

$frequencyScale = [
    'Nunca' => 0,
    'Varios días' => 1,
    'Más de la mitad de los días' => 2,
    'Casi todos los días' => 3,
];

$occurrenceScale = [
    'No me ocurrió' => 0,
    'Me ocurrió un poco' => 1,
    'Me ocurrió bastante' => 2,
    'Me ocurrió mucho' => 3,
];

$perceivedStressScale = [
    'Nunca' => 0,
    'Casi nunca' => 1,
    'De vez en cuando' => 2,
    'A menudo' => 3,
    'Muy a menudo' => 4,
];

$agreementScale = [
    'Totalmente en desacuerdo' => 0,
    'En desacuerdo' => 1,
    'De acuerdo' => 2,
    'Totalmente de acuerdo' => 3,
];

$wellbeingScale = [
    'En ningún momento' => 0,
    'De vez en cuando' => 1,
    'Menos de la mitad del tiempo' => 2,
    'Más de la mitad del tiempo' => 3,
    'La mayor parte del tiempo' => 4,
    'Todo el tiempo' => 5,
];

return [
    'PHQ-9' => [
        'name' => 'Cuestionario de Salud del Paciente',
        'domain' => 'Depresión',
        'window' => 'Últimas 2 semanas',
        'description' => 'Tamizaje y seguimiento de sintomatología depresiva en nueve ítems alineados con los criterios diagnósticos.',
        'scale' => $frequencyScale,
        'reverse' => [],
        'items' => [
            'Poco interés o placer en hacer las cosas',
            'Se ha sentido decaído, deprimido o sin esperanza',
            'Dificultad para dormir o ha dormido demasiado',
            'Se ha sentido cansado o con poca energía',
            'Poco apetito o ha comido en exceso',
            'Se ha sentido mal consigo mismo o que ha fallado a los suyos',
            'Dificultad para concentrarse en actividades cotidianas',
            'Se ha movido o hablado más lento de lo habitual, o por el contrario ha estado inquieto',
            'Pensamientos de que estaría mejor muerto o de hacerse daño',
        ],
        'critical_items' => [8],
        'bands' => [
            [0, 4, 'Minima', 'Sintomatologia minima. Seguimiento de rutina.'],
            [5, 9, 'Leve', 'Sintomatologia leve. Vigilancia activa y psicoeducacion.'],
            [10, 14, 'Moderada', 'Sintomatologia moderada. Se sugiere plan de tratamiento estructurado.'],
            [15, 19, 'Moderada-severa', 'Requiere intervencion activa y evaluacion de comorbilidad.'],
            [20, 27, 'Severa', 'Sintomatologia severa. Priorizar intervencion y valorar interconsulta.'],
        ],
    ],

    'GAD-7' => [
        'name' => 'Escala de Ansiedad Generalizada',
        'domain' => 'Ansiedad',
        'window' => 'Últimas 2 semanas',
        'description' => 'Instrumento breve para detección y medición de la severidad de la ansiedad generalizada.',
        'scale' => $frequencyScale,
        'reverse' => [],
        'items' => [
            'Se ha sentido nervioso, ansioso o muy alterado',
            'No ha podido dejar de preocuparse o controlar la preocupación',
            'Se ha preocupado demasiado por diferentes cosas',
            'Ha tenido dificultad para relajarse',
            'Ha estado tan inquieto que le costaba quedarse sentado',
            'Se ha molestado o irritado con facilidad',
            'Ha sentido miedo de que algo terrible pudiera ocurrir',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 4, 'Minima', 'Sin indicadores relevantes de ansiedad.'],
            [5, 9, 'Leve', 'Ansiedad leve. Psicoeducacion y monitoreo.'],
            [10, 14, 'Moderada', 'Ansiedad moderada. Intervencion recomendada.'],
            [15, 21, 'Severa', 'Ansiedad severa. Intervencion prioritaria.'],
        ],
    ],

    'DASS-21' => [
        'name' => 'Escalas de Depresión, Ansiedad y Estrés',
        'domain' => 'Multidimensional',
        'window' => 'Última semana',
        'description' => 'Versión abreviada con tres subescalas independientes. Cada subescala se multiplica por dos para compararla con la versión de 42 ítems.',
        'scale' => $occurrenceScale,
        'reverse' => [],
        'multiplier' => 2,
        'items' => [
            'Me costó mucho relajarme',
            'Me di cuenta de que tenía la boca seca',
            'No pude sentir ningún sentimiento positivo',
            'Se me hizo difícil respirar sin haber hecho esfuerzo físico',
            'Se me hizo difícil tomar la iniciativa para hacer cosas',
            'Reaccioné exageradamente ante ciertas situaciones',
            'Sentí temblores, por ejemplo en las manos',
            'He sentido que gastaba mucha energía nerviosa',
            'Me preocupaban situaciones en las que pudiera entrar en pánico',
            'Sentí que no tenía nada por qué vivir',
            'Noté que me agitaba con facilidad',
            'Se me hizo difícil descansar',
            'Me sentí triste y deprimido',
            'No toleré nada que me impidiera continuar con lo que hacía',
            'Sentí que estaba al punto del pánico',
            'No me pude entusiasmar por nada',
            'Sentí que valía muy poco como persona',
            'Sentí que estaba muy irritable',
            'Noté el ritmo de mi corazón sin haber hecho esfuerzo físico',
            'Sentí miedo sin razón aparente',
            'Sentí que la vida no tenía sentido',
        ],
        'critical_items' => [9, 20],
        'subscales' => [
            'Depresion' => [2, 4, 9, 12, 15, 16, 20],
            'Ansiedad' => [1, 3, 6, 8, 14, 18, 19],
            'Estres' => [0, 5, 7, 10, 11, 13, 17],
        ],
        'subscale_bands' => [
            'Depresion' => [
                [0, 9, 'Normal'],
                [10, 13, 'Leve'],
                [14, 20, 'Moderada'],
                [21, 27, 'Severa'],
                [28, 42, 'Extremadamente severa'],
            ],
            'Ansiedad' => [
                [0, 7, 'Normal'],
                [8, 9, 'Leve'],
                [10, 14, 'Moderada'],
                [15, 19, 'Severa'],
                [20, 42, 'Extremadamente severa'],
            ],
            'Estres' => [
                [0, 14, 'Normal'],
                [15, 18, 'Leve'],
                [19, 25, 'Moderada'],
                [26, 33, 'Severa'],
                [34, 42, 'Extremadamente severa'],
            ],
        ],
        'bands' => [
            [0, 25, 'Bajo', 'Malestar global bajo. Revisar cada subescala por separado.'],
            [26, 50, 'Moderado', 'Malestar global moderado. Revisar cada subescala por separado.'],
            [51, 126, 'Alto', 'Malestar global alto. Revisar cada subescala por separado.'],
        ],
    ],

    'PSS-10' => [
        'name' => 'Escala de Estrés Percibido',
        'domain' => 'Estrés',
        'window' => 'Último mes',
        'description' => 'Mide el grado en que las situaciones de la vida se valoran como impredecibles, incontrolables y sobrecargadas.',
        'scale' => $perceivedStressScale,
        'reverse' => [3, 4, 6, 7],
        'items' => [
            'Se ha sentido afectado por algo que ocurrió inesperadamente',
            'Ha sentido que no podía controlar las cosas importantes de su vida',
            'Se ha sentido nervioso o estresado',
            'Ha confiado en su capacidad para manejar sus problemas personales',
            'Ha sentido que las cosas le iban bien',
            'Ha sentido que no podía afrontar todo lo que tenía que hacer',
            'Ha podido controlar las dificultades de su vida',
            'Ha sentido que tenía todo bajo control',
            'Se ha sentido enfadado por cosas que estaban fuera de su control',
            'Ha sentido que las dificultades se acumulan tanto que no puede superarlas',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 13, 'Bajo', 'Estres percibido bajo.'],
            [14, 26, 'Moderado', 'Estres percibido moderado. Trabajar estrategias de afrontamiento.'],
            [27, 40, 'Alto', 'Estres percibido alto. Intervencion recomendada.'],
        ],
    ],

    'RSES' => [
        'name' => 'Escala de Autoestima de Rosenberg',
        'domain' => 'Autoestima',
        'window' => 'Estado general',
        'description' => 'Evalúa el sentimiento global de valía personal y autoaceptación.',
        'scale' => $agreementScale,
        'reverse' => [5, 6, 7, 8, 9],
        'items' => [
            'Siento que soy una persona digna de aprecio, al menos igual que los demás',
            'Estoy convencido de que tengo buenas cualidades',
            'Soy capaz de hacer las cosas tan bien como la mayoría de la gente',
            'Tengo una actitud positiva hacia mí mismo',
            'En general estoy satisfecho conmigo mismo',
            'Siento que no tengo mucho de lo que estar orgulloso',
            'En general me inclino a pensar que soy un fracasado',
            'Me gustaría poder sentir más respeto por mí mismo',
            'Hay veces que realmente pienso que soy un inútil',
            'A veces creo que no soy buena persona',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 14, 'Baja', 'Autoestima baja. Trabajo terapeutico en autoconcepto.'],
            [15, 24, 'Media', 'Autoestima media. Reforzar recursos personales.'],
            [25, 30, 'Alta', 'Autoestima alta.'],
        ],
    ],

    'WHO-5' => [
        'name' => 'Índice de Bienestar de la OMS',
        'domain' => 'Bienestar',
        'window' => 'Últimas 2 semanas',
        'description' => 'Medida breve de bienestar subjetivo. El puntaje bruto se multiplica por cuatro para obtener un índice de 0 a 100.',
        'scale' => $wellbeingScale,
        'reverse' => [],
        'multiplier' => 4,
        'items' => [
            'Me he sentido alegre y de buen humor',
            'Me he sentido tranquilo y relajado',
            'Me he sentido activo y con energía',
            'Me he despertado sintiéndome fresco y descansado',
            'Mi vida diaria ha estado llena de cosas que me interesan',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 28, 'Bajo', 'Bienestar bajo. Se sugiere evaluar sintomatologia depresiva.'],
            [29, 50, 'Reducido', 'Bienestar reducido. Monitorear evolucion.'],
            [51, 100, 'Adecuado', 'Bienestar dentro de rango adecuado.'],
        ],
    ],
];
