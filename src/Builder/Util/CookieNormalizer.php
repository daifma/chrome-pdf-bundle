<?php

namespace Daif\ChromePdfBundle\Builder\Util;

use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Normalizes cookies (arrays or Symfony Cookie objects) into the plain array
 * shape expected by the browser layer.
 *
 * @internal
 */
final class CookieNormalizer
{
    /**
     * @param Cookie|array<string, mixed> $cookie
     * @param string|null                 $defaultDomain used when a Symfony Cookie has no domain
     *
     * @return array{name: string, value: string, domain: string, path?: string, secure?: bool, httpOnly?: bool, sameSite?: 'Strict'|'Lax'|'None'}
     *
     * @throws InvalidBuilderConfiguration
     */
    public static function normalize(Cookie|array $cookie, string|null $defaultDomain = null): array
    {
        if ($cookie instanceof Cookie) {
            $domain = $cookie->getDomain() ?? $defaultDomain;
            if (null === $domain || '' === $domain) {
                throw new InvalidBuilderConfiguration(\sprintf('Cookie "%s" has no domain: Chrome requires one. Set it on the Cookie object or pass an array with a "domain" key.', $cookie->getName()));
            }

            $cookie = [
                'name' => $cookie->getName(),
                'value' => $cookie->getValue() ?? '',
                'domain' => $domain,
                'path' => $cookie->getPath(),
                'secure' => $cookie->isSecure(),
                'httpOnly' => $cookie->isHttpOnly(),
                'sameSite' => $cookie->getSameSite(),
            ];
        }

        ValidatorFactory::cookie($cookie);

        /** @var string $name */
        $name = $cookie['name'];
        /** @var string $domain */
        $domain = $cookie['domain'];
        /** @var scalar|\Stringable $value */
        $value = $cookie['value'];

        $normalized = [
            'name' => $name,
            'value' => \is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'domain' => $domain,
        ];

        if (\is_string($cookie['path'] ?? null) && '' !== $cookie['path']) {
            $normalized['path'] = $cookie['path'];
        }

        if (null !== ($cookie['secure'] ?? null)) {
            $normalized['secure'] = (bool) $cookie['secure'];
        }

        if (null !== ($cookie['httpOnly'] ?? null)) {
            $normalized['httpOnly'] = (bool) $cookie['httpOnly'];
        }

        if (\is_string($cookie['sameSite'] ?? null)) {
            $normalized['sameSite'] = match (strtolower($cookie['sameSite'])) {
                'strict' => 'Strict',
                'lax' => 'Lax',
                default => 'None',
            };
        }

        return $normalized;
    }
}
