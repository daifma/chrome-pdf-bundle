<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;

/**
 * Requires exiftool.
 *
 * @package Behavior\\Metadata
 */
trait MetadataTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Resets the metadata.
     *
     * @see https://exiftool.org/TagNames/XMP.html#pdf
     *
     * Common PDF metadata keys: Author, Copyright, CreationDate, Creator, Keywords,
     * Marked, ModDate, PDFVersion, Producer, Subject, Title, Trapped.
     *
     * Any ExifTool-compatible key is accepted, including custom XMP namespaces
     * (e.g., 'XMP-fx:DocumentType' for Factur-X).
     *
     * @param array<string, mixed>&array{
     *      Author?: string,
     *      Copyright?: string,
     *      CreationDate?: string,
     *      Creator?: string,
     *      Keywords?: string,
     *      Marked?: bool,
     *      ModDate?: string,
     *      PDFVersion?: string,
     *      Producer?: string,
     *      Subject?: string,
     *      Title?: string,
     *      Trapped?: 'True'|'False'|'Unknown',
     *  } $metadata
     *
     * @example metadata(['Author' => 'SensioLabs', 'Subject' => 'Gotenberg', 'XMP-fx:DocumentType' => 'INVOICE', 'XMP-fx:DocumentFileName' => 'factur-x.xml'])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('metadata', normalizeKeys: false, useAttributeAsKey: 'name', prototype: 'variable'))]
    public function metadata(array $metadata): static
    {
        $this->getBodyBag()->set('metadata', $metadata);

        return $this;
    }

    /**
     * If you want to add metadata from the ones already loaded in the configuration.
     *
     * @example addMetadata('key', 'value')
     */
    public function addMetadata(string $key, string $value): static
    {
        $this->getBodyBag()->set('metadata', [$key => $value] + $this->getBodyBag()->get('metadata', []));

        return $this;
    }
}
