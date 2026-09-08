<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

class ModelTest extends TestCase
{
    private PDO $pdo;
    private \Model $model;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = 'sqlite::memory:';
        $this->pdo = new PDO($dsn);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        $this->pdo->exec("
            CREATE TABLE song (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                artist TEXT NOT NULL,
                track TEXT NOT NULL,
                link TEXT
            )
        ");

        $this->model = new \Model($this->pdo);
    }

    public function testGetAllSongsReturnsEmptyArray(): void
    {
        $songs = $this->model->getAllSongs();
        $this->assertIsArray($songs);
        $this->assertCount(0, $songs);
    }

    public function testAddSongAndRetrieve(): void
    {
        $this->model->addSong('Radiohead', 'Creep', 'https://example.com/creep');
        $songs = $this->model->getAllSongs();
        $this->assertCount(1, $songs);
        $this->assertEquals('Radiohead', $songs[0]->artist);
        $this->assertEquals('Creep', $songs[0]->track);
    }

    public function testGetSongById(): void
    {
        $this->model->addSong('Nirvana', 'Smells Like Teen Spirit', 'https://example.com');
        $song = $this->model->getSong(1);
        $this->assertNotNull($song);
        $this->assertEquals('Nirvana', $song->artist);
    }

    public function testGetSongByIdNotFound(): void
    {
        $song = $this->model->getSong(999);
        $this->assertFalse($song);
    }

    public function testUpdateSong(): void
    {
        $this->model->addSong('Beatles', 'Yesterday', null);
        $this->model->updateSong('Beatles', 'Hey Jude', 'https://example.com/jude', 1);
        $song = $this->model->getSong(1);
        $this->assertEquals('Hey Jude', $song->track);
        $this->assertEquals('https://example.com/jude', $song->link);
    }

    public function testDeleteSong(): void
    {
        $this->model->addSong('Pink Floyd', 'Wish You Were Here', null);
        $this->model->deleteSong(1);
        $songs = $this->model->getAllSongs();
        $this->assertCount(0, $songs);
    }

    public function testGetAmountOfSongs(): void
    {
        $this->model->addSong('Artist A', 'Track 1', null);
        $this->model->addSong('Artist B', 'Track 2', null);
        $amount = $this->model->getAmountOfSongs();
        $this->assertEquals(2, $amount);
    }

    public function testParameterizedQueryPreventsSqlInjection(): void
    {
        $maliciousInput = "'; DROP TABLE song; --";
        $this->model->addSong($maliciousInput, 'Test', null);
        $songs = $this->model->getAllSongs();
        $this->assertCount(1, $songs);
        $this->assertEquals($maliciousInput, $songs[0]->artist);
    }
}
