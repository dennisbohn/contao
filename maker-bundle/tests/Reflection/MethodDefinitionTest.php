<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\MakerBundle\Tests\Reflection;

use Contao\MakerBundle\Reflection\MethodDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MethodDefinitionTest extends TestCase
{
    #[DataProvider('getReturnValues')]
    public function testSetsTheCorrectMethodBody(string|null $returnType, string $expected, string|null $body = null): void
    {
        $hookDefinition = new MethodDefinition($returnType, [], $body);

        $this->assertSame($returnType, $hookDefinition->getReturnType());
        $this->assertSame([], $hookDefinition->getParameters());
        $this->assertSame($expected, $hookDefinition->getBody());
    }

    public static function getReturnValues(): iterable
    {
        yield ['string', "return '';"];
        yield ['?string', 'return null;'];
        yield ['array', 'return [];'];
        yield ['bool', 'return true;'];
        yield [null, '// Do something'];
        yield ['Foo\Bar\Class', '// Do something'];
        yield ['string', 'return $foo;', 'return $foo;'];
    }
}
