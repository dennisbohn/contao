<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Asset;

use Contao\PageModel;
use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal Do not use this class in your code; use the "contao.assets.assets_context" or "contao.assets.files_context" service instead
 */
class ContaoContext implements ContextInterface
{
    public function __construct(private RequestStack $requestStack, private string $field, private bool $debug = false)
    {
    }

    public function getBasePath(): string
    {
        if (null === ($request = $this->requestStack->getMainRequest())) {
            return '';
        }

        if ($this->debug || '' === ($staticUrl = $this->getFieldValue($this->getPageModel()))) {
            return $request->getBasePath();
        }

        $protocol = $this->isSecure() ? 'https' : 'http';
        $relative = preg_replace('@https?://@', '', $staticUrl);

        return sprintf('%s://%s%s', $protocol, $relative, $request->getBasePath());
    }

    public function isSecure(): bool
    {
        $page = $this->getPageModel();

        if (null !== $page) {
            return $page->loadDetails()->rootUseSSL;
        }

        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            return false;
        }

        return $request->isSecure();
    }

    /**
     * Returns the base path with a trailing slash.
     */
    public function getStaticUrl(): string
    {
        return $this->getBasePath().'/';
    }

    private function getPageModel(): PageModel|null
    {
        $request = $this->requestStack->getMainRequest();

        if ($request && ($pageModel = $request->attributes->get('pageModel')) instanceof PageModel) {
            return $pageModel;
        }

        return null;
    }

    /**
     * Returns a field value from the page model.
     */
    private function getFieldValue(PageModel|null $page): string
    {
        return (string) $page?->{$this->field};
    }
}
