<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Command;

use Contao\CoreBundle\Command\SuperviseWorkersCommand;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Util\ProcessUtil;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Process\Process;
use Toflar\CronjobSupervisor\BasicCommand;
use Toflar\CronjobSupervisor\Supervisor;

class SuperviseWorkersCommandTest extends TestCase
{
    #[DataProvider('autoscalingProvider')]
    public function testCorrectAmountOfWorkersAreCreated(int $messageCount, int $desiredSize, int $max, int $min, array $expectedCommands): void
    {
        $messengerTransportLocator = new Container();
        $messengerTransportLocator->set('prio_normal', $this->mockMessengerTransporter(0, false));
        $messengerTransportLocator->set('prio_high', $this->mockMessengerTransporter($messageCount, true));

        $processUtil = $this->createMock(ProcessUtil::class);
        $processUtil
            ->method('createSymfonyConsoleProcess')
            ->willReturnCallback(
                function () {
                    $process = $this->createMock(Process::class);
                    $process
                        ->method('getCommandLine')
                        ->willReturn(implode(' ', \func_get_args()))
                    ;

                    return $process;
                },
            )
        ;

        $commands = [];

        $supervisor = $this->createMock(Supervisor::class);
        $supervisor
            ->method('withCommand')
            ->willReturnCallback(
                static function (BasicCommand $command) use (&$commands, &$supervisor) {
                    $commands[] = $command;

                    return $supervisor;
                },
            )
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('close')
        ;

        $command = new SuperviseWorkersCommand(
            $messengerTransportLocator,
            $processUtil,
            $connection,
            $supervisor,
            $this->getWorkers($desiredSize, $max, $min),
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame($expectedCommands, $this->convertCommands($commands));
    }

    public static function autoscalingProvider(): iterable
    {
        yield 'Test minimum workers if no message count (minimum to 1)' => [
            0, // queue empty
            10,
            15,
            1,
            [
                '[worker-1 / 1] messenger:consume --time-limit=60 prio_normal',
                '[worker-2 / 1] messenger:consume --sleep=5 --time-limit=60 prio_high',
            ],
        ];

        yield 'Test minimum workers if no message count (minimum to 3)' => [
            0, // queue empty
            10,
            15,
            3,
            [
                '[worker-1 / 1] messenger:consume --time-limit=60 prio_normal',
                '[worker-2 / 3] messenger:consume --sleep=5 --time-limit=60 prio_high',
            ],
        ];

        yield 'Test minimum workers if we meet exactly the autoscaling target' => [
            10, // exactly desired target
            10,
            15,
            1,
            [
                '[worker-1 / 1] messenger:consume --time-limit=60 prio_normal',
                '[worker-2 / 1] messenger:consume --sleep=5 --time-limit=60 prio_high',
            ],
        ];

        yield 'Test starts a second process if double the desired target (autoscaling)' => [
            20, // exactly double the desired target
            10,
            15,
            1,
            [
                '[worker-1 / 1] messenger:consume --time-limit=60 prio_normal',
                '[worker-2 / 2] messenger:consume --sleep=5 --time-limit=60 prio_high',
            ],
        ];

        yield 'Test starts even more processes (autoscaling)' => [
            60,
            10,
            15,
            1,
            [
                '[worker-1 / 1] messenger:consume --time-limit=60 prio_normal',
                '[worker-2 / 6] messenger:consume --sleep=5 --time-limit=60 prio_high',
            ],
        ];

        yield 'Test respects maximum of 15 workers' => [
            9999, // very long queue
            10,
            15,
            1,
            [
                '[worker-1 / 1] messenger:consume --time-limit=60 prio_normal',
                '[worker-2 / 15] messenger:consume --sleep=5 --time-limit=60 prio_high',
            ],
        ];
    }

    /**
     * @param array<BasicCommand> $commands
     *
     * @return array<string>
     */
    private function convertCommands(array $commands): array
    {
        $converted = [];

        foreach ($commands as $command) {
            $converted[] = \sprintf('[%s / %d] %s',
                $command->getIdentifier(),
                $command->getNumProcs(),
                $command->startNewProcess()->getCommandLine(),
            );
        }

        return $converted;
    }

    private function mockMessengerTransporter(int $messageCount, bool $hasAutoscaling): MessageCountAwareInterface
    {
        $transport = $this->createMock(MessageCountAwareInterface::class);
        $transport
            ->expects($hasAutoscaling ? $this->once() : $this->never())
            ->method('getMessageCount')
            ->willReturn($messageCount)
        ;

        return $transport;
    }

    private function getWorkers(int $desiredSize, int $max, int $min): array
    {
        return [
            [
                'transports' => ['prio_normal'],
                'options' => ['--time-limit=60'],
                'autoscale' => [
                    'enabled' => false,
                ],
            ],
            [
                'transports' => ['prio_high'],
                'options' => ['--sleep=5', '--time-limit=60'],
                'autoscale' => [
                    'desired_size' => $desiredSize,
                    'max' => $max,
                    'min' => $min,
                    'enabled' => true,
                ],
            ],
        ];
    }
}
