<?php
declare(strict_types=1);

final class AttemptService
{
    public function __construct(private PDO $db, private QuizService $quizzes) {}

    public function start(string $publicId, string $nickname): string
    {
        $publicId = Input::token($publicId, 32);
        $nickname = Input::text($nickname, 60);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM quizzes WHERE public_id=? FOR UPDATE');
            $stmt->execute([$publicId]);
            $quiz = $stmt->fetch();
            if (!$quiz || !$quiz['is_published'] || !$this->quizzes->questions((int) $quiz['id'])) {
                throw new HttpError(404, 'Quiz belum tersedia untuk dikerjakan.');
            }
            $token = bin2hex(random_bytes(32));
            $this->db->prepare('INSERT INTO quiz_attempts (quiz_id, token, nickname) VALUES (?, ?, ?)')
                ->execute([$quiz['id'], $token, $nickname]);
            $this->db->commit();
            return $token;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    // All reads/writes that may finish an attempt lock quiz first, then attempt.
    private function locked(string $token, callable $callback): array
    {
        $token = Input::token($token);
        $stmt = $this->db->prepare('SELECT quiz_id FROM quiz_attempts WHERE token=?');
        $stmt->execute([$token]);
        $quizId = $stmt->fetchColumn();
        if (!$quizId) {
            throw new HttpError(404, 'Attempt tidak ditemukan.');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM quizzes WHERE id=? FOR UPDATE');
            $stmt->execute([$quizId]);
            $quiz = $stmt->fetch();
            $stmt = $this->db->prepare('SELECT * FROM quiz_attempts WHERE token=? FOR UPDATE');
            $stmt->execute([$token]);
            $attempt = $stmt->fetch();
            if (!$quiz || !$attempt) {
                throw new HttpError(404, 'Attempt tidak ditemukan.');
            }
            $result = $callback($quiz, $attempt);
            $this->db->commit();
            return $result;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public static function deadline(array $quiz, array $attempt): ?int
    {
        return $quiz['timer_minutes'] === null ? null
            : strtotime($attempt['started_at'] . ' UTC') + (int) $quiz['timer_minutes'] * 60;
    }

    private function ensureAvailable(array $quiz): void
    {
        if (!$quiz['is_published']) {
            throw new HttpError(403, 'Creator telah unpublish quiz ini. Pengerjaan tidak tersedia.');
        }
    }

    private function answers(int $attemptId): array
    {
        $stmt = $this->db->prepare('SELECT question_id, question_revision, selected_position FROM player_answers WHERE attempt_id=?');
        $stmt->execute([$attemptId]);
        return array_column($stmt->fetchAll(), null, 'question_id');
    }

    private function finish(array $quiz, array $attempt, array $questions, bool $expired): array
    {
        if ($attempt['finished_at'] !== null) {
            return $this->result($attempt);
        }
        if (!$questions) {
            throw new HttpError(409, 'Quiz belum memiliki soal.');
        }
        $answers = $this->answers((int) $attempt['id']);
        $correct = 0;
        $records = [];
        foreach ($questions as $question) {
            $answer = $answers[$question['id']] ?? null;
            $valid = $answer && (int) $answer['question_revision'] === $question['revision']
                && $answer['selected_position'] !== null && isset($question['options'][(int) $answer['selected_position']]);
            if (!$expired && !$valid) {
                throw new HttpError(422, 'Semua soal wajib dijawab sebelum submit. Quiz mungkin baru saja diperbarui creator.');
            }
            $isCorrect = $valid && (int) $answer['selected_position'] === $question['correct'];
            $correct += (int) $isCorrect;
            $records[] = [$attempt['id'], $question['id'], $question['revision'], $valid ? $answer['selected_position'] : null,
                $question['prompt'], (int) $isCorrect];
        }
        // Frozen per-question result records keep historical analytics valid after edits/deletes.
        $this->db->prepare('UPDATE player_answers SET counted=0 WHERE attempt_id=?')->execute([$attempt['id']]);
        $stmt = $this->db->prepare('INSERT INTO player_answers
            (attempt_id, question_id, question_revision, selected_position, question_prompt, is_correct, counted)
            VALUES (?, ?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE
            question_revision=VALUES(question_revision), selected_position=VALUES(selected_position),
            question_prompt=VALUES(question_prompt), is_correct=VALUES(is_correct), counted=1');
        foreach ($records as $record) {
            $stmt->execute($record);
        }
        $score = Scoring::calculate($correct, count($questions));
        $finishedAt = gmdate('Y-m-d H:i:s', $expired ? (self::deadline($quiz, $attempt) ?? time()) : time());
        $stmt = $this->db->prepare('UPDATE quiz_attempts SET finished_at=?, correct_count=?, question_count=?, score=?, category=? WHERE id=? AND finished_at IS NULL');
        $stmt->execute([$finishedAt, $correct, count($questions), $score['score'], $score['category'], $attempt['id']]);
        return $this->result(array_merge($attempt, $score, ['finished_at' => $finishedAt]));
    }

    private function result(array $attempt): array
    {
        return ['finished' => true, 'token' => $attempt['token'], 'nickname' => $attempt['nickname'],
            'score' => (int) $attempt['score'], 'category' => $attempt['category']];
    }

    private function state(array $quiz, array $attempt, array $questions): array
    {
        $answers = $this->answers((int) $attempt['id']);
        $safeQuestions = [];
        foreach ($questions as $question) {
            $answer = $answers[$question['id']] ?? null;
            $safeQuestions[] = ['id' => $question['id'], 'revision' => $question['revision'], 'prompt' => $question['prompt'],
                'type' => $question['type'], 'options' => $question['options'],
                'selected' => $answer && (int) $answer['question_revision'] === $question['revision'] && $answer['selected_position'] !== null
                    ? (int) $answer['selected_position'] : null];
        }
        return ['finished' => false, 'token' => $attempt['token'], 'nickname' => $attempt['nickname'],
            'title' => $quiz['title'], 'revision' => (int) $quiz['revision'], 'rageLevel' => $quiz['rage_level'],
            'deadline' => self::deadline($quiz, $attempt), 'serverTime' => time(), 'questions' => $safeQuestions];
    }

    public function getState(string $token): array
    {
        return $this->locked($token, function (array $quiz, array $attempt): array {
            if ($attempt['finished_at'] !== null) {
                return $this->result($attempt);
            }
            $this->ensureAvailable($quiz);
            $questions = $this->quizzes->questions((int) $quiz['id']);
            $deadline = self::deadline($quiz, $attempt);
            if ($deadline !== null && time() >= $deadline) {
                return $this->finish($quiz, $attempt, $questions, true);
            }
            return $this->state($quiz, $attempt, $questions);
        });
    }

    public function answer(string $token, int $revision, int $questionId, int $position): array
    {
        return $this->locked($token, function (array $quiz, array $attempt) use ($revision, $questionId, $position): array {
            if ($attempt['finished_at'] !== null) {
                return $this->result($attempt);
            }
            $this->ensureAvailable($quiz);
            $questions = $this->quizzes->questions((int) $quiz['id']);
            $deadline = self::deadline($quiz, $attempt);
            if ($deadline !== null && time() >= $deadline) {
                return $this->finish($quiz, $attempt, $questions, true);
            }
            if ($revision !== (int) $quiz['revision']) {
                return ['updated' => true] + $this->state($quiz, $attempt, $questions);
            }
            $byId = array_column($questions, null, 'id');
            $question = $byId[$questionId] ?? null;
            if (!$question || !array_key_exists($position, $question['options'])) {
                throw new HttpError(422, 'Pilihan jawaban tidak valid.');
            }
            $stmt = $this->db->prepare('INSERT INTO player_answers (attempt_id, question_id, question_revision, selected_position, answered_at)
                VALUES (?, ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE question_revision=VALUES(question_revision),
                selected_position=VALUES(selected_position), answered_at=UTC_TIMESTAMP()');
            $stmt->execute([$attempt['id'], $questionId, $question['revision'], $position]);
            return $this->state($quiz, $attempt, $questions);
        });
    }

    public function submit(string $token, int $revision): array
    {
        return $this->locked($token, function (array $quiz, array $attempt) use ($revision): array {
            if ($attempt['finished_at'] !== null) {
                return $this->result($attempt);
            }
            $this->ensureAvailable($quiz);
            $questions = $this->quizzes->questions((int) $quiz['id']);
            $deadline = self::deadline($quiz, $attempt);
            $expired = $deadline !== null && time() >= $deadline;
            if (!$expired && $revision !== (int) $quiz['revision']) {
                return ['updated' => true] + $this->state($quiz, $attempt, $questions);
            }
            return $this->finish($quiz, $attempt, $questions, $expired);
        });
    }

    public function finalizeExpired(int $quizId): void
    {
        $stmt = $this->db->prepare('SELECT a.token FROM quiz_attempts a JOIN quizzes q ON q.id=a.quiz_id
            WHERE a.quiz_id=? AND a.finished_at IS NULL AND q.timer_minutes IS NOT NULL
            AND UTC_TIMESTAMP() >= TIMESTAMPADD(MINUTE, q.timer_minutes, a.started_at)');
        $stmt->execute([$quizId]);
        foreach ($stmt->fetchAll() as $row) {
            $this->locked($row['token'], function (array $quiz, array $attempt): array {
                $deadline = self::deadline($quiz, $attempt);
                if ($attempt['finished_at'] === null && $deadline !== null && time() >= $deadline) {
                    return $this->finish($quiz, $attempt, $this->quizzes->questions((int) $quiz['id']), true);
                }
                return [];
            });
        }
    }

    public function analytics(int $quizId): array
    {
        $this->finalizeExpired($quizId);
        $stmt = $this->db->prepare('SELECT COUNT(*) AS starts, COUNT(finished_at) AS participants, AVG(score) AS average_score
            FROM quiz_attempts WHERE quiz_id=?');
        $stmt->execute([$quizId]);
        $stats = $stmt->fetch();
        $stats['completion_rate'] = $stats['starts'] ? round($stats['participants'] / $stats['starts'] * 100, 1) : 0;
        $stmt = $this->db->prepare('SELECT p.question_id, p.question_revision, MAX(p.question_prompt) AS prompt,
            COUNT(*) AS attempts, SUM(p.is_correct=0) AS wrong_count, AVG(p.is_correct=0)*100 AS wrong_rate
            FROM player_answers p JOIN quiz_attempts a ON a.id=p.attempt_id
            WHERE a.quiz_id=? AND a.finished_at IS NOT NULL AND p.counted=1
            GROUP BY p.question_id, p.question_revision ORDER BY wrong_rate DESC, attempts DESC, p.question_id ASC LIMIT 1');
        $stmt->execute([$quizId]);
        $stats['hardest'] = $stmt->fetch() ?: null;
        $stmt = $this->db->prepare('SELECT nickname, score, category, finished_at FROM quiz_attempts
            WHERE quiz_id=? AND finished_at IS NOT NULL ORDER BY finished_at DESC, id DESC');
        $stmt->execute([$quizId]);
        $stats['results'] = $stmt->fetchAll();
        return $stats;
    }
}
