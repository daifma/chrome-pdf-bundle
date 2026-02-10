# Configuration

The default configuration for the bundle:

```yaml
# config/packages/daif_chrome_pdf.yaml

daif_chrome_pdf:

    # Base directory for assets, files, markdown
    assets_directory: '%kernel.project_dir%/assets'

    # Enables the listener on kernel.view to stream ChromePdfFileResult objects
    controller_listener: true

    default_options:
        pdf:
            html:

                # Add default header to the builder
                header:
                    template: null
                    context: []

                # Add default footer to the builder
                footer:
                    template: null
                    context: []

                # Print the entire content in one single page - default false
                single_page: null

                # Standard paper size: "letter", "legal", "tabloid", "ledger", "A0"-"A6"
                paper_standard_size: null

                # Paper width, in inches - default 8.5
                paper_width: null

                # Paper height, in inches - default 11
                paper_height: null

                # Top margin, in inches - default 0.39
                margin_top: null

                # Bottom margin, in inches - default 0.39
                margin_bottom: null

                # Left margin, in inches - default 0.39
                margin_left: null

                # Right margin, in inches - default 0.39
                margin_right: null

                # Prefer page size as defined by CSS - default false
                prefer_css_page_size: null

                # Print the background graphics - default false
                print_background: null

                # Hide default white background for transparency - default false
                omit_background: null

                # Paper orientation to landscape - default false
                landscape: null

                # Scale of the page rendering (e.g., 1.0) - default 1.0
                scale: null

                # Page ranges to print, e.g., "1-5, 8, 11-13" - default all pages
                native_page_ranges: null

                # Duration to wait before converting (e.g., "5s") - default none
                wait_delay: null

                # JavaScript expression to wait for before converting - default none
                wait_for_expression: null

                # Media type to emulate: "screen" or "print" - default "print"
                emulated_media_type: null # One of "print"; "screen"

                # Cookies to store in the Chromium cookie jar
                cookies:
                    -
                        name: ~
                        value: ~
                        domain: ~
                        path: null
                        secure: null
                        httpOnly: null
                        sameSite: null # One of "Strict"; "Lax"; "None"

                # Override the default User-Agent header
                user_agent: null

                # Extra HTTP headers sent by Chromium while loading the document
                extra_http_headers: []

                # Skip the network idle event for faster conversion - default false
                skip_network_idle_event: null

            url:
                # Same options as html (header, footer, paper size, margins, etc.)
                # ...

            markdown:
                # Same options as html (header, footer, paper size, margins, etc.)
                # ...

        screenshot:
            html:

                # Device screen width in pixels - default 800
                width: null

                # Device screen height in pixels - default 600
                height: null

                # Clip screenshot to device dimensions - default false
                clip: null

                # Image format: "png", "jpeg", or "webp" - default png
                format: null

                # Compression quality 0-100 (jpeg only) - default 100
                quality: null

                # Hide default white background for transparency - default false
                omit_background: null

                # Optimize image encoding for speed - default false
                optimize_for_speed: null

                # Same chromium options as PDF (wait_delay, cookies, headers, etc.)
                # ...

            url:
                # Same options as html
                # ...

            markdown:
                # Same options as html
                # ...
```

## Header and footer default templates

You can set default header and/or footer templates for your PDFs. If your template contains variables, add them under `context`:

```yaml
daif_chrome_pdf:
    assets_directory: 'assets'
    default_options:
        pdf:
            html:
                header:
                    template: 'header.html.twig'
                    context:
                        title: 'Hello'
                        first_name: 'Jean Michel'
                footer:
                    template: 'footer.html.twig'
                    context:
                        foo: 'bar'
```

## Extra HTTP headers

HTTP headers sent by Chromium while loading the HTML document:

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            html:
                extra_http_headers:
                    'My-Header': 'MyValue'
```

Or using the list syntax:

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            html:
                extra_http_headers:
                    - { name: 'My-Header', value: 'MyValue' }
```

## Cookies

Cookies to store in the Chromium cookie jar:

```yaml
daif_chrome_pdf:
    default_options:
        pdf:
            html:
                cookies:
                    - { name: 'yummy_cookie', value: 'choco', domain: 'example.com' }
                    - { name: 'my_cookie', value: 'symfony', domain: 'symfony.com', secure: true, httpOnly: true, sameSite: 'Lax' }
```

## Controller Listener

Whenever a controller returns something other than a `Response` object, the [`kernel.view`](https://symfony.com/doc/current/reference/events.html#kernel-view) event is fired.
The listener detects `ChromePdfFileResult` objects and automatically calls `->stream()` to convert them to a Response.

Enabled by default, can be disabled via configuration:

```yaml
daif_chrome_pdf:
    controller_listener: false
```
