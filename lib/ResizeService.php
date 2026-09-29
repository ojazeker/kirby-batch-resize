<?php

namespace Allsizes\BatchResize;

use Kirby\Cms\FileBlueprint;

class ResizeService
{
    private $kirby;
    private $forceName;
    private $forceCreate;

    public function __construct($kirby, $forceName)
    {
        $this->kirby = $kirby;
        $this->forceName = $forceName;
        $this->forceCreate = null;

        if ($forceName) {
            try {
                $blueprint = FileBlueprint::factory('files/' . $forceName, 'files/default', $kirby->site());
                $this->forceCreate = $blueprint->create() ?: null;
            } catch (\Exception $e) {
            }
        }
    }

    public function files(): array
    {
        $files = [];
        foreach ($this->kirby->site()->index(true) as $page) {
            foreach ($page->files() as $file) {
                if ($file->isResizable()) {
                    $files[] = $file;
                }
            }
        }
        foreach ($this->kirby->site()->files() as $file) {
            if ($file->isResizable()) {
                $files[] = $file;
            }
        }
        return $files;
    }

    public function createFor($file): ?array
    {
        $template = $file->template() ?: 'default';
        $create = $file->blueprint()->create();
        if (empty($create) && $this->forceCreate && $template === 'default') {
            return $this->forceCreate;
        }
        return $create ?: null;
    }

    public function reasonsFor($file, array $create): array
    {
        $reasons = [];
        $fmt = $create['format'] ?? null;
        if ($fmt && $file->extension() !== $fmt) {
            $reasons[] = 'Convert ' . $file->extension() . ' → ' . $fmt;
        }
        $w = $create['width'] ?? null;
        $h = $create['height'] ?? null;
        if ($w || $h) {
            try {
                $fw = $file->width();
                $fh = $file->height();
                if ($w && $fw > $w) $reasons[] = 'Width ' . $fw . ' → max ' . $w;
                if ($h && $fh > $h) $reasons[] = 'Height ' . $fh . ' → max ' . $h;
            } catch (\Exception $e) {
                $reasons[] = 'Resize (could not read dimensions)';
            }
        }
        return $reasons;
    }

    public function scan(array $files): array
    {
        $pending = [];
        $skip = [];
        foreach ($files as $file) {
            $create = $this->createFor($file);
            if (!$create) {
                $template = $file->template() ?: 'default';
                $skip[] = ['id' => $file->id(), 'reason' => 'No create settings (' . $template . ')'];
                continue;
            }
            $reasons = $this->reasonsFor($file, $create);
            if (empty($reasons)) {
                try {
                    $skip[] = ['id' => $file->id(), 'reason' => 'Already within limits (' . $file->width() . '×' . $file->height() . ' ' . $file->extension() . ')'];
                } catch (\Exception $e) {
                    $skip[] = ['id' => $file->id(), 'reason' => 'Already within limits'];
                }
                continue;
            }
            try {
                $current = $file->width() . '×' . $file->height() . ' ' . $file->extension();
            } catch (\Exception $e) {
                $current = $file->extension();
            }
            $pending[] = [
                'id' => $file->id(),
                'template' => ($file->template() ?: 'default') . ($this->forceCreate && !$file->template() ? ' (forced:' . $this->forceName . ')' : ''),
                'current' => $current,
                'actions' => implode(', ', $reasons),
            ];
        }
        return ['pending' => $pending, 'skip' => $skip];
    }

    public function process(array $files, int $offset, int $limit): array
    {
        $processed = [];
        $errors = [];
        $nextOffset = null;
        $fileIndex = 0;
        $processCount = 0;

        foreach ($files as $file) {
            if ($fileIndex < $offset) {
                $fileIndex++;
                continue;
            }
            $create = $this->createFor($file);
            if (!$create) {
                $fileIndex++;
                continue;
            }
            $reasons = $this->reasonsFor($file, $create);
            if (empty($reasons)) {
                $fileIndex++;
                continue;
            }
            try {
                $file->manipulate($create);
                $processed[] = [
                    'id' => $file->id(),
                    'template' => $file->template() ?: 'default',
                    'actions' => implode(', ', $reasons),
                ];
            } catch (\Exception $e) {
                $errors[] = ['id' => $file->id(), 'error' => $e->getMessage()];
            }
            $fileIndex++;
            $processCount++;

            if ($processCount >= $limit) {
                $nextOffset = $fileIndex;
                for ($i = $fileIndex; $i < count($files); $i++) {
                    $create = $this->createFor($files[$i]);
                    if ($create && $this->reasonsFor($files[$i], $create)) {
                        break;
                    }
                }
                if ($i === count($files)) {
                    $nextOffset = null;
                }
                break;
            }
        }
        return ['processed' => $processed, 'errors' => $errors, 'nextOffset' => $nextOffset];
    }
}