<?php

/**
 * PsiClinic - sistema de historia clínica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

final class ConsentTemplates
{
    public static function all(): array
    {
        return [
            'general' => [
                'title' => 'Consentimiento informado para atención psicológica',
                'body' => "Declaro que he sido informado sobre la naturaleza del proceso psicoterapéutico, sus objetivos, duración estimada y metodología de trabajo.\n\nEntiendo que la información compartida durante las sesiones está protegida por el secreto profesional, y que este puede levantarse únicamente cuando exista riesgo vital para mí o para terceros, o por requerimiento de autoridad judicial competente.\n\nSe me ha informado que puedo suspender el proceso en cualquier momento, solicitar copia de mi historia clínica y formular preguntas sobre cualquier intervención.\n\nAcepto de forma libre y voluntaria iniciar el proceso de atención psicológica.",
            ],
            'teleconsulta' => [
                'title' => 'Consentimiento para atención por medios virtuales',
                'body' => "Autorizo la realización de sesiones mediante videollamada o teléfono.\n\nComprendo que la modalidad virtual tiene limitaciones técnicas, que la calidad de la conexión puede afectar la sesión y que es mi responsabilidad ubicarme en un espacio privado durante la consulta.\n\nEntiendo que ante una situación de crisis el profesional podrá contactar a la persona designada como contacto de emergencia o a los servicios de atención correspondientes.\n\nAcepto la modalidad virtual bajo estas condiciones.",
            ],
            'menores' => [
                'title' => 'Consentimiento de representante legal para atención de menores',
                'body' => "En calidad de representante legal autorizo la atención psicológica del menor identificado en esta historia clínica.\n\nEntiendo que el profesional compartirá conmigo la información necesaria para el acompañamiento del proceso, preservando los espacios de confidencialidad que resulten terapéuticamente necesarios para el menor.\n\nAcepto participar en las sesiones de orientación a familia cuando el profesional lo considere pertinente.",
            ],
            'datos' => [
                'title' => 'Autorización de tratamiento de datos personales',
                'body' => "Autorizo el tratamiento de mis datos personales y de mis datos sensibles de salud con la finalidad exclusiva de la prestación del servicio de atención psicológica, su facturación y el cumplimiento de obligaciones legales.\n\nConozco que puedo ejercer los derechos de acceso, corrección, actualización y supresión sobre mis datos, y que estos se conservarán durante el tiempo que exija la normativa aplicable a las historias clínicas.",
            ],
            'grabación' => [
                'title' => 'Autorización de registro audiovisual con fines de supervisión',
                'body' => "Autorizo el registro en audio o video de las sesiones con fines exclusivos de supervisión clínica y formación profesional.\n\nEntiendo que el material será custodiado de forma segura, no será divulgado a terceros ajenos al proceso de supervisión y será eliminado una vez cumplida su finalidad.\n\nConozco que puedo revocar esta autorización en cualquier momento sin que ello afecte mi atención.",
            ],
        ];
    }

    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }
}
