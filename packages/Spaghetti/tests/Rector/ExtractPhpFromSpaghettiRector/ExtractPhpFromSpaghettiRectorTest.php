<?php declare(strict_types=1);

namespace Rector\Spaghetti\Tests\Rector\ExtractPhpFromSpaghettiRector;

use Iterator;
use Nette\Utils\FileSystem;
use Rector\FileSystemRector\FileSystemFileProcessor;
use Rector\HttpKernel\RectorKernel;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;
use Symplify\PackageBuilder\Tests\AbstractKernelTestCase;

/**
 * @covers \Rector\Spaghetti\Rector\ExtractPhpFromSpaghettiRector
 */
final class ExtractPhpFromSpaghettiRectorTest extends AbstractKernelTestCase
{
    /**
     * @var FileSystemFileProcessor
     */
    private $fileSystemFileProcessor;

    protected function setUp(): void
    {
        $this->bootKernelWithConfigs(RectorKernel::class, [__DIR__ . '/config.yaml']);
        $this->fileSystemFileProcessor = self::$container->get(FileSystemFileProcessor::class);

        FileSystem::copy(__DIR__ . '/Backup', __DIR__ . '/Source');
    }

    protected function tearDown(): void
    {
        if (! $this->getProvidedData()) {
            return;
        }

        // cleanup filesystem
        FileSystem::delete(__DIR__ . '/Source');
    }

    /**
     * @param string[] $expectedFiles
     * @dataProvider provideExceptionsData
     */
    public function test(string $file, array $expectedFiles): void
    {
        $this->fileSystemFileProcessor->processFileInfo(new SmartFileInfo($file));

        foreach ($expectedFiles as $expectedFileLocation => $expectedFileContent) {
            $this->assertFileExists($expectedFileLocation);

            $this->assertFileEquals($expectedFileContent, $expectedFileLocation);
        }
    }

    public function provideExceptionsData(): Iterator
    {
        yield [
//            __DIR__ . '/Source/index.php',
//            [
//                // expected file location => expected file content
//                __DIR__ . '/Source/index.php' => __DIR__ . '/Expected/index.php',
//                __DIR__ . '/Source/IndexController.php' => __DIR__ . '/Expected/IndexController.php',
//            ],

            __DIR__ . '/Source/simple_foreach.php',
            [
//                // expected file location => expected file content
                __DIR__ . '/Source/simple_foreach.php' => __DIR__ . '/Expected/simple_foreach.php',
                __DIR__ . '/Source/SimpleForeachController.php' => __DIR__ . '/Expected/SimpleForeachController.php',
            ],
        ];
    }
}
