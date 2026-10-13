<?php

namespace Daif\ChromePdfBundle\Engine;

use Daif\ChromePdfBundle\Browser\FileUrl;
use Daif\ChromePdfBundle\Browser\WorkingDirectory;
use Daif\ChromePdfBundle\Exception\BrowserException;
use Daif\ChromePdfBundle\Exception\EngineException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Converts office documents to PDF with a local LibreOffice (soffice).
 *
 * Each call starts soffice with its own user profile (UserInstallation), so calls can run in parallel.
 * The profile holds a generated Basic macro that loads the document (with its password, macros
 * disabled and links not updated), optionally switches the page styles to landscape and stores it
 * with the PDF export filter. The command line --convert-to cannot do the password nor the landscape.
 */
final class LibreOffice
{
    private const PDF_VERSIONS = [
        'PDF/A-1b' => 1,
        'PDF/A-2b' => 2,
        'PDF/A-3b' => 3,
    ];

    private readonly ProcessRunner $runner;
    private readonly Filesystem $filesystem;
    private string|null $resolvedBinary = null;

    public function __construct(
        private readonly BinaryFinder $binaryFinder,
        private readonly string|null $binary = null,
        private readonly LoggerInterface|null $logger = null,
        int $timeout = 120000,
        private readonly string|null $tempDir = null,
    ) {
        $this->runner = new ProcessRunner('LibreOffice', $logger, $timeout);
        $this->filesystem = new Filesystem();
    }

    /**
     * @param array<string, mixed> $filterOptions LibreOffice PDF export filter data, e.g. ['PageRange' => '1-2', 'SelectPdfVersion' => 2]
     * @param string|null          $password      password opening the source document
     * @param bool                 $landscape     switch every page style to landscape (text and spreadsheet documents)
     *
     * @return string the produced PDF: $outputDir/<input name>.pdf
     */
    public function convert(string $input, string $outputDir, array $filterOptions = [], string|null $password = null, bool $landscape = false): string
    {
        self::assertFile($input);

        $name = self::fileName($input);
        $dot = strrpos($name, '.');
        $output = rtrim($outputDir, '\\/').\DIRECTORY_SEPARATOR.(false === $dot || 0 === $dot ? $name : substr($name, 0, $dot)).'.pdf';

        try {
            $this->filesystem->mkdir($outputDir);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('Unable to create the directory "%s": %s', $outputDir, $e->getMessage()), 0, $e);
        }

        $this->run($input, $output, null, null, $filterOptions, $password, $landscape);

        return $output;
    }

    /**
     * Re-exports a PDF as PDF/A and/or PDF/UA through LibreOffice Draw.
     *
     * @param string|null $pdfFormat 'PDF/A-1b', 'PDF/A-2b', 'PDF/A-3b' or null
     */
    public function convertPdf(string $input, string $output, string|null $pdfFormat, bool $universalAccess): void
    {
        self::assertFile($input);

        if (null !== $pdfFormat && !isset(self::PDF_VERSIONS[$pdfFormat])) {
            throw new EngineException(\sprintf('Invalid PDF format "%s", expected one of "%s".', $pdfFormat, implode('", "', array_keys(self::PDF_VERSIONS))));
        }

        $filterOptions = [];
        if (null !== $pdfFormat) {
            $filterOptions['SelectPdfVersion'] = self::PDF_VERSIONS[$pdfFormat];
        }
        if ($universalAccess) {
            $filterOptions['PDFUACompliance'] = true;
        }

        if ([] === $filterOptions) {
            if (realpath($input) !== realpath($output)) {
                try {
                    $this->filesystem->copy($input, $output, true);
                } catch (IOExceptionInterface $e) {
                    throw new EngineException(\sprintf('Unable to write "%s": %s', $output, $e->getMessage()), 0, $e);
                }
            }

            return;
        }

        $this->run($input, $output, 'draw_pdf_import', 'draw_pdf_Export', $filterOptions, null, false);
    }

    /**
     * @param array<string, mixed> $filterOptions
     */
    private function run(string $input, string $output, string|null $importFilter, string|null $exportFilter, array $filterOptions, string|null $password, bool $landscape): void
    {
        foreach ([$password, ...array_filter($filterOptions, '\\is_string')] as $value) {
            if (null !== $value && 1 !== preg_match('//u', $value)) {
                throw new EngineException('LibreOffice passwords and filter options must be valid UTF-8.');
            }
        }

        $directory = ProcessRunner::createWorkingDirectory($this->tempDir);
        try {
            $profile = $directory->getPath().\DIRECTORY_SEPARATOR.'profile';
            $result = $directory->getPath().\DIRECTORY_SEPARATOR.'output.pdf';
            $status = $directory->getPath().\DIRECTORY_SEPARATOR.'status.txt';
            // an ASCII copy: soffice cannot open non-ASCII paths when the process locale is not UTF-8
            $extension = strtolower(pathinfo(self::fileName($input), \PATHINFO_EXTENSION));
            $source = $directory->getPath().\DIRECTORY_SEPARATOR.'input'.(1 === preg_match('/^[a-z0-9]{1,10}$/', $extension) ? '.'.$extension : '');
            $this->filesystem->copy($input, $source, true);

            try {
                $macro = self::macro(FileUrl::fromPath($source), FileUrl::fromPath($result), FileUrl::fromPath($status), $importFilter, $exportFilter, $filterOptions, $password, $landscape);
                $profileUrl = FileUrl::fromPath($profile);
            } catch (BrowserException $e) {
                throw new EngineException($e->getMessage(), 0, $e);
            }
            $this->writeProfile($directory, $macro);

            $this->runner->run([
                $this->binary(),
                '-env:UserInstallation='.$profileUrl,
                '--headless',
                '--invisible',
                '--nocrashreport',
                '--nodefault',
                '--nofirststartwizard',
                '--nolockcheck',
                '--nologo',
                '--norestore',
                'macro:///Standard.Module1.Convert',
            ], [0], null, null, self::utf8Locale());

            $message = is_file($status) ? trim((string) file_get_contents($status)) : null;
            if ('OK' !== $message || !is_file($result)) {
                throw new EngineException(\sprintf('LibreOffice could not convert "%s": %s', $input, $message ?? 'no result, the document may be unsupported or soffice crashed'));
            }

            $this->filesystem->mkdir(\dirname($output));
            $this->filesystem->rename($result, $output, true);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('LibreOffice conversion of "%s" failed: %s', $input, $e->getMessage()), 0, $e);
        } finally {
            $directory->remove($this->logger);
        }
    }

    /**
     * Minimal user profile: marks the installation as completed (otherwise LibreOffice resets the
     * Basic libraries on first start) and provides Standard.Module1.
     */
    private function writeProfile(WorkingDirectory $directory, string $macro): void
    {
        $header = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $libraries = $header.'<!DOCTYPE library:libraries PUBLIC "-//OpenOffice.org//DTD OfficeDocument 1.0//EN" "libraries.dtd">'."\n"
            .'<library:libraries xmlns:library="http://openoffice.org/2000/library" xmlns:xlink="http://www.w3.org/1999/xlink"><library:library library:name="Standard" library:link="false"/></library:libraries>'."\n";

        $directory->writeFile('profile/user/registrymodifications.xcu', $header
            .'<oor:items xmlns:oor="http://openoffice.org/2001/registry" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'."\n"
            .'<item oor:path="/org.openoffice.Setup/Office"><prop oor:name="ooSetupInstCompleted" oor:op="fuse"><value>true</value></prop></item>'."\n"
            .'<item oor:path="/org.openoffice.Office.Common/Misc"><prop oor:name="FirstRun" oor:op="fuse"><value>false</value></prop></item>'."\n"
            .'</oor:items>'."\n");
        $directory->writeFile('profile/user/basic/script.xlc', $libraries);
        $directory->writeFile('profile/user/basic/dialog.xlc', $libraries);
        $directory->writeFile('profile/user/basic/Standard/script.xlb', $header
            .'<!DOCTYPE library:library PUBLIC "-//OpenOffice.org//DTD OfficeDocument 1.0//EN" "library.dtd">'."\n"
            .'<library:library xmlns:library="http://openoffice.org/2000/library" library:name="Standard" library:readonly="false" library:passwordprotected="false"><library:element library:name="Module1"/></library:library>'."\n");
        $directory->writeFile('profile/user/basic/Standard/dialog.xlb', $header
            .'<!DOCTYPE library:library PUBLIC "-//OpenOffice.org//DTD OfficeDocument 1.0//EN" "library.dtd">'."\n"
            .'<library:library xmlns:library="http://openoffice.org/2000/library" library:name="Standard" library:readonly="false" library:passwordprotected="false"/>'."\n");
        $directory->writeFile('profile/user/basic/Standard/Module1.xba', $header
            .'<!DOCTYPE script:module PUBLIC "-//OpenOffice.org//DTD OfficeDocument 1.0//EN" "module.dtd">'."\n"
            .'<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Module1" script:language="StarBasic">'
            .htmlspecialchars($macro, \ENT_XML1 | \ENT_QUOTES, 'UTF-8')
            .'</script:module>'."\n");
    }

    /**
     * @param array<string, mixed> $filterOptions
     */
    private static function macro(string $inputUrl, string $outputUrl, string $statusUrl, string|null $importFilter, string|null $exportFilter, array $filterOptions, string|null $password, bool $landscape): string
    {
        $load = ['Hidden' => 'True', 'MacroExecutionMode' => '0', 'UpdateDocMode' => '0'];
        if (null !== $importFilter) {
            $load['FilterName'] = self::basicString($importFilter);
        }
        if (null !== $password) {
            $load['Password'] = self::basicString($password);
        }

        $filterData = [];
        foreach ($filterOptions as $name => $value) {
            $name = (string) $name;
            if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                throw new EngineException(\sprintf('Invalid LibreOffice PDF export option name "%s".', $name));
            }
            $filterData[$name] = match (true) {
                \is_bool($value) => $value ? 'True' : 'False',
                \is_int($value) => 'CLng('.$value.')',
                \is_float($value) => is_finite($value) ? var_export($value, true) : throw new EngineException(\sprintf('The LibreOffice PDF export option "%s" must be a finite number.', $name)),
                \is_string($value) => self::basicString($value),
                default => throw new EngineException(\sprintf('The LibreOffice PDF export option "%s" must be a string, a boolean or a number, "%s" given.', $name, get_debug_type($value))),
            };
        }

        $lines = [
            'Sub Convert',
            '  On Error Goto Failed',
            ...self::propertyArray('loadArgs', $load),
            '  doc = StarDesktop.loadComponentFromURL('.self::basicString($inputUrl).', "_blank", 0, loadArgs())',
            '  If IsNull(doc) Or IsEmpty(doc) Then',
            '    Report("unable to open the document (unsupported format, damaged file or wrong password)")',
            '    StarDesktop.terminate()',
            '    Exit Sub',
            '  End If',
        ];

        if (null !== $exportFilter) {
            $lines[] = '  filterName = '.self::basicString($exportFilter);
        } else {
            $lines = [...$lines,
                '  filterName = "writer_pdf_Export"',
                '  If doc.supportsService("com.sun.star.sheet.SpreadsheetDocument") Then',
                '    filterName = "calc_pdf_Export"',
                '  ElseIf doc.supportsService("com.sun.star.presentation.PresentationDocument") Then',
                '    filterName = "impress_pdf_Export"',
                '  ElseIf doc.supportsService("com.sun.star.drawing.DrawingDocument") Then',
                '    filterName = "draw_pdf_Export"',
                '  ElseIf doc.supportsService("com.sun.star.text.WebDocument") Then',
                '    filterName = "writer_web_pdf_Export"',
                '  End If',
            ];
        }

        if ($landscape) {
            $lines = [...$lines,
                '  If HasUnoInterfaces(doc, "com.sun.star.style.XStyleFamiliesSupplier") Then',
                '    If doc.StyleFamilies.hasByName("PageStyles") Then',
                '      pageStyles = doc.StyleFamilies.getByName("PageStyles")',
                '      For i = 0 To pageStyles.Count - 1',
                '        pageStyle = pageStyles.getByIndex(i)',
                '        If pageStyle.Width < pageStyle.Height Then',
                '          pageWidth = pageStyle.Width',
                '          pageStyle.Width = pageStyle.Height',
                '          pageStyle.Height = pageWidth',
                '          pageStyle.IsLandscape = True',
                '        End If',
                '      Next i',
                '    End If',
                '  End If',
            ];
        }

        $lines = [...$lines,
            ...self::propertyArray('filterData', $filterData),
            '  Dim storeArgs(1) As New com.sun.star.beans.PropertyValue',
            '  storeArgs(0).Name = "FilterName"',
            '  storeArgs(0).Value = filterName',
            '  storeArgs(1).Name = "FilterData"',
            '  storeArgs(1).Value = filterData()',
            '  doc.storeToURL('.self::basicString($outputUrl).', storeArgs())',
            '  doc.close(True)',
            '  Report("OK")',
            '  StarDesktop.terminate()',
            '  Exit Sub',
            'Failed:',
            '  Report("error " & Err & " at line " & Erl & ": " & Error$)',
            '  StarDesktop.terminate()',
            'End Sub',
            '',
            'Sub Report(message As String)',
            '  fileNumber = FreeFile',
            '  Open '.self::basicString($statusUrl).' For Output As #fileNumber',
            '  Print #fileNumber, message',
            '  Close #fileNumber',
            'End Sub',
        ];

        return "\n".implode("\n", $lines)."\n";
    }

    /**
     * @param array<string, string> $properties name => Basic expression
     *
     * @return list<string>
     */
    private static function propertyArray(string $variable, array $properties): array
    {
        if ([] === $properties) {
            return ['  Dim '.$variable.'() As New com.sun.star.beans.PropertyValue'];
        }

        $lines = ['  Dim '.$variable.'('.(\count($properties) - 1).') As New com.sun.star.beans.PropertyValue'];
        $index = 0;
        foreach ($properties as $name => $expression) {
            $lines[] = '  '.$variable.'('.$index.').Name = "'.$name.'"';
            $lines[] = '  '.$variable.'('.$index.').Value = '.$expression;
            ++$index;
        }

        return $lines;
    }

    /**
     * Basic string literal: quotes are doubled, control characters are concatenated with Chr().
     */
    private static function basicString(string $value): string
    {
        $parts = preg_split('/([\x00-\x1F])/', $value, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$value];
        $expressions = [];
        foreach ($parts as $index => $part) {
            if (1 === $index % 2) {
                $expressions[] = 'Chr('.\ord($part).')';
            } elseif ('' !== $part || 1 === \count($parts)) {
                $expressions[] = '"'.str_replace('"', '""', $part).'"';
            }
        }

        return implode(' & ', $expressions);
    }

    /**
     * @return array<string, string>|null
     */
    private static function utf8Locale(): array|null
    {
        return match (\PHP_OS_FAMILY) {
            'Windows' => null,
            'Darwin' => ['LC_ALL' => 'en_US.UTF-8', 'LANG' => 'en_US.UTF-8'],
            default => ['LC_ALL' => 'C.UTF-8', 'LANG' => 'C.UTF-8'],
        };
    }

    private function binary(): string
    {
        return $this->resolvedBinary ??= $this->binaryFinder->find('soffice', $this->binary);
    }

    private static function assertFile(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new EngineException(\sprintf('The file "%s" does not exist or is not readable.', $file));
        }
    }

    private static function fileName(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return substr($normalized, (int) strrpos('/'.$normalized, '/'));
    }
}
