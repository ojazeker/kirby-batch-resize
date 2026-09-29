<?php

use Kirby\Exception\PermissionException;

return function ($kirby) {
    return [
        'label' => 'Clean Up',
        'icon' => 'refresh',
        'menu' => $kirby->user()?->isAdmin() === true,
        'link' => 'clean-up',
        'views' => [
            [
                'pattern' => 'clean-up',
                'action' => function () use ($kirby) {
                    if ($kirby->user()?->isAdmin() !== true) {
                        throw new PermissionException('Only administrators can resize images.');
                    }

                    return [
                        'component' => 'k-clean-up-view',
                        'title' => 'Clean Up',
                    ];
                },
            ],
        ],
    ];
};