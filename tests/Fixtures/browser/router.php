<?php

// Router for the PHP built-in web server used by tests/Browser/ChromeBrowserTest.php.

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH);
parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_QUERY), $query);

if (1 === preg_match('#^/status/(\d{3})$#', $path, $matches)) {
    http_response_code((int) $matches[1]);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>STATUS '.$matches[1].'</p>';

    return true;
}

if ('/image-status' === $path) {
    $code = (int) ($query['code'] ?? 404);
    $host = \is_string($query['host'] ?? null) ? $query['host'] : '127.0.0.1';
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>PAGE</p><img src="http://'.htmlspecialchars($host).':'.$_SERVER['SERVER_PORT'].'/status/'.$code.'?img">';

    return true;
}

if ('/echo' === $path) {
    header('Content-Type: text/html; charset=utf-8');
    printf(
        '<p>COOKIE=%s</p><p>HEADER=%s</p><p>AGENT=%s</p>',
        htmlspecialchars((string) ($_COOKIE['chrome_pdf_test'] ?? 'none')),
        htmlspecialchars((string) ($_SERVER['HTTP_X_CHROME_PDF_TEST'] ?? 'none')),
        htmlspecialchars((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'none')),
    );

    return true;
}

http_response_code(404);
echo 'Not found';

return true;
