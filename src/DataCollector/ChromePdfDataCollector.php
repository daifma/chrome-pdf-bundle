<?php

namespace Daif\ChromePdfBundle\DataCollector;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;
use Daif\ChromePdfBundle\Debug\TraceableChromePdf;
use Daif\ChromePdfBundle\Debug\TraceableChromePdfScreenshot;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Caster\ArgsStub;
use Symfony\Component\VarDumper\Caster\CutArrayStub;
use Symfony\Component\VarDumper\Cloner\Data;

final class ChromePdfDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @param ServiceLocator<TraceableBuilder|BuilderInterface>  $builders
     * @param array<string, array<string, array<string, mixed>>> $defaultOptions keyed by type ("pdf"/"screenshot") then builder name
     */
    public function __construct(
        private readonly TraceableChromePdf $traceableChromePdf,
        private readonly TraceableChromePdfScreenshot $traceableChromeScreenshot,
        private readonly ServiceLocator $builders,
        private readonly array $defaultOptions,
    ) {
    }

    public function collect(Request $request, Response $response, \Throwable|null $exception = null): void
    {
        $this->data['request_total_size'] = 0;
        $this->data['request_total_time'] = 0;
        $this->data['request_count'] = 0;
        $this->data['builders'] = [];
        $this->data['files'] = [];

        foreach ($this->builders->getProvidedServices() as $id => $type) {
            $builder = $this->builders->get($id);

            if ($builder instanceof TraceableBuilder) {
                $builder = $builder->getInner();
            }

            $builderType = null;
            $name = $id;
            foreach (['pdf' => '.daif_chrome_pdf.pdf_builder.', 'screenshot' => '.daif_chrome_pdf.screenshot_builder.'] as $candidateType => $prefix) {
                if (str_starts_with($id, $prefix)) {
                    $builderType = $candidateType;
                    $name = substr($id, \strlen($prefix));
                    break;
                }
            }

            $key = null === $builderType ? $id : $builderType.'.'.$name;

            $this->data['builders'][$key] = [
                'class' => $builder::class,
                'default_options' => null === $builderType ? [] : ($this->defaultOptions[$builderType][$name] ?? []),
            ];
        }
    }

    public function getName(): string
    {
        return 'daif_chrome_pdf';
    }

    public function reset(): void
    {
        parent::reset();
        $this->traceableChromePdf->reset();
        $this->traceableChromeScreenshot->reset();
    }

    public function lateCollect(): void
    {
        $this->lateCollectFiles($this->traceableChromePdf->getBuilders(), 'pdf');
        $this->lateCollectFiles($this->traceableChromeScreenshot->getBuilders(), 'screenshot');
    }

    /**
     * @param list<array{string, TraceableBuilder}> $builders
     */
    private function lateCollectFiles(array $builders, string $type): void
    {
        $prefix = '.daif_chrome_pdf.'.$type.'_builder.';
        foreach ($builders as [$id, $builder]) {
            // get() may be called with the full service id instead of the short builder name.
            $name = str_starts_with($id, $prefix) ? substr($id, \strlen($prefix)) : $id;
            foreach ($builder->getFiles() as $request) {
                $this->data['request_total_time'] += $request['time'];
                $this->data['request_total_size'] += $request['size'] ?? 0;
                $this->data['files'][] = [
                    'builderClass' => $builder->getInner()::class,
                    'configuration' => [
                        'default_options' => $this->cloneVar($this->defaultOptions[$type][$name] ?? []),
                    ],
                    'type' => $type,
                    'request_type' => $request['type'],
                    'time' => $request['time'],
                    'memory' => $request['memory'],
                    'size' => $this->formatSize($request['size'] ?? 0),
                    'fileName' => $request['fileName'],
                    'contentType' => $request['contentType'] ?? null,
                    'calls' => array_map(function (array $call): array {
                        $args = array_map(static function (mixed $arg): mixed {
                            if (\is_array($arg)) {
                                return new CutArrayStub($arg, array_keys($arg));
                            }

                            return $arg;
                        }, $call['arguments']);

                        return [
                            'method' => $call['method'],
                            'stub' => $this->cloneVar(new ArgsStub($args, $call['method'], $call['class'])),
                        ];
                    }, $request['calls']),
                ];
            }

            $this->data['request_count'] += \count($builder->getFiles());
        }
    }

    /**
     * @param int<0, max> $size
     *
     * @return array{int|float, string}
     */
    private function formatSize(int $size): array
    {
        return match (true) {
            ($size / 1024 < 1) => [$size, 'B'],
            ($size / (1024 ** 2) < 1) => [round($size / 1024, 2), 'kB'],
            ($size / (1024 ** 3) < 1) => [round($size / (1024 ** 2), 2), 'MB'],
            ($size / (1024 ** 4) < 1) => [round($size / (1024 ** 3), 2), 'GB'],
            ($size / (1024 ** 5) < 1) => [round($size / (1024 ** 4), 2), 'TB'],
            default => throw new \LogicException('File too big'),
        };
    }

    public function getRequestCount(): int
    {
        return $this->data['request_count'] ?? 0;
    }

    public function getRequestTotalTime(): int|float
    {
        return $this->data['request_total_time'] ?? 0.0;
    }

    /**
     * @return array{int|float, string}
     */
    public function getRequestTotalSize(): array
    {
        return $this->formatSize($this->data['request_total_size'] ?? 0);
    }

    /**
     * @return list<array{
     *      builderClass: string,
     *      type: string,
     *      time: float,
     *      memory: int,
     *      size: int<0, max>|null,
     *      fileName: string|null,
     *      contentType: string|null,
     *      configuration: array{default_options: Data},
     *      calls: list<array{
     *          method: string,
     *          stub: Data,
     *      }>
     * }>
     */
    public function getFiles(): array
    {
        return $this->data['files'] ?? [];
    }
}
