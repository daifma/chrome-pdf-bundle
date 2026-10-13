<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\MarkdownScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationNodesTest extends TestCase
{
    private const COMMON = [
        'cookies', 'download_from', 'emulated_media_features', 'emulated_media_type', 'extra_http_headers',
        'fail_on_console_exceptions', 'fail_on_http_status_codes', 'fail_on_resource_http_status_codes',
        'fail_on_resource_loading_failed', 'ignore_resource_http_status_domains',
        'skip_network_almost_idle_event', 'skip_network_idle_event', 'user_agent', 'wait_delay',
        'wait_for_expression', 'wait_for_selector',
    ];

    private const PDF = [
        'footer', 'header', 'generate_document_outline', 'generate_tagged_pdf', 'landscape', 'margin_bottom', 'margin_left', 'margin_right',
        'margin_top', 'native_page_ranges', 'omit_background', 'paper_height', 'paper_standard_size', 'paper_width',
        'prefer_css_page_size', 'print_background', 'scale', 'single_page',
    ];

    private const SCREENSHOT = [
        'clip', 'device_scale_factor', 'format', 'height', 'omit_background', 'optimize_for_speed', 'quality', 'width',
    ];

    /**
     * @return iterable<string, array{class-string, list<string>}>
     */
    public static function builders(): iterable
    {
        foreach ([HtmlPdfBuilder::class, UrlPdfBuilder::class, MarkdownPdfBuilder::class] as $class) {
            yield $class => [$class, [...self::COMMON, ...self::PDF]];
        }

        foreach ([HtmlScreenshotBuilder::class, UrlScreenshotBuilder::class, MarkdownScreenshotBuilder::class] as $class) {
            yield $class => [$class, [...self::COMMON, ...self::SCREENSHOT]];
        }
    }

    /**
     * @param class-string $class
     * @param list<string> $expected
     */
    #[DataProvider('builders')]
    public function testEveryBuilderExposesItsConfigurationNodes(string $class, array $expected): void
    {
        $names = [];
        foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(WithConfigurationNode::class) as $attribute) {
                $name = $attribute->newInstance()->node->getName();
                self::assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)*$/', $name, \sprintf('Node of %s() must be snake_case.', $method->getName()));
                self::assertArrayNotHasKey($name, $names, \sprintf('Node "%s" is declared twice.', $name));
                $names[$name] = $method->getName();
            }
        }

        $names = array_keys($names);
        sort($names);
        sort($expected);
        self::assertSame($expected, $names);
    }
}
