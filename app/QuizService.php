<?php
declare(strict_types=1);

final class QuizService
{
    public function __construct(private PDO $db) {}

    public function owned(int $id, int $userId, bool $lock = false): array
    {
        $stmt = $this->db->prepare('SELECT * FROM quizzes WHERE id = ? AND user_id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: throw new HttpError(404, 'Quiz tidak ditemukan.');
    }

    public function dashboard(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND finished_at IS NOT NULL) AS participant_count
            FROM quizzes q WHERE user_id = ? ORDER BY updated_at DESC, id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function questions(int $quizId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY position, id');
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll();
        $options = $this->db->prepare('SELECT label, position, is_correct FROM answer_options WHERE question_id = ? ORDER BY position');
        foreach ($questions as &$question) {
            $options->execute([$question['id']]);
            $rows = $options->fetchAll();
            $question['options'] = array_column($rows, 'label');
            $question['correct'] = (int) array_search(1, array_column($rows, 'is_correct'));
            $question['id'] = (int) $question['id'];
            $question['revision'] = (int) $question['revision'];
        }
        return $questions;
    }

    public static function validate(array $input): array
    {
        $title = Input::text($input['title'] ?? null, 160);
        $description = Input::text($input['description'] ?? '', 5000, false);
        $level = $input['rage_level'] ?? 'mild';
        if (!in_array($level, ['mild', 'annoying', 'hell'], true)) {
            throw new HttpError(422, 'Rage Level tidak valid.');
        }
        $timer = $input['timer_minutes'] ?? null;
        if ($timer !== null) $timer = Input::integer($timer, 1, 30, true);
        $questions = $input['questions'] ?? [];
        if (!is_array($questions) || !array_is_list($questions) || count($questions) > 30) {
            throw new HttpError(422, 'Satu quiz maksimal memiliki 30 pertanyaan.');
        }
        $clean = [];
        $ids = [];
        foreach ($questions as $index => $question) {
            if (!is_array($question)) {
                throw new HttpError(422, 'Format pertanyaan tidak valid.');
            }
            $prompt = Input::text($question['prompt'] ?? null, 3000);
            $type = $question['type'] ?? '';
            $options = $question['options'] ?? [];
            $correct = $question['correct'] ?? null;
            if (!in_array($type, ['multiple_choice', 'true_false'], true)) {
                throw new HttpError(422, 'Soal ' . ($index + 1) . ': isi pertanyaan dan pilih tipe yang valid. Maksimal 3.000 karakter.');
            }
            if (!is_array($options) || !array_is_list($options) || count($options) < 2 || count($options) > 4) {
                throw new HttpError(422, 'Soal ' . ($index + 1) . ': gunakan 2–4 pilihan.');
            }
            foreach ($options as &$option) {
                if (!is_string($option) || trim($option) === '' || mb_strlen($option) > 500) {
                    throw new HttpError(422, 'Setiap pilihan wajib diisi, maksimal 500 karakter.');
                }
                $option = Input::text($option, 500);
            }
            unset($option);
            if ($type === 'true_false' && $options !== ['True', 'False']) {
                throw new HttpError(422, 'True / False harus memiliki pilihan True dan False.');
            }
            if (!is_int($correct) || !array_key_exists($correct, $options)) {
                throw new HttpError(422, 'Tentukan satu jawaban benar untuk setiap soal.');
            }
            $id = Input::integer($question['id'] ?? 0);
            if ($id > 0 && isset($ids[$id])) {
                throw new HttpError(422, 'Pertanyaan duplikat tidak valid.');
            }
            $ids[$id] = true;
            $clean[] = ['id' => $id, 'prompt' => $prompt, 'type' => $type, 'options' => $options, 'correct' => $correct];
        }
        return ['title' => $title, 'description' => $description, 'rage_level' => $level,
            'timer_minutes' => $timer === null ? null : (int) $timer, 'questions' => $clean];
    }

    public function save(int $userId, ?int $id, array $input): int
    {
        $data = self::validate($input);
        if ($id) {
            $this->owned($id, $userId);
            // An already expired attempt must finish against the content at expiry,
            // before a later creator edit can change its timer or answer key.
            (new AttemptService($this->db, $this))->finalizeExpired($id);
        }
        $this->db->beginTransaction();
        try {
            if ($id) {
                $quiz = $this->owned($id, $userId, true);
                if (Input::integer($input['revision'] ?? 0) !== (int) $quiz['revision']) {
                    throw new HttpError(409, 'Quiz berubah di tab lain. Muat ulang sebelum mengedit kembali.');
                }
                if ($quiz['is_published'] && count($data['questions']) === 0) {
                    throw new HttpError(422, 'Quiz yang dipublish wajib memiliki minimal satu soal.');
                }
                if (!$data['questions']) {
                    $active = $this->db->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND finished_at IS NULL');
                    $active->execute([$id]);
                    if ((int) $active->fetchColumn() > 0) {
                        throw new HttpError(422, 'Pertahankan minimal satu soal selama masih ada attempt yang belum selesai.');
                    }
                }
                $stmt = $this->db->prepare('UPDATE quizzes SET title=?, description=?, rage_level=?, timer_minutes=?, revision=revision+1 WHERE id=?');
                $stmt->execute([$data['title'], $data['description'], $data['rage_level'], $data['timer_minutes'], $id]);
            } else {
                $stmt = $this->db->prepare('INSERT INTO quizzes (user_id, public_id, title, description, rage_level, timer_minutes) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, bin2hex(random_bytes(16)), $data['title'], $data['description'], $data['rage_level'], $data['timer_minutes']]);
                $id = (int) $this->db->lastInsertId();
            }
            $existing = array_column($this->questions($id), null, 'id');
            $kept = [];
            foreach ($data['questions'] as $position => $question) {
                $qid = $question['id'];
                $changed = true;
                if ($qid > 0) {
                    if (!isset($existing[$qid])) {
                        throw new HttpError(422, 'Pertanyaan bukan milik quiz ini.');
                    }
                    $old = $existing[$qid];
                    $changed = $old['prompt'] !== $question['prompt'] || $old['type'] !== $question['type']
                        || $old['options'] !== $question['options'] || $old['correct'] !== $question['correct'];
                    $stmt = $this->db->prepare('UPDATE questions SET prompt=?, type=?, position=?, revision=revision+? WHERE id=?');
                    $stmt->execute([$question['prompt'], $question['type'], $position, (int) $changed, $qid]);
                } else {
                    $stmt = $this->db->prepare('INSERT INTO questions (quiz_id, prompt, type, position) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$id, $question['prompt'], $question['type'], $position]);
                    $qid = (int) $this->db->lastInsertId();
                }
                $kept[] = $qid;
                if ($changed) {
                    $this->db->prepare('DELETE FROM answer_options WHERE question_id=?')->execute([$qid]);
                    $insert = $this->db->prepare('INSERT INTO answer_options (question_id, label, position, is_correct) VALUES (?, ?, ?, ?)');
                    foreach ($question['options'] as $optionPosition => $label) {
                        $insert->execute([$qid, $label, $optionPosition, (int) ($optionPosition === $question['correct'])]);
                    }
                }
            }
            foreach (array_keys($existing) as $oldId) {
                if (!in_array($oldId, $kept, true)) {
                    $this->db->prepare('DELETE FROM questions WHERE id=?')->execute([$oldId]);
                }
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function publish(int $id, int $userId, bool $publish): void
    {
        $this->db->beginTransaction();
        try {
            $this->owned($id, $userId, true);
            if ($publish && !$this->questions($id)) {
                throw new HttpError(422, 'Tambahkan minimal satu pertanyaan lengkap sebelum publish.');
            }
            $this->db->prepare('UPDATE quizzes SET is_published=?, revision=revision+1 WHERE id=?')->execute([(int) $publish, $id]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id, int $userId): void
    {
        $this->owned($id, $userId);
        $this->db->prepare('DELETE FROM quizzes WHERE id=? AND user_id=?')->execute([$id, $userId]);
    }

    public function publicQuiz(string $publicId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM quizzes WHERE public_id=? AND is_published=1');
        $stmt->execute([$publicId]);
        return $stmt->fetch() ?: throw new HttpError(404, 'Quiz tidak tersedia. Creator mungkin belum publish atau sudah unpublish quiz ini.');
    }
}
