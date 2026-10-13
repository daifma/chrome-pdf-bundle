<?php

namespace Daif\ChromePdfBundle\Tests\DependencyInjection;

use Daif\ChromePdfBundle\DaifChromePdfBundle;
use Daif\ChromePdfBundle\DependencyInjection\DaifChromePdfExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigurationTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $configs
     *
     * @return array<string, mixed>
     */
    private static function process(array $configs): array
    {
        $extension = (new DaifChromePdfBundle())->getContainerExtension();
        self::assertInstanceOf(DaifChromePdfExtension::class, $extension);

        return (new Processor())->processConfiguration($extension->getConfiguration([], new ContainerBuilder()), $configs);
    }

    public function testDefaultConfiguration(): void
    {
        $config = self::process([]);

        self::assertSame(['%kernel.project_dir%/assets'], $config['assets_directory']);
        self::assertNull($config['chrome_binary']);
        self::assertSame(30000, $config['timeout']);
        self::assertFalse($config['no_sandbox']);
        self::assertFalse($config['ignore_certificate_errors']);
        self::assertNull($config['temp_dir']);
        self::assertSame([], $config['allow_list']);
        self::assertSame([], $config['deny_list']);
        self::assertSame([], $config['browser_options']);
        self::assertTrue($config['controller_listener']);
        self::assertArrayNotHasKey('request_context', $config);
    }

    public function testDefaultOptionsAreGeneratedForEveryRegisteredBuilder(): void
    {
        $config = self::process([]);

        self::assertIsArray($config['default_options']);
        self::assertSame(['pdf', 'screenshot'], array_keys($config['default_options']));
        foreach (['pdf', 'screenshot'] as $type) {
            self::assertIsArray($config['default_options'][$type]);
            self::assertEqualsCanonicalizing(['html', 'url', 'markdown'], array_keys($config['default_options'][$type]));
        }
    }

    public function testCustomConfiguration(): void
    {
        $config = self::process([[
            'assets_directory' => 'templates/pdf',
            'chrome_binary' => 'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'timeout' => 120000,
            'no_sandbox' => true,
            'ignore_certificate_errors' => true,
            'temp_dir' => '%kernel.project_dir%/var/chrome-pdf',
            'allow_list' => ['#^https://intranet\.example\.com/#'],
            'deny_list' => '#^https?://169\.254\.#',
            'browser_options' => ['windowSize' => [1920, 1080], 'customFlags' => ['--lang=fr']],
            'request_context' => ['base_uri' => 'https://example.com'],
            'controller_listener' => false,
        ]]);

        self::assertSame(['templates/pdf'], $config['assets_directory']);
        self::assertSame('C:\Program Files\Google\Chrome\Application\chrome.exe', $config['chrome_binary']);
        self::assertSame(120000, $config['timeout']);
        self::assertTrue($config['no_sandbox']);
        self::assertTrue($config['ignore_certificate_errors']);
        self::assertSame('%kernel.project_dir%/var/chrome-pdf', $config['temp_dir']);
        self::assertSame(['#^https://intranet\.example\.com/#'], $config['allow_list']);
        self::assertSame(['#^https?://169\.254\.#'], $config['deny_list']);
        self::assertSame(['windowSize' => [1920, 1080], 'customFlags' => ['--lang=fr']], $config['browser_options']);
        self::assertSame(['base_uri' => 'https://example.com'], $config['request_context']);
        self::assertFalse($config['controller_listener']);
    }

    public function testPatternListsAreMergedAcrossConfigs(): void
    {
        $config = self::process([
            ['deny_list' => ['#^https://a\.test/#']],
            ['deny_list' => ['#^https://b\.test/#']],
        ]);

        self::assertSame(['#^https://a\.test/#', '#^https://b\.test/#'], $config['deny_list']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidConfiguration(): iterable
    {
        yield 'timeout too low' => [['timeout' => 999], 'daif_chrome_pdf.timeout'];
        yield 'timeout not an int' => [['timeout' => '30s'], 'daif_chrome_pdf.timeout'];
        yield 'no_sandbox not a bool' => [['no_sandbox' => 'yes'], 'daif_chrome_pdf.no_sandbox'];
        yield 'ignore_certificate_errors not a bool' => [['ignore_certificate_errors' => 'yes'], 'daif_chrome_pdf.ignore_certificate_errors'];
        yield 'allow_list without delimiters' => [['allow_list' => ['^https://example\.com/']], 'not a valid PCRE regular expression'];
        yield 'deny_list with broken pattern' => [['deny_list' => ['#^https://(unclosed#']], 'not a valid PCRE regular expression'];
        yield 'deny_list with non string' => [['deny_list' => [['nested']]], 'daif_chrome_pdf.deny_list'];
        yield 'unknown key' => [['http_client' => 'http_client'], 'Unrecognized option "http_client"'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideInvalidConfiguration')]
    public function testInvalidConfiguration(array $config, string $expectedMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        self::process([$config]);
    }

    public function testEnvPlaceholdersAreAccepted(): void
    {
        $container = new ContainerBuilder();
        $binary = $container->getParameterBag()->resolveValue('%env(CHROME_PATH)%');
        $pattern = $container->getParameterBag()->resolveValue('%env(DENY_PATTERN)%');

        $config = self::process([[
            'chrome_binary' => $binary,
            'deny_list' => [$pattern],
        ]]);

        // Placeholders cannot be validated at compile time: they must pass through untouched.
        self::assertIsString($binary);
        self::assertStringStartsWith('env_', $binary);
        self::assertSame($binary, $config['chrome_binary']);
        self::assertSame([$pattern], $config['deny_list']);
    }
}
