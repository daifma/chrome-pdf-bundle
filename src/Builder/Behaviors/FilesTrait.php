<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\AssetBaseDirFormatterAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;

trait FilesTrait
{
    use AssetBaseDirFormatterAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * @return array<string>
     */
    abstract protected function getAllowedFilesExtensions(): array;

    /**
     * Adds files (overrides any previous files).
     *
     * @example files('document.pdf', '/absolute/path/document_2.pdf')
     */
    public function files(string|\Stringable ...$paths): static
    {
        $files = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            $resolved = $this->getAssetBaseDirFormatter()->resolve($path);
            ValidatorFactory::filesExtension([new \SplFileInfo($resolved)], $this->getAllowedFilesExtensions());

            // The resolved path (not an SplFileInfo) keeps the body bag serializable.
            $files[$path] = $resolved;
        }

        if ([] === $files) {
            $this->getBodyBag()->unset('files');
        } else {
            $this->getBodyBag()->set('files', $files);
        }

        return $this;
    }
}
