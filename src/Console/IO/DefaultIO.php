<?php

/**
 * This file is part of CaptainHook
 *
 * (c) Sebastian Feldmann <sf@sebastian-feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CaptainHook\App\Console\IO;

use CaptainHook\App\Console\IO;
use CaptainHook\App\Console\IOUtil;
use SebastianFeldmann\Cli\Reader\StandardInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class DefaultIO
 *
 * @package CaptainHook
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/captainhookphp/captainhook
 * @since   Class available since Release 0.9.0
 */
class DefaultIO extends Base
{
    /**
     * Piped standard input handler
     *
     * @var resource
     */
    private $stdIn;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Symfony\Component\Console\Helper\HelperSet
     */
    protected $helperSet;

    /**
     * @var array<int, int>
     */
    private $verbosityMap;

    /**
     * Constructor
     *
     * @param resource                                          $stdIn
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Symfony\Component\Console\Helper\HelperSet       $helperSet
     */
    public function __construct($stdIn, InputInterface $input, OutputInterface $output, HelperSet $helperSet = null)
    {
        $this->stdIn        = $stdIn;
        $this->input        = $input;
        $this->output       = $output;
        $this->helperSet    = $helperSet;
        $this->verbosityMap = [
            IO::QUIET        => OutputInterface::VERBOSITY_QUIET,
            IO::NORMAL       => OutputInterface::VERBOSITY_NORMAL,
            IO::VERBOSE      => OutputInterface::VERBOSITY_VERBOSE,
            IO::VERY_VERBOSE => OutputInterface::VERBOSITY_VERY_VERBOSE,
            IO::DEBUG        => OutputInterface::VERBOSITY_DEBUG
        ];
    }

    /**
     * Return the original cli arguments
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->input->getArguments();
    }

    /**
     * Return the original cli argument or a given default
     *
     * @param  string $name
     * @param  string $default
     * @return string
     */
    public function getArgument(string $name, string $default = ''): string
    {
        return (string)($this->getArguments()[$name] ?? $default);
    }

    /**
     * Return the original cli options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->input->getOptions();
    }

    /**
     * Return the original cli option or a given default
     *
     * @param string $name
     * @param string|string[]|bool|null $default
     * @return string|string[]|bool|null
     */
    public function getOption(string $name, $default = null)
    {
        return $this->getOptions()[$name] ?? $default;
    }

    /**
     * Return the piped in standard input
     *
     * @return string[]
     */
    public function getStandardInput(): array
    {
        $iterator = new StandardInput($this->stdIn);
        $stdIn    = [];
        foreach ($iterator as $line) {
            $stdIn[] = $line;
        }
        return $stdIn;
    }

    /**
     * {@inheritDoc}
     */
    public function isInteractive()
    {
        return $this->input->isInteractive();
    }

    /**
     * {@inheritDoc}
     */
    public function isVerbose()
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * {@inheritDoc}
     */
    public function isVeryVerbose()
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
    }

    /**
     * {@inheritDoc}
     */
    public function isDebug()
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;
    }

    /**
     * {@inheritDoc}
     */
    public function write($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->doWrite($messages, $newline, false, $verbosity);
    }

    /**
     * {@inheritDoc}
     */
    public function writeError($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->doWrite($messages, $newline, true, $verbosity);
    }

    /**
     * Write to the appropriate user output
     *
     * @param array|string $messages
     * @param bool         $newline
     * @param bool         $stderr
     * @param int          $verbosity
     */
    private function doWrite($messages, $newline, $stderr, $verbosity)
    {
        $sfVerbosity = $this->verbosityMap[$verbosity];
        if ($sfVerbosity > $this->output->getVerbosity()) {
            return;
        }

        $this->getOutputToWriteTo($stderr)->write($messages, $newline, $sfVerbosity);
    }

    /**
     * {@inheritDoc}
     */
    public function ask($question, $default = null)
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper   = $this->helperSet->get('question');
        $question = new Question($question, $default);

        return $helper->ask($this->input, $this->getOutputToWriteTo(), $question);
    }

    /**
     * {@inheritDoc}
     */
    public function askConfirmation($question, $default = true)
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper   = $this->helperSet->get('question');
        $question = new ConfirmationQuestion($question, $default);

        return IOUtil::answerToBool($helper->ask($this->input, $this->getOutputToWriteTo(), $question));
    }

    /**
     * {@inheritDoc}
     */
    public function askAndValidate($question, $validator, $attempts = null, $default = null)
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper   = $this->helperSet->get('question');
        $question = new Question($question, $default);
        $question->setValidator($validator);
        $question->setMaxAttempts($attempts);

        return $helper->ask($this->input, $this->getOutputToWriteTo(), $question);
    }

    /**
     * Return the output to write to
     *
     * @param  bool $stdErr
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    private function getOutputToWriteTo($stdErr = false)
    {
        if ($stdErr && $this->output instanceof ConsoleOutputInterface) {
            return $this->output->getErrorOutput();
        }

        return $this->output;
    }
}
