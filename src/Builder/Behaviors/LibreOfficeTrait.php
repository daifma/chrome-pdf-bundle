<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

trait LibreOfficeTrait
{
    use DownloadFromTrait;
    use FlattenTrait;
    use LibreOffice\PagePropertiesTrait;
    use MetadataTrait;
    use PdfFormatTrait;
    use RotateTrait;
    use SplitTrait;
    use StampTrait;
    use WatermarkTrait;
    use WebhookTrait;
}
