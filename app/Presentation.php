<?php
declare(strict_types=1);

final class Presentation
{
    public static function scoreClass(string $category): string
    {
        return ['Low' => 'hell', 'Mid' => 'annoying', 'Good' => 'mild'][$category];
    }
}
