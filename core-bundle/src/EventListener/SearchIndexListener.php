<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener;

use Contao\CoreBundle\Crawl\Escargot\Factory;
use Contao\CoreBundle\Messenger\Message\SearchIndexMessage;
use Contao\CoreBundle\Search\Document;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsEventListener]
class SearchIndexListener
{
    final public const FEATURE_INDEX = 0b01;

    final public const FEATURE_DELETE = 0b10;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly string $fragmentPath = '_fragment',
        private readonly string $contaoBackendRoutePrefix = '/contao',
        private readonly int $enabledFeatures = self::FEATURE_INDEX | self::FEATURE_DELETE,
    ) {
    }

    /**
     * Checks if the request can be indexed and forwards it accordingly.
     */
    public function __invoke(TerminateEvent $event): void
    {
        $response = $event->getResponse();

        if ($response->isRedirection()) {
            return;
        }

        $request = $event->getRequest();

        // Only handle GET requests (see #1194, #7240)
        if (!$request->isMethod(Request::METHOD_GET)) {
            return;
        }

        // Do not handle fragments
        if (preg_match('~(?:^|/)'.preg_quote($this->fragmentPath, '~').'/~', $request->getPathInfo())) {
            return;
        }

        // Do not handle Contao backend requests
        if (preg_match('~(?:^|/)'.preg_quote($this->contaoBackendRoutePrefix, '~').'~', $request->getPathInfo())) {
            return;
        }

        $document = Document::createFromRequestResponse($request, $response);
        $needsIndex = $this->needsIndex($request, $response, $document);
        $needsDelete = $this->needsDelete($response, $document);

        if ($needsIndex && $this->enabledFeatures & self::FEATURE_INDEX) {
            $this->messageBus->dispatch(SearchIndexMessage::createWithIndex($document));
        }

        if ($needsDelete && $this->enabledFeatures & self::FEATURE_DELETE) {
            $this->messageBus->dispatch(SearchIndexMessage::createWithDelete($document));
        }
    }

    private function needsIndex(Request $request, Response $response, Document $document): bool
    {
        // Do not index if response was not successful
        if (!$response->isSuccessful()) {
            return false;
        }

        // Do not index if called by crawler
        if (Factory::USER_AGENT === $request->headers->get('User-Agent')) {
            return false;
        }

        // Do not index if the X-Robots-Tag header contains "noindex"
        if (str_contains((string) $response->headers->get('X-Robots-Tag', ''), 'noindex')) {
            return false;
        }

        try {
            $robots = $document->getContentCrawler()->filterXPath('//head/meta[@name="robots"]')->first()->attr('content');

            // Do not index if the meta robots tag contains "noindex"
            if (str_contains((string) $robots, 'noindex')) {
                return false;
            }
        } catch (\Exception) {
            // No meta robots tag found
        }

        // If there are no json ld scripts at all, this should not be handled by our indexer
        return [] !== $document->extractJsonLdScripts();
    }

    private function needsDelete(Response $response, Document $document): bool
    {
        // Always delete on 404 and 410 responses
        if (\in_array($response->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_GONE], true)) {
            return true;
        }

        // Do not delete if the response was not successful
        if (!$response->isSuccessful()) {
            return false;
        }

        // Delete if the X-Robots-Tag header contains "noindex"
        if (str_contains($response->headers->get('X-Robots-Tag', ''), 'noindex')) {
            return true;
        }

        try {
            $robots = $document->getContentCrawler()->filterXPath('//head/meta[@name="robots"]')->first()->attr('content');

            // Delete if the meta robots tag contains "noindex"
            if (str_contains($robots, 'noindex')) {
                return true;
            }
        } catch (\Exception) {
            // No meta robots tag found
        }

        // Otherwise do not delete
        return false;
    }
}
