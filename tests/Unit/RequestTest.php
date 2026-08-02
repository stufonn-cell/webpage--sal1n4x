<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Core\Request;
use PsiClinic\Tests\TestCase;

final class RequestTest extends TestCase
{
    public function testBodyTakesPrecedenceOverQueryString(): void
    {
        $request = new Request(['q' => 'desde-url'], ['q' => 'desde-formulario'], [], []);

        $this->assertSame('desde-formulario', $request->input('q'));
    }

    public function testStringInputIsTrimmed(): void
    {
        $request = new Request([], ['name' => '  Mariana  '], [], []);

        $this->assertSame('Mariana', $request->input('name'));
    }

    public function testIntegerCastsAndFallsBack(): void
    {
        $request = new Request([], ['page' => '3', 'other' => 'abc'], [], []);

        $this->assertSame(3, $request->integer('page'));
        $this->assertSame(1, $request->integer('other', 1));
        $this->assertSame(0, $request->integer('missing'));
    }

    public function testArrayAlwaysReturnsAnArray(): void
    {
        $request = new Request([], ['answers' => [1, 2, 3], 'name' => 'texto'], [], []);

        $this->assertCount(3, $request->array('answers'));
        $this->assertCount(0, $request->array('name'));
        $this->assertCount(0, $request->array('missing'));
    }

    public function testMethodOverrideOnlyAcceptsSafeVerbs(): void
    {
        $server = ['REQUEST_METHOD' => 'POST'];

        $this->assertSame('PUT', (new Request([], ['_method' => 'put'], [], $server))->method());
        $this->assertSame('DELETE', (new Request([], ['_method' => 'DELETE'], [], $server))->method());
        $this->assertSame('POST', (new Request([], ['_method' => 'GET'], [], $server))->method());
    }

    public function testPathStripsTheQueryStringAndTrailingSlash(): void
    {
        $request = new Request([], [], [], ['REQUEST_URI' => '/pacientes/12/?tab=notas']);

        $this->assertSame('/pacientes/12', $request->path());
    }

    public function testRootPathIsNormalised(): void
    {
        $this->assertSame('/', (new Request([], [], [], ['REQUEST_URI' => '/']))->path());
        $this->assertSame('/', (new Request([], [], [], []))->path());
    }

    public function testFilesWithErrorsAreDiscarded(): void
    {
        $failed = new Request([], [], ['doc' => ['error' => UPLOAD_ERR_NO_FILE]], []);
        $valid = new Request([], [], ['doc' => ['error' => UPLOAD_ERR_OK, 'name' => 'a.pdf']], []);

        $this->assertNull($failed->file('doc'));
        $this->assertNotNull($valid->file('doc'));
    }

    public function testAttributesCanBeAttachedByTheRouter(): void
    {
        $request = new Request([], [], [], []);
        $request->setAttribute('id', '42');

        $this->assertSame('42', $request->attribute('id'));
        $this->assertSame('sin-valor', $request->attribute('otro', 'sin-valor'));
    }

    public function testUserAgentIsTruncated(): void
    {
        $request = new Request([], [], [], ['HTTP_USER_AGENT' => str_repeat('x', 400)]);

        $this->assertSame(255, strlen($request->userAgent()));
    }
}
