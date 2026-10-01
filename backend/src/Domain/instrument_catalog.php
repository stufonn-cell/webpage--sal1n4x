<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

/*
 * Psychometric instrument catalog: the original English versions of each
 * scale. Severity band labels use the words the frontend maps to a tone
 * ("severe", "moderate", "mild", "minimal", ...).
 */

$frequencyScale = [
    'Not at all' => 0,
    'Several days' => 1,
    'More than half the days' => 2,
    'Nearly every day' => 3,
];

$occurrenceScale = [
    'Did not apply to me at all' => 0,
    'Applied to me to some degree, or some of the time' => 1,
    'Applied to me to a considerable degree, or a good part of the time' => 2,
    'Applied to me very much, or most of the time' => 3,
];

$perceivedStressScale = [
    'Never' => 0,
    'Almost never' => 1,
    'Sometimes' => 2,
    'Fairly often' => 3,
    'Very often' => 4,
];

$agreementScale = [
    'Strongly disagree' => 0,
    'Disagree' => 1,
    'Agree' => 2,
    'Strongly agree' => 3,
];

$wellbeingScale = [
    'At no time' => 0,
    'Some of the time' => 1,
    'Less than half of the time' => 2,
    'More than half of the time' => 3,
    'Most of the time' => 4,
    'All of the time' => 5,
];

return [
    'PHQ-9' => [
        'name' => 'Patient Health Questionnaire',
        'domain' => 'Depression',
        'window' => 'The last 2 weeks',
        'description' => 'Screens for and tracks depressive symptoms with nine items that follow the diagnostic criteria.',
        'scale' => $frequencyScale,
        'reverse' => [],
        'items' => [
            'Little interest or pleasure in doing things',
            'Feeling down, depressed, or hopeless',
            'Trouble falling or staying asleep, or sleeping too much',
            'Feeling tired or having little energy',
            'Poor appetite or overeating',
            'Feeling bad about yourself, or that you are a failure or have let yourself or your family down',
            'Trouble concentrating on things, such as reading the newspaper or watching television',
            'Moving or speaking so slowly that other people could have noticed, or the opposite: being so fidgety or restless that you have been moving around a lot more than usual',
            'Thoughts that you would be better off dead, or of hurting yourself in some way',
        ],
        'critical_items' => [8],
        'bands' => [
            [0, 4, 'Minimal', 'Minimal symptoms. Routine follow-up.'],
            [5, 9, 'Mild', 'Mild symptoms. Watchful waiting and psychoeducation.'],
            [10, 14, 'Moderate', 'Moderate symptoms. A structured treatment plan is suggested.'],
            [15, 19, 'Moderately severe', 'Requires active treatment and an assessment of comorbidity.'],
            [20, 27, 'Severe', 'Severe symptoms. Prioritize treatment and consider a specialist referral.'],
        ],
    ],

    'GAD-7' => [
        'name' => 'Generalized Anxiety Disorder Scale',
        'domain' => 'Anxiety',
        'window' => 'The last 2 weeks',
        'description' => 'Brief tool to detect generalized anxiety and measure how severe it is.',
        'scale' => $frequencyScale,
        'reverse' => [],
        'items' => [
            'Feeling nervous, anxious, or on edge',
            'Not being able to stop or control worrying',
            'Worrying too much about different things',
            'Trouble relaxing',
            'Being so restless that it is hard to sit still',
            'Becoming easily annoyed or irritable',
            'Feeling afraid, as if something awful might happen',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 4, 'Minimal', 'No relevant signs of anxiety.'],
            [5, 9, 'Mild', 'Mild anxiety. Psychoeducation and monitoring.'],
            [10, 14, 'Moderate', 'Moderate anxiety. Treatment is recommended.'],
            [15, 21, 'Severe', 'Severe anxiety. Treatment is a priority.'],
        ],
    ],

    'DASS-21' => [
        'name' => 'Depression Anxiety Stress Scales',
        'domain' => 'Multidimensional',
        'window' => 'The past week',
        'description' => 'Short form with three independent subscales. Each subscale is multiplied by two so it can be compared with the 42-item version.',
        'scale' => $occurrenceScale,
        'reverse' => [],
        'multiplier' => 2,
        'items' => [
            'I found it hard to wind down',
            'I was aware of dryness of my mouth',
            'I couldn\'t seem to experience any positive feeling at all',
            'I experienced breathing difficulty (for example, excessively rapid breathing or breathlessness in the absence of physical exertion)',
            'I found it difficult to work up the initiative to do things',
            'I tended to over-react to situations',
            'I experienced trembling (for example, in the hands)',
            'I felt that I was using a lot of nervous energy',
            'I was worried about situations in which I might panic and make a fool of myself',
            'I felt that I had nothing to look forward to',
            'I found myself getting agitated',
            'I found it difficult to relax',
            'I felt down-hearted and blue',
            'I was intolerant of anything that kept me from getting on with what I was doing',
            'I felt I was close to panic',
            'I was unable to become enthusiastic about anything',
            'I felt I wasn\'t worth much as a person',
            'I felt that I was rather touchy',
            'I was aware of the action of my heart in the absence of physical exertion (for example, a sense of heart rate increase or of my heart missing a beat)',
            'I felt scared without any good reason',
            'I felt that life was meaningless',
        ],
        'critical_items' => [9, 20],
        'subscales' => [
            'Depression' => [2, 4, 9, 12, 15, 16, 20],
            'Anxiety' => [1, 3, 6, 8, 14, 18, 19],
            'Stress' => [0, 5, 7, 10, 11, 13, 17],
        ],
        'subscale_bands' => [
            'Depression' => [
                [0, 9, 'Normal'],
                [10, 13, 'Mild'],
                [14, 20, 'Moderate'],
                [21, 27, 'Severe'],
                [28, 42, 'Extremely severe'],
            ],
            'Anxiety' => [
                [0, 7, 'Normal'],
                [8, 9, 'Mild'],
                [10, 14, 'Moderate'],
                [15, 19, 'Severe'],
                [20, 42, 'Extremely severe'],
            ],
            'Stress' => [
                [0, 14, 'Normal'],
                [15, 18, 'Mild'],
                [19, 25, 'Moderate'],
                [26, 33, 'Severe'],
                [34, 42, 'Extremely severe'],
            ],
        ],
        'bands' => [
            [0, 25, 'Low', 'Low overall distress. Review each subscale separately.'],
            [26, 50, 'Moderate', 'Moderate overall distress. Review each subscale separately.'],
            [51, 126, 'High', 'High overall distress. Review each subscale separately.'],
        ],
    ],

    'PSS-10' => [
        'name' => 'Perceived Stress Scale',
        'domain' => 'Stress',
        'window' => 'The last month',
        'description' => 'Measures how far life situations are appraised as unpredictable, uncontrollable and overloading.',
        'scale' => $perceivedStressScale,
        'reverse' => [3, 4, 6, 7],
        'items' => [
            'How often have you been upset because of something that happened unexpectedly?',
            'How often have you felt that you were unable to control the important things in your life?',
            'How often have you felt nervous and stressed?',
            'How often have you felt confident about your ability to handle your personal problems?',
            'How often have you felt that things were going your way?',
            'How often have you found that you could not cope with all the things that you had to do?',
            'How often have you been able to control irritations in your life?',
            'How often have you felt that you were on top of things?',
            'How often have you been angered because of things that happened that were outside of your control?',
            'How often have you felt difficulties were piling up so high that you could not overcome them?',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 13, 'Low stress', 'Low perceived stress.'],
            [14, 26, 'Moderate stress', 'Moderate perceived stress. Work on coping strategies.'],
            [27, 40, 'High perceived stress', 'High perceived stress. Intervention is recommended.'],
        ],
    ],

    'RSES' => [
        'name' => 'Rosenberg Self-Esteem Scale',
        'domain' => 'Self-esteem',
        'window' => 'How you generally feel about yourself',
        'description' => 'Assesses global feelings of self-worth and self-acceptance.',
        'scale' => $agreementScale,
        'reverse' => [5, 6, 7, 8, 9],
        'items' => [
            'I feel that I\'m a person of worth, at least on an equal plane with others',
            'I feel that I have a number of good qualities',
            'I am able to do things as well as most other people',
            'I take a positive attitude toward myself',
            'On the whole, I am satisfied with myself',
            'I feel I do not have much to be proud of',
            'All in all, I am inclined to feel that I am a failure',
            'I wish I could have more respect for myself',
            'I certainly feel useless at times',
            'At times I think I am no good at all',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 14, 'Low self-esteem', 'Low self-esteem. Therapeutic work on self-concept.'],
            [15, 24, 'Normal self-esteem', 'Self-esteem within the normal range. Strengthen personal resources.'],
            [25, 30, 'Strong self-esteem', 'Strong self-esteem.'],
        ],
    ],

    'WHO-5' => [
        'name' => 'WHO-5 Well-Being Index',
        'domain' => 'Wellbeing',
        'window' => 'The last 2 weeks',
        'description' => 'Brief measure of subjective wellbeing. The raw score is multiplied by four to give an index from 0 to 100.',
        'scale' => $wellbeingScale,
        'reverse' => [],
        'multiplier' => 4,
        'items' => [
            'I have felt cheerful and in good spirits',
            'I have felt calm and relaxed',
            'I have felt active and vigorous',
            'I woke up feeling fresh and rested',
            'My daily life has been filled with things that interest me',
        ],
        'critical_items' => [],
        'bands' => [
            [0, 28, 'Low', 'Low wellbeing. Consider screening for depressive symptoms.'],
            [29, 50, 'Reduced', 'Reduced wellbeing. Monitor how it evolves.'],
            [51, 100, 'Adequate', 'Wellbeing within an adequate range.'],
        ],
    ],
];
