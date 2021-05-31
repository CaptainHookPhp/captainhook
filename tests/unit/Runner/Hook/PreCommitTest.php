<?php

/**
 * This file is part of CaptainHook
 *
 * (c) Sebastian Feldmann <sf@sebastian-feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CaptainHook\App\Runner\Hook;

use CaptainHook\App\Config\Mockery as ConfigMockery;
use CaptainHook\App\Console\IO\Mockery as IOMockery;
use CaptainHook\App\Exception\ActionFailed;
use CaptainHook\App\Mockery as CHMockery;
use PHPUnit\Framework\TestCase;

class PreCommitTest extends TestCase
{
    use ConfigMockery;
    use IOMockery;
    use CHMockery;

    /**
     * Tests PreCommit::run
     *
     * @throws \Exception
     */
    public function testRunHookEnabled(): void
    {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $this->markTestSkipped('not tested on windows');
        }

        // fail on first error must be active
        $config = $this->createConfigMock();
        $config->method('failOnFirstError')->willReturn(true);

        $io           = $this->createIOMock();
        $repo         = $this->createRepositoryMock();
        $hookConfig   = $this->createHookConfigMock();
        $actionConfig = $this->createActionConfigMock();
        $actionConfig->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success');
        $hookConfig->expects($this->atLeast(1))->method('isEnabled')->willReturn(true);
        $hookConfig->expects($this->once())->method('getActions')->willReturn([$actionConfig]);
        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);
        $io->expects($this->atLeast(1))->method('write');

        $runner = new PreCommit($io, $config, $repo);
        $runner->run();
    }

    /**
     * Tests PreCommit::run
     *
     * @throws \Exception
     */
    public function testRunHookDontFailOnFirstError(): void
    {
        $this->expectException(ActionFailed::class);

        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $this->markTestSkipped('not tested on windows');
        }
        // we have to create a config that does not fail on first error
        $config              = $this->createConfigMock();
        $config->expects($this->once())->method('failOnFirstError')->willReturn(false);

        $io                  = $this->createIOMock();
        $repo                = $this->createRepositoryMock();
        $hookConfig          = $this->createHookConfigMock();
        $actionConfigFail    = $this->createActionConfigMock();
        $actionConfigSuccess = $this->createActionConfigMock();

        // every action has to get executed
        $actionConfigFail->expects($this->atLeastOnce())
                         ->method('getAction')
                         ->willReturn(CH_PATH_FILES . '/bin/failure');

        // so even if the first actions fails this action has to get executed
        $actionConfigSuccess->expects($this->atLeastOnce())
                            ->method('getAction')
                            ->willReturn(CH_PATH_FILES . '/bin/success');

        $actionConfigWithReallyLongName = $this->createActionConfigMock();
        $actionConfigWithReallyLongName
            ->expects($this->atLeastOnce())
            ->method('getAction')
            ->willReturn(CH_PATH_FILES . '/bin/success --really-long-option-name-to-ensure-this-is-over-65-characters');

        $hookConfig->expects($this->atLeast(1))->method('isEnabled')->willReturn(true);
        $hookConfig->expects($this->once())
                   ->method('getActions')
                   ->willReturn([$actionConfigFail, $actionConfigSuccess, $actionConfigWithReallyLongName]);

        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);
        $io->expects($this->atLeast(1))->method('write');

        $runner = new PreCommit($io, $config, $repo);
        $runner->run();
    }

    /**
     * Tests PreCommit::run
     *
     * @throws \Exception
     */
    public function testRunHookDisabled(): void
    {
        $io           = $this->createIOMock();
        $config       = $this->createConfigMock();
        $hookConfig   = $this->createHookConfigMock();
        $repo         = $this->createRepositoryMock();
        $hookConfig->expects($this->once())->method('isEnabled')->willReturn(false);
        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);
        $io->expects($this->atLeast(1))->method('write');

        $runner = new PreCommit($io, $config, $repo);
        $runner->run();
    }
}
