<?php declare(strict_types=1);

namespace Rector\Spaghetti\Rector;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\InlineHTML;
use Rector\FileSystemRector\Rector\AbstractFileSystemRector;
use Rector\PhpParser\Node\NodeFactory;
use Rector\RectorDefinition\RectorDefinition;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class ExtractPhpFromSpaghettiRector extends AbstractFileSystemRector
{
    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    public function __construct(NodeFactory $nodeFactory)
    {
        $this->nodeFactory = $nodeFactory;
    }

    public function refactor(SmartFileInfo $smartFileInfo): void
    {
        $nodes = $this->parseFileInfoToNodes($smartFileInfo);

        // analyze here! - collect variables
        $variables = [];

        $i = 0;
        foreach ($nodes as $node) {
            if ($node instanceof InlineHTML) {
                // @todo are we in a for/foreach?
                continue;
            }

            if ($node instanceof Echo_) {
                if (count($node->exprs) === 1) {
                    // it is already variable, nothing to change
                    if ($node->exprs[0] instanceof Variable) {
                        continue;
                    }

                    ++$i;

                    $variableName = 'variable' . $i;
                    $variables[$variableName] = $node->exprs[0];

                    $node->exprs[0] = new Variable($variableName);
                }
            }
        }

        // create Controller here
        dump($variables);

        $controllerName = $this->createControllerName($smartFileInfo);
        $classController = new Class_($controllerName);

        $renderMethod = new ClassMethod('render');
        $renderMethod->flags |= Class_::MODIFIER_PUBLIC;

        foreach ($variables as $name => $expr) {
            $renderMethod->stmts[] = new Assign(new Variable($name), $expr);
        }

        $include = new Include_(new String_('some_file'), Include_::TYPE_REQUIRE_ONCE);
        $renderMethod->stmts[] = new Expression($include);

        // include file
//        $renderMethod->stmts[] = new Include_();
        $classController->stmts[] = $renderMethod;

        // print controller
        $fileDestination = $this->createControllerFileDestination($smartFileInfo);
        $this->printNodesToFilePath([$classController], $fileDestination);
    }

    public function getDefinition(): RectorDefinition
    {
    }

    private function createControllerFileDestination(SmartFileInfo $smartFileInfo): string
    {
        $currentDirectory = dirname($smartFileInfo->getRealPath());

        return $currentDirectory . DIRECTORY_SEPARATOR . ucfirst(
            $smartFileInfo->getBasenameWithoutSuffix()
        ) . 'Controller.php';
    }

    private function createControllerName(SmartFileInfo $smartFileInfo): string
    {
        return ucfirst($smartFileInfo->getBasenameWithoutSuffix()) . 'Controller';
    }
}
