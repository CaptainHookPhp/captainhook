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
use CaptainHook\App\Console\IO;
use CaptainHook\App\Console\IOUtil;
use CaptainHook\App\Exception\ActionFailed;
use CaptainHook\App\Hook\Constrained;
use CaptainHook\App\Hooks;
use CaptainHook\App\Plugin;
use Exception;
use RuntimeException;

/**
 * Hook
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/captainhookphp/captainhook
 * @since   Class available since Release 0.9.0
 */
abstract class Hook extends RepositoryAware
{
    /**
     * Hook that should be handled.
     *
     * @var string
     */
    protected $hook;

    /**
     * Set to `true` to skip processing this hook's actions.
     *
     * @var bool
     */
    private $skipActions = false;

    /**
     * Plugins to apply to this hook.
     *
     * @var array<Plugin\Hook>|null
     */
    private $hookPlugins = null;

    /**
     * Return this hook's name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->hook;
    }

    /**
     * Execute stuff before executing any actions
     *
     * @return void
     */
    public function beforeHook(): void
    {
        $this->executeHookPluginsFor('beforeHook');
    }

    /**
     * Execute stuff before every actions
     *
     * @param Config\Action $action
     * @return void
     */
    public function beforeAction(Config\Action $action): void
    {
        $this->executeHookPluginsFor('beforeAction', $action);
    }

    /**
     * Execute stuff after every actions
     *
     * @param Config\Action $action
     * @return void
     */
    public function afterAction(Config\Action $action): void
    {
        $this->executeHookPluginsFor('afterAction', $action);
    }

    /**
     * Execute stuff after all actions
     *
     * @return void
     */
    public function afterHook(): void
    {
        $this->executeHookPluginsFor('afterHook');
    }

    /**
     * Execute the hook and all its actions
     *
     * @return void
     * @throws \Exception
     */
    public function run(): void
    {
        $hookConfigs = $this->getHookConfigsToHandle();

        /** @var IO\Base $io */
        $io = $this->io;

        $io->write('<comment>' . $this->hook . ':</comment> ');

        if ($io->getOption('disable-plugins')) {
            $io->write('<fg=magenta>Running with plugins disabled</>');
        }

        // if the hook and all triggered virtual hooks
        // are NOT enabled in the captainhook configuration skip the execution
        if (!$this->isAnyConfigEnabled($hookConfigs)) {
            $io->write(' - hook is disabled');
            return;
        }

        $this->beforeHook();

        $actions = $this->getActionsToExecute($hookConfigs);
        // are any actions configured
        if (count($actions) === 0) {
            $io->write(' - no actions to execute', true);
        } else {
            $this->executeActions($actions);
        }

        $this->afterHook();
    }

    /**
     * Return all configs that should be handled original and virtual
     *
     * @return \CaptainHook\App\Config\Hook[]
     */
    public function getHookConfigsToHandle(): array
    {
        $hookConfig = $this->config->getHookConfig($this->hook);
        $configs    = [$hookConfig];

        if (Hooks::triggersVirtualHook($hookConfig->getName())) {
            $configs[] = $this->config->getHookConfig(Hooks::getVirtualHook($hookConfig->getName()));
        }

        return $configs;
    }

    /**
     * Returns true if this hook or any applicable virtual hooks are enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isAnyConfigEnabled($this->getHookConfigsToHandle());
    }

    /**
     * @param  \CaptainHook\App\Config\Hook[] $configs
     * @return bool
     */
    private function isAnyConfigEnabled(array $configs): bool
    {
        foreach ($configs as $hookConfig) {
            if ($hookConfig->isEnabled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns `true` if something has indicated that the hook should skip all
     * remaining actions; pass a boolean value to toggle this
     *
     * There may be times you want to conditionally skip all actions, based on
     * logic in {@see beforeHook()}. Other times, you may wish to skip the rest
     * of the actions based on some condition of the current action.
     *
     * - To skip all actions for a hook, set this to `true`
     *   in {@see beforeHook()}.
     * - To skip the current action and all remaining actions, set this
     *   to `true` in {@see beforeAction()}.
     * - To run the current action but skip all remaining actions, set this
     *   to `true` in {@see afterAction()}.
     *
     * @param bool|null $shouldSkip
     * @return bool
     */
    public function shouldSkipActions(?bool $shouldSkip = null): bool
    {
        if ($shouldSkip !== null) {
            $this->skipActions = $shouldSkip;
        }

        return $this->skipActions;
    }

    /**
     * Returns all actions configured for this hook or applicable virtual hooks
     *
     * @return \CaptainHook\App\Config\Action[]
     */
    public function getActions(): array
    {
        return $this->getActionsToExecute($this->getHookConfigsToHandle());
    }

    /**
     * Return all the actions to execute
     *
     * Returns all actions from the triggered hook but also any actions of virtual hooks that might be triggered.
     * E.g. 'post-rewrite' or 'post-checkout' trigger the virtual/artificial 'post-change' hook.
     * Virtual hooks are special hooks to simplify configuration.
     *
     * @param  \CaptainHook\App\Config\Hook[] $hookConfigs
     * @return \CaptainHook\App\Config\Action[]
     */
    private function getActionsToExecute(array $hookConfigs): array
    {
        $actions = [];
        foreach ($hookConfigs as $hookConfig) {
            if ($hookConfig->isEnabled()) {
                $actions = array_merge($actions, $hookConfig->getActions());
            }
        }
        return $actions;
    }

    /**
     * Executes all the Actions configured for the hook
     *
     * @param  \CaptainHook\App\Config\Action[] $actions
     * @throws \Exception
     */
    private function executeActions(array $actions): void
    {
        if ($this->config->failOnFirstError()) {
            $this->executeFailOnFirstError($actions);
        } else {
            $this->executeFailAfterAllActions($actions);
        }
    }

    /**
     * Executes all actions and fails at the first error
     *
     * @param  \CaptainHook\App\Config\Action[] $actions
     * @return void
     * @throws \Exception
     */
    private function executeFailOnFirstError(array $actions): void
    {
        foreach ($actions as $action) {
            $this->handleAction($action);
        }
    }

    /**
     * Executes all actions but does not fail immediately
     *
     * @param \CaptainHook\App\Config\Action[] $actions
     * @return void
     * @throws \CaptainHook\App\Exception\ActionFailed
     */
    private function executeFailAfterAllActions(array $actions): void
    {
        $failedActions = 0;
        $errors        = '';

        foreach ($actions as $action) {
            try {
                $this->handleAction($action);
            } catch (Exception $exception) {
                $errors .= $exception->getMessage() . PHP_EOL . PHP_EOL;
                $failedActions++;
            }
        }

        if ($failedActions > 0) {
            throw new ActionFailed(
                '<error>' . $failedActions . ' action' . ($failedActions > 1 ? 's' : '') . ' failed</error>'
                . PHP_EOL
                . PHP_EOL . $errors
            );
        }
    }

    /**
     * Executes a configured hook action
     *
     * @param  \CaptainHook\App\Config\Action $action
     * @return void
     * @throws \Exception
     */
    private function handleAction(Config\Action $action): void
    {
        $this->io->write(' - <fg=blue>' . $this->formatActionOutput($action->getAction()) . '</> : ', false);

        if ($this->checkSkipAction($action)) {
            return;
        }

        $this->beforeAction($action);

        // The beforeAction() method may indicate that the current and all
        // remaining actions should be skipped. If so, return here.
        if ($this->shouldSkipActions()) {
            $this->io->write('<comment>deactivated</comment>', true);
            return;
        }

        try {
            $execMethod = self::getExecMethod(Util::getExecType($action->getAction()));
            $this->{$execMethod}($action);
            $this->io->write('<info>done</info>', true);
        } catch (Exception  $e) {
            $this->io->write('<error>failed</error>', true);
            throw $e;
        }

        $this->afterAction($action);
    }

    /**
     * Execute a php hook action
     *
     * @param  \CaptainHook\App\Config\Action $action
     * @return void
     * @throws \CaptainHook\App\Exception\ActionFailed
     */
    private function executePhpAction(Config\Action $action): void
    {
        $runner = new Action\PHP($this->hook);
        $runner->execute($this->config, $this->io, $this->repository, $action);
    }

    /**
     * Execute a cli hook action
     *
     * @param  \CaptainHook\App\Config\Action $action
     * @return void
     * @throws \CaptainHook\App\Exception\ActionFailed
     */
    private function executeCliAction(Config\Action $action): void
    {
        $runner = new Action\Cli();
        $runner->execute($this->config, $this->io, $this->repository, $action);
    }

    /**
     * Return the right method name to execute an action
     *
     * @param  string $type
     * @return string
     */
    public static function getExecMethod(string $type): string
    {
        $valid = ['php' => 'executePhpAction', 'cli' => 'executeCliAction'];

        if (!isset($valid[$type])) {
            throw new RuntimeException('invalid action type: ' . $type);
        }
        return $valid[$type];
    }

    /**
     * Check if the action should be skipped
     *
     * @param Config\Action $action
     * @return bool
     */
    private function checkSkipAction(Config\Action $action): bool
    {
        if (
            $this->shouldSkipActions()
            || $this->cliSkipAction($action)
            || !$this->doConditionsApply($action->getConditions())
        ) {
            $this->io->write('<comment>skipped</comment>', true);

            return true;
        }

        return false;
    }

    /**
     * Check if the CLI `action` options indicates the action should be skipped
     *
     * @param Config\Action $action
     * @return bool
     */
    private function cliSkipAction(Config\Action $action): bool
    {
        /** @var IO\Base $io */
        $io = $this->io;

        /** @var string[] $actionsToRun */
        $actionsToRun = $io->getOption('action', []);

        if (empty($actionsToRun)) {
            // No actions specified on CLI; run all actions.
            return false;
        }

        if (in_array($action->getAction(), $actionsToRun)) {
            // Action specified on CLI; do not skip.
            return false;
        }

        // Action not specified on CLI; skip.
        return true;
    }

    /**
     * Check if conditions apply
     *
     * @param  \CaptainHook\App\Config\Condition[] $conditions
     * @return bool
     */
    private function doConditionsApply(array $conditions): bool
    {
        $conditionRunner = new Condition($this->io, $this->repository, $this->hook);
        foreach ($conditions as $config) {
            if (!$conditionRunner->doesConditionApply($config)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Some fancy output formatting
     *
     * @param  string $action
     * @return string
     */
    private function formatActionOutput(string $action): string
    {
        $actionLength = 65;
        if (mb_strlen($action) < $actionLength) {
            return str_pad($action, $actionLength, ' ');
        }

        return mb_substr($action, 0, $actionLength - 3) . '...';
    }

    /**
     * Return plugins to apply to this hook.
     *
     * @return array<Plugin\Hook>
     */
    private function getHookPlugins(): array
    {
        if ($this->hookPlugins !== null) {
            return $this->hookPlugins;
        }

        $this->hookPlugins = [];

        foreach ($this->config->getPlugins() as $pluginConfig) {
            $pluginClass = $pluginConfig->getPlugin();
            if (!is_a($pluginClass, Plugin\Hook::class, true)) {
                continue;
            }

            $this->io->write(
                ['Configured Hook Plugin: <comment>' . $pluginClass . '</comment>'],
                true,
                IO::VERBOSE
            );

            if (
                is_a($pluginClass, Constrained::class, true)
                && !$pluginClass::getRestriction()->isApplicableFor($this->hook)
            ) {
                $this->io->write(
                    'Skipped because plugin is not applicable for hook ' . $this->hook,
                    true,
                    IO::VERBOSE
                );
                continue;
            }

            $plugin = new $pluginClass();
            $plugin->configure($this->config, $this->io, $this->repository, $pluginConfig);

            $this->hookPlugins[] = $plugin;
        }

        return $this->hookPlugins;
    }

    /**
     * Execute hook plugins for the given method name (i.e., beforeHook,
     * beforeAction, afterAction, afterHook).
     *
     * @param string $method
     * @param Config\Action|null $action
     * @return void
     */
    private function executeHookPluginsFor(string $method, ?Config\Action $action = null): void
    {
        /** @var IO\Base $io */
        $io = $this->io;

        if ($io->getOption('disable-plugins')) {
            return;
        }

        $plugins = $this->getHookPlugins();

        if (count($plugins) === 0) {
            $io->write(['', 'No plugins to execute for: <comment>' . $method . '</comment>'], true, IO::DEBUG);

            return;
        }

        $params = [$this];

        if ($action !== null) {
            $params[] = $action;
        }

        $io->write(['', 'Executing plugins for: <comment>' . $method . '</comment>'], true, IO::DEBUG);

        foreach ($plugins as $plugin) {
            $io->write(' <info>- Running ' . get_class($plugin) . '::' . $method . '</info>', true, IO::DEBUG);
            $plugin->{$method}(...$params);
        }
    }
}
