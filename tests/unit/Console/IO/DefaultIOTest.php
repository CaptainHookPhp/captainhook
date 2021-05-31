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
use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DefaultIOTest extends TestCase
{
    /**
     * @return \Symfony\Component\Console\Input\InputInterface&MockObject
     */
    public function getInputMock()
    {
        return $this->getMockBuilder(InputInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    /**
     * @return \Symfony\Component\Console\Output\ConsoleOutputInterface&MockObject
     */
    public function getConsoleOutputMock()
    {
        return $this->getMockBuilder(ConsoleOutputInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface&MockObject
     */
    public function getOutputMock()
    {
        return $this->getMockBuilder(OutputInterface::class)
                     ->disableOriginalConstructor()
                     ->getMock();
    }

    /**
     * @return \Symfony\Component\Console\Helper\HelperSet&MockObject
     */
    public function getHelperSetMock()
    {
        return $this->getMockBuilder(HelperSet::class)
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    /**
     * @return \Symfony\Component\Console\Helper\QuestionHelper&MockObject
     */
    public function getQuestionHelper()
    {
        return $this->getMockBuilder(QuestionHelper::class)
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    /**
     * Tests DefaultIO::getArguments
     */
    public function testGetArguments(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $input->expects($this->once())->method('getArguments')->willReturn(['foo' => 'bar']);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertEquals(['foo' => 'bar'], $io->getArguments());
    }

    /**
     * Tests DefaultIO::getArgument
     */
    public function testGetArgument(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $input->expects($this->exactly(2))->method('getArguments')->willReturn(['foo' => 'bar']);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertEquals('bar', $io->getArgument('foo'));
        $this->assertEquals('bar', $io->getArgument('fiz', 'bar'));
    }

    /**
     * Tests DefaultIO::getOptions
     */
    public function testGetOptions(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $input->expects($this->once())->method('getOptions')->willReturn(['foo' => 'bar']);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertEquals(['foo' => 'bar'], $io->getOptions());
    }

    /**
     * Tests DefaultIO::getOption
     */
    public function testGetOption(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $input->expects($this->exactly(2))->method('getOptions')->willReturn(['foo' => 'bar']);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertEquals('bar', $io->getOption('foo'));
        $this->assertEquals('bar', $io->getOption('fiz', 'bar'));
    }

    /**
     * Tests DefaultIO::getStandardInput
     */
    public function testGetStandardInput(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertCount(3, $io->getStandardInput());
    }

    /**
     * Tests DefaultIO::isInteractive
     */
    public function testIsInteractive(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $input->expects($this->once())->method('isInteractive')->willReturn(false);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertFalse($io->isInteractive());
    }

    /**
     * Tests DefaultIO::isVerbose
     */
    public function testIsVerbose(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $output->expects($this->once())->method('getVerbosity')->willReturn(0);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertFalse($io->isVerbose());
    }

    /**
     * Tests DefaultIO::isVeryVerbose
     */
    public function testIsVeryVerbose(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $output->expects($this->once())->method('getVerbosity')->willReturn(0);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertFalse($io->isVeryVerbose());
    }

    /**
     * Tests DefaultIO::isDebug
     */
    public function testIsDebug(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $output->expects($this->once())->method('getVerbosity')->willReturn(0);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $this->assertFalse($io->isDebug());
    }

    /**
     * Tests DefaultIO::writeError
     */
    public function testWriteError(): void
    {
        $input  = $this->getInputMock();
        $output = $this->getOutputMock();
        $helper = $this->getHelperSetMock();

        $output->expects($this->once())->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_DEBUG);
        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);

        $io->writeError('foo');
    }

    /**
     * Tests DefaultIO::ask
     */
    public function testAsk(): void
    {
        $input          = $this->getInputMock();
        $output         = $this->getOutputMock();
        $helper         = $this->getHelperSetMock();
        $questionHelper = $this->getQuestionHelper();

        $helper->expects($this->once())->method('get')->willReturn($questionHelper);
        $questionHelper->expects($this->once())->method('ask')->willReturn(true);

        $io     = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);
        $answer = $io->ask('foo');
        $this->assertTrue($answer);
    }

    /**
     * Tests DefaultIO::askConfirmation
     */
    public function testAskConfirmation(): void
    {
        $input          = $this->getInputMock();
        $output         = $this->getOutputMock();
        $helper         = $this->getHelperSetMock();
        $questionHelper = $this->getQuestionHelper();

        $helper->expects($this->once())->method('get')->willReturn($questionHelper);
        $questionHelper->expects($this->once())->method('ask')->willReturn(true);

        $io     = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);
        $answer = $io->askConfirmation('foo');
        $this->assertTrue($answer);
    }

    /**
     * Tests DefaultIO::askAbdValidate
     *
     * @throws \Exception
     */
    public function testAskAndValidate(): void
    {
        $input          = $this->getInputMock();
        $output         = $this->getOutputMock();
        $helper         = $this->getHelperSetMock();
        $questionHelper = $this->getQuestionHelper();

        $helper->expects($this->once())->method('get')->willReturn($questionHelper);
        $questionHelper->expects($this->once())->method('ask')->willReturn(true);

        $io     = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);
        $answer = $io->askAndValidate(
            'foo',
            function () {
                return true;
            }
        );
        $this->assertTrue($answer);
    }

    /**
     * Tests DefaultIO::write
     */
    public function testWrite(): void
    {
        $input          = $this->getInputMock();
        $output         = $this->getConsoleOutputMock();
        $helper         = $this->getHelperSetMock();

        $output->expects($this->once())->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_DEBUG);
        $output->expects($this->once())->method('getErrorOutput')->willReturn($this->getOutputMock());

        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);
        $io->writeError('foo');
    }


    /**
     * Tests DefaultIO::write
     */
    public function testWriteSkipped(): void
    {
        $input          = $this->getInputMock();
        $output         = $this->getConsoleOutputMock();
        $helper         = $this->getHelperSetMock();

        $output->expects($this->once())->method('getVerbosity')->willReturn(OutputInterface::VERBOSITY_NORMAL);
        $output->expects($this->exactly(0))->method('getErrorOutput');

        $io = new DefaultIO($this->fakeStdIn(), $input, $output, $helper);
        $io->writeError('foo', false, IO::DEBUG);
    }

    /**
     * Return fake standard input handle
     *
     * @return resource
     * @throws \Exception
     */
    private function fakeStdIn()
    {
        $handle = fopen(CH_PATH_FILES . '/input/stdin.txt', 'r');

        if ($handle === false) {
            throw new Exception('fake stdIn file not found');
        }

        return $handle;
    }
}
