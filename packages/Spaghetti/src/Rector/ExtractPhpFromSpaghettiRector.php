<?php declare(strict_types=1);

namespace Rector\Spaghetti\Rector;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Return_;
use Rector\FileSystemRector\Rector\AbstractFileSystemRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class ExtractPhpFromSpaghettiRector extends AbstractFileSystemRector
{
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
        return [
            'variable1' => 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
        ];
    }
}

?>

-----

<?php

$variables = (new IndexController)->render();
extract($variables);

?>

<ul>
    <li><a href="<?php echo $variable1 ?>">Odkaz</a>
</ul>
CODE_SAMPLE
                ),
            ]
        );
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

        $newController = new New_(new Name($classController->name->toString()));
        $renderMethodCall = new MethodCall($newController, 'render');

        $nodesToPrepend = [];
        $variables = new Variable('variables');
        $variablesAssign = new Assign($variables, $renderMethodCall);
        $nodesToPrepend[] = new Expression($variablesAssign);
        $extractVariables = new FuncCall(new Name('extract'), [$variables]);
        $nodesToPrepend[] = new Expression($extractVariables);

        // print template file
        $fileContent = '<?php' . PHP_EOL .  $this->print($nodesToPrepend) . PHP_EOL . '?>' . PHP_EOL . $this->printNodesToString($nodes);

        $this->filesystem->dumpFile($smartFileInfo->getRealPath(), $fileContent);
    }

    private function createControllerFileDestination(SmartFileInfo $smartFileInfo): string
    {
        $currentDirectory = dirname($smartFileInfo->getRealPath());

        return $currentDirectory . DIRECTORY_SEPARATOR . $this->createControllerName($smartFileInfo) . '.php';
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
        $classController->stmts[] = $this->createControllerRenderMethod($variables);

        return $classController;
    }

    /**
     * @param Expr[] $variables
     */
    private function createControllerRenderMethod(array $variables): ClassMethod
    {
        $renderMethod = $this->nodeFactory->createPublicMethod('render');

        $array = new Array_();
        foreach ($variables as $name => $expr) {
            $array->items[] = new ArrayItem($expr, new String_($name));
        }

        $renderMethod->stmts[] = new Return_($array);

        return $renderMethod;
    }
}
