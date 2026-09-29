<?php

require_once __DIR__ . '/../lib/ResizeService.php';

use Allsizes\BatchResize\ResizeService;

class FakeBlueprint
{
    public function __construct(private array $create)
    {
    }

    public function create(): array
    {
        return $this->create;
    }
}

class FakeFile
{
    public array $manipulations = [];
    public bool $fails = false;

    public function __construct(private string $name, private array $create, private int $size, private bool $resizable = true)
    {
    }

    public function id(): string { return $this->name; }
    public function template(): string { return 'image'; }
    public function blueprint(): FakeBlueprint { return new FakeBlueprint($this->create); }
    public function extension(): string { return 'jpg'; }
    public function width(): int { return $this->size; }
    public function height(): int { return $this->size; }
    public function isResizable(): bool { return $this->resizable; }

    public function manipulate(array $create): void
    {
        if ($this->fails) {
            throw new RuntimeException('Resize failed');
        }
        $this->manipulations[] = $create;
        $this->size = $create['width'];
    }
}

$create = ['width' => 800];
$first = new FakeFile('first.jpg', $create, 1200);
$ignored = new FakeFile('ignored.txt', [], 100, false);
$withinLimits = new FakeFile('small.jpg', $create, 400);
$missingSettings = new FakeFile('missing.jpg', [], 1200);
$failure = new FakeFile('failure.jpg', $create, 1200);
$failure->fails = true;
$last = new FakeFile('last.jpg', $create, 1300);

$page = new class([$first, $ignored, $withinLimits, $missingSettings, $failure]) {
    public function __construct(private array $files) {}
    public function files(): array { return $this->files; }
};
$site = new class($page, $last) {
    public function __construct(private $page, private $file) {}
    public function index(bool $all): array { return [$this->page]; }
    public function files(): array { return [$this->file]; }
};
$kirby = new class($site) {
    public function __construct(private $site) {}
    public function site() { return $this->site; }
};

$service = new ResizeService($kirby, null);
$files = $service->files();
if (count($files) !== 5) {
    throw new RuntimeException('File collection should exclude non-resizable files');
}

$scan = $service->scan($files);
if (array_column($scan['pending'], 'id') !== ['first.jpg', 'failure.jpg', 'last.jpg'] || count($scan['skip']) !== 2) {
    throw new RuntimeException('Dry run should list pending and skipped images');
}

$batch = $service->process($files, 0, 1);
if (array_column($batch['processed'], 'id') !== ['first.jpg'] || $batch['nextOffset'] !== 1 || $first->manipulations !== [$create]) {
    throw new RuntimeException('First batch should stop after one pending image');
}

$batch = $service->process($files, $batch['nextOffset'], 1);
if (array_column($batch['errors'], 'id') !== ['failure.jpg'] || $batch['nextOffset'] !== 4) {
    throw new RuntimeException('Failed images should count towards the batch limit');
}

$batch = $service->process($files, $batch['nextOffset'], 1);
if (array_column($batch['processed'], 'id') !== ['last.jpg'] || $batch['nextOffset'] !== null) {
    throw new RuntimeException('Final batch should finish without a next offset');
}

echo "Resize service tests passed\n";