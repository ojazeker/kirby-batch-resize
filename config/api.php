<?php

use Allsizes\BatchResize\ResizeService;
use Kirby\Exception\PermissionException;

$serviceFor = static function ($context, $forceName): ResizeService {
    if ($context->kirby()->user()?->isAdmin() !== true) {
        throw new PermissionException('Only administrators can resize images.');
    }
    if ($forceName !== null && $forceName !== '' && (!is_string($forceName) || !preg_match('/^[a-z0-9_-]+$/i', $forceName))) {
        throw new InvalidArgumentException('Invalid file blueprint name.');
    }

    set_time_limit(0);
    ini_set('memory_limit', '512M');

    return new ResizeService($context->kirby(), $forceName ?: null);
};

return [
    [
        'pattern' => 'clean-up/preview',
        'method' => 'GET',
        'action' => function () use ($serviceFor) {
            $service = $serviceFor($this, $this->requestQuery('force'));
            return $service->scan($service->files());
        },
    ],
    [
        'pattern' => 'clean-up/batch',
        'method' => 'POST',
        'action' => function () use ($serviceFor) {
            $body = $this->requestBody();
            if (!is_array($body)) {
                throw new InvalidArgumentException('Invalid batch request.');
            }
            $service = $serviceFor($this, $body['force'] ?? null);
            $offset = max(0, (int)($body['offset'] ?? 0));
            $limit = max(1, min(100, (int)($body['limit'] ?? 10)));
            return $service->process($service->files(), $offset, $limit);
        },
    ],
];