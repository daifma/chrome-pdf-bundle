<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use PHPUnit\Framework\Assert;
use Symfony\Component\Process\Process;

/**
 * Reads PDF structures with the qpdf JSON output in assertions.
 */
final class PdfInspector
{
    public function __construct(private readonly string $qpdfBinary)
    {
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): Process
    {
        $process = new Process([$this->qpdfBinary, ...$arguments]);
        $process->run();

        return $process;
    }

    /**
     * @param list<string> $arguments
     *
     * @return array<string, mixed>
     */
    public function json(array $arguments): array
    {
        $process = $this->run($arguments);
        Assert::assertContains($process->getExitCode(), [0, 3], $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * Number of XObjects drawn ("/Name Do") by the content streams of each page: an overlay or underlay adds some.
     *
     * @return list<int>
     */
    public function drawnXObjects(string $file): array
    {
        $document = $this->json(['--json=2', '--json-key=pages', '--json-key=qpdf', '--json-stream-data=inline', '--decode-level=generalized', $file]);
        $objects = $document['qpdf'][1];

        $counts = [];
        foreach ($document['pages'] as $page) {
            $content = '';
            foreach ($page['contents'] as $ref) {
                $content .= base64_decode($objects['obj:'.$ref]['stream']['data'] ?? '', true)."\n";
            }
            $counts[] = (int) preg_match_all('#/[^\s/\[\]()<>]+\s+Do\b#', $content);
        }

        return $counts;
    }

    /**
     * Form and image XObjects reachable from each page resources, recursively through forms.
     *
     * @return list<array{forms: int, images: int}>
     */
    public function reachableXObjects(string $file): array
    {
        $document = $this->json(['--json=2', '--json-key=pages', '--json-key=qpdf', $file]);
        $objects = $document['qpdf'][1];
        $resolve = static function (mixed $value) use ($objects): mixed {
            if (\is_string($value) && isset($objects['obj:'.$value])) {
                return $objects['obj:'.$value]['value'] ?? $objects['obj:'.$value]['stream']['dict'] ?? null;
            }

            return $value;
        };

        $result = [];
        foreach ($document['pages'] as $page) {
            $seen = [];
            $counts = ['forms' => 0, 'images' => 0];
            $queue = [$page['object']];
            while (null !== ($ref = array_shift($queue))) {
                $dictionary = $resolve($ref);
                $resources = \is_array($dictionary) ? $resolve($dictionary['/Resources'] ?? []) : [];
                $xObjects = \is_array($resources) ? $resolve($resources['/XObject'] ?? []) : [];
                foreach (\is_array($xObjects) ? $xObjects : [] as $xObjectRef) {
                    if (!\is_string($xObjectRef) || isset($seen[$xObjectRef])) {
                        continue;
                    }
                    $seen[$xObjectRef] = true;
                    $xObject = $resolve($xObjectRef);
                    $subtype = \is_array($xObject) ? ($xObject['/Subtype'] ?? null) : null;
                    if ('/Image' === $subtype) {
                        ++$counts['images'];
                    } elseif ('/Form' === $subtype) {
                        ++$counts['forms'];
                        $queue[] = $xObjectRef;
                    }
                }
            }
            $result[] = $counts;
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    public function rotations(string $file): array
    {
        $document = $this->json(['--json=2', '--json-key=pages', '--json-key=qpdf', $file]);

        return array_values(array_map(static fn (array $page): int => $document['qpdf'][1]['obj:'.$page['object']]['value']['/Rotate'] ?? 0, $document['pages']));
    }
}
