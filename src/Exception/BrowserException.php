<?php

namespace Daif\ChromePdfBundle\Exception;

/**
 * Thrown by the browser engine for every conversion failure: Chrome not found or crashed,
 * timeouts, blocked targets, "fail on" conditions (HTTP status codes, resource loading
 * failures, console exceptions), PDF or screenshot errors.
 */
class BrowserException extends RuntimeException
{
}
