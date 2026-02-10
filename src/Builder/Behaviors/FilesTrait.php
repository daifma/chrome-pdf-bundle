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
    public function files(string|\Stringable ...$paths): self
    {
        foreach ($paths as $path) {
            $path = (string) $path;
            $info = new \SplFileInfo($this->getAssetBaseDirFormatter()->resolve($path));
            ValidatorFactory::filesExtension([$info], $this->getAllowedFilesExtensions());

            $files[$path] = $info;
        }

        $this->getBodyBag()->set('files', $files ?? null);

        return $this;
    }
}
