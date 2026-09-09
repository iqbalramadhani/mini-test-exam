<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use ReflectionClass;

/**
 * Provides testable versions of API controllers that capture responses
 * instead of calling exit().
 */
trait TestableControllers
{
    /** @var mixed */
    protected $capturedResponse = null;
    protected ?int $capturedStatus = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($this->pdo)) {
            $this->pdo = new PDO('sqlite::memory:');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_start();
        $_SESSION = [];
    }

    /**
     * Create a testable AuthController with the given PDO injected.
     */
    protected function makeTestableAuthController(): AuthControllerTestable
    {
        return new AuthControllerTestable($this->pdo);
    }

    /**
     * Create a testable ExamController with the given PDO injected.
     */
    protected function makeTestableExamController(): ExamControllerTestable
    {
        return new ExamControllerTestable($this->pdo);
    }

    /**
     * Create a testable AttemptController with the given PDO injected.
     */
    protected function makeTestableAttemptController(): AttemptControllerTestable
    {
        return new AttemptControllerTestable($this->pdo);
    }

    /**
     * Clear captured response state.
     */
    protected function clearCapture(): void
    {
        $this->capturedResponse = null;
        $this->capturedStatus   = null;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Testable Controller base trait — shared by all testable controllers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Shared behaviour for all testable controller subclasses.
 * Stores the JSON body to return from getJsonInput() and captures responses.
 */
trait TestableControllerBase
{
    /** @var mixed */
    public $capturedResponse = null;
    public ?int $capturedStatus = null;

    /** The JSON body to return from getJsonInput(). */
    private array $injectedInput = [];

    /**
     * Set the request body that this testable controller should parse.
     * Call this instead of writing to php://input.
     */
    public function setInput(mixed $data): void
    {
        $this->injectedInput = is_array($data) ? $data : (json_decode(json_encode($data), true) ?? []);
    }

    protected function getJsonInput(): array
    {
        return $this->injectedInput;
    }

    protected function respond(mixed $data, int $status = 200): never
    {
        $this->capturedResponse = $data;
        $this->capturedStatus   = $status;
        throw new ResponseCapturedException($data, $status);
    }

    protected function error(string $message, int $status = 400): never
    {
        $this->capturedResponse = ['error' => $message];
        $this->capturedStatus   = $status;
        throw new ResponseCapturedException(['error' => $message], $status);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Concrete testable controller classes
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Testable version of AuthController that captures responses instead of exiting.
 */
class AuthControllerTestable extends \AuthController
{
    use TestableControllerBase;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }
}

/**
 * Testable version of ExamController that captures responses instead of exiting.
 */
class ExamControllerTestable extends \ExamController
{
    use TestableControllerBase;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    protected function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->capturedResponse = ['error' => 'Unauthorized'];
            $this->capturedStatus   = 401;
            throw new ResponseCapturedException(['error' => 'Unauthorized'], 401);
        }
    }
}

/**
 * Testable version of AttemptController that captures responses instead of exiting.
 */
class AttemptControllerTestable extends \AttemptController
{
    use TestableControllerBase;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }
}
