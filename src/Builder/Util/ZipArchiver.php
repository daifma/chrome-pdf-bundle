<?php

namespace Daif\ChromePdfBundle\Builder\Util;

use Daif\ChromePdfBundle\Exception\RuntimeException;
use Daif\ChromePdfBundle\Formatter\PathHelper;

/**
 * @internal
 */
final class ZipArchiver
{
    /**
     * Writes the files into a ZIP archive, under their base name (suffixed with a counter on duplicates).
     *
     * @param list<string> $files
     */
    public static function create(array $files, string $target): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('The zip PHP extension is required to return several files as a ZIP archive.');
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new RuntimeException(\sprintf('Could not create the ZIP archive "%s".', $target));
        }

        $names = [];
        foreach ($files as $file) {
            $name = self::uniqueName(PathHelper::basename($file), $names);
            $names[$name] = true;

            if (!$zip->addFile($file, $name)) {
                $zip->close();

                throw new RuntimeException(\sprintf('Could not add "%s" to the ZIP archive.', $file));
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException(\sprintf('Could not write the ZIP archive "%s".', $target));
        }
    }

    /**
     * @param array<string, true> $names
     */
    private static function uniqueName(string $name, array $names): string
    {
        if (!isset($names[$name])) {
            return $name;
        }

        $extension = PathHelper::extension($name);
        $stem = '' !== $extension ? substr($name, 0, -\strlen($extension) - 1) : $name;
        $suffix = '' !== $extension ? '.'.$extension : '';

        $i = 2;
        while (isset($names[$stem.'_'.$i.$suffix])) {
            ++$i;
        }

        return $stem.'_'.$i.$suffix;
    }
}
