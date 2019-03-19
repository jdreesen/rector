<?php declare(strict_types=1);

namespace Rector\Spaghetti\Tests\Rector\ExtractPhpFromSpaghettiRector;

use Iterator;
use Rector\FileSystemRector\FileSystemFileProcessor;
use Rector\HttpKernel\RectorKernel;
use Symfony\Component\Filesystem\Filesystem;
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
    }

    protected function tearDown(): void
    {
        if (! $this->getProvidedData()) {
            return;
        }

        // cleanup filesystem
        $generatedFiles = array_keys($this->getProvidedData()[1]);
        (new Filesystem())->remove($generatedFiles);
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
            __DIR__ . '/Source/index.php',
            [
                // expected file location => expected file content
//                __DIR__ . '/Source/index_template.php' => __DIR__ . '/Expected/index_template.php',
                __DIR__ . '/Source/IndexController.php' => __DIR__ . '/Expected/IndexController.php',
            ],
        ];
    }
}
