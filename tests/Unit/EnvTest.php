<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    private string $tempEnvFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempEnvFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_env_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempEnvFile)) {
            unlink($this->tempEnvFile);
        }
        parent::tearDown();
    }

    public function testLoadDotEnvWithNonExistentFileDoesNothing(): void
    {
        // Should return gracefully without throwing error
        loadDotEnv('/path/to/non_existent_file_xyz.env');
        $this->assertTrue(true);
    }

    public function testLoadDotEnvParsesKeyValuePairs(): void
    {
        $testKey = 'TEST_ENV_VAR_' . uniqid();
        file_put_contents($this->tempEnvFile, "{$testKey}=hello_world\n");

        loadDotEnv($this->tempEnvFile);

        $this->assertEquals('hello_world', getenv($testKey));
        $this->assertEquals('hello_world', $_ENV[$testKey] ?? null);
        $this->assertEquals('hello_world', $_SERVER[$testKey] ?? null);

        // Cleanup
        putenv($testKey);
        unset($_ENV[$testKey], $_SERVER[$testKey]);
    }

    public function testLoadDotEnvTrimsQuotesAndSpaces(): void
    {
        $testKey1 = 'TEST_QUOTED_DOUBLE_' . uniqid();
        $testKey2 = 'TEST_QUOTED_SINGLE_' . uniqid();
        $content = "{$testKey1} = \"double_quoted_value\" \n";
        $content .= "{$testKey2} = 'single_quoted_value' \n";
        file_put_contents($this->tempEnvFile, $content);

        loadDotEnv($this->tempEnvFile);

        $this->assertEquals('double_quoted_value', getenv($testKey1));
        $this->assertEquals('single_quoted_value', getenv($testKey2));

        // Cleanup
        putenv($testKey1);
        putenv($testKey2);
        unset($_ENV[$testKey1], $_ENV[$testKey2], $_SERVER[$testKey1], $_SERVER[$testKey2]);
    }

    public function testLoadDotEnvIgnoresCommentsAndEmptyLines(): void
    {
        $testKey = 'TEST_VALID_KEY_' . uniqid();
        $content = "\n\n# This is a comment\n   # Another comment with spaces\n{$testKey}=active\n# footer comment\n";
        file_put_contents($this->tempEnvFile, $content);

        loadDotEnv($this->tempEnvFile);

        $this->assertEquals('active', getenv($testKey));

        // Cleanup
        putenv($testKey);
        unset($_ENV[$testKey], $_SERVER[$testKey]);
    }

    public function testLoadDotEnvIgnoresLinesWithoutEqualsSign(): void
    {
        $testKey = 'TEST_LINE_NO_EQ_' . uniqid();
        $content = "INVALID_LINE_WITHOUT_EQUALS\n{$testKey}=valid\nJUST_WORDS\n";
        file_put_contents($this->tempEnvFile, $content);

        loadDotEnv($this->tempEnvFile);

        $this->assertEquals('valid', getenv($testKey));

        // Cleanup
        putenv($testKey);
        unset($_ENV[$testKey], $_SERVER[$testKey]);
    }

    public function testLoadDotEnvDoesNotOverwriteExistingEnvVar(): void
    {
        $testKey = 'TEST_PREEXISTING_' . uniqid();
        putenv("{$testKey}=original_value");

        file_put_contents($this->tempEnvFile, "{$testKey}=new_overwritten_value\n");

        loadDotEnv($this->tempEnvFile);

        $this->assertEquals('original_value', getenv($testKey));

        // Cleanup
        putenv($testKey);
    }

    public function testLoadDotEnvHandlesValueContainingEqualsSign(): void
    {
        $testKey = 'TEST_EQ_VAL_' . uniqid();
        file_put_contents($this->tempEnvFile, "{$testKey}=http://localhost/?param=1&other=2\n");

        loadDotEnv($this->tempEnvFile);

        $this->assertEquals('http://localhost/?param=1&other=2', getenv($testKey));

        // Cleanup
        putenv($testKey);
        unset($_ENV[$testKey], $_SERVER[$testKey]);
    }
}

