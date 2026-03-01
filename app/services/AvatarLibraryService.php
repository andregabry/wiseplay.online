<?php

declare(strict_types=1);

namespace App\services;

final class AvatarLibraryService
{
    private const DEFAULT_FALLBACK = '/assets/img/placeholders/image-placeholder.svg';
    private const SECTION_MAP = [
        'A-D' => ['A', 'B', 'C', 'D'],
        'E-H' => ['E', 'F', 'G', 'H'],
        'I-L' => ['I', 'J', 'K', 'L'],
        'M-P' => ['M', 'N', 'O', 'P'],
        'Q-T' => ['Q', 'R', 'S', 'T'],
        'U-Z' => ['U', 'V', 'W', 'X', 'Y', 'Z'],
        'Special' => [],
    ];

    /**
     * @return list<string>
     */
    public function profileAvatarOptions(): array
    {
        $paths = array_column($this->discoverProfileAvatars(), 'path');

        if ($paths === []) {
            return [self::DEFAULT_FALLBACK];
        }

        return $paths;
    }

    /**
     * @return list<array{label:string,items:list<array{name:string,path:string}>}>
     */
    public function profileAvatarSections(): array
    {
        $avatars = $this->discoverProfileAvatars();
        if ($avatars === []) {
            return [[
                'label' => 'Default',
                'items' => [[
                    'name' => 'Default avatar',
                    'path' => self::DEFAULT_FALLBACK,
                ]],
            ]];
        }

        $sections = [];
        foreach (self::SECTION_MAP as $label => $letters) {
            $sections[$label] = [];
        }

        foreach ($avatars as $avatar) {
            $name = $avatar['name'];
            $leading = strtoupper(substr($name, 0, 1));
            $target = 'Special';

            foreach (self::SECTION_MAP as $label => $letters) {
                if ($letters !== [] && in_array($leading, $letters, true)) {
                    $target = $label;
                    break;
                }
            }

            $sections[$target][] = $avatar;
        }

        $result = [];
        foreach ($sections as $label => $items) {
            if ($items === []) {
                continue;
            }
            $result[] = [
                'label' => $label,
                'items' => array_values($items),
            ];
        }

        return $result;
    }

    public function defaultProfileAvatar(): string
    {
        $options = $this->profileAvatarOptions();
        return $options[0] ?? self::DEFAULT_FALLBACK;
    }

    /**
     * @return list<array{name:string,path:string}>
     */
    private function discoverProfileAvatars(): array
    {
        $baseDir = BASE_PATH . '/public/assets/img/avatar-library';
        if (!is_dir($baseDir)) {
            return [];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
        $items = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $filename = (string) $file->getFilename();
            if (str_starts_with($filename, '.') || str_ends_with(strtolower($filename), '.css')) {
                continue;
            }

            $extension = strtolower((string) $file->getExtension());
            if (!in_array($extension, $allowed, true)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($baseDir) + 1));
            if ($relative === '') {
                continue;
            }

            $items[] = [
                'name' => $this->humanizeName(pathinfo($filename, PATHINFO_FILENAME)),
                'path' => '/assets/img/avatar-library/' . implode('/', array_map('rawurlencode', explode('/', $relative))),
            ];
        }

        usort($items, static function (array $left, array $right): int {
            return strcasecmp($left['name'], $right['name']);
        });

        $seen = [];
        $filtered = [];
        foreach ($items as $item) {
            if (isset($seen[$item['path']])) {
                continue;
            }
            $seen[$item['path']] = true;
            $filtered[] = $item;
        }

        return $filtered;
    }

    private function humanizeName(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;
        return trim($value);
    }
}
