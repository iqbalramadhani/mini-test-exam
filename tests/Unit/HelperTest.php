<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    public function testDebugPdowithNamedParameters(): void
    {
        $sql = "SELECT * FROM song WHERE artist = :artist AND id = :id";
        $params = [':artist' => 'Radiohead', ':id' => 42];
        $result = \Helper::debugPDO($sql, $params);
        $this->assertStringContainsString("'Radiohead'", $result);
        // Numeric values are rendered without quotes in debugPDO
        $this->assertStringContainsString("id = 42", $result);
    }

    public function testDebugPdowithAnonymousParameters(): void
    {
        $sql = "SELECT * FROM song WHERE id = ? AND artist = ?";
        $params = [0 => 'Nirvana', 1 => 7];
        $result = \Helper::debugPDO($sql, $params);
        $this->assertStringContainsString("'Nirvana'", $result);
        // Numeric values are rendered without quotes in debugPDO
        $this->assertStringContainsString("artist = 7", $result);
    }

    public function testDebugPdowithNullValue(): void
    {
        $sql = "SELECT * FROM song WHERE link = :link";
        $params = [':link' => null];
        $result = \Helper::debugPDO($sql, $params);
        $this->assertStringContainsString("NULL", $result);
    }

    public function testDebugPdowithArrayValue(): void
    {
        $sql = "SELECT * FROM song WHERE id IN (:ids)";
        $params = [':ids' => [1, 2, 3]];
        $result = \Helper::debugPDO($sql, $params);
        $this->assertStringContainsString("1,2,3", $result);
    }

    public function testDebugPdowithMixedParameters(): void
    {
        $sql = "SELECT * FROM song WHERE artist = :artist OR id IN (:ids)";
        $params = [':artist' => 'Beatles', ':ids' => [1, 2]];
        $result = \Helper::debugPDO($sql, $params);
        $this->assertStringContainsString("'Beatles'", $result);
        $this->assertStringContainsString("1,2", $result);
    }

    public function testDebugPDOwithEmptyParameters(): void
    {
        $sql = "SELECT * FROM song";
        $result = \Helper::debugPDO($sql, []);
        $this->assertEquals($sql, $result);
    }

    public function testDebugPDOkeepsOriginalQueryStructure(): void
    {
        $sql = "SELECT id, artist FROM song WHERE track = :track";
        $params = [':track' => 'Creep'];
        $result = \Helper::debugPDO($sql, $params);
        $this->assertStringNotContainsString(":track", $result);
        $this->assertStringContainsString("'Creep'", $result);
    }
}
