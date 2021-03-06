<?php declare(strict_types=1);

namespace Rector\FileSystemRector\Rector;

use PhpParser\Lexer;
use PhpParser\Node;
use Rector\FileSystemRector\Contract\FileSystemRectorInterface;
use Rector\NodeTypeResolver\NodeScopeAndMetadataDecorator;
use Rector\PhpParser\Parser\Parser;
use Rector\PhpParser\Printer\FormatPerservingPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

abstract class AbstractFileSystemRector implements FileSystemRectorInterface
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var FormatPerservingPrinter
     */
    private $formatPerservingPrinter;

    /**
     * @var NodeScopeAndMetadataDecorator
     */
    private $nodeScopeAndMetadataDecorator;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Node[]
     */
    private $oldStmts = [];

    /**
     * @required
     */
    public function setAbstractFileSystemRectorDependencies(
        Parser $parser,
        Lexer $lexer,
        FormatPerservingPrinter $formatPerservingPrinter,
        Filesystem $filesystem,
        NodeScopeAndMetadataDecorator $nodeScopeAndMetadataDecorator
    ): void {
        $this->parser = $parser;
        $this->lexer = $lexer;
        $this->formatPerservingPrinter = $formatPerservingPrinter;
        $this->nodeScopeAndMetadataDecorator = $nodeScopeAndMetadataDecorator;
        $this->filesystem = $filesystem;
    }

    /**
     * @return Node[]
     */
    protected function parseFileInfoToNodes(SmartFileInfo $smartFileInfo): array
    {
        $oldStmts = $this->parser->parseFile($smartFileInfo->getRealPath());
        $this->oldStmts = $oldStmts;
        // needed for format preserving
        return $this->nodeScopeAndMetadataDecorator->decorateNodesFromFile(
            $oldStmts,
            $smartFileInfo->getRealPath()
        );
    }

    /**
     * @param Node[] $nodes
     */
    protected function printNodesToFilePath(array $nodes, string $fileDestination): void
    {
        $fileContent = $this->formatPerservingPrinter->printToString(
            $nodes,
            $this->oldStmts,
            $this->lexer->getTokens()
        );
        $this->filesystem->dumpFile($fileDestination, $fileContent);
    }
}
