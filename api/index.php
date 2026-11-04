<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const PATTERN_NAME = '/^[A-Za-z][A-Za-z.\'\- ]{1,49}$/D';
const PATTERN_EMAIL = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/D';
const PATTERN_MOBILE = '/^\+?[0-9]{10,15}$/D';
const PATTERN_PASSWORD = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,64}$/D';

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array
{
    $decoded = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($decoded) ? $decoded : [];
}

function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        fail('Method not allowed.', 405);
    }
}

function requireLogin(): array
{
    if (empty($_SESSION['user'])) {
        fail('Please log in to continue.', 401);
    }
    return $_SESSION['user'];
}

function sessionUser(array $row): array
{
    return ['id' => (int) $row['id'], 'fullName' => $row['full_name']];
}

function validationError(): string
{
    return 'Please check the highlighted fields and try again.';
}

function validateRegistration(array $in): array
{
    $errors = [];
    $fullName = trim((string) ($in['fullName'] ?? ''));
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $mobile = preg_replace('/[\s()-]/', '', (string) ($in['mobile'] ?? ''));
    $password = (string) ($in['password'] ?? '');

    if (!preg_match(PATTERN_NAME, $fullName)) $errors['fullName'] = 'Invalid full name';
    if (!preg_match(PATTERN_EMAIL, $email)) $errors['email'] = 'Invalid email';
    if (!preg_match(PATTERN_MOBILE, $mobile)) $errors['mobile'] = 'Invalid mobile number';
    if (!preg_match(PATTERN_PASSWORD, $password)) $errors['password'] = 'Weak password';

    return [$errors, compact('fullName', 'email', 'mobile', 'password')];
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'session':
            requireMethod('GET');
            respond($_SESSION['user'] ?? null);

        case 'register':
            requireMethod('POST');
            [$errors, $values] = validateRegistration(input());
            if ($errors) fail(validationError());

            $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$values['email']]);
            if ($exists->fetch()) fail('An account with this email already exists. Try logging in instead.', 409);

            $insert = db()->prepare('INSERT INTO users (full_name, email, mobile, password_hash) VALUES (?, ?, ?, ?)');
            $insert->execute([
                $values['fullName'],
                $values['email'],
                $values['mobile'],
                password_hash($values['password'], PASSWORD_DEFAULT),
            ]);
            $user = sessionUser(['id' => db()->lastInsertId(), 'full_name' => $values['fullName']]);
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            respond($user, 201);

        case 'login':
            requireMethod('POST');
            $in = input();
            $email = strtolower(trim((string) ($in['email'] ?? '')));
            $password = (string) ($in['password'] ?? '');
            if (!preg_match(PATTERN_EMAIL, $email) || $password === '') {
                fail('Please enter a valid email and password.');
            }

            $stmt = db()->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($password, $row['password_hash'])) {
                fail('Incorrect email or password.', 401);
            }

            session_regenerate_id(true);
            $_SESSION['user'] = sessionUser($row);
            respond($_SESSION['user']);

        case 'logout':
            requireMethod('POST');
            $_SESSION = [];
            session_destroy();
            respond(null);

        case 'comments':
            requireMethod('GET');
            $stmt = db()->query(
                'SELECT c.id, c.body, c.created_at AS createdAt, u.full_name AS fullName
                   FROM comments c
                   JOIN users u ON u.id = c.user_id
                  ORDER BY c.created_at DESC
                  LIMIT 100'
            );
            respond($stmt->fetchAll());

        case 'add_comment':
            requireMethod('POST');
            $user = requireLogin();
            $body = trim((string) (input()['body'] ?? ''));
            $length = mb_strlen($body);
            if ($length < 2 || $length > 500) fail('Comment must be between 2 and 500 characters.');

            $stmt = db()->prepare('INSERT INTO comments (user_id, body) VALUES (?, ?)');
            $stmt->execute([$user['id'], $body]);
            respond(['id' => (int) db()->lastInsertId(), 'body' => $body, 'fullName' => $user['fullName']], 201);

        case 'contact':
            requireMethod('POST');
            $in = input();
            $fullName = trim((string) ($in['fullName'] ?? ''));
            $email = strtolower(trim((string) ($in['email'] ?? '')));
            $message = trim((string) ($in['message'] ?? ''));
            $errors = [];
            if (!preg_match(PATTERN_NAME, $fullName)) $errors['fullName'] = 'Invalid name';
            if (!preg_match(PATTERN_EMAIL, $email)) $errors['email'] = 'Invalid email';
            $length = mb_strlen($message);
            if ($length < 10 || $length > 1000) $errors['message'] = 'Invalid message';
            if ($errors) fail(validationError());

            $stmt = db()->prepare('INSERT INTO contact_messages (full_name, email, message) VALUES (?, ?, ?)');
            $stmt->execute([$fullName, $email, $message]);
            respond(null, 201);

        default:
            fail('Unknown request.', 404);
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    fail('The server could not complete the request. Check that MySQL is running and the database is set up.', 500);
}
