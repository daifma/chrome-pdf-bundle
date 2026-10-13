<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\LoggerAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use Daif\ChromePdfBundle\Formatter\PathHelper;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Shared implementation of the Markdown builders (PDF and screenshot).
 *
 * The wrapper (Twig template or HTML file) references each markdown file with `{{ toHTML "file.md" }}`.
 * Spacing and quotes are flexible: `{{toHTML 'file.md'}}`, `{{ toHTML "dir/file.md" }}` and HTML-escaped quotes
 * (`{{ toHTML &quot;file.md&quot; }}`) are supported. In a Twig wrapper, protect the directive from Twig with
 * `{% verbatim %}{{ toHTML "file.md" }}{% endverbatim %}` or print it with `{{ '{{ toHTML "file.md" }}' }}`.
 *
 * @package Behavior\\Markdown
 */
trait MarkdownTrait
{
    use FilesTrait;
    use LoggerAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * The template that wraps the markdown content.
     *
     * Reference each markdown file with `{% verbatim %}{{ toHTML "file.md" }}{% endverbatim %}`:
     * it is replaced by the HTML conversion of the file (GitHub flavored markdown).
     *
     * @param string               $template #Template
     * @param array<string, mixed> $context
     *
     * @throws PartRenderingException if the template could not be rendered
     *
     * @example wrapper('wrapper.html.twig', ['my_var' => 'value'])
     */
    public function wrapper(string $template, array $context = []): static
    {
        return $this->withRenderedPart(Part::Body, $template, $context);
    }

    /**
     * The HTML file that wraps the markdown content. Reference each markdown file with `{{ toHTML "file.md" }}`.
     *
     * As assets files, by default the markdown files are fetch in the assets folder of your application.
     *
     * @throws PartRenderingException if the file could not be found
     *
     * @example wrapperFile('../templates/wrapper.html')
     */
    public function wrapperFile(string $path): static
    {
        return $this->withFilePart(Part::Body, $path);
    }

    /**
     * The raw HTML string that wraps the markdown content. Reference each markdown file with `{{ toHTML "file.md" }}`.
     *
     * @example wrapperRaw('<html><body>{{ toHTML "content.md" }}</body></html>')
     */
    public function wrapperRaw(string $html): static
    {
        return $this->withRawPart(Part::Body, $html);
    }

    /**
     * @return array<string>
     */
    protected function getAllowedFilesExtensions(): array
    {
        return ['md'];
    }

    protected function validatePayloadBody(): void
    {
        if ($this->getBodyBag()->get(Part::Body->value) === null) {
            throw new MissingRequiredFieldException('HTML template is required');
        }

        if ($this->getBodyBag()->get('files') === null && [] === $this->getBodyBag()->get('downloadFrom', [])) {
            throw new MissingRequiredFieldException('At least one markdown file is required.');
        }
    }

    /**
     * Builds the final HTML by converting markdown files to HTML and injecting them into the wrapper.
     *
     * @throws PartRenderingException if the wrapper references an unknown markdown file or a file cannot be read
     */
    protected function buildHtmlFromMarkdown(string $wrapperHtml): string
    {
        /** @var array<string, string> $files */
        $files = $this->getBodyBag()->get('files') ?? [];

        // Index files by the path given to files() and by their basename.
        $byPath = [];
        $byBasename = [];
        $ambiguous = [];
        foreach ($files as $path => $pathname) {
            $byPath[str_replace('\\', '/', (string) $path)] = $pathname;
            $basename = PathHelper::basename((string) $path);
            if (isset($byBasename[$basename]) && $byBasename[$basename] !== $pathname) {
                $ambiguous[$basename] = true;
            }
            $byBasename[$basename] = $pathname;
        }

        $converter = null;
        $converted = [];
        $referenced = [];

        $quote = '(?:"|\'|&quot;|&\#0*34;|&\#0*39;|&\#x0*22;|&\#x0*27;|&apos;)';
        $html = preg_replace_callback(
            '/\{\{-?\s*toHTML\s+'.$quote.'\s*(.+?)\s*'.$quote.'\s*-?\}\}/i',
            function (array $matches) use ($byPath, $byBasename, $ambiguous, &$converter, &$converted, &$referenced): string {
                $name = str_replace('\\', '/', html_entity_decode($matches[1], \ENT_QUOTES | \ENT_HTML5));
                $basename = PathHelper::basename($name);

                if (!isset($byPath[$name]) && isset($ambiguous[$basename])) {
                    throw new PartRenderingException(\sprintf('The markdown wrapper references "%s" which is ambiguous: several markdown files share that name. Reference it by the path given to files().', $name));
                }

                $pathname = $byPath[$name] ?? $byBasename[$basename] ?? null;
                if (null === $pathname) {
                    throw new PartRenderingException(\sprintf('The markdown wrapper references "%s" but no such markdown file was provided. Available files: "%s".', $name, implode('", "', array_keys($byPath))));
                }

                $referenced[$pathname] = true;

                if (!isset($converted[$pathname])) {
                    $markdownContent = @file_get_contents($pathname);
                    if (false === $markdownContent) {
                        throw new PartRenderingException(\sprintf('Could not read markdown file "%s".', $pathname));
                    }

                    $converter ??= new GithubFlavoredMarkdownConverter(['allow_unsafe_links' => false]);
                    $converted[$pathname] = $converter->convert($markdownContent)->getContent();
                }

                return $converted[$pathname];
            },
            $wrapperHtml,
        );

        if (null === $html) {
            throw new PartRenderingException('Could not inject the markdown files into the wrapper: '.preg_last_error_msg());
        }

        foreach ($files as $path => $pathname) {
            if (!isset($referenced[$pathname])) {
                $this->getLogger()?->debug('Markdown file "{daif_chrome_pdf.markdown_file}" is never referenced by the wrapper with {{ toHTML "..." }}.', [
                    'daif_chrome_pdf.markdown_file' => $path,
                ]);
            }
        }

        return $html;
    }
}
