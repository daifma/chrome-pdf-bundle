<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Formatter\AssetBaseDirFormatter;
use Daif\ChromePdfBundle\Tests\Builder\Fixtures\RecordingBrowser;
use Daif\ChromePdfBundle\Twig\ChromePdfExtension;
use Daif\ChromePdfBundle\Twig\ChromePdfRuntime;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

abstract class AbstractBuilderTestCase extends TestCase
{
    protected const FIXTURE_DIR = __DIR__.'/../Fixtures';

    protected RecordingBrowser $browser;

    protected RequestStack $requestStack;

    /** @var array<string, object> */
    protected array $extraServices = [];

    protected function setUp(): void
    {
        $this->browser = new RecordingBrowser();
        $this->requestStack = new RequestStack();
        $this->extraServices = [];
    }

    /**
     * @template T of AbstractBuilder
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function createBuilder(string $class): AbstractBuilder
    {
        $builder = new $class();
        $builder->setContainer($this->createContainer());

        return $builder;
    }

    protected function createTwig(): Environment
    {
        $twig = new Environment(new FilesystemLoader(self::FIXTURE_DIR.'/templates'), ['strict_variables' => true]);
        $twig->addExtension(new ChromePdfExtension());
        $runtime = new ChromePdfRuntime();
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([ChromePdfRuntime::class => static fn () => $runtime]));

        return $twig;
    }

    private function createContainer(): ContainerInterface
    {
        $services = [
            'daif_chrome_pdf.browser' => $this->browser,
            'asset_base_dir_formatter' => new AssetBaseDirFormatter(self::FIXTURE_DIR, ['assets', '.']),
            'twig' => $this->createTwig(),
            'request_stack' => $this->requestStack,
        ] + $this->extraServices;

        return new ServiceLocator(array_map(static fn (object $service): \Closure => static fn (): object => $service, $services));
    }
}
