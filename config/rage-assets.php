<?php
declare(strict_types=1);

// Semua path relatif terhadap public/, bukan root project.
// files: null = baca otomatis file langsung di directory (tanpa subfolder).
// files: ['nama.mp3', ...] = hanya gunakan nama file ini dari directory.
// files: [] = pool kosong. Gambar masih boleh memakai fallback jika diisi.
$defaultPictures = [
    'assets/images/judging-cat.svg',
    'assets/images/brain-offline.svg',
    'assets/images/this-is-fine.svg',
];

return [
    'images' => [
        'loading' => [
            'directory' => 'assets/images/rage/loading',
            'files' => null,
            'fallback' => $defaultPictures,
        ],
        'backgrounds' => [
            'directory' => 'assets/images/rage/backgrounds',
            'files' => null,
            'fallback' => $defaultPictures,
        ],
    ],
    'sounds' => [
        'fake_countdown' => [
            'directory' => 'assets/sounds/rage/fake-countdown',
            'files' => null,
        ],
        'moving_button' => [
            'directory' => 'assets/sounds/rage/moving-button',
            'files' => null,
        ],
        'trap_laugh' => [
            'directory' => 'assets/sounds/rage/trap-question',
            'files' => null,
        ],
        'result_low' => [
            'directory' => 'assets/sounds/rage/results/low',
            'files' => null,
        ],
        'result_mid' => [
            'directory' => 'assets/sounds/rage/results/mid',
            'files' => null,
        ],
        'result_good' => [
            'directory' => 'assets/sounds/rage/results/good',
            'files' => null,
        ],
    ],
];
