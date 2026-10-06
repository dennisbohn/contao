<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\File;

/**
 * Determines the sort priority of a video file from a codec hint in its file
 * name, e.g. "video.av1.mp4" or "video.h265.mp4".
 *
 * Browsers pick the first <source> they can play. Files with different codecs in the
 * same container (AV1 and H.265 are both .mp4) share the MIME type, so the media type
 * priority cannot order them. The hint sorts them by codec efficiency, so that the most
 * efficient codec is offered first and a browser without a matching decoder falls
 * through to the next source. Files without a hint are not affected.
 */
final class MediaCodec
{
    /**
     * Supported hints, ordered by efficiency (most efficient first). Aliases share
     * the position of their first occurrence.
     */
    private const HINTS = [
        ['av1'],
        ['hevc', 'h265'],
        ['vp9'],
        ['h264', 'avc'],
    ];

    /**
     * Returns the sort priority (lower is better); files without a hint come last.
     */
    public static function getPriority(string $path): int
    {
        if (!preg_match('/\.([a-z0-9]+)\.[a-z0-9]+$/i', basename($path), $matches)) {
            return PHP_INT_MAX;
        }

        $hint = strtolower($matches[1]);

        foreach (self::HINTS as $priority => $aliases) {
            if (\in_array($hint, $aliases, true)) {
                return $priority;
            }
        }

        return PHP_INT_MAX;
    }
}
