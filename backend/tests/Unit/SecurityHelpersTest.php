<?php

/**
 * PsiClinic - clinical records system for psychology practices.
 * Made by Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. All rights reserved. See LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use InvalidArgumentException;
use PsiClinic\Core\Database;
use PsiClinic\Core\HttpException;
use PsiClinic\Core\Log;
use PsiClinic\Core\OutboundUrl;
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Core\Validator;
use PsiClinic\Domain\Documents;
use PsiClinic\Support\Signature;
use PsiClinic\Tests\TestCase;

/** Building blocks of the hardening that need no database. */
final class SecurityHelpersTest extends TestCase
{
    public function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testOnlyPlainLowerCaseIdentifiersAreAccepted(): void
    {
        foreach (['users', 'clinical_notes', 'icd10_code', '_private'] as $name) {
            $this->assertSame('`' . $name . '`', Database::identifier($name));
        }

        $payloads = [
            'users; DROP TABLE users', 'users`', '`users`', 'users -- x', 'users/**/', 'Users', '1users',
            'users.id', "users'", 'users"', 'users OR 1=1', '', str_repeat('a', 65), "users\0", 'usérs',
        ];
        foreach ($payloads as $name) {
            $this->assertThrows(static fn () => Database::identifier($name), 'Accepted identifier: ' . $name);
        }
    }

    public function testInsertUpdateAndDeleteRefuseUnsafeNamesBeforeTouchingTheDatabase(): void
    {
        $this->assertRefused(static fn () => Database::insert('users; DROP TABLE users', ['username' => 'x']));
        $this->assertRefused(static fn () => Database::insert('users', ['username`) VALUES (1); --' => 'x']));
        $this->assertRefused(static fn () => Database::update('users', 1, ['role = "admin", username' => 'x']));
        $this->assertRefused(static fn () => Database::update('users` SET role = "admin" --', 1, ['username' => 'x']));
        $this->assertRefused(static fn () => Database::delete('users WHERE 1=1 --', 1));
    }

    public function testLikePatternsMatchWildcardsLiterally(): void
    {
        $this->assertSame('%50\\%%', Database::like('50%'));
        $this->assertSame('%a\\_b%', Database::like('a_b'));
        $this->assertSame('%back\\\\slash%', Database::like('back\\slash'));
        $this->assertSame('F41\\_%', Database::like('F41_', 'prefix'));
        $this->assertSame("%' OR 1=1 --%", Database::like("' OR 1=1 --"), 'Quotes stay data: the value is bound');
    }

    public function testLimitAndOffsetAreAlwaysSaneIntegers(): void
    {
        $this->assertSame(' LIMIT 20 OFFSET 40', Database::limit(20, 40));
        $this->assertSame(' LIMIT 1 OFFSET 0', Database::limit(-5, -10));
        $this->assertSame(' LIMIT 500 OFFSET 0', Database::limit(PHP_INT_MAX));
        $this->assertSame(' LIMIT 50 OFFSET 0', Database::limit(999, 0, 50));
        $this->assertSame(0, Database::offset(-3, 15));
        $this->assertSame(15, Database::offset(2, 15));
        $this->assertTrue(Database::offset(PHP_INT_MAX, 15) <= 1000000000, 'A huge page number must not overflow');
    }

    public function testJsonBodiesThatAreMalformedOrTooDeepAreRefused(): void
    {
        $this->assertSame([], Request::decodeJson(''));
        $this->assertSame(['a' => 1], Request::decodeJson('{"a":1}'));
        $this->assertSame(400, self::statusOf(static fn () => Request::decodeJson('{"a":')));
        $this->assertSame(400, self::statusOf(static fn () => Request::decodeJson('"just a string"')));
        $this->assertSame(400, self::statusOf(static fn () => Request::decodeJson(str_repeat('[', 40) . str_repeat(']', 40))));
        $this->assertSame(413, self::statusOf(static fn () => Request::decodeJson('"' . str_repeat('x', Request::maxJsonBytes()) . '"')));

        // The deepest real payload (signature strokes) still fits.
        $strokes = Request::decodeJson('{"strokes":[[[1,2],[3,4]]]}');
        $this->assertSame(3, $strokes['strokes'][0][1][0]);
    }

    public function testTheMethodCanOnlyBeOverriddenFromAPost(): void
    {
        $get = new Request([], ['_method' => 'DELETE'], [], ['REQUEST_METHOD' => 'GET']);
        $post = new Request([], ['_method' => 'DELETE'], [], ['REQUEST_METHOD' => 'POST']);
        $array = new Request([], ['_method' => ['DELETE']], [], ['REQUEST_METHOD' => 'POST']);

        $this->assertSame('GET', $get->method());
        $this->assertSame('DELETE', $post->method());
        $this->assertSame('POST', $array->method());
    }

    public function testIncomingTextIsNormalisedToValidUtf8(): void
    {
        $request = new Request(['q' => "ab\xFFc"], ['name' => "Ana\0 Paz", 'list' => ["x\xC3"]], [], []);

        $this->assertTrue(mb_check_encoding($request->string('q'), 'UTF-8'));
        $this->assertSame('Ana Paz', $request->string('name'));
        $this->assertTrue(mb_check_encoding($request->array('list')[0], 'UTF-8'));
    }

    public function testIntegersNeverOverflow(): void
    {
        $request = new Request(['page' => '99999999999999999999999', 'neg' => '-1e400', 'f' => '2.9'], [], [], []);

        $this->assertSame(PHP_INT_MAX, $request->integer('page'));
        $this->assertSame(7, $request->integer('neg', 7), 'Infinity falls back to the default');
        $this->assertSame(2, $request->integer('f'));
    }

    public function testTheClientIpIsOnlyTakenFromRemoteAddr(): void
    {
        $spoofed = new Request([], [], [], ['REMOTE_ADDR' => '10.0.0.8', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $garbage = new Request([], [], [], ['REMOTE_ADDR' => "1.2.3.4\r\nX-Evil: 1"]);

        $this->assertSame('10.0.0.8', $spoofed->ip());
        $this->assertSame('0.0.0.0', $garbage->ip());
    }

    public function testValidationRefusesListsWhereTextIsExpected(): void
    {
        $validator = (new Validator(['name' => ['x'], 'email' => ['a@b.co'], 'age' => ['1']]))
            ->validate(['name' => 'required|max:10', 'email' => 'email', 'age' => 'numeric']);

        $this->assertArrayHasKey('name', $validator->errors());
        $this->assertArrayHasKey('email', $validator->errors());
        $this->assertArrayHasKey('age', $validator->errors());
    }

    public function testDatesMustBeRealCalendarDates(): void
    {
        foreach (['2026-02-28', '2024-02-29', '2026-10-01 14:30', '2026-10-01T14:30:59'] as $date) {
            $this->assertTrue(Validator::isDate($date), 'Refused ' . $date);
        }
        foreach (['tomorrow', '+1 day', '2026-02-30', '2026-13-01', '01/10/2026', '2026-10-01 25:00', "2026-10-01' OR 1=1 --", '0999-01-01'] as $date) {
            $this->assertFalse(Validator::isDate($date), 'Accepted ' . $date);
        }
        $this->assertTrue((new Validator(['n' => 'INF']))->validate(['n' => 'numeric'])->fails());
        $this->assertTrue((new Validator(['n' => '1e400']))->validate(['n' => 'numeric'])->fails());
        $this->assertTrue((new Validator(['n' => '1.5']))->validate(['n' => 'integer'])->fails());
        $this->assertFalse((new Validator(['n' => '15']))->validate(['n' => 'integer'])->fails());
    }

    public function testSessionsExpireWhenIdleAndAfterTheAbsoluteLifetime(): void
    {
        $now = 1_800_000_000;

        $_SESSION = ['user_id' => 1, '_last_activity' => $now - Session::idleLifetime() - 1];
        Session::enforceTimeouts($now);
        $this->assertFalse(isset($_SESSION['user_id']), 'Idle session kept alive');

        $_SESSION = ['user_id' => 1, '_last_activity' => $now - 10, '_authenticated_at' => $now - Session::absoluteLifetime() - 1];
        Session::enforceTimeouts($now);
        $this->assertFalse(isset($_SESSION['user_id']), 'Session older than the absolute lifetime kept alive');

        $_SESSION = ['user_id' => 1, '_last_activity' => $now - 10, '_authenticated_at' => $now - 60];
        Session::enforceTimeouts($now);
        $this->assertSame(1, $_SESSION['user_id']);
    }

    public function testCookiesAreStrictAndSecureInProduction(): void
    {
        $this->assertSame('Strict', Session::sameSite());

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(Session::secure(), 'HTTPS behind the proxy must give a Secure cookie');
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function testOutboundCallsNeverReachMetadataOrLinkLocalAddresses(): void
    {
        $resolver = static fn (string $host): array => match ($host) {
            'localhost' => ['127.0.0.1'],
            'validator.clinic.lan' => ['192.168.1.20'],
            'rebind.example' => ['203.0.113.10', '169.254.169.254'],
            default => [],
        };

        foreach ([
            'https://localhost:9443', 'http://10.0.0.5:9443/api', 'https://validator.clinic.lan', 'https://[::1]:9443',
        ] as $url) {
            $this->assertNull(OutboundUrl::problem($url, $resolver), 'Refused ' . $url);
        }

        foreach ([
            'http://169.254.169.254/latest/meta-data/', 'http://[fe80::1]/', 'http://[::ffff:169.254.169.254]/',
            'http://metadata.google.internal/computeMetadata/v1/', 'http://0.0.0.0:9443', 'http://100.100.100.200/',
            'http://224.0.0.1/', 'http://[fd00:ec2::254]/', 'https://rebind.example', 'ftp://localhost/',
            'file:///etc/passwd', 'gopher://localhost:25/', 'https://user:secret@localhost:9443', 'https://unknown.invalid',
            'javascript:alert(1)', '//localhost:9443',
        ] as $url) {
            $this->assertNotNull(OutboundUrl::problem($url, $resolver), 'Accepted ' . $url);
        }
    }

    public function testLogLinesCannotBeForged(): void
    {
        $line = Log::format("Login failed\r\n[ERROR] admin logged in", ['user' => "x\ny", 'esc' => "\x1b[31m"]);

        $this->assertFalse(str_contains($line, "\n"));
        $this->assertFalse(str_contains($line, "\r"));
        $this->assertFalse(str_contains($line, "\x1b"));
        $this->assertContains('\\x0D\\x0A', $line);
    }

    public function testSignaturesNeverCarryMarkupFromTheBrowser(): void
    {
        $stored = '<svg onload="alert(1)"><script>alert(1)</script><path d="M10 10 L20 20" onclick="x()"/>'
            . '<path d="M1 1 L2 2&quot;/><script>"/></svg>';
        $clean = Signature::sanitize($stored);

        $this->assertFalse(str_contains($clean, 'script'));
        $this->assertFalse(str_contains($clean, 'onload'));
        $this->assertFalse(str_contains($clean, 'onclick'));
        $this->assertContains('d="M10 10 L20 20"', $clean);
        $this->assertSame('', Signature::fromStrokes([['<script>', '"x"'], 'nope']));
    }

    public function testUploadsNeedAnAllowedTypeAndAMatchingExtension(): void
    {
        $text = tempnam(sys_get_temp_dir(), 'psi');
        file_put_contents($text, "Plain notes\n");
        $fakeImage = tempnam(sys_get_temp_dir(), 'psi');
        file_put_contents($fakeImage, "\x89PNG\r\n\x1a\n" . 'not really an image <?php echo 1; ?>');
        $realImage = tempnam(sys_get_temp_dir(), 'psi');
        file_put_contents($realImage, (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));

        try {
            $this->assertTrue(Documents::isAllowed('image/png', 'dot.png', $realImage));
            $this->assertFalse(Documents::isAllowed('image/png', 'dot.jpg', $realImage), 'The extension must agree with the content');
            $this->assertTrue(Documents::isAllowed('text/plain', 'notes.txt', $text));
            $this->assertFalse(Documents::isAllowed('text/plain', 'shell.php', $text), 'A .php name must be refused');
            $this->assertFalse(Documents::isAllowed('text/plain', 'notes.txt.phtml', $text));
            $this->assertFalse(Documents::isAllowed('text/html', 'page.txt', $text));
            $this->assertFalse(Documents::isAllowed('image/svg+xml', 'logo.svg', $text));
            $this->assertFalse(Documents::isAllowed('image/png', 'photo.png', $fakeImage), 'A broken image must be refused');
        } finally {
            @unlink($text);
            @unlink($fakeImage);
            @unlink($realImage);
        }

        $this->assertSame('passwd', Documents::cleanFileName('../../etc/passwd'));
        $this->assertSame('evil.pdf', Documents::cleanFileName("C:\\Users\\x\\evil.pdf"));
        $this->assertSame('report.pdf', Documents::cleanFileName("report\r\n.pdf"));
        $this->assertSame('document', Documents::cleanFileName('...'));
        $this->assertTrue(str_ends_with(Documents::cleanFileName(str_repeat('a', 300) . '.pdf'), '.pdf'));
    }

    public function testJsonResponsesEscapeHtmlCharacters(): void
    {
        ob_start();
        \PsiClinic\Core\Response::json(['label' => '<script>alert("x")</script> & co']);
        $output = (string) ob_get_clean();

        $this->assertFalse(str_contains($output, '<script>'));
        $this->assertSame('<script>alert("x")</script> & co', json_decode($output, true)['label']);
    }

    private function assertRefused(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);

            return;
        }

        $this->assertTrue(false, 'An unsafe identifier reached the database layer.');
    }

    private static function statusOf(callable $callback): int
    {
        try {
            $callback();
        } catch (HttpException $exception) {
            return $exception->status();
        }

        return 200;
    }
}
