<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\LoggerAwareTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\RequestAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\EnumNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @package Behavior\\Chromium\\Cookie
 */
trait CookieTrait
{
    use LoggerAwareTrait;
    use RequestAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * Cookies to store in the Chromium cookie jar.
     *
     * @param list<Cookie|array{name: string, value: string, domain: string, path?: string|null, secure?: bool|null, httpOnly?: bool|null, sameSite?: 'Strict'|'Lax'|null}> $cookies
     *
     * @example cookies([[ 'name' => 'my_cookie', 'value' => 'symfony', 'domain' => 'symfony.com', 'secure' => true, 'httpOnly' => true, 'sameSite' => 'Lax']])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('cookies', prototype: 'array', children: [
        new ScalarNodeBuilder('name', required: true, restrictTo: 'string'),
        new ScalarNodeBuilder('value', required: true),
        new ScalarNodeBuilder('domain', required: true, restrictTo: 'string'),
        new ScalarNodeBuilder('path', restrictTo: 'string'),
        new BooleanNodeBuilder('secure'),
        new BooleanNodeBuilder('httpOnly'),
        new EnumNodeBuilder('sameSite', values: ['Strict', 'Lax', 'None']),
    ]))]
    public function cookies(array $cookies): static
    {
        if ([] === $cookies) {
            $this->getBodyBag()->unset('cookies');

            return $this;
        }

        $this->addCookies($cookies);

        return $this;
    }

    /**
     * Add cookies to store in the Chromium cookie jar.
     *
     * @param list<Cookie|array{name: string, value: string, domain: string, path?: string|null, secure?: bool|null, httpOnly?: bool|null, sameSite?: 'Strict'|'Lax'|null}> $cookies
     *
     * @example addCookies([['name' => 'my_cookie','value' => 'symfony','domain' => 'symfony.com','secure' => true,'httpOnly' => true,'sameSite' => 'Lax']])
     */
    public function addCookies(array $cookies): static
    {
        ValidatorFactory::cookies($cookies);
        $c = $this->getBodyBag()->get('cookies', []);

        foreach ($cookies as $cookie) {
            if ($cookie instanceof Cookie) {
                $c[$cookie->getName()] = $cookie;

                continue;
            }

            $c[$cookie['name']] = $cookie;
        }

        $this->getBodyBag()->set('cookies', $c);

        return $this;
    }

    /**
     * If you want to add cookies and delete the ones already loaded in the configuration .
     *
     * @param Cookie|array{name: string, value: string, domain: string, path?: string|null, secure?: bool|null, httpOnly?: bool|null, sameSite?: 'Strict'|'Lax'|null} $cookie
     *
     * @example setCookie([ 'name' => 'my_cookie', 'value' => 'symfony', 'domain' => 'symfony.com', 'secure' => true, 'httpOnly' => true, 'sameSite' => 'Lax'])
     */
    public function setCookie(string $name, Cookie|array $cookie): static
    {
        $current = $this->getBodyBag()->get('cookies', []);
        $current[$name] = $cookie;

        $this->getBodyBag()->set('cookies', $current);

        return $this;
    }

    /**
     * If you want to forward cookies from the current request.
     *
     * @example forwardCookie('my_cookie')
     */
    public function forwardCookie(string $name): static
    {
        $request = $this->getCurrentRequest();

        if (null === $request) {
            $this->getLogger()?->debug('Cookie {daif_chrome_pdf.cookie_name} cannot be forwarded because there is no Request.', [
                'daif_chrome_pdf.cookie_name' => $name,
            ]);

            return $this;
        }

        if (false === $request->cookies->has($name)) {
            $this->getLogger()?->debug('Cookie {daif_chrome_pdf.cookie_name} does not exists.', [
                'daif_chrome_pdf.cookie_name' => $name,
            ]);

            return $this;
        }

        return $this->setCookie($name, [
            'name' => $name,
            'value' => (string) $request->cookies->get($name),
            'domain' => $request->getHost(),
        ]);
    }
}
