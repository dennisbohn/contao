<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Filesystem\PublicUri;

use League\Flysystem\FilesystemAdapter;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SymlinkedLocalFilesProvider implements PublicUriProviderInterface
{
    public function __construct(
        private FilesystemAdapter $localFilesAdapter,
        private string $uploadDir,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Generates public URLs for the symlinked local files, so that they can be
     * provided directly by the web server.
     */
    public function getUri(FilesystemAdapter $adapter, string $adapterPath, OptionsInterface|null $options): UriInterface|null
    {
        if ($adapter !== $this->localFilesAdapter || null !== $options) {
            return null;
        }

        return new Uri(sprintf('%s/%s/%s', $this->getSchemeAndHost(), $this->uploadDir, $adapterPath));
    }

    private function getSchemeAndHost(): string
    {
        if (null === ($request = $this->requestStack->getMainRequest())) {
            return '';
        }

        return $request->getSchemeAndHttpHost();
    }
}
