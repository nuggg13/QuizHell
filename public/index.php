<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
$route = is_string($_GET['r'] ?? null) ? $_GET['r'] : 'home';
$isApi = str_starts_with($route, 'api/');
try {
    Security::enforceProduction($config);
    Auth::expire();
    $db = Database::connect($config);
    $limiter = new RateLimiter($db);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $auth = new Auth($db);
    $quizzes = new QuizService($db);
    $attempts = new AttemptService($db, $quizzes);
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new HttpError(405, 'Metode tidak didukung.');
    }
    if ($isApi) {
        $limiter->hit('api-ip', $ip, 3000, 60);
        if ($route === 'api/state' && $method === 'GET') {
            $token = Input::token($_GET['token'] ?? null);
            $limiter->hit('state-token', $token, 120, 60);
            json_response($attempts->getState($token));
        }
        if ($method !== 'POST') {
            throw new HttpError(405, 'Gunakan POST untuk tindakan ini.');
        }
        $raw = file_get_contents('php://input', false, null, 0, 1000001);
        if (strlen($raw) > 1000000) {
            throw new HttpError(413, 'Data terlalu besar.');
        }
        try {
            $input = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpError(422, 'Format data tidak valid.');
        }
        if (!is_array($input) || array_is_list($input)) {
            throw new HttpError(422, 'Format data tidak valid.');
        }
        verify_csrf($input);
        if ($route === 'api/quiz/save') {
            if (!isset($_SESSION['user']['id'])) {
                throw new HttpError(401, 'Silakan login kembali sebelum menyimpan.');
            }
            $limiter->hit('save-user', (string) $_SESSION['user']['id'], 60, 60);
            $quizId = ($input['id'] ?? null) === null ? null : Input::integer($input['id'], 1);
            $id = $quizzes->save((int) $_SESSION['user']['id'], $quizId, $input);
            json_response(['redirect' => url('builder', ['id' => $id])]);
        }
        if (!in_array($route, ['api/answer', 'api/submit'], true)) {
            throw new HttpError(404, 'Endpoint tidak ditemukan.');
        }
        $token = Input::token($input['token'] ?? null);
        $limiter->hit('write-token', $token, 120, 60);
        if ($route === 'api/answer') {
            foreach (['revision', 'questionId', 'position'] as $field) {
                if (!isset($input[$field]) || !is_int($input[$field])) {
                    throw new HttpError(422, 'Data jawaban tidak valid.');
                }
            }
            json_response($attempts->answer($token, Input::integer($input['revision'], 1),
                Input::integer($input['questionId'], 1), Input::integer($input['position'], 0, 3)));
        }
        if ($route === 'api/submit') {
            json_response($attempts->submit($token, Input::integer($input['revision'] ?? null, 1)));
        }
    }

    if ($method === 'POST') {
        $limiter->hit('post-ip', $ip, 300, 60);
        verify_csrf();
        if ($route === 'login' || $route === 'register') {
            try {
                $limiter->hit($route . '-ip', $ip, $route === 'login' ? 30 : 5, $route === 'login' ? 900 : 3600);
                $email = mb_strtolower(Input::text($_POST['email'] ?? null, 190));
                $password = Input::password($_POST['password'] ?? null);
                if ($route === 'login') $limiter->hit('login-email', $email, 10, 900);
                $auth->{$route}($email, $password);
                redirect('dashboard');
            } catch (HttpError $exception) {
                http_response_code($exception->status);
                view('auth', ['title' => $route === 'login' ? 'Login' : 'Register', 'mode' => $route,
                    'error' => $exception->getMessage(), 'email' => is_string($_POST['email'] ?? null) ? mb_substr($_POST['email'], 0, 190) : '']);
                exit;
            }
        }
        if ($route === 'logout') {
            Auth::logout();
            redirect('home');
        }
        if ($route === 'start') {
            $limiter->hit('start-ip', $ip, 20, 60);
            $publicId = Input::token($_POST['quiz'] ?? null, 32);
            try {
                $token = $attempts->start($publicId, Input::text($_POST['nickname'] ?? null, 60));
                redirect('play', ['token' => $token]);
            } catch (HttpError $exception) {
                if ($exception->status !== 422) {
                    throw $exception;
                }
                http_response_code(422);
                $quiz = $quizzes->publicQuiz($publicId);
                view('landing', ['title' => $quiz['title'], 'quiz' => $quiz,
                    'questionCount' => count($quizzes->questions((int) $quiz['id'])), 'error' => $exception->getMessage()]);
                exit;
            }
        }
        $userId = Auth::requireUser();
        $id = Input::integer($_POST['id'] ?? null, 1, PHP_INT_MAX, true);
        if ($route === 'publish' || $route === 'unpublish') {
            $quizzes->publish($id, $userId, $route === 'publish');
            flash($route === 'publish' ? 'Quiz dipublish. Link siap dibagikan.' : 'Quiz di-unpublish. Akses pengerjaan ditutup.');
            redirect('builder', ['id' => $id]);
        }
        if ($route === 'delete') {
            $quizzes->delete($id, $userId);
            flash('Quiz dan hasilnya sudah dihapus.');
            redirect('dashboard');
        }
        throw new HttpError(404, 'Halaman tidak ditemukan.');
    }

    switch ($route) {
        case 'home':
            view('home', ['title' => 'Quiz biasa. Pengalaman luar biasa ngeselin.']);
            break;
        case 'login':
        case 'register':
            if (isset($_SESSION['user'])) {
                redirect('dashboard');
            }
            view('auth', ['title' => $route === 'login' ? 'Login' : 'Register', 'mode' => $route]);
            break;
        case 'dashboard':
            $userId = Auth::requireUser();
            $limiter->hit('analytics-user', (string) $userId, 60, 60);
            $items = $quizzes->dashboard($userId);
            foreach ($items as $item) {
                $attempts->finalizeExpired((int) $item['id']);
            }
            view('dashboard', ['title' => 'Dashboard', 'quizzes' => $quizzes->dashboard($userId)]);
            break;
        case 'builder':
            $userId = Auth::requireUser();
            $id = Input::integer($_GET['id'] ?? 0, 0, PHP_INT_MAX, true);
            $quiz = $id ? $quizzes->owned($id, $userId) : null;
            view('builder', ['title' => $quiz ? 'Edit quiz' : 'Buat quiz', 'quiz' => $quiz,
                'questions' => $quiz ? $quizzes->questions($id) : []]);
            break;
        case 'results/export':
            $userId = Auth::requireUser();
            $limiter->hit('export-user', (string) $userId, 10, 60);
            $quiz = $quizzes->owned(Input::integer($_GET['id'] ?? null, 1, PHP_INT_MAX, true), $userId);
            ResultsExport::download($quiz, $attempts->analytics((int) $quiz['id']));
        case 'results':
            $userId = Auth::requireUser();
            $limiter->hit('analytics-user', (string) $userId, 60, 60);
            $quiz = $quizzes->owned(Input::integer($_GET['id'] ?? null, 1, PHP_INT_MAX, true), $userId);
            view('results', ['title' => 'Hasil & analytics', 'quiz' => $quiz, 'stats' => $attempts->analytics((int) $quiz['id'])]);
            break;
        case 'quiz':
            $quiz = $quizzes->publicQuiz(Input::token($_GET['q'] ?? null, 32));
            view('landing', ['title' => $quiz['title'], 'quiz' => $quiz, 'questionCount' => count($quizzes->questions((int) $quiz['id']))]);
            break;
        case 'play':
        case 'result':
            $limiter->hit('play-ip', $ip, 300, 60);
            $token = Input::token($_GET['token'] ?? null);
            $limiter->hit('state-token', $token, 120, 60);
            $state = $attempts->getState($token);
            if ($state['finished'] && $route !== 'result') {
                redirect('result', ['token' => $state['token']]);
            }
            if (!$state['finished'] && $route === 'result') {
                redirect('play', ['token' => $state['token']]);
            }
            view($state['finished'] ? 'result' : 'play', ['title' => $state['finished'] ? 'Hasil kamu' : $state['title'],
                'state' => $state, 'rageConfig' => RageConfig::all()]);
            break;
        default:
            throw new HttpError(404, 'Halaman tidak ditemukan.');
    }
} catch (Throwable $exception) {
    $status = $exception instanceof HttpError ? $exception->status : 500;
    $message = $exception instanceof HttpError ? $exception->getMessage()
        : 'Aplikasi belum dapat memproses permintaan. Periksa konfigurasi database atau coba kembali.';
    if (!$exception instanceof HttpError) {
        Security::logException($exception);
    }
    if ($isApi) {
        json_response(['error' => $message], $status);
    }
    http_response_code($status);
    view('error', ['title' => 'Oops', 'status' => $status, 'message' => $message]);
}
