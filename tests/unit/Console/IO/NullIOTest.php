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

use PHPUnit\Framework\TestCase;

class NullIOTest extends TestCase
{
    /**
     * Tests NullIO::getArguments
     */
    public function testGetArguments(): void
    {
        $io = new NullIO();
        $this->assertEquals([], $io->getArguments());
    }

    /**
     * Tests NullIO::getArgument
     */
    public function testGetArgument(): void
    {
        $io = new NullIO();
        $this->assertEquals('', $io->getArgument('foo'));
        $this->assertEquals('bar', $io->getArgument('foo', 'bar'));
    }

    /**
     * Tests NullIO::getOptions
     */
    public function testGetOptions(): void
    {
        $io = new NullIO();
        $this->assertEquals([], $io->getOptions());
    }

    /**
     * Tests NullIO::getOption
     */
    public function testGetOption(): void
    {
        $io = new NullIO();
        $this->assertEquals('', $io->getOption('foo'));
        $this->assertEquals('bar', $io->getOption('foo', 'bar'));
    }

    /**
     * Tests NullIO::getStandardInput
     */
    public function testGetStandardInput(): void
    {
        $io = new NullIO();
        $this->assertEquals([], $io->getStandardInput());
    }

    /**
     * Tests NullIO::isInteractive
     */
    public function testIsInteractive(): void
    {
        $io = new NullIO();
        $this->assertFalse($io->isInteractive());
    }

    /**
     * Tests NullIO::isDebug
     */
    public function testIsDebug(): void
    {
        $io = new NullIO();
        $this->assertFalse($io->isDebug());
    }

    /**
     * Tests NullIO::isVerbose
     */
    public function testIsVerbose(): void
    {
        $io = new NullIO();
        $this->assertFalse($io->isVerbose());
    }

    /**
     * Tests NullIO::isVeryVerbose
     */
    public function testIsVeryVerbose(): void
    {
        $io = new NullIO();
        $this->assertFalse($io->isVeryVerbose());
    }

    /**
     * Tests NullIO::writeError
     */
    public function testWriteError(): void
    {
        $io = new NullIO();
        $io->writeError('foo');
        $this->assertTrue(true);
    }

    /**
     * Tests NullIO::ask
     */
    public function testAsk(): void
    {
        $io = new NullIO();
        $this->assertEquals('bar', $io->ask('foo', 'bar'));
    }

    /**
     * Tests NullIO::askConfirmation
     */
    public function testAskConfirmation(): void
    {
        $io = new NullIO();
        $this->assertEquals(true, $io->askConfirmation('foo', true));
    }

    /**
     * Tests NullIO::askAbdValidate
     *
     * @throws \Exception
     */
    public function testAskAndValidate(): void
    {
        $io = new NullIO();
        $this->assertEquals(
            true,
            $io->askAndValidate(
                'foo',
                function () {
                    return true;
                },
                false,
                true
            )
        );
    }
}
