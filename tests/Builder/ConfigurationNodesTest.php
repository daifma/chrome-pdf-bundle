<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Pdf\ConvertPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EmbedPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EncryptPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\FlattenPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\LibreOfficePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\RotatePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\SplitPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\StampPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\WatermarkPdfBuilder;
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
        'wait_for_expression', 'wait_for_selector', 'webhook',
    ];

    private const ENCRYPT = ['owner_password', 'user_password'];
    private const PDF_FORMAT = ['pdf_format', 'pdf_universal_access'];
    private const ROTATE = ['rotate_angle', 'rotate_pages'];
    private const SPLIT = ['split_mode', 'split_span', 'split_unify'];
    private const STAMP = ['stamp_expression', 'stamp_file', 'stamp_options', 'stamp_pages', 'stamp_source'];
    private const WATERMARK = ['watermark_expression', 'watermark_file', 'watermark_options', 'watermark_pages', 'watermark_source'];

    private const POST_PROCESS = [
        ...self::ENCRYPT, 'flatten', 'metadata', ...self::PDF_FORMAT, ...self::ROTATE, ...self::SPLIT, ...self::STAMP, ...self::WATERMARK,
    ];

    private const OFFICE = [
        'add_original_document_as_stream', 'allow_duplicate_field_names', 'center_window', 'convert_ooo_target_to_pdf_target',
        'display_pdf_document_title', 'do_not_export_bookmarks', 'do_not_export_form_fields', 'export_bookmarks_to_pdf_destination',
        'export_hidden_slides', 'export_links_relative_fsys', 'export_notes', 'export_notes_in_margin', 'export_notes_pages',
        'export_only_notes_pages', 'export_placeholders', 'first_page_on_left', 'hide_viewer_menubar', 'hide_viewer_toolbar',
        'hide_viewer_window_controls', 'initial_page', 'initial_view', 'landscape', 'lossless_image_compression', 'magnification',
        'max_image_resolution', 'merge', 'native_page_ranges', 'open_bookmark_levels', 'open_in_full_screen_mode', 'page_layout',
        'password', 'quality', 'reduce_image_resolution', 'resize_window_to_initial_page', 'single_page_sheets', 'skip_empty_pages',
        'tiled_watermark_text', 'update_indexes', 'use_transition_effects', 'watermark_color', 'watermark_font_height',
        'watermark_font_name', 'watermark_rotate_angle', 'watermark_text', 'zoom',
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
            yield $class => [$class, [...self::COMMON, ...self::PDF, ...self::POST_PROCESS]];
        }

        foreach ([HtmlScreenshotBuilder::class, UrlScreenshotBuilder::class, MarkdownScreenshotBuilder::class] as $class) {
            yield $class => [$class, [...self::COMMON, ...self::SCREENSHOT]];
        }

        yield ConvertPdfBuilder::class => [ConvertPdfBuilder::class, ['download_from', 'webhook', 'flatten', ...self::PDF_FORMAT]];
        yield EmbedPdfBuilder::class => [EmbedPdfBuilder::class, ['download_from', 'webhook']];
        yield EncryptPdfBuilder::class => [EncryptPdfBuilder::class, ['download_from', 'webhook', ...self::ENCRYPT]];
        yield FlattenPdfBuilder::class => [FlattenPdfBuilder::class, ['download_from', 'webhook']];
        yield RotatePdfBuilder::class => [RotatePdfBuilder::class, ['download_from', 'webhook', ...self::ROTATE]];
        yield StampPdfBuilder::class => [StampPdfBuilder::class, ['download_from', 'webhook', ...self::STAMP]];
        yield WatermarkPdfBuilder::class => [WatermarkPdfBuilder::class, ['download_from', 'webhook', ...self::WATERMARK]];
        yield MergePdfBuilder::class => [MergePdfBuilder::class, [
            'auto_index_bookmarks', 'download_from', 'webhook', 'flatten', 'metadata', ...self::ENCRYPT, ...self::PDF_FORMAT, ...self::STAMP, ...self::WATERMARK,
        ]];
        yield SplitPdfBuilder::class => [SplitPdfBuilder::class, [
            'download_from', 'webhook', 'flatten', 'metadata', ...self::ENCRYPT, ...self::PDF_FORMAT, ...self::SPLIT, ...self::STAMP, ...self::WATERMARK,
        ]];
        yield LibreOfficePdfBuilder::class => [LibreOfficePdfBuilder::class, [
            'download_from', 'webhook', 'flatten', 'metadata', ...self::ENCRYPT, ...self::PDF_FORMAT, ...self::ROTATE, ...self::SPLIT,
            ...self::STAMP, ...self::WATERMARK, ...self::OFFICE,
        ]];
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
