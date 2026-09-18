<?php
declare(strict_types=1);

final class RageConfig
{
    public static function all(): array
    {
        $assets = RageAssets::load(require __DIR__ . '/../config/rage-assets.php', __DIR__ . '/../public');
        return [
            'levels' => [
                'mild' => ['interval' => 4, 'events' => ['taunt', 'loading', 'background']],
                'annoying' => ['interval' => 2, 'events' => ['taunt', 'countdown', 'moving', 'trap', 'loading', 'background']],
                'hell' => ['interval' => 1, 'events' => ['taunt', 'countdown', 'moving', 'trap', 'loading', 'background']],
            ],
            'taunts' => ['Yakin itu jawabanmu?', "Nahh fr you don't get this one 😭😭", 'U sure?', 'Jawabannya BENER tuh', 'Nuh uhh', "It's lowk right dawg"],
            'countdown_reveals' => [
                ['title' => 'chill, itu cuma prank ✌️', 'text' => 'GOTCHA.'],
                ['title' => 'eitsss jangan panik broo...', 'text' => 'SIKE!!!!'],
                ['title' => 'ihh becanda doang 😜', 'text' => 'YOU GOT PRANKED'],
                ['title' => 'eh maaf ya cuma prank 🙏', 'text' => 'GOT EM'],
                ['title' => 'prank doang kok.....', 'text' => 'RAGED?'],
                ['title' => 'emang kenapa kalo prank 😏', 'text' => 'MAD CUZ BAD'],
                ['title' => 'lowk cuma prank fr', 'text' => 'L + GOTCHA + RATIO'],
            ],
            'traps' => [
                ['prompt' => 'Are you single?', 'options' => ['Yes', 'No']],
                ['prompt' => 'Be honest, did you actually study for this?', 'options' => ['Yes', 'No']],
                ['prompt' => "Do you think you're doing well right now?", 'options' => ['Absolutely', 'Probably not']],
                ['prompt' => 'Which button looks more trustworthy?', 'options' => ['This one', 'The other one']],
                ['prompt' => 'Are you sure this is still part of the quiz?', 'options' => ['Yes', 'Wait... no?']],
                ['prompt' => 'Would you trust QuizHell with your score?', 'options' => ['Yes', 'Absolutely not']],
                ['prompt' => 'Did you just guess the last answer?', 'options' => ['Maybe', 'No bro trust me']],
            ],
            'images' => $assets['images'],
            'sounds' => $assets['sounds'],
        ];
    }
}
