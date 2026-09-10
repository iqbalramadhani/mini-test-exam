<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase
{
    use TestableControllers;

    private PDO $pdo;

    private function seedUser(string $username, string $email, string $password, string $role = 'user', bool $active = true): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->pdo->prepare(
            "INSERT INTO user (username, email, password_hash, role, name, is_active) VALUES (:u, :e, :p, :r, :n, :a)"
        )->execute([
            ':u' => $username,
            ':e' => $email,
            ':p' => $hash,
            ':r' => $role,
            ':n' => $username,
            ':a' => $active ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function loginAs(int $userId): void
    {
        $stmt = $this->pdo->prepare("SELECT id, username, email, role, name FROM user WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['name']     = $user->name;
            $_SESSION['email']    = $user->email;
            $_SESSION['role']     = $user->role;
        }
    }

    /**
     * Call a controller method and catch ResponseCapturedException.
     */
    private function call(callable $fn, ?object $controller = null): void
    {
        try {
            $fn();
        } catch (ResponseCapturedException $e) {
            if ($controller !== null && $controller->capturedStatus === null) {
                $controller->capturedResponse = $e->responseData;
                $controller->capturedStatus   = $e->responseStatus;
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        $this->pdo->exec("
            CREATE TABLE user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                name VARCHAR(100) NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                confirmation_token VARCHAR(64) NULL,
                token_expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    // ─── Register ────────────────────────────────────────────────────────────

    public function testRegisterCreatesUserAndRequiresEmailVerification(): void
    {
        $before     = $this->pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'newuser', 'email' => 'new@example.com', 'password' => 'Password1']);
        $this->call(fn() => $controller->register());

        $after = $this->pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
        $this->assertEquals(1, $after - $before);

        $user = $this->pdo->query("SELECT * FROM user WHERE username = 'newuser'")->fetch();
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('Password1', $user->password_hash));
        $this->assertEquals('new@example.com', $user->email);
        $this->assertEquals('user', $user->role);
        $this->assertEquals(0, $user->is_active); // User should be inactive initially
        $this->assertNotNull($user->confirmation_token);
        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertArrayHasKey('message', $controller->capturedResponse);
    }

    public function testRegisterDoesNotSetSession(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'sessionuser', 'email' => 'sess@example.com', 'password' => 'Password1']);
        $this->call(fn() => $controller->register());

        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testRegisterRejectsShortUsername(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'ab', 'email' => 'ab@example.com', 'password' => 'Password1']);
        $this->call(fn() => $controller->register());

        $count = $this->pdo->query("SELECT COUNT(*) FROM user WHERE username = 'ab'")->fetchColumn();
        $this->assertEquals(0, $count);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testRegisterRejectsTooLongUsername(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => str_repeat('a', 51), 'email' => 'long@example.com', 'password' => 'Password1']);
        $this->call(fn() => $controller->register());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testRegisterRejectsInvalidEmail(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'validuser', 'email' => 'notanemail', 'password' => 'Password1']);
        $this->call(fn() => $controller->register());

        $count = $this->pdo->query("SELECT COUNT(*) FROM user WHERE username = 'validuser'")->fetchColumn();
        $this->assertEquals(0, $count);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testRegisterRejectsShortPassword(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'validuser', 'email' => 'v@example.com', 'password' => 'short']);
        $this->call(fn() => $controller->register());

        $count = $this->pdo->query("SELECT COUNT(*) FROM user WHERE username = 'validuser'")->fetchColumn();
        $this->assertEquals(0, $count);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testRegisterRejectsDuplicateUsername(): void
    {
        $this->seedUser('existing', 'existing@example.com', 'Password1');

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'existing', 'email' => 'different@example.com', 'password' => 'Password1']);
        $this->call(fn() => $controller->register());

        $count = $this->pdo->query("SELECT COUNT(*) FROM user WHERE username = 'existing'")->fetchColumn();
        $this->assertEquals(1, $count);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->seedUser('existing', 'existing@example.com', 'Password1');

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'different', 'email' => 'existing@example.com', 'password' => 'Password1']);
        $this->call(fn() => $controller->register());

        $count = $this->pdo->query("SELECT COUNT(*) FROM user WHERE email = 'existing@example.com'")->fetchColumn();
        $this->assertEquals(1, $count);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testRegisterRejectsEmptyInput(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput([]);
        $this->call(fn() => $controller->register());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testRegisterPasswordIsHashed(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['username' => 'hashtest', 'email' => 'hash@example.com', 'password' => 'Plainpass1']);
        $this->call(fn() => $controller->register());

        $user = $this->pdo->query("SELECT password_hash FROM user WHERE username = 'hashtest'")->fetch();
        $this->assertNotNull($user);
        $this->assertNotEquals('plainpassword', $user->password_hash);
        $this->assertTrue(password_verify('Plainpass1', $user->password_hash));
    }

    // ─── Login ───────────────────────────────────────────────────────────────

    public function testLoginWithValidCredentials(): void
    {
        $userId     = $this->seedUser('testuser', 't@example.com', 'Secret12');
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['identifier' => 'testuser', 'password' => 'Secret12']);
        $this->call(fn() => $controller->login());

        $this->assertEquals($userId, (int) $_SESSION['user_id']);
        $this->assertEquals('testuser', $_SESSION['username']);
        $this->assertArrayHasKey('user', $controller->capturedResponse);
        $this->assertEquals($userId, $controller->capturedResponse['user']['id']);
    }

    public function testLoginWithEmailAsIdentifier(): void
    {
        $userId     = $this->seedUser('emailuser', 'email@example.com', 'Pass1234');
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['identifier' => 'email@example.com', 'password' => 'Pass1234']);
        $this->call(fn() => $controller->login());

        $this->assertEquals($userId, (int) $_SESSION['user_id']);
        $this->assertArrayHasKey('user', $controller->capturedResponse);
    }

    public function testLoginWithWrongPassword(): void
    {
        $this->seedUser('user', 'u@example.com', 'Correct1');
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['identifier' => 'user', 'password' => 'Wrongpas1']);
        $this->call(fn() => $controller->login());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLoginWithNonExistentUser(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['identifier' => 'nobody', 'password' => 'Pass1234']);
        $this->call(fn() => $controller->login());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testLoginRejectsEmptyCredentials(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['identifier' => '', 'password' => '']);
        $this->call(fn() => $controller->login());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testLoginWithDisabledUser(): void
    {
        $this->seedUser('disabled', 'd@example.com', 'Secret12', 'user', false);
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['identifier' => 'disabled', 'password' => 'Secret12']);
        $this->call(fn() => $controller->login());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLoginSetsSessionOnSuccess(): void
    {
        $userId     = $this->seedUser('sesstest', 'ss@example.com', 'Pass1234');
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['identifier' => 'sesstest', 'password' => 'Pass1234']);
        $this->call(fn() => $controller->login());

        $this->assertEquals($userId, (int) $_SESSION['user_id']);
        $this->assertEquals('sesstest', $_SESSION['username']);
        $this->assertEquals('ss@example.com', $_SESSION['email']);
    }

    // ─── Me ──────────────────────────────────────────────────────────────────

    public function testMeReturnsCurrentUser(): void
    {
        $userId = $this->seedUser('member', 'm@example.com', 'Pass1234');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $this->call(fn() => $controller->me());

        $this->assertEquals($userId, $controller->capturedResponse['user']['id']);
        $this->assertEquals('member', $controller->capturedResponse['user']['username']);
    }

    public function testMeReturns401WhenNotLoggedIn(): void
    {
        $controller = $this->makeTestableAuthController();
        $this->call(fn() => $controller->me());

        $this->assertEquals(401, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testMeReturnsRoleFromSession(): void
    {
        $userId = $this->seedUser('admin', 'a@example.com', 'Pass1234', 'admin');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $this->call(fn() => $controller->me());

        $this->assertEquals('admin', $controller->capturedResponse['user']['role']);
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    public function testLogoutDestroysSession(): void
    {
        $userId = $this->seedUser('logouttest', 'l@test.com', 'Pass1234');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $this->call(fn() => $controller->logout());

        $this->assertTrue($controller->capturedResponse['success']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    // ─── UpdateProfile ───────────────────────────────────────────────────────

    public function testUpdateProfileChangesName(): void
    {
        $userId = $this->seedUser('editor', 'e@test.com', 'Pass1234');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['name' => 'Editor Updated']);
        $this->call(fn() => $controller->updateProfile());

        $this->assertEquals('Editor Updated', $controller->capturedResponse['user']['name']);

        $dbUser = $this->pdo->query("SELECT name FROM user WHERE id = $userId")->fetch();
        $this->assertEquals('Editor Updated', $dbUser->name);
    }

    public function testUpdateProfileRejectsEmptyName(): void
    {
        $userId = $this->seedUser('up', 'up@test.com', 'Pass1234');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['name' => '']);
        $this->call(fn() => $controller->updateProfile());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testUpdateProfileRejectsTooLongName(): void
    {
        $userId = $this->seedUser('uplong', 'upl@test.com', 'Pass1234');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['name' => str_repeat('x', 101)]);
        $this->call(fn() => $controller->updateProfile());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testUpdateProfileUpdatesSessionName(): void
    {
        $userId = $this->seedUser('sessupdate', 'su@test.com', 'Pass1234');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['name' => 'New Display Name']);
        $this->call(fn() => $controller->updateProfile());

        $this->assertEquals('New Display Name', $_SESSION['name']);
    }

    // ─── ChangePassword ──────────────────────────────────────────────────────

    public function testChangePasswordValidatesCurrent(): void
    {
        $userId  = $this->seedUser('changer', 'ch@test.com', 'Current1');
        $this->loginAs($userId);
        $oldHash = $this->pdo->query("SELECT password_hash FROM user WHERE id = $userId")->fetch()->password_hash;

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['current_password' => 'Wrongpas1', 'new_password' => 'Newpass12']);
        $this->call(fn() => $controller->changePassword());

        $newHash = $this->pdo->query("SELECT password_hash FROM user WHERE id = $userId")->fetch()->password_hash;
        $this->assertEquals($oldHash, $newHash);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testChangePasswordSuccess(): void
    {
        $userId = $this->seedUser('cp', 'cp@test.com', 'Oldpass12');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['current_password' => 'Oldpass12', 'new_password' => 'Newpass12']);
        $this->call(fn() => $controller->changePassword());

        $this->assertTrue($controller->capturedResponse['success']);
        $user = $this->pdo->query("SELECT password_hash FROM user WHERE id = $userId")->fetch();
        $this->assertTrue(password_verify('Newpass12', $user->password_hash));
    }

    public function testChangePasswordRejectsShortNewPassword(): void
    {
        $userId = $this->seedUser('cps', 'cps@test.com', 'Oldpass12');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['current_password' => 'Oldpass12', 'new_password' => 'short']);
        $this->call(fn() => $controller->changePassword());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
        $user = $this->pdo->query("SELECT password_hash FROM user WHERE id = $userId")->fetch();
        $this->assertTrue(password_verify('Oldpass12', $user->password_hash));
    }

    public function testUpdateProfileRequiresAuth(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['name' => 'Should Fail']);
        $this->call(fn() => $controller->updateProfile(), $controller);

        $this->assertEquals(401, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testChangePasswordRequiresAuth(): void
    {
        $controller = $this->makeTestableAuthController();
        $controller->setInput(['current_password' => 'secret', 'new_password' => 'Newsecre1']);
        $this->call(fn() => $controller->changePassword(), $controller);

        $this->assertEquals(401, $controller->capturedStatus);
        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }

    public function testMeReturnsDefaultRoleWhenRoleNotSetInSession(): void
    {
        $_SESSION['user_id'] = 99;
        $_SESSION['username'] = 'norole';
        $_SESSION['email'] = 'norole@example.com';
        unset($_SESSION['role']);

        $controller = $this->makeTestableAuthController();
        $this->call(fn() => $controller->me());

        $this->assertEquals(200, $controller->capturedStatus);
        $this->assertEquals('user', $controller->capturedResponse['user']['role']);
    }

    public function testChangePasswordRejectsEmptyFields(): void
    {
        $userId = $this->seedUser('cpempty', 'cpe@test.com', 'Oldpass12');
        $this->loginAs($userId);

        $controller = $this->makeTestableAuthController();
        $controller->setInput(['current_password' => '', 'new_password' => '']);
        $this->call(fn() => $controller->changePassword());

        $this->assertArrayHasKey('error', $controller->capturedResponse);
    }
}
