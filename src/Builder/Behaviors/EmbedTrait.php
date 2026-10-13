<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\AssetBaseDirFormatterAwareTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\LoggerAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\ValueObject\EmbeddedFile;

/**
 * Requires qpdf.
 *
 * @package Behavior\\Embed
 */
trait EmbedTrait
{
    use AssetBaseDirFormatterAwareTrait;
    use LoggerAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * Set files to embed.
     *
     * As assets files, by default the files to embed are fetch in the assets folder
     * of your application. For more information about path resolution go to
     * assets documentation.
     *
     * The relationship and MIME type of an EmbeddedFile are kept for reference only: qpdf attaches the file without them.
     *
     * @example embedFiles('document.xml','document_2.json')
     * @example embedFiles(new EmbeddedFile('factur-x.xml', 'Data'))
     */
    public function embedFiles(string|\Stringable|EmbeddedFile ...$paths): static
    {
        $files = [];
        $metadata = [];

        foreach ($paths as $item) {
            if ($item instanceof \SplFileInfo) {
                $filename = $item->getFilename();
                $path = $item->getPathname();
            } else {
                $filename = (string) $item;
                $path = $this->getAssetBaseDirFormatter()->resolve($filename);
            }
            // The path (not an SplFileInfo) keeps the body bag serializable.
            $files[$filename] = $path;

            if ($item instanceof EmbeddedFile) {
                $metadata[$filename] = array_filter([
                    'mimeType' => $item->getMimeType(),
                    'relationship' => $item->getRelationship(),
                ], static fn (string|null $value): bool => null !== $value);
            }
        }

        if ([] === $files) {
            $this->getBodyBag()->unset('embeds');
        } else {
            $this->getBodyBag()->set('embeds', $files);
        }

        if ([] === $metadata) {
            $this->getBodyBag()->unset('embedsMetadata');
        } else {
            $this->getBodyBag()->set('embedsMetadata', $metadata);
        }

        return $this;
    }
}
