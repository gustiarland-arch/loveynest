<?php

namespace App\Support;

/** The five games of the Arena: what they are called, how long they last and how many stars a result is worth. */
class ArenaGames
{
    /** 'race' games are played side by side on the same board; 'turn' games are played one move at a time. */
    public const GAMES = [
        'kartu' => ['title' => 'Kartu Kembar Kenangan', 'kind' => 'race', 'duration' => 120000, 'max' => 1000, 'stars' => [550, 800]],
        'puzzle' => ['title' => 'Puzzle Momen', 'kind' => 'race', 'duration' => 180000, 'max' => 1000, 'stars' => [550, 800]],
        'tangkap' => ['title' => 'Tangkap Hati', 'kind' => 'race', 'duration' => 45000, 'max' => 1500, 'stars' => [300, 600]],
        'ngobrol' => ['title' => 'Kartu Ngobrol', 'kind' => 'turn', 'duration' => 0, 'max' => 500, 'stars' => [5, 10]],
        'penalti' => ['title' => 'Adu Penalti', 'kind' => 'turn', 'duration' => 0, 'max' => 5, 'stars' => [2, 4]],
    ];

    /** Penalty kicks per player in one match. */
    public const KICKS_EACH = 5;

    public static function keys(): array
    {
        return array_keys(self::GAMES);
    }

    public static function has(string $game): bool
    {
        return isset(self::GAMES[$game]);
    }

    public static function title(string $game): string
    {
        return self::GAMES[$game]['title'] ?? $game;
    }

    public static function stars(string $game, int $score): int
    {
        [$two, $three] = self::GAMES[$game]['stars'];

        return $score >= $three ? 3 : ($score >= $two ? 2 : 1);
    }

    public static function clamp(string $game, int $score): int
    {
        return max(0, min($score, self::GAMES[$game]['max']));
    }
}
