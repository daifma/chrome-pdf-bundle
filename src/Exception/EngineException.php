<?php

namespace Daif\ChromePdfBundle\Exception;

/**
 * Thrown by the local PDF engines (qpdf, exiftool, LibreOffice): binary not found,
 * command failure (with its exit code and error output), timeout or invalid input.
 */
class EngineException extends RuntimeException
{
}
