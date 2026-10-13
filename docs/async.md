# Async generation and webhooks (optional)

> [!NOTE]
> This feature is **optional and disabled by default**. The bundle does not need Symfony Messenger or HttpClient
> for anything else. Enable it only if you want `generateAsync()`.

GotenbergBundle can ask the Gotenberg server to generate a file in the background and send it to a webhook URL
(`generateAsync()`). There is no server here, so this bundle does the same with
[Symfony Messenger](https://symfony.com/doc/current/messenger.html):

1. `generateAsync()` puts the builder's state into a `Daif\ChromePdfBundle\Messenger\GenerateDocumentMessage` and
   dispatches it on the configured message bus.
2. A Messenger worker (`messenger:consume`) handles the message: it rebuilds the builder, calls `generate()`, then
   sends the file to the **success** URL with an HTTP request.
3. If the generation or the request fails, it sends a JSON error to the **error** URL.

The builder methods (`webhookUrl()`, `webhookConfiguration()`, ...) and the `webhook` configuration are the ones of
GotenbergBundle, so existing code and YAML can be reused.

## Enabling it

1. Install the two packages:

   ```bash
   composer require symfony/messenger symfony/http-client
   ```

2. Enable the feature in the bundle configuration:

   ```yaml
   # config/packages/daif_chrome_pdf.yaml
   daif_chrome_pdf:
       async:
           enabled: true
           # bus: 'messenger.default_bus'   # service id of the bus the messages are dispatched on
   ```

The async services use the configured bus (default `messenger.default_bus`) and the `http_client` service, so
`framework.messenger` and `framework.http_client` must be enabled (Symfony Flex does it when you install the
packages). Setting `enabled: true` without the two packages makes the container compilation fail with a message
telling what to install.

While the feature is disabled (the default), `generateAsync()` throws a `LogicException`. The `webhook*()` builder methods and the `webhook`
configuration are always accepted, they are only used by `generateAsync()`.

Route the message to an asynchronous transport, otherwise Messenger handles it synchronously, in the same request:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Daif\ChromePdfBundle\Messenger\GenerateDocumentMessage: async
```

Then run a worker:

```bash
php bin/console messenger:consume async
```

## Usage

```php
use Daif\ChromePdfBundle\ChromePdfInterface;

public function export(ChromePdfInterface $chromePdf): Response
{
    $chromePdf->html()
        ->content('report.html.twig', ['report' => $report])
        ->fileName('report')
        ->webhookUrl('https://example.com/webhook/pdf-ready')
        ->webhookErrorUrl('https://example.com/webhook/pdf-failed')
        ->generateAsync()
    ;

    return new Response('', Response::HTTP_ACCEPTED);
}
```

`generateAsync()` returns nothing. A webhook URL is required, from the builder methods or from the configuration;
without one it throws a `MissingRequiredFieldException`.

It works with every PDF builder (`html()`, `url()`, `markdown()`, `office()`, `merge()`, `split()`, ...) and every
screenshot builder.

## What the endpoints receive

**Success**: an HTTP request (`POST` by default, or the configured method) whose body is the generated file, with:

- `Content-Type`: `application/pdf`, the image type of a screenshot, or `application/zip` for a
  [ZIP result](pdf/post-processing.md#output-one-pdf-or-a-zip);
- `Content-Disposition`: the disposition and file name set with `fileName()`;
- the extra headers configured with `webhookExtraHeaders()` / `extra_http_headers`.

**Error**: a `POST` (or the configured method) with a JSON body and the same extra headers. `status` is `400` for an
invalid builder configuration (missing field, incompatible options), with the validation message, and `500` for any
other failure. A `500` message never contains the engine or browser error, which can include local paths and tool
output: that detail is logged on the worker under the correlation id.

```json
{"status": 500, "message": "The document could not be generated, see the server logs (correlation id \"5f0c...\")."}
```

If no error URL is set, the error is sent to the success URL, as in Gotenberg.

**Events** (optional, `webhookEventsUrl()` / `webhookEventsRoute()`): a JSON `POST` after the success or error call:

```json
{"type": "webhook.success", "correlationId": "5f0c...", "timestamp": "2026-10-13T09:30:00.000+00:00",
 "fileName": "report.pdf", "contentType": "application/pdf", "contentLength": 48213}
```

For `webhook.error`, an `error` object with `status` and `message` replaces the file fields. The correlation id is
generated for each `generateAsync()` call. A failing events endpoint is logged and does not make the message fail.

Webhook URLs go through the same `allow_list` / `deny_list` as `downloadFrom()` (http and https only, redirects
not followed). A blocked URL is not called and the message is not retried.

Protect these endpoints: use a secret header (`webhookExtraHeaders(['Authorization' => 'Bearer ...'])`) and check it
in the receiving controller.

## Configuration

Named configurations are declared under `webhook`, and selected with `webhookConfiguration()` or set as a default in
`default_options`:

```yaml
# config/packages/daif_chrome_pdf.yaml
daif_chrome_pdf:
    webhook:
        default:
            success:
                url: 'https://example.com/webhook/pdf-ready'
                # method: PUT       # POST (default), PUT or PATCH
            error:
                route: ['webhook_pdf_failed', { source: 'chrome-pdf' }]
            events:
                url: 'https://example.com/webhook/pdf-events'
            extra_http_headers:
                - { name: 'Authorization', value: '%env(WEBHOOK_TOKEN)%' }
        invoices:
            success:
                route: 'webhook_invoice_ready'

    default_options:
        webhook: 'default'          # used by every builder
        pdf:
            html:
                webhook:
                    config_name: 'invoices'   # this builder uses another configuration
```

Each named configuration needs at least a `success` URL, as a plain `url` or as a `route` of your application
(a route name, or `[name, parameters]`). `error` and `events` are optional and take the same `url` or `route` keys
(`events` has no `method`, it is always `POST`). A builder default that names an unknown configuration makes the
container compilation fail.

When a builder default combines `config_name` with its own `success` or `error`, the named configuration is loaded
first and the builder's values are merged over it.

### At runtime

```php
$builder
    ->webhookConfiguration('invoices')       // a named configuration
    ->webhookUrl('https://example.com/a')    // or explicit URLs
    ->webhookErrorUrl('https://example.com/b', 'PUT')
    ->webhookRoute('webhook_pdf_ready', ['id' => 42])
    ->webhookErrorRoute('webhook_pdf_failed')
    ->webhookEventsUrl('https://example.com/events')
    ->webhookExtraHeaders(['Authorization' => 'Bearer xxx'])
    ->addWebhookExtraHeaders(['X-Request-Id' => $id])
;
```

| Method | Description |
|---|---|
| `webhook(array $webhook)` | Full definition, same keys as the YAML (`config_name`, `success`, `error`, `events`, `extra_http_headers`) |
| `webhookConfiguration(string $name)` | Load a named configuration from `daif_chrome_pdf.webhook` |
| `webhookUrl(string $url, string\|null $method = null)` | Success URL, method `POST` (default), `PUT` or `PATCH` |
| `webhookRoute(string $route, array $parameters = [], string\|null $method = null)` | Success URL from a route |
| `webhookErrorUrl(string $url, string\|null $method = null)` | Error URL |
| `webhookErrorRoute(string $route, array $parameters = [], string\|null $method = null)` | Error URL from a route |
| `webhookEventsUrl(string $url)` / `webhookEventsRoute(string $route, array $parameters = [])` | Events URL |
| `webhookExtraHeaders(array $extraHttpHeaders)` | Headers sent to every endpoint (replaces) |
| `addWebhookExtraHeaders(array $extraHttpHeaders)` | Add headers |

> [!WARNING]
> The order of the calls matters when you combine them. `webhookConfiguration()` then `webhookUrl()` overrides
> only the success URL. `webhookUrl()` then `webhookConfiguration()` replaces everything set before.

Routes are turned into absolute URLs. In a worker there is no current request, so set
[`request_context.base_uri`](configuration.md#global-options), otherwise the generated URLs point to `localhost`.

## What is carried to the worker

The message contains the builder class, its options, the file name and the disposition. Everything must be
serializable, so keep in mind:

- **Twig templates are rendered when you call `content()`, `header()`, ...**, in the request. The message carries the
  rendered HTML, not your Twig context (no entities to serialize).
- **Files are carried as paths** (`files()`, `assets()`, `embedFiles()`, `stampFile()`, ...). They are read by the
  worker, so they must exist on the worker's machine at the same path. Do not pass temporary files that the request
  deletes when it ends.
- **Processors are not carried over.** `processor()` is ignored by `generateAsync()`: the file goes to the webhook.
  If you want to store the file yourself, dispatch your own message instead, see below.
- **Secrets are stored in the transport as plain text**: `userPassword()`, `ownerPassword()`, the `password()` of
  `office()` and `webhookExtraHeaders()` values (an `Authorization` token, for example) are part of the message, so
  they end up in your queue (Doctrine, Redis, AMQP...) and in the failure transport (`messenger:failed:show`). Prefer
  named webhook configurations for tokens, restrict access to the transport, or encrypt messages with a custom
  Messenger serializer.
- `url()` / `route()` targets are loaded by the worker, which may not have the user's session. Forwarded cookies are
  sent as they were when `generateAsync()` was called.

## Without webhooks

If you only want the generation out of the request, and to store the file (disk, S3, Flysystem) rather than POST it,
write your own message and handler and call `generate()` with a [processor](processing.md):

```php
// src/Message/GenerateInvoicePdf.php
final class GenerateInvoicePdf
{
    public function __construct(public readonly int $invoiceId) {}
}

// src/MessageHandler/GenerateInvoicePdfHandler.php
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Processor\FileProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateInvoicePdfHandler
{
    public function __construct(
        private readonly ChromePdfInterface $chromePdf,
        #[Autowire('%kernel.project_dir%/var/invoices')]
        private readonly string $storage,
    ) {}

    public function __invoke(GenerateInvoicePdf $message): void
    {
        $this->chromePdf->html()
            ->content('invoice.html.twig', ['id' => $message->invoiceId])
            ->fileName('invoice-'.$message->invoiceId)
            ->processor(new FileProcessor(new Filesystem(), $this->storage))
            ->generate()
            ->process()
        ;
    }
}
```

## Notes for workers

- The Chrome process is reused between messages and restarted if it crashed. Tabs are closed after each conversion,
  so long-running consumers do not leak memory. `--memory-limit` and `--time-limit` still help.
- qpdf, ExifTool and LibreOffice are started per call and exit afterwards. Each LibreOffice call uses its own
  profile, so several workers can convert in parallel.
- The worker's OS user runs Chrome and the engines: it needs the same permissions as the web server user, see
  [security](security.md#run-chrome-as-a-dedicated-user).
- A generation failure is not retried: the error webhook is called once and the message is acknowledged. If a webhook
  endpoint cannot be reached or answers with a status of 300 or more, the handler throws and Messenger retries the
  message according to the transport's retry strategy, which generates the document again.
