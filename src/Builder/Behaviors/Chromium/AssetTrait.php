<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\AssetBaseDirFormatterAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;

/**
 * @package Behavior\\Assets
 */
trait AssetTrait
{
    use AssetBaseDirFormatterAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * Adds additional files, like images, fonts, stylesheets, and so on (overrides any previous files).
     *
     * By default, the assets files are fetch in the assets folder of your application.
     * If your assets are in another folder, you can override the default value of assets_directory in your
     * configuration file config/daif_chrome_pdf.yml.
     *
     * @example assets('../img/ceo.jpeg', __DIR__'/../../public/admin.jpeg')
     */
    public function assets(string|\Stringable ...$paths): static
    {
        $this->getBodyBag()->unset('assets');

        foreach ($paths as $path) {
            $path = (string) $path;

            $this->addAsset($path);
        }

        return $this;
    }

    /**
     * Adds a file, like an image, font, stylesheet, and so on.
     *
     * By default, the assets files are fetch in the assets folder of your application.
     * If your assets are in another folder, you can override the default value of assets_directory in your
     * configuration file config/daif_chrome_pdf.yml.
     *
     * @example addAsset('../img/ceo.jpeg', __DIR__'/../../public/admin.jpeg')
     */
    public function addAsset(string|\Stringable $path): static
    {
        $path = (string) $path;

        $assets = $this->getBodyBag()->get('assets', []);

        if (\array_key_exists($path, $assets)) {
            return $this;
        }

        $assets[$path] = new \SplFileInfo($this->getAssetBaseDirFormatter()->resolve($path));

        $this->getBodyBag()->set('assets', $assets);

        return $this;
    }
}
