<?php
/**
 * This file is part of CaptainHook.
 *
 * (c) Sebastian Feldmann <sf@sebastian.feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace CaptainHook\App\Console\Application;

use CaptainHook\App\Hook\Util;
use CaptainHook\App\Console\Command;
use CaptainHook\App\Hooks;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Hook
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/sebastianfeldmann/captainhook
 * @since   Class available since Release 0.9.0
 */
class Hook extends ConfigHandler
{
    /**
     * Path to the git repository
     *
     * @var string
     */
    protected $repositoryPath;

    /**
     * Hook that gets executed.
     *
     * @var string
     */
    protected $hookToExecute;

    /**
     * Hook to command map
     *
     * @var array
     */
    protected $hookCommandMap = [
        Hooks::PRE_COMMIT => 'PreCommit',
        Hooks::COMMIT_MSG => 'CommitMsg',
        Hooks::PRE_PUSH   => 'PrePush'
    ];

    /**
     * Repository path setter.
     *
     * @param string $git
     */
    public function setRepositoryPath(string $git)
    {
        $this->repositoryPath = $git;
    }

    /**
     * Get the git repository root path.
     *
     * @return string
     */
    public function getRepositoryPath() : string
    {
        if (empty($this->repositoryPath)) {
            $this->repositoryPath = (string) getcwd();
        }
        return $this->repositoryPath;
    }

    /**
     * Set the hook to execute.
     *
     * @param  string $hook
     * @return \CaptainHook\App\Console\Application\Hook
     */
    public function setHook(string $hook)
    {
        if (!Util::isValid($hook)) {
            throw new \RuntimeException('Invalid hook name');
        }
        $this->hookToExecute = $hook;
        return $this;
    }

    /**
     * Execute hook.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     * @throws \Exception
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $output->setDecorated(false);
        $input->setInteractive(false);

        $command = $this->createCommand();
        return $command->run($input, $output);
    }

    /**
     * Create the hook command.
     *
     * @return \CaptainHook\App\Console\Command\Hook
     */
    private function createCommand() : Command\Hook
    {
        /* @var \CaptainHook\App\Console\Command\Hook $command */
        $class   = '\\CaptainHook\\App\\Console\\Command\\Hook\\' . $this->getHookCommand();
        $command = new $class($this->getConfigFile(), $this->getRepositoryPath());
        $command->setHelperSet($this->getHelperSet());

        return $command;
    }

    /**
     * Get the command class name to execute.
     *
     * @return string
     */
    private function getHookCommand() : string
    {
        if (null === $this->hookToExecute) {
            throw new \RuntimeException('No hook to execute');
        }
        return $this->hookCommandMap[$this->hookToExecute];
    }
}
