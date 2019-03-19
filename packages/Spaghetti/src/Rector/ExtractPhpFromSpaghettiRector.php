<?php declare(strict_types=1);

namespace Rector\Spaghetti\Rector;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\InlineHTML;
use Rector\FileSystemRector\Rector\AbstractFileSystemRector;
use Rector\PhpParser\Node\NodeFactory;
use Rector\RectorDefinition\CodeSample;
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
        $classController = $this->createControllerClass($smartFileInfo, $variables);

        // print controller
        $fileDestination = $this->createControllerFileDestination($smartFileInfo);
        $this->printNodesToFilePath([$classController], $fileDestination);
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Take spaghetti template and separate it into 2 files: new class with render() method and variables + template only using the variables',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
<ul>
    <li><a href="<?php echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; ?>">Odkaz</a>
</ul>
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
<?php

class IndexController
{
    public function render()
    {
        global $variable1;
        $variable1 = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }
}

?>

-----

<?php

(new IndexController)->render();

?>

<ul>
    <li><a href="<?php echo $variable1 ?>">Odkaz</a>
</ul>
CODE_SAMPLE
                ),
            ]
        );
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

    /**
     * @param Expr[] $variables
     */
    private function createControllerClass(SmartFileInfo $smartFileInfo, array $variables): Class_
    {
        $controllerName = $this->createControllerName($smartFileInfo);
        $classController = new Class_($controllerName);

        $renderMethod = $this->createControllerRenderMethod($variables);
        $classController->stmts[] = $renderMethod;

        return $classController;
    }

    /**
     * @param Expr[] $variables
     */
    private function createControllerRenderMethod(array $variables): ClassMethod
    {
        $renderMethod = $this->nodeFactory->createPublicMethod('render');

        foreach ($variables as $name => $expr) {
            $variable = new Variable($name);
            $renderMethod->stmts[] = new Global_([$variable]);
            $renderMethod->stmts[] = new Expression(new Assign($variable, $expr));
        }

        return $renderMethod;
    }
}
