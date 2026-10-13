<?php

namespace Daif\ChromePdfBundle\Enumeration;

use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;

enum Unit: string
{
    case Inches = 'in';
    case Points = 'pt';
    case Pixels = 'px';
    case Millimeters = 'mm';
    case Centimeters = 'cm';
    case Picas = 'pc';

    /**
     * @param string|int|float $raw Must respect format %f%s like '12in', '12.2px', '6pc' or '12' (spaces between the number and the unit are tolerated).
     *
     * @return array{float, self}
     *
     * @throws InvalidBuilderConfiguration if $raw does not follow correct format (unknown unit, garbage, negative value, ...)
     */
    public static function parse(string|int|float $raw, self $defaultUnit = self::Inches): array
    {
        if (\is_int($raw) || \is_float($raw)) {
            if (!is_finite((float) $raw) || $raw < 0) {
                throw new InvalidBuilderConfiguration(\sprintf('Unexpected value "%s", expected a positive number.', $raw));
            }

            return [(float) $raw, $defaultUnit];
        }

        if (!preg_match('/^\s*(\d+(?:\.\d*)?|\.\d+)(?:[eE]([-+]?\d+))?\s*([a-zA-Z]*)\s*$/', $raw, $matches)) {
            throw new InvalidBuilderConfiguration(\sprintf('Unexpected value "%s", expected format is "%%f%%s" (e.g. "12.5mm") with one of the units "%s".', $raw, implode('", "', array_column(self::cases(), 'value'))));
        }

        $value = (float) ($matches[1].('' !== $matches[2] ? 'e'.$matches[2] : ''));

        if ('' === $matches[3]) {
            return [$value, $defaultUnit];
        }

        $unit = self::tryFrom(strtolower($matches[3])) ?? throw new InvalidBuilderConfiguration(\sprintf('Unexpected unit "%s" in "%s", expected one of "%s".', $matches[3], $raw, implode('", "', array_column(self::cases(), 'value'))));

        return [$value, $unit];
    }

    /**
     * Converts a value expressed in this unit into inches (the unit expected by Chrome's Page.printToPDF).
     */
    public function toInches(float $value): float
    {
        return match ($this) {
            self::Inches => $value,
            self::Points => $value / 72.0,
            self::Pixels => $value / 96.0,
            self::Millimeters => $value / 25.4,
            self::Centimeters => $value / 2.54,
            self::Picas => $value / 6.0,
        };
    }

    /**
     * Parses a raw value (see {@see self::parse()}) and returns it converted into inches.
     *
     * @throws InvalidBuilderConfiguration
     */
    public static function parseToInches(string|int|float $raw, self $defaultUnit = self::Inches): float
    {
        [$value, $unit] = self::parse($raw, $defaultUnit);

        return $unit->toInches($value);
    }
}
