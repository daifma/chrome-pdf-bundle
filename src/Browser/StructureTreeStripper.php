<?php

namespace Daif\ChromePdfBundle\Browser;

/**
 * Removes the logical structure tree (tags) from a PDF printed by Chrome.
 *
 * Chrome tags every PDF even when Page.printToPDF receives generateTaggedPdf: false. On table-heavy documents the
 * tree holds one object per cell and can weigh ten times the page content. The file is rewritten with only the
 * objects still reachable once the tree is detached, renumbered so the cross-reference table shrinks too.
 *
 * Only the single, classic cross-reference table layout Chrome writes is handled; any other PDF is returned as is.
 *
 * @internal
 */
final class StructureTreeStripper
{
    public static function strip(string $pdf): string
    {
        if (!str_contains($pdf, '/StructTreeRoot') || 1 !== substr_count($pdf, 'startxref')
            || str_contains($pdf, '/ObjStm') || str_contains($pdf, '/Encrypt')
        ) {
            return $pdf;
        }

        if (1 !== preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $match)) {
            return $pdf;
        }
        $xrefOffset = (int) $match[1];
        if ('xref' !== substr($pdf, $xrefOffset, 4)) {
            return $pdf;
        }

        $trailerOffset = strpos($pdf, 'trailer', $xrefOffset);
        if (false === $trailerOffset) {
            return $pdf;
        }

        $offsets = self::readCrossReferenceTable(substr($pdf, $xrefOffset + 4, $trailerOffset - $xrefOffset - 4));
        if (null === $offsets || [] === $offsets) {
            return $pdf;
        }

        $trailer = substr($pdf, $trailerOffset + 7, (int) strrpos($pdf, 'startxref') - $trailerOffset - 7);
        if (1 !== preg_match('/\/Root (\d+) 0 R/', $trailer, $rootMatch)) {
            return $pdf;
        }

        $objects = self::readObjects($pdf, $offsets, $xrefOffset);
        $root = (int) $rootMatch[1];
        if (null === $objects || !isset($objects[$root])) {
            return $pdf;
        }

        foreach ($objects as $number => [$dictionary, $data]) {
            if ($number === $root) {
                $dictionary = (string) preg_replace(['/\/StructTreeRoot \d+ 0 R/', '/\/MarkInfo\s*<<[^<>]*>>/'], '', $dictionary);
            }
            // Page and annotation keys pointing into the removed tree
            $objects[$number][0] = (string) preg_replace(['/\/StructParents? \d+/', '/\/Tabs \/S\b/'], '', $dictionary);
        }

        $reachable = self::reachableObjects($objects, $trailer);

        $renumber = [];
        foreach (array_keys($objects) as $number) {
            if (isset($reachable[$number])) {
                $renumber[$number] = \count($renumber) + 1;
            }
        }

        $rewriteReferences = static fn (string $text): string => (string) preg_replace_callback(
            '/\b(\d+) 0 R\b/',
            static fn (array $ref): string => isset($renumber[(int) $ref[1]]) ? $renumber[(int) $ref[1]].' 0 R' : $ref[0],
            $text,
        );

        $output = substr($pdf, 0, min($offsets));
        $newOffsets = [];
        foreach ($renumber as $old => $new) {
            [$dictionary, $data] = $objects[$old];
            $newOffsets[$new] = \strlen($output);
            $output .= $new.' 0 obj'.$rewriteReferences($dictionary).$data;
        }

        $size = \count($renumber) + 1;
        $xref = "xref\n0 ".$size."\n0000000000 65535 f \n";
        foreach ($newOffsets as $offset) {
            $xref .= \sprintf("%010d 00000 n \n", $offset);
        }

        $trailer = (string) preg_replace('/\/Size \d+/', '/Size '.$size, $rewriteReferences($trailer));

        return $output.$xref.'trailer'.$trailer.'startxref'."\n".\strlen($output)."\n%%EOF\n";
    }

    /**
     * @return array<int, int>|null object number => byte offset, null on an unexpected layout
     */
    private static function readCrossReferenceTable(string $table): array|null
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($table)) ?: [];
        $offsets = [];
        $index = 0;
        while ($index < \count($lines)) {
            if (1 !== preg_match('/^(\d+) (\d+)$/', trim($lines[$index]), $section)) {
                return null;
            }
            ++$index;
            for ($i = 0; $i < (int) $section[2]; ++$i, ++$index) {
                if (!isset($lines[$index]) || 1 !== preg_match('/^(\d{10}) (\d{5}) ([nf])/', $lines[$index], $entry)) {
                    return null;
                }
                if ('n' === $entry[3]) {
                    if ('00000' !== $entry[2]) {
                        return null;
                    }
                    $offsets[(int) $section[1] + $i] = (int) $entry[1];
                }
            }
        }

        return $offsets;
    }

    /**
     * Splits each object into the part holding its references (dictionary or value) and the untouched stream data.
     *
     * @param array<int, int> $offsets
     *
     * @return array<int, array{string, string}>|null in file order, null on an unexpected layout
     */
    private static function readObjects(string $pdf, array $offsets, int $xrefOffset): array|null
    {
        asort($offsets);
        $numbers = array_keys($offsets);
        $objects = [];
        foreach ($numbers as $position => $number) {
            $start = $offsets[$number];
            $end = isset($numbers[$position + 1]) ? $offsets[$numbers[$position + 1]] : $xrefOffset;
            $body = substr($pdf, $start, $end - $start);

            $header = $number.' 0 obj';
            if (!str_starts_with($body, $header)) {
                return null;
            }
            $body = substr($body, \strlen($header));

            $split = 1 === preg_match('/>>\s*stream\r?\n/', $body, $stream, \PREG_OFFSET_CAPTURE) ? $stream[0][1] + 2 : \strlen($body);
            $objects[$number] = [substr($body, 0, $split), substr($body, $split)];
        }

        return $objects;
    }

    /**
     * @param array<int, array{string, string}> $objects
     *
     * @return array<int, true>
     */
    private static function reachableObjects(array $objects, string $trailer): array
    {
        $reachable = [];
        preg_match_all('/\b(\d+) 0 R\b/', $trailer, $refs);
        $queue = array_map('intval', $refs[1]);
        while ([] !== $queue) {
            $number = array_pop($queue);
            if (isset($reachable[$number]) || !isset($objects[$number])) {
                continue;
            }
            $reachable[$number] = true;
            preg_match_all('/\b(\d+) 0 R\b/', $objects[$number][0], $refs);
            foreach ($refs[1] as $ref) {
                $queue[] = (int) $ref;
            }
        }

        return $reachable;
    }
}
