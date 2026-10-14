<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\PrintOptions;
use Daif\ChromePdfBundle\Browser\StructureTreeStripper;
use PHPUnit\Framework\TestCase;

final class StructureTreeStripperTest extends TestCase
{
    public function testItRemovesTheStructureTreeAndRenumbersTheObjects(): void
    {
        $pdf = self::buildPdf([
            1 => '<</Producer (test)>>',
            2 => "<</Type /Page\n/MediaBox [0 0 100 100]\n/Contents 3 0 R\n/StructParents 0\n/Tabs /S\n/Parent 4 0 R>>",
            3 => "<</Length 9>> stream\nBT 12 0 R\nendstream",
            4 => "<</Type /Pages\n/Count 1\n/Kids [2 0 R]>>",
            5 => '<</Type /StructTreeRoot /K [6 0 R 7 0 R] /ParentTree 8 0 R>>',
            6 => '<</Type /StructElem /S /TD /P 5 0 R /Pg 2 0 R>>',
            7 => '<</Type /StructElem /S /TD /P 5 0 R /Pg 2 0 R>>',
            8 => '<</Nums [0 [6 0 R 7 0 R]]>>',
            9 => "<</Type /Catalog\n/Pages 4 0 R\n/MarkInfo <</Type /MarkInfo\n/Marked true>>\n/StructTreeRoot 5 0 R\n/Lang (fr)>>",
        ], 9);

        $stripped = StructureTreeStripper::strip($pdf);

        self::assertLessThan(\strlen($pdf), \strlen($stripped));
        self::assertStringNotContainsString('/StructTreeRoot', $stripped);
        self::assertStringNotContainsString('/StructElem', $stripped);
        self::assertStringNotContainsString('/MarkInfo', $stripped);
        self::assertStringNotContainsString('/StructParents', $stripped);
        self::assertStringNotContainsString('/Tabs', $stripped);
        self::assertStringContainsString('/Lang (fr)', $stripped);
        self::assertSame(1, PrintOptions::countPdfPages($stripped));
        // References inside stream data are content, not object references
        self::assertStringContainsString("BT 12 0 R\nendstream", $stripped);
        self::assertValidCrossReferenceTable($stripped, 5);
    }

    public function testItReturnsAnUntaggedPdfUnchanged(): void
    {
        $pdf = self::buildPdf([
            1 => "<</Type /Catalog\n/Pages 2 0 R>>",
            2 => "<</Type /Pages\n/Count 0\n/Kids []>>",
        ], 1);

        self::assertSame($pdf, StructureTreeStripper::strip($pdf));
    }

    public function testItReturnsAPdfWithObjectStreamsUnchanged(): void
    {
        $pdf = self::buildPdf([
            1 => "<</Type /Catalog\n/Pages 2 0 R\n/StructTreeRoot 3 0 R>>",
            2 => "<</Type /Pages\n/Count 0\n/Kids []>>",
            3 => '<</Type /ObjStm /N 0 /First 0 /Length 0>>',
        ], 1);

        self::assertSame($pdf, StructureTreeStripper::strip($pdf));
    }

    public function testItReturnsAPdfWithIncrementalUpdatesUnchanged(): void
    {
        $pdf = self::buildPdf([
            1 => "<</Type /Catalog\n/Pages 2 0 R\n/StructTreeRoot 3 0 R>>",
            2 => "<</Type /Pages\n/Count 0\n/Kids []>>",
            3 => '<</Type /StructTreeRoot>>',
        ], 1);
        $pdf .= "xref\n0 1\n0000000000 65535 f \ntrailer\n<</Size 4>>\nstartxref\n0\n%%EOF\n";

        self::assertSame($pdf, StructureTreeStripper::strip($pdf));
    }

    /**
     * @param array<int, string> $objects
     */
    private static function buildPdf(array $objects, int $root): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = \strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = \strlen($pdf);
        $pdf .= "xref\n0 ".(\count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<</Size ".(\count($objects) + 1)."\n/Root ".$root." 0 R\n/Info 1 0 R>>\nstartxref\n".$xrefOffset."\n%%EOF\n";
    }

    private static function assertValidCrossReferenceTable(string $pdf, int $expectedObjects): void
    {
        self::assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF\n$/', $pdf, $match));
        $xref = substr($pdf, (int) $match[1]);
        self::assertStringStartsWith("xref\n0 ".($expectedObjects + 1)."\n", $xref);
        self::assertStringContainsString('/Size '.($expectedObjects + 1), $xref);

        preg_match_all('/^(\d{10}) 00000 n $/m', $xref, $entries);
        self::assertCount($expectedObjects, $entries[1]);
        foreach ($entries[1] as $index => $offset) {
            self::assertStringStartsWith(($index + 1).' 0 obj', substr($pdf, (int) $offset));
        }

        preg_match_all('/\b(\d+) 0 R\b/', substr($pdf, 0, (int) $match[1]), $refs);
        foreach ($refs[1] as $ref) {
            if ('12' !== $ref) {
                self::assertLessThanOrEqual($expectedObjects, (int) $ref);
            }
        }
    }
}
