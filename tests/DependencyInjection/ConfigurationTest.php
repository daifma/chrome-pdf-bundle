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
        self::assertNull($config['qpdf_binary']);
        self::assertNull($config['exiftool_binary']);
        self::assertNull($config['libreoffice_binary']);
        self::assertSame(120000, $config['engine_timeout']);
        self::assertSame([], $config['webhook']);
        self::assertSame(['enabled' => false, 'bus' => 'messenger.default_bus'], $config['async']);
        self::assertArrayNotHasKey('webhook', $config['default_options']);
    }

    public function testDefaultOptionsAreGeneratedForEveryRegisteredBuilder(): void
    {
        $config = self::process([]);

        self::assertIsArray($config['default_options']);
        self::assertEqualsCanonicalizing(['pdf', 'screenshot'], array_keys($config['default_options']));
        self::assertIsArray($config['default_options']['pdf']);
        self::assertEqualsCanonicalizing(
            ['html', 'url', 'markdown', 'office', 'merge', 'convert', 'split', 'flatten', 'encrypt', 'embed', 'watermark', 'stamp', 'rotate'],
            array_keys($config['default_options']['pdf']),
        );
        self::assertIsArray($config['default_options']['screenshot']);
        self::assertEqualsCanonicalizing(['html', 'url', 'markdown'], array_keys($config['default_options']['screenshot']));
    }

    public function testEngineConfiguration(): void
    {
        $config = self::process([[
            'qpdf_binary' => '/usr/bin/qpdf',
            'exiftool_binary' => 'C:\Strawberry\perl\bin\exiftool',
            'libreoffice_binary' => '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            'engine_timeout' => 300000,
        ]]);

        self::assertSame('/usr/bin/qpdf', $config['qpdf_binary']);
        self::assertSame('C:\Strawberry\perl\bin\exiftool', $config['exiftool_binary']);
        self::assertSame('/Applications/LibreOffice.app/Contents/MacOS/soffice', $config['libreoffice_binary']);
        self::assertSame(300000, $config['engine_timeout']);
    }

    public function testNamedWebhookConfiguration(): void
    {
        $config = self::process([[
            'webhook' => [
                'default' => [
                    'success' => ['url' => 'https://example.com/success', 'method' => 'PUT'],
                    'error' => ['route' => 'webhook_error'],
                    'events' => ['route' => ['webhook_events', ['id' => 1]]],
                    'extra_http_headers' => ['Authorization' => 'Bearer token'],
                ],
            ],
            'default_options' => ['webhook' => 'default'],
        ]]);

        self::assertSame('default', $config['default_options']['webhook']);
        self::assertIsArray($config['webhook']);
        self::assertSame('https://example.com/success', $config['webhook']['default']['success']['url']);
        self::assertSame('PUT', $config['webhook']['default']['success']['method']);
        self::assertSame(['webhook_error', []], $config['webhook']['default']['error']['route']);
        self::assertNull($config['webhook']['default']['error']['method']);
        self::assertSame(['webhook_events', ['id' => 1]], $config['webhook']['default']['events']['route']);
        self::assertSame(['Authorization' => 'Bearer token'], $config['webhook']['default']['extra_http_headers']);
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
        yield 'engine_timeout too low' => [['engine_timeout' => 999], 'daif_chrome_pdf.engine_timeout'];
        yield 'webhook without success' => [['webhook' => ['foo' => ['error' => ['url' => 'https://example.com']]]], 'At least a "success" key is required'];
        yield 'webhook with url and route' => [['webhook' => ['foo' => ['success' => ['url' => 'https://example.com', 'route' => 'foo']]]], 'either a "url" or a "route"'];
        yield 'webhook with invalid method' => [['webhook' => ['foo' => ['success' => ['url' => 'https://example.com', 'method' => 'GET']]]], 'daif_chrome_pdf.webhook.foo.success.method'];
        yield 'webhook with invalid route' => [['webhook' => ['foo' => ['success' => ['route' => [1, 2]]]]], 'The "route" parameter must be a string'];
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
