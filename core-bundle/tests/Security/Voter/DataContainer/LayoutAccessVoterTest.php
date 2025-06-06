<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Security\Voter\DataContainer;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\Voter\DataContainer\LayoutAccessVoter;

class LayoutAccessVoterTest extends AbstractAccessVoterTestCase
{
    public static function votesProvider(): \Generator
    {
        // Permission granted, so abstain! Our voters either deny or abstain, they must
        // never grant access (see #6201).
        yield [
            ['user' => 2],
            [
                [[ContaoCorePermissions::USER_CAN_ACCESS_MODULE], 'themes', true],
                [[ContaoCorePermissions::USER_CAN_ACCESS_LAYOUTS], null, true],
            ],
            true,
        ];

        // Permission denied
        yield [
            ['user' => 3],
            [
                [[ContaoCorePermissions::USER_CAN_ACCESS_MODULE], 'themes', true],
                [[ContaoCorePermissions::USER_CAN_ACCESS_LAYOUTS], null, false],
            ],
            false,
        ];
    }

    protected function getVoterClass(): string
    {
        return LayoutAccessVoter::class;
    }

    protected function getTable(): string
    {
        return 'tl_layout';
    }
}
