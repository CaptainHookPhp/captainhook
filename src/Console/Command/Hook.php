<?php

/**
 * This file is part of CaptainHook
 *
 * (c) Sebastian Feldmann <sf@sebastian-feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CaptainHook\App\Console\Command;

use CaptainHook\App\Config;
use CaptainHook\App\Console\IOUtil;
use CaptainHook\App\Hook\Util;
use CaptainHook\App\Runner\Hook as RunnerHook;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Hook
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/captainhookphp/captainhook
 * @since   Class available since Release 0.9.0
 */
abstract class Hook extends RepositoryAware
{
    /**
     * Name of the hook to execute
     *
     * @var string
     */
    protected $hookName;

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('hook:' . $this->hookName)
             ->setDescription('Run git ' . $this->hookName . ' hook.')
             ->setHelp('This command executes the ' . $this->hookName . ' hook.');

        $this->addOption(
            'bootstrap',
            'b',
            InputOption::VALUE_OPTIONAL,
            'Relative path from your config file to your bootstrap file'
        );

        $this->addOption(
            'list-actions',
            'l',
            InputOption::VALUE_NONE,
            'List actions for this hook without running the hook'
        );

        $this->addOption(
            'action',
            'a',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'Run only the actions listed'
        );

        $this->addOption(
            'disable-plugins',
            null,
            InputOption::VALUE_NONE,
            'Disable all hook plugins'
        );
    }

    /**
     * Initialize the command by checking/modifying inputs before validation
     *
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // If `--list-actions` is present, we will ignore any arguments, since
        // this option intends to output a list of actions for the hook without
        // running the hook. So, if any arguments are required but not present
        // in the input, we will set them to an empty string in the input to
        // suppress any validation errors.
        if ($input->getOption('list-actions') === true) {
            foreach ($this->getDefinition()->getArguments() as $arg) {
                if ($arg->isRequired() && $input->getArgument($arg->getName()) === null) {
                    $input->setArgument($arg->getName(), '');
                }
            }
        }
    }

    /**
     * Execute the command
     *
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io         = $this->getIO($input, $output);
        $config     = $this->createConfig($input, true, ['git-directory', 'bootstrap']);
        $repository = $this->createRepository(dirname($config->getGitDirectory()));

        // if CaptainHook is executed via PHAR we have to make sure to load
        $this->handleBootstrap($config);

        // use ansi coloring only if not disabled in captainhook.json
        $output->setDecorated($config->useAnsiColors());

        // If the verbose option is present on the command line, then use it.
        // Otherwise, use the verbosity setting from the configuration.
        if (!$input->hasOption('verbose') || !$input->getOption('verbose')) {
            $output->setVerbosity(IOUtil::mapConfigVerbosity($config->getVerbosity()));
        }

        $class = '\\CaptainHook\\App\\Runner\\Hook\\' . Util::getHookCommand($this->hookName);
        /** @var \CaptainHook\App\Runner\Hook $hook */
        $hook  = new $class($io, $config, $repository);

        // If list-actions is true, then list the hook actions instead of running them.
        if ($input->getOption('list-actions') === true) {
            $this->listActions($output, $hook);
            return 0;
        }

        try {
            $hook->run();
            return 0;
        } catch (Exception $e) {
            if ($output->isDebug()) {
                throw $e;
            }
            return $this->handleError($output, $e);
        }
    }

    /**
     * Handles the bootstrap file inclusion
     *
     * @param \CaptainHook\App\Config $config
     */
    private function handleBootstrap(Config $config): void
    {
        if ($this->resolver->isPharRelease()) {
            $bootstrapFile = dirname($config->getPath()) . '/' . $config->getBootstrap();
            if (!file_exists($bootstrapFile)) {
                throw new RuntimeException('bootstrap file not found');
            }
            require $bootstrapFile;
        }
    }

    /**
     * Handle all hook errors
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @param  \Exception                                        $e
     * @return int
     */
    private function handleError(OutputInterface $output, Exception $e): int
    {
        $header = PHP_EOL . IOUtil::getLineSeparator() . PHP_EOL
                . IOUtil::formatHeadline(get_class($e), 80, '>', '<') . PHP_EOL
                . IOUtil::getLineSeparator();

        $output->writeLn($header, OutputInterface::VERBOSITY_VERBOSE);
        $output->writeLn(PHP_EOL . $e->getMessage());

        return 1;
    }

    /**
     * Print out a list of actions for this hook
     *
     * @param OutputInterface $output
     * @param RunnerHook $hook
     */
    private function listActions(OutputInterface $output, RunnerHook $hook): void
    {
        $output->writeln('<comment>Listing ' . $hook->getName() . ' actions:</comment>');

        if (!$hook->isEnabled()) {
            $output->writeln(' - hook is disabled');
            return;
        }

        $actions = $hook->getActions();
        if (count($actions) === 0) {
            $output->writeln(' - no actions configured');
            return;
        }

        foreach ($actions as $action) {
            $output->writeln(" - <fg=blue>{$action->getAction()}</>");
        }
    }
}
