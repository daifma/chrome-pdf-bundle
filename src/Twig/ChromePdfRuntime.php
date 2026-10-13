<?php

namespace Daif\ChromePdfBundle\Twig;

use Daif\ChromePdfBundle\Builder\AssetPathResolverInterface;
use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Formatter\PathHelper;
use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\AssetMapperRepository;

/**
 * @internal
 *
 *  This class is marked as internal to allow flexibility in evolving the runtime API.
 *  However, it is considered safe to use for custom builders or test purposes.
 */
final class ChromePdfRuntime
{
    private const MIME_TYPES = [
        'apng' => 'image/apng',
        'avif' => 'image/avif',
        'bmp' => 'image/bmp',
        'css' => 'text/css',
        'eot' => 'application/vnd.ms-fontobject',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/javascript',
        'otf' => 'font/otf',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'ttf' => 'font/ttf',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    private BuilderAssetInterface|null $builder = null;

    private Part|null $part = null;

    public function __construct(
        private readonly Packages|null $packages = null,
        private readonly AssetMapperRepository|null $assetMapperRepository = null,
    ) {
    }

    /**
     * @param Part|null $part The part currently rendered. Header and footer are rendered by Chrome in an isolated
     *                        context which cannot load files: assets are then inlined as data: URIs.
     */
    public function setBuilder(BuilderAssetInterface|null $builder, Part|null $part = null): void
    {
        $this->builder = $builder;
        $this->part = null === $builder ? null : $part;
    }

    public function getBuilder(): BuilderAssetInterface|null
    {
        return $this->builder;
    }

    public function getPart(): Part|null
    {
        return $this->part;
    }

    /**
     * This function is used to get the URL of an asset during the rendering
     * of a PDF or a screenshot with the ChromePdfFacade client.
     *
     * In the body, the asset is copied next to the document and its basename is returned.
     * In a header or a footer, a data: URI is returned instead.
     *
     * It only works if the builder is an instance of BuilderAssetInterface
     */
    public function getAssetUrl(string $path): string
    {
        $path = $this->getVersionedPathIfExist($path);

        if ($this->isIsolatedPart()) {
            return $this->toDataUri($path, 'chrome_pdf_asset');
        }

        $this->addAsset($path, 'chrome_pdf_asset');

        return PathHelper::basename($path);
    }

    public function getFontStyleTag(string $path, string $name): string
    {
        $path = $this->getVersionedPathIfExist($path);

        return '<style>'.$this->generateFontFace($path, $name, 'chrome_pdf_font_style_tag').'</style>';
    }

    public function getFontFace(string $path, string $name): string
    {
        $path = $this->getVersionedPathIfExist($path);

        return $this->generateFontFace($path, $name, 'chrome_pdf_font_face');
    }

    private function generateFontFace(string $path, string $name, string $function): string
    {
        if ($this->isIsolatedPart()) {
            $url = $this->toDataUri($path, $function);
        } else {
            $this->addAsset($path, $function);
            $url = PathHelper::basename($path);
        }

        $name = htmlspecialchars($name);
        $url = htmlspecialchars($url);

        return '@font-face {font-family: "'.$name.'";src: url("'.$url.'");}';
    }

    private function isIsolatedPart(): bool
    {
        return null !== $this->builder && (Part::Header === $this->part || Part::Footer === $this->part);
    }

    private function addAsset(string $path, string $function): void
    {
        if (null === $this->builder) {
            throw new \LogicException(\sprintf('The %s function must be used in a Chrome PDF context.', $function));
        }

        $this->builder->addAsset($path);
    }

    private function toDataUri(string $path, string $function): string
    {
        if (!$this->builder instanceof AssetPathResolverInterface) {
            throw new \LogicException(\sprintf('The %s function cannot be used in a header or a footer: the builder "%s" must implement "%s".', $function, get_debug_type($this->builder), AssetPathResolverInterface::class));
        }

        $resolvedPath = $this->builder->resolveAssetPath($path);

        if (preg_match('#^(https?|data):#i', $resolvedPath)) {
            return $resolvedPath;
        }

        $content = @file_get_contents($resolvedPath);
        if (false === $content) {
            throw new \RuntimeException(\sprintf('The %s function could not read the asset "%s" to inline it in the header/footer.', $function, $resolvedPath));
        }

        return 'data:'.$this->guessMimeType($resolvedPath).';base64,'.base64_encode($content);
    }

    private function guessMimeType(string $path): string
    {
        return self::MIME_TYPES[PathHelper::extension($path)] ?? 'application/octet-stream';
    }

    private function getVersionedPathIfExist(string $path): string
    {
        $assetRepository = $this->assetMapperRepository;
        if (null !== $assetRepository) {
            $mappedPath = $assetRepository->find($path);

            if (null !== $mappedPath) {
                return $mappedPath;
            }
        }

        $packages = $this->packages;
        if (null !== $packages) {
            $url = $packages->getUrl($path);
            if (!preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $url)) {
                // Strip query-string based versioning ("logo.png?v=123"): we need a real file path.
                $url = (string) preg_replace('/[?#].*$/', '', $url);
                $path = ltrim($url, '/');
            }
        }

        return $path;
    }
}
