<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\File;

use Contao\CoreBundle\File\MediaCodec;
use Contao\CoreBundle\Tests\TestCase;

class MediaCodecTest extends TestCase
{
    public function testSortsByCodecEfficiency(): void
    {
        $this->assertLessThan(MediaCodec::getPriority('video.hevc.mp4'), MediaCodec::getPriority('video.av1.mp4'));
        $this->assertLessThan(MediaCodec::getPriority('video.vp9.webm'), MediaCodec::getPriority('video.hevc.mp4'));
        $this->assertLessThan(MediaCodec::getPriority('video.h264.mp4'), MediaCodec::getPriority('video.vp9.webm'));
        $this->assertLessThan(MediaCodec::getPriority('video.mp4'), MediaCodec::getPriority('video.h264.mp4'));
    }

    public function testTreatsAliasesAndCaseEqually(): void
    {
        $this->assertSame(MediaCodec::getPriority('video.hevc.mp4'), MediaCodec::getPriority('video.H265.mp4'));
        $this->assertSame(MediaCodec::getPriority('video.h264.mp4'), MediaCodec::getPriority('files/clip.AVC.mp4'));
    }

    public function testIgnoresFilesWithoutOrWithUnknownHint(): void
    {
        $this->assertSame(PHP_INT_MAX, MediaCodec::getPriority('video.mp4'));
        $this->assertSame(PHP_INT_MAX, MediaCodec::getPriority('video.final.mp4'));
        $this->assertSame(PHP_INT_MAX, MediaCodec::getPriority('av1.videos/video.mp4'));
    }
}
