<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        parent::tearDown();
    }

    public function testGenerateTokenReturnsNonEmptyString(): void
    {
        $token = \Security::generateToken();
        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testValidateTokenValid(): void
    {
        $token = \Security::generateToken();
        $this->assertTrue(\Security::validateToken($token));
    }

    public function testValidateTokenInvalid(): void
    {
        $this->assertFalse(\Security::validateToken('invalid-token'));
    }

    public function testValidateTokenEmpty(): void
    {
        $this->assertFalse(\Security::validateToken(''));
    }

    public function testInvalidateToken(): void
    {
        \Security::generateToken();
        $oldToken = $_SESSION['csrf_token'];
        \Security::invalidateToken();
        $this->assertFalse(\Security::validateToken($oldToken));
    }

    public function testIsValidControllerNameWithValidNames(): void
    {
        $this->assertTrue(\Security::isValidControllerName('Home'));
        $this->assertTrue(\Security::isValidControllerName('Songs'));
        $this->assertTrue(\Security::isValidControllerName('exam_builder'));
    }

    public function testIsValidControllerNameRejectsPathTraversal(): void
    {
        $this->assertFalse(\Security::isValidControllerName('../admin'));
        $this->assertFalse(\Security::isValidControllerName('controller/../../etc'));
        $this->assertFalse(\Security::isValidControllerName('foo\\bar'));
    }

    public function testIsValidControllerNameRejectsEmpty(): void
    {
        $this->assertFalse(\Security::isValidControllerName(''));
    }

    public function testEscapeHtml(): void
    {
        $input = '<script>alert("xss")</script>';
        $output = \Security::escape($input);
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $output);
    }

    public function testIsValidIdWithPositiveInt(): void
    {
        $this->assertTrue(\Security::isValidId('123'));
        $this->assertTrue(\Security::isValidId(456));
    }

    public function testIsValidIdRejectsNonNumeric(): void
    {
        $this->assertFalse(\Security::isValidId('abc'));
        $this->assertFalse(\Security::isValidId(''));
        $this->assertFalse(\Security::isValidId(null));
        $this->assertFalse(\Security::isValidId('-1'));
    }

    public function testIsPostAndIsGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(\Security::isPost());
        $this->assertFalse(\Security::isGet());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertFalse(\Security::isPost());
        $this->assertTrue(\Security::isGet());
    }
}
