<?php

namespace Kirby\Exception {
    class PermissionException extends \Exception
    {
    }
}

namespace {
    require_once __DIR__ . '/../lib/ResizeService.php';

    class PanelTestFile
    {
        public array $manipulations = [];

        public function id(): string { return 'image.jpg'; }
        public function template(): string { return 'image'; }
        public function extension(): string { return 'jpg'; }
        public function width(): int { return $this->manipulations ? 800 : 1200; }
        public function height(): int { return 600; }
        public function isResizable(): bool { return true; }
        public function blueprint(): object
        {
            return new class {
                public function create(): array { return ['width' => 800]; }
            };
        }
        public function manipulate(array $create): void { $this->manipulations[] = $create; }
    }

    $file = new PanelTestFile();
    $site = new class($file) {
        public function __construct(private $file) {}
        public function index(bool $all): array { return []; }
        public function files(): array { return [$this->file]; }
    };
    $admin = new class($site) {
        public function __construct(private $site) {}
        public function site() { return $this->site; }
        public function user(): object { return new class { public function isAdmin(): bool { return true; } }; }
    };
    $guest = new class($site) {
        public function __construct(private $site) {}
        public function site() { return $this->site; }
        public function user() { return null; }
    };

    $area = require __DIR__ . '/../config/area.php';
    if ($area($admin)['menu'] !== true || $area($guest)['menu'] !== false) {
        throw new \RuntimeException('Clean Up menu visibility is incorrect');
    }
    try {
        $area($guest)['views'][0]['action']();
        throw new \RuntimeException('Guest could open the Panel area');
    } catch (\Kirby\Exception\PermissionException $error) {
    }

    $routes = require __DIR__ . '/../config/api.php';
    $context = new class($admin) {
        public function __construct(private $kirby) {}
        public function kirby() { return $this->kirby; }
        public function requestQuery(string $key) { return null; }
        public function requestBody(): array { return ['offset' => 0, 'limit' => 1]; }
    };
    $preview = $routes[0]['action']->call($context);
    if (array_column($preview['pending'], 'id') !== ['image.jpg']) {
        throw new \RuntimeException('Admin preview did not return the pending image');
    }
    $batch = $routes[1]['action']->call($context);
    if (array_column($batch['processed'], 'id') !== ['image.jpg'] || $batch['nextOffset'] !== null || $file->manipulations !== [['width' => 800]]) {
        throw new \RuntimeException('Admin batch did not process the pending image');
    }

    $blocked = new class($guest) {
        public function __construct(private $kirby) {}
        public function kirby() { return $this->kirby; }
        public function requestQuery(string $key) { return null; }
        public function requestBody(): array { return ['offset' => 0, 'limit' => 1]; }
    };
    foreach ($routes as $route) {
        try {
            $route['action']->call($blocked);
            throw new \RuntimeException('Guest could call ' . $route['pattern']);
        } catch (\Kirby\Exception\PermissionException $error) {
        }
    }

    echo "Panel access and API tests passed\n";
}