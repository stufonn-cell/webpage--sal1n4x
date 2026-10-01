<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

/**
 * Informed consent templates in every document language. Both versions of a
 * template say the same thing; a consent stores the text it was created with,
 * so later edits here never change a document a patient already signed.
 */
final class ConsentTemplates
{
    private const TEMPLATES = [
        'en' => [
            'general' => [
                'title' => 'Informed consent for psychological care',
                'body' => "I confirm that I have been informed about the nature of the psychotherapy process, its goals, its estimated length and the way of working, as required by Law 1090 of 2006, which regulates the practice of psychology in Colombia.\n\nI understand that what I share during sessions is protected by professional confidentiality, and that confidentiality may only be lifted when there is a risk to my life or to the lives of others, or when a competent judicial authority requires it.\n\nI have been told that I may stop the process at any time, request a copy of my clinical record and ask questions about any intervention.\n\nI freely and voluntarily agree to begin psychological care.",
            ],
            'telehealth' => [
                'title' => 'Consent for online and phone sessions',
                'body' => "I authorize sessions to take place by video call or by phone, under the telehealth rules of Law 1419 of 2010 and Resolution 2654 of 2019.\n\nI understand that remote sessions have technical limits, that the quality of the connection may affect the session, and that it is my responsibility to be in a private space during the session.\n\nI understand that, in a crisis, the professional may contact the person I named as my emergency contact or the appropriate emergency services.\n\nI accept remote care under these conditions.",
            ],
            'minors' => [
                'title' => 'Consent of the legal representative for the care of a minor',
                'body' => "As the legal representative, I authorize psychological care for the minor identified in this clinical record, in line with Law 1098 of 2006 (Code of Childhood and Adolescence) and Law 1090 of 2006.\n\nI understand that the professional will share with me the information needed to support the process, while protecting the confidential space the minor needs for the therapy to work.\n\nI agree to take part in family guidance sessions when the professional considers it appropriate.",
            ],
            'data_processing' => [
                'title' => 'Authorization to process personal data',
                'body' => "Under Law 1581 of 2012 and its regulations, I authorize the processing of my personal data, including my sensitive health data, for the sole purpose of providing psychological care, billing for it and meeting legal obligations.\n\nI know that I may exercise my rights to access, correct, update and delete my data, and that my data will be kept for as long as the rules that apply to clinical records require.",
            ],
            'recording' => [
                'title' => 'Authorization to record sessions for supervision',
                'body' => "I authorize audio or video recording of my sessions for the sole purpose of clinical supervision and professional training.\n\nI understand that the recordings will be stored securely, will not be shared with anyone outside the supervision process and will be deleted once they have served their purpose, in line with Law 1090 of 2006 and Law 1581 of 2012.\n\nI know that I may withdraw this authorization at any time without it affecting my care.",
            ],
        ],
        'es' => [
            'general' => [
                'title' => 'Consentimiento informado para atención psicológica',
                'body' => "Declaro que he sido informado sobre la naturaleza del proceso psicoterapéutico, sus objetivos, su duración estimada y la metodología de trabajo, conforme a la Ley 1090 de 2006, que reglamenta el ejercicio de la psicología en Colombia.\n\nEntiendo que la información compartida durante las sesiones está protegida por el secreto profesional, y que este solo puede levantarse cuando exista riesgo para mi vida o la de terceros, o por requerimiento de autoridad judicial competente.\n\nSe me ha informado que puedo suspender el proceso en cualquier momento, solicitar copia de mi historia clínica y formular preguntas sobre cualquier intervención.\n\nAcepto de forma libre y voluntaria iniciar el proceso de atención psicológica.",
            ],
            'telehealth' => [
                'title' => 'Consentimiento para atención por medios virtuales',
                'body' => "Autorizo la realización de sesiones mediante videollamada o teléfono, conforme a las normas de telesalud de la Ley 1419 de 2010 y la Resolución 2654 de 2019.\n\nComprendo que la modalidad virtual tiene limitaciones técnicas, que la calidad de la conexión puede afectar la sesión y que es mi responsabilidad ubicarme en un espacio privado durante la consulta.\n\nEntiendo que, ante una situación de crisis, el profesional podrá contactar a la persona que designé como contacto de emergencia o a los servicios de atención correspondientes.\n\nAcepto la atención virtual en estas condiciones.",
            ],
            'minors' => [
                'title' => 'Consentimiento del representante legal para la atención de un menor',
                'body' => "En calidad de representante legal, autorizo la atención psicológica del menor identificado en esta historia clínica, conforme a la Ley 1098 de 2006 (Código de la Infancia y la Adolescencia) y la Ley 1090 de 2006.\n\nEntiendo que el profesional compartirá conmigo la información necesaria para acompañar el proceso, preservando los espacios de confidencialidad que el menor necesita para que la terapia funcione.\n\nAcepto participar en las sesiones de orientación familiar cuando el profesional lo considere pertinente.",
            ],
            'data_processing' => [
                'title' => 'Autorización para el tratamiento de datos personales',
                'body' => "En los términos de la Ley 1581 de 2012 y sus decretos reglamentarios, autorizo el tratamiento de mis datos personales, incluidos mis datos sensibles de salud, con la finalidad exclusiva de prestar la atención psicológica, facturarla y cumplir las obligaciones legales.\n\nConozco que puedo ejercer los derechos de acceso, corrección, actualización y supresión de mis datos, y que estos se conservarán durante el tiempo que exija la normativa aplicable a las historias clínicas.",
            ],
            'recording' => [
                'title' => 'Autorización de grabación de sesiones con fines de supervisión',
                'body' => "Autorizo la grabación en audio o video de mis sesiones con la finalidad exclusiva de supervisión clínica y formación profesional.\n\nEntiendo que el material será custodiado de forma segura, no será divulgado a personas ajenas al proceso de supervisión y será eliminado una vez cumplida su finalidad, conforme a la Ley 1090 de 2006 y la Ley 1581 de 2012.\n\nConozco que puedo revocar esta autorización en cualquier momento sin que ello afecte mi atención.",
            ],
        ],
    ];

    /** Templates in one language, keyed by code. Unknown languages fall back to English. */
    public static function all(string $language = 'en'): array
    {
        return self::TEMPLATES[$language] ?? self::TEMPLATES['en'];
    }

    public static function get(string $code, string $language = 'en'): ?array
    {
        return self::all($language)[$code] ?? null;
    }
}
