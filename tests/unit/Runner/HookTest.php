<?php

/**
 * This file is part of CaptainHook
 *
 * (c) Sebastian Feldmann <sf@sebastian-feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CaptainHook\App\Runner;

use CaptainHook\App\Config;
use CaptainHook\App\Config\Mockery as ConfigMockery;
use CaptainHook\App\Console\IO;
use CaptainHook\App\Console\IO\Mockery as IOMockery;
use CaptainHook\App\Hook\Restriction;
use CaptainHook\App\Hooks;
use CaptainHook\App\Mockery as CHMockery;
use CaptainHook\App\Plugin\DummyConstrainedPlugin;
use CaptainHook\App\Plugin\DummyConstrainedHookPlugin;
use CaptainHook\App\Plugin\DummyConstrainedHookPluginAlt;
use CaptainHook\App\Plugin\DummyPlugin;
use CaptainHook\App\Plugin\DummyHookPlugin;
use CaptainHook\App\Plugin\DummyHookPluginSkipsActions;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HookTest extends TestCase
{
    use ConfigMockery;
    use IOMockery;
    use CHMockery;

    protected function setUp(): void
    {
        // Ensure the static properties on dummy plugins are all set to their defaults.
        DummyHookPlugin::$beforeHookCalled = 0;
        DummyHookPlugin::$beforeActionCalled = 0;
        DummyHookPlugin::$afterActionCalled = 0;
        DummyHookPlugin::$afterHookCalled = 0;
        DummyHookPluginSkipsActions::$skipStartIn = 'beforeHook';
        DummyHookPluginSkipsActions::$skipStartAt = 1;
        DummyConstrainedHookPlugin::$restriction = null;
        DummyConstrainedHookPluginAlt::$restriction = null;
        DummyConstrainedPlugin::$restriction = null;
    }

    protected function tearDown(): void
    {
        // Reset the static properties on dummy plugins to their defaults.
        DummyHookPlugin::$beforeHookCalled = 0;
        DummyHookPlugin::$beforeActionCalled = 0;
        DummyHookPlugin::$afterActionCalled = 0;
        DummyHookPlugin::$afterHookCalled = 0;
        DummyHookPluginSkipsActions::$skipStartIn = 'beforeHook';
        DummyHookPluginSkipsActions::$skipStartAt = 1;
        DummyConstrainedHookPlugin::$restriction = null;
        DummyConstrainedHookPluginAlt::$restriction = null;
        DummyConstrainedPlugin::$restriction = null;
    }

    /**
     * Tests Hook::getActionRunner
     */
    public function testGetExecMethod(): void
    {
        $php = Hook::getExecMethod('php');
        $cli = Hook::getExecMethod('cli');

        $this->assertEquals('executePhpAction', $php);
        $this->assertEquals('executeCliAction', $cli);
    }

    /**
     * Tests Hook::getActionRunner
     */
    public function testGetRunnerFailure(): void
    {
        $this->expectException(Exception::class);

        Hook::getExecMethod('foo');
    }

    public function testGetName(): void
    {
        $io = $this->createIOMock();
        $config = $this->createConfigMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };

        $this->assertSame('pre-commit', $runner->getName());
    }

    public function testShouldSkipActionsIsFalseByDefault(): void
    {
        $io = $this->createIOMock();
        $config = $this->createConfigMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };

        $this->assertFalse($runner->shouldSkipActions());
    }

    public function testShouldSkipActionsCanBeSetToTrue(): void
    {
        $io = $this->createIOMock();
        $config = $this->createConfigMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };

        $this->assertTrue($runner->shouldSkipActions(true));
        $this->assertTrue($runner->shouldSkipActions());
    }

    public function testShouldSkipActionsCanBeSetToFalse(): void
    {
        $io = $this->createIOMock();
        $config = $this->createConfigMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };

        $runner->shouldSkipActions(true);

        $this->assertFalse($runner->shouldSkipActions(false));
        $this->assertFalse($runner->shouldSkipActions());
    }

    public function testRunHookWithPlugins(): void
    {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $this->markTestSkipped('not tested on windows');
        }

        $restriction1 = new Restriction(Hooks::POST_CHECKOUT);
        DummyConstrainedPlugin::$restriction = $restriction1;
        DummyConstrainedHookPluginAlt::$restriction = $restriction1;

        $restriction2 = new Restriction(Hooks::PRE_COMMIT);
        DummyConstrainedHookPlugin::$restriction = $restriction2;

        $pluginConfig1 = new Config\Plugin(DummyPlugin::class);
        $pluginConfig2 = new Config\Plugin(DummyHookPlugin::class);
        $pluginConfig3 = new Config\Plugin(DummyConstrainedPlugin::class);
        $pluginConfig4 = new Config\Plugin(DummyConstrainedHookPlugin::class);
        $pluginConfig5 = new Config\Plugin(DummyConstrainedHookPluginAlt::class);

        $config = $this->createConfigMock();
        $config->method('failOnFirstError')->willReturn(true);
        $config->method('getPlugins')->willReturn([
            $pluginConfig1,
            $pluginConfig2,
            $pluginConfig3,
            $pluginConfig4,
            $pluginConfig5,
        ]);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();
        $hookConfig = $this->createHookConfigMock();
        $actionConfig = $this->createActionConfigMock();
        $actionConfig->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success');
        $hookConfig->expects($this->atLeastOnce())->method('isEnabled')->willReturn(true);
        $hookConfig->expects($this->once())->method('getActions')->willReturn([$actionConfig, $actionConfig]);
        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);
        $io->expects($this->atLeast(1))->method('write');

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };
        $runner->run();

        $this->assertSame(2, DummyHookPlugin::$beforeHookCalled);
        $this->assertSame(4, DummyHookPlugin::$beforeActionCalled);
        $this->assertSame(4, DummyHookPlugin::$afterActionCalled);
        $this->assertSame(2, DummyHookPlugin::$afterHookCalled);
    }

    public function testRunHookSkipsActionsFromPluginBeforeHook(): void
    {
        DummyHookPluginSkipsActions::$skipStartAt = 1;

        $pluginConfig = new Config\Plugin(DummyHookPluginSkipsActions::class);

        $config = $this->createConfigMock();
        $config->method('failOnFirstError')->willReturn(true);
        $config->method('getPlugins')->willReturn([$pluginConfig]);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();
        $hookConfig = $this->createHookConfigMock();
        $actionConfig = $this->createActionConfigMock();
        $hookConfig->expects($this->atLeastOnce())->method('isEnabled')->willReturn(true);
        $hookConfig->expects($this->once())->method('getActions')->willReturn([
            $actionConfig,
            $actionConfig,
        ]);
        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);
        $io->expects($this->atLeast(1))->method('write');

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };
        $runner->run();

        $this->assertSame(1, DummyHookPlugin::$beforeHookCalled);
        $this->assertSame(0, DummyHookPlugin::$beforeActionCalled);
        $this->assertSame(0, DummyHookPlugin::$afterActionCalled);
        $this->assertSame(1, DummyHookPlugin::$afterHookCalled);
    }

    public function testRunHookSkipsActionsFromPluginBeforeAction(): void
    {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $this->markTestSkipped('not tested on windows');
        }

        DummyHookPluginSkipsActions::$skipStartIn = 'beforeAction';
        DummyHookPluginSkipsActions::$skipStartAt = 3;

        $pluginConfig = new Config\Plugin(DummyHookPluginSkipsActions::class);

        $config = $this->createConfigMock();
        $config->method('failOnFirstError')->willReturn(true);
        $config->method('getPlugins')->willReturn([$pluginConfig]);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();
        $hookConfig = $this->createHookConfigMock();
        $actionConfig = $this->createActionConfigMock();
        $actionConfig->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success');
        $hookConfig->expects($this->atLeastOnce())->method('isEnabled')->willReturn(true);
        $hookConfig->expects($this->once())->method('getActions')->willReturn([
            $actionConfig,
            $actionConfig,
            $actionConfig,
            $actionConfig,
            $actionConfig,
        ]);
        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);
        $io->expects($this->atLeast(1))->method('write');

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };
        $runner->run();

        $this->assertSame(1, DummyHookPlugin::$beforeHookCalled);
        $this->assertSame(3, DummyHookPlugin::$beforeActionCalled);
        $this->assertSame(2, DummyHookPlugin::$afterActionCalled);
        $this->assertSame(1, DummyHookPlugin::$afterHookCalled);
    }

    public function testGetActionsWithoutVirtualHooks(): void
    {
        $actionConfig1 = $this->createActionConfigMock();
        $actionConfig1->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --foo');

        $actionConfig2 = $this->createActionConfigMock();
        $actionConfig2->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --bar');

        $hookConfig = $this->createHookConfigMock();
        $hookConfig->expects($this->atLeast(1))->method('isEnabled')->willReturn(true);
        $hookConfig->expects($this->once())->method('getActions')->willReturn([$actionConfig1, $actionConfig2]);

        $config = $this->createConfigMock();
        $config->expects($this->once())->method('getHookConfig')->with('pre-commit')->willReturn($hookConfig);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };

        $this->assertSame([$actionConfig1, $actionConfig2], $runner->getActions());
    }

    public function testGetActionsWithVirtualHooks(): void
    {
        $actionConfig1 = $this->createActionConfigMock();
        $actionConfig1->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --foo');

        $actionConfig2 = $this->createActionConfigMock();
        $actionConfig2->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --bar');

        $actionConfig3 = $this->createActionConfigMock();
        $actionConfig3->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --baz');

        $hookConfig1 = $this->createHookConfigMock();
        $hookConfig1->expects($this->exactly(2))->method('getName')->willReturn('post-checkout');
        $hookConfig1->expects($this->atLeast(1))->method('isEnabled')->willReturn(true);
        $hookConfig1->expects($this->once())->method('getActions')->willReturn([$actionConfig1, $actionConfig2]);

        $hookConfig2 = $this->createHookConfigMock();
        $hookConfig2->expects($this->atLeast(1))->method('isEnabled')->willReturn(true);
        $hookConfig2->expects($this->once())->method('getActions')->willReturn([$actionConfig3]);

        $config = $this->createConfigMock();
        $config
            ->expects($this->exactly(2))
            ->method('getHookConfig')
            ->withConsecutive(['post-checkout'], ['post-change'])
            ->willReturn($hookConfig1, $hookConfig2);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::POST_CHECKOUT;
        };

        $this->assertSame([$actionConfig1, $actionConfig2, $actionConfig3], $runner->getActions());
    }

    public function testGetActionsReturnsEmptyArrayWhenNoConfigsAreEnabled(): void
    {
        $actionConfig1 = $this->createActionConfigMock();
        $actionConfig1->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --foo');

        $actionConfig2 = $this->createActionConfigMock();
        $actionConfig2->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --bar');

        $actionConfig3 = $this->createActionConfigMock();
        $actionConfig3->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --baz');

        $hookConfig1 = $this->createHookConfigMock();
        $hookConfig1->expects($this->exactly(2))->method('getName')->willReturn('post-checkout');
        $hookConfig1->expects($this->atLeast(1))->method('isEnabled')->willReturn(false);

        $hookConfig2 = $this->createHookConfigMock();
        $hookConfig2->expects($this->atLeast(1))->method('isEnabled')->willReturn(false);

        $config = $this->createConfigMock();
        $config
            ->expects($this->exactly(2))
            ->method('getHookConfig')
            ->withConsecutive(['post-checkout'], ['post-change'])
            ->willReturn($hookConfig1, $hookConfig2);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::POST_CHECKOUT;
        };

        $this->assertSame([], $runner->getActions());
    }

    public function testGetActionReturnsOnlyConfigsThatAreEnabled(): void
    {
        $actionConfig1 = $this->createActionConfigMock();
        $actionConfig1->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --foo');

        $actionConfig2 = $this->createActionConfigMock();
        $actionConfig2->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --bar');

        $actionConfig3 = $this->createActionConfigMock();
        $actionConfig3->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --baz');

        $hookConfig1 = $this->createHookConfigMock();
        $hookConfig1->expects($this->exactly(2))->method('getName')->willReturn('post-checkout');
        $hookConfig1->expects($this->atLeast(1))->method('isEnabled')->willReturn(false);

        $hookConfig2 = $this->createHookConfigMock();
        $hookConfig2->expects($this->atLeast(1))->method('isEnabled')->willReturn(true);
        $hookConfig2->expects($this->once())->method('getActions')->willReturn([$actionConfig3]);

        $config = $this->createConfigMock();
        $config
            ->expects($this->exactly(2))
            ->method('getHookConfig')
            ->withConsecutive(['post-checkout'], ['post-change'])
            ->willReturn($hookConfig1, $hookConfig2);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::POST_CHECKOUT;
        };

        $this->assertSame([$actionConfig3], $runner->getActions());
    }

    public function testIsEnabledReturnsFalseWhenNoConfigsAreEnabled(): void
    {
        $actionConfig1 = $this->createActionConfigMock();
        $actionConfig1->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --foo');

        $actionConfig2 = $this->createActionConfigMock();
        $actionConfig2->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --bar');

        $actionConfig3 = $this->createActionConfigMock();
        $actionConfig3->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --baz');

        $hookConfig1 = $this->createHookConfigMock();
        $hookConfig1->expects($this->exactly(2))->method('getName')->willReturn('post-checkout');
        $hookConfig1->expects($this->atLeast(1))->method('isEnabled')->willReturn(false);

        $hookConfig2 = $this->createHookConfigMock();
        $hookConfig2->expects($this->atLeast(1))->method('isEnabled')->willReturn(false);

        $config = $this->createConfigMock();
        $config
            ->expects($this->exactly(2))
            ->method('getHookConfig')
            ->withConsecutive(['post-checkout'], ['post-change'])
            ->willReturn($hookConfig1, $hookConfig2);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::POST_CHECKOUT;
        };

        $this->assertFalse($runner->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenAtLeastOneConfigIsEnabled(): void
    {
        $actionConfig1 = $this->createActionConfigMock();
        $actionConfig1->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --foo');

        $actionConfig2 = $this->createActionConfigMock();
        $actionConfig2->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --bar');

        $actionConfig3 = $this->createActionConfigMock();
        $actionConfig3->method('getAction')->willReturn(CH_PATH_FILES . '/bin/success --baz');

        $hookConfig1 = $this->createHookConfigMock();
        $hookConfig1->expects($this->exactly(2))->method('getName')->willReturn('post-checkout');
        $hookConfig1->expects($this->atLeast(1))->method('isEnabled')->willReturn(false);

        $hookConfig2 = $this->createHookConfigMock();
        $hookConfig2->expects($this->atLeast(1))->method('isEnabled')->willReturn(true);

        $config = $this->createConfigMock();
        $config
            ->expects($this->exactly(2))
            ->method('getHookConfig')
            ->withConsecutive(['post-checkout'], ['post-change'])
            ->willReturn($hookConfig1, $hookConfig2);

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::POST_CHECKOUT;
        };

        $this->assertTrue($runner->isEnabled());
    }

    public function testRunHookWhenPluginsAreDisabled(): void
    {
        $successProgram = CH_PATH_FILES . '/bin/success';

        $pluginConfig1 = new Config\Plugin(DummyHookPlugin::class);
        $pluginConfig2 = new Config\Plugin(DummyHookPlugin::class);

        $config = $this->createConfigMock();
        $config->method('failOnFirstError')->willReturn(false);
        $config->method('getPlugins')->willReturn([$pluginConfig1, $pluginConfig2]);

        $optionCallback = function (string $option) {
            switch ($option) {
                case 'disable-plugins':
                    return true;
                case 'action':
                    return [];
                default:
                    throw new InvalidArgumentException('Received invalid option: ' . $option);
            }
        };

        $io = $this->createIOMock();
        $repo = $this->createRepositoryMock();
        $hookConfig = $this->createHookConfigMock();
        $actionConfig = $this->createActionConfigMock();
        $actionConfig->expects($this->atLeastOnce())->method('getAction')->willReturn($successProgram);
        $hookConfig->expects($this->atLeastOnce())->method('isEnabled')->willReturn(true);
        $hookConfig->expects($this->once())->method('getActions')->willReturn([$actionConfig, clone $actionConfig]);
        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);

        $io
            ->method('getOption')
            ->with($this->logicalOr(
                $this->equalTo('disable-plugins'),
                $this->equalTo('action')
            ))
            ->willReturn($this->returnCallback($optionCallback));

        $io
            ->expects($this->exactly(8))
            ->method('write')
            ->withConsecutive(
                ['<comment>pre-commit:</comment> '],
                ['<fg=magenta>Running with plugins disabled</>'],
                [' - <fg=blue>' . $this->formatActionOutput($successProgram) . '</> : ', false],
                [['', 'foo', ''], true, IO::VERBOSE],
                ['<info>done</info>'],
                [' - <fg=blue>' . $this->formatActionOutput($successProgram) . '</> : ', false],
                [['', 'foo', ''], true, IO::VERBOSE],
                ['<info>done</info>']
            );

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };
        $runner->run();

        $this->assertSame(0, DummyHookPlugin::$beforeHookCalled);
        $this->assertSame(0, DummyHookPlugin::$beforeActionCalled);
        $this->assertSame(0, DummyHookPlugin::$afterActionCalled);
        $this->assertSame(0, DummyHookPlugin::$afterHookCalled);
    }

    public function testRunHookWhenActionsSpecifiedOnCli(): void
    {
        $successProgram = CH_PATH_FILES . '/bin/success';
        $failureProgram = CH_PATH_FILES . '/bin/failure';

        $optionCallback = function (string $option) {
            switch ($option) {
                case 'disable-plugins':
                    return true;
                case 'action':
                    return [CH_PATH_FILES . '/bin/success'];
                default:
                    throw new InvalidArgumentException('Received invalid option: ' . $option);
            }
        };

        $repo = $this->createRepositoryMock();

        $actionSuccessConfig = $this->createActionConfigMock();
        $actionSuccessConfig
            ->expects($this->atLeastOnce())
            ->method('getAction')
            ->willReturn($successProgram);

        $actionFailureConfig = $this->createActionConfigMock();
        $actionFailureConfig
            ->expects($this->atLeastOnce())
            ->method('getAction')
            ->willReturn($failureProgram);

        $hookConfig = $this->createHookConfigMock();
        $hookConfig->expects($this->atLeastOnce())->method('isEnabled')->willReturn(true);
        $hookConfig
            ->expects($this->once())
            ->method('getActions')
            ->willReturn([$actionSuccessConfig, $actionFailureConfig]);

        $config = $this->createConfigMock();
        $config->method('failOnFirstError')->willReturn(false);
        $config->method('getPlugins')->willReturn([]);
        $config->expects($this->once())->method('getHookConfig')->willReturn($hookConfig);

        $io = $this->createIOMock();

        $io
            ->method('getOption')
            ->with($this->logicalOr(
                $this->equalTo('disable-plugins'),
                $this->equalTo('action')
            ))
            ->willReturn($this->returnCallback($optionCallback));

        $io
            ->expects($this->exactly(7))
            ->method('write')
            ->withConsecutive(
                ['<comment>pre-commit:</comment> '],
                ['<fg=magenta>Running with plugins disabled</>'],
                [' - <fg=blue>' . $this->formatActionOutput($successProgram) . '</> : ', false],
                [['', 'foo', ''], true, IO::VERBOSE],
                ['<info>done</info>'],
                [' - <fg=blue>' . $this->formatActionOutput($failureProgram) . '</> : ', false],
                ['<comment>skipped</comment>']
            );

        $runner = new class ($io, $config, $repo) extends Hook {
            protected $hook = Hooks::PRE_COMMIT;
        };
        $runner->run();
    }

    private function formatActionOutput(string $action): string
    {
        $actionLength = 65;
        if (mb_strlen($action) < $actionLength) {
            return str_pad($action, $actionLength, ' ');
        }

        return mb_substr($action, 0, $actionLength - 3) . '...';
    }
}
