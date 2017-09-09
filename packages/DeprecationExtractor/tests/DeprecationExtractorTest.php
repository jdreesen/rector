<?php declare(strict_types=1);

namespace Rector\DeprecationExtractor\Tests;

use Rector\DeprecationExtractor\Deprecation\DeprecationCollector;
use Rector\DeprecationExtractor\DeprecationExtractor;
use Rector\Tests\AbstractContainerAwareTestCase;

final class DeprecationExtractorTest extends AbstractContainerAwareTestCase
{
    /**
     * @var DeprecationExtractor
     */
    private $deprecationExtractor;

    /**
     * @var DeprecationCollector
     */
    private $deprecationCollector;

    protected function setUp(): void
    {
        $this->deprecationExtractor = $this->container->get(DeprecationExtractor::class);
        $this->deprecationCollector = $this->container->get(DeprecationCollector::class);
    }

    public function test(): void
    {
        $this->deprecationExtractor->scanDirectories([__DIR__ . '/DeprecationExtractorSource']);
        $deprecations = $this->deprecationCollector->getDeprecations();

        $this->assertCount(2, $deprecations);

        $setClassToSetFacoryDeprecation = $deprecations[0];
        $this->assertSame(
            'Nette\DI\Definition::setClass() second parameter $args is deprecated,'
            . ' use Nette\DI\Definition::setFactory()',
            $setClassToSetFacoryDeprecation
        );

        $injectMethodToTagDeprecation = $deprecations[1];
        $this->assertSame(
            'Nette\DI\Definition::setInject() is deprecated, use Nette\DI\Definition::addTag(\'inject\')',
            $injectMethodToTagDeprecation
        );
    }
}