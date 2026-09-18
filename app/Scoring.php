<?php
declare(strict_types=1);

final class Scoring
{
    public static function calculate(int $correct, int $total): array
    {
        if ($total < 1 || $correct < 0 || $correct > $total) {
            throw new InvalidArgumentException('Invalid real question counts.');
        }
        $score = max(1, min(10, (int) round($correct / $total * 10, 0, PHP_ROUND_HALF_UP)));
        return ['score' => $score, 'category' => $score <= 4 ? 'Low' : ($score <= 7 ? 'Mid' : 'Good')];
    }
}
