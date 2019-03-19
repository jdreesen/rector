<?php declare(strict_types=1);

namespace Rector\Spaghetti\Rector;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Return_;
use Rector\Exception\ShouldNotHappenException;
use Rector\FileSystemRector\Rector\AbstractFileSystemRector;
use Rector\PhpParser\NodeTraverser\CallableNodeTraverser;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;
use Symplify\PackageBuilder\Strings\StringFormatConverter;

final class ExtractPhpFromSpaghettiRector extends AbstractFileSystemRector
{
    /**
     * @var StringFormatConverter
     */
    private $stringFormatConverter;

    /**
     * @var Node[]
     */
    private $rootNodesToRenderMethod = [];

    /**
     * @var CallableNodeTraverser
     */
    private $callableNodeTraverser;

    /**
     * @var Node[]
     */
    private $nodesContainingEcho = [];

    public function __construct(
        StringFormatConverter $stringFormatConverter,
        CallableNodeTraverser $callableNodeTraverser
    ) {
        $this->stringFormatConverter = $stringFormatConverter;
        $this->callableNodeTraverser = $callableNodeTraverser;
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

    public function isNodeEchoedAnywhereInside(Node $node): bool
    {
        return (bool) $this->betterNodeFinder->findInstanceOf($node, Echo_::class);
    }

    public function refactor(SmartFileInfo $smartFileInfo): void
    {
        $this->rootNodesToRenderMethod = [];
        $this->nodesContainingEcho = [];

        $nodes = $this->parseFileInfoToNodes($smartFileInfo);

        // analyze here! - collect variables
        $variables = [];

        $i = 0;

        foreach ($nodes as $key => $node) {
            if ($node instanceof InlineHTML) {
                // @todo are we in a for/foreach?
                continue;
            }

            if ($node instanceof Echo_) {
                if (count($node->exprs) === 1) {
                    // is it already a variable? nothing to change
                    if ($node->exprs[0] instanceof Variable) {
                        continue;
                    }

                    ++$i;

                    $variableName = 'variable' . $i;
                    $variables[$variableName] = $node->exprs[0];

                    $node->exprs[0] = new Variable($variableName);
                }
            } else {
                // expression assign variable!?
                if ($this->isNodeEchoedAnywhereInside($node)) {
                    $this->rootNodesToRenderMethod[] = $node;
                    $this->nodesContainingEcho[] = $node;
                } else {
                    // nodes to skip
                    if ($node instanceof Declare_) {
                        continue;
                    }

                    // just copy
                    $this->rootNodesToRenderMethod[] = $node;
                    // remove node
                    unset($nodes[$key]);
                    continue;
                }
            }
        }

        $classController = $this->createControllerClass($smartFileInfo, $variables);

        // print controller
        $fileDestination = $this->createControllerFileDestination($smartFileInfo);
        $this->printNodesToFilePath([$classController], $fileDestination);

        if ($classController->name === null) {
            throw new ShouldNotHappenException();
        }

        $fileContent = $this->completeAndPrintControllerRenderMethod($classController, $nodes);
        $this->filesystem->dumpFile($smartFileInfo->getRealPath(), $fileContent);
    }

    private function createControllerFileDestination(SmartFileInfo $smartFileInfo): string
    {
        $currentDirectory = dirname($smartFileInfo->getRealPath());

        return $currentDirectory . DIRECTORY_SEPARATOR . $this->createControllerName($smartFileInfo) . '.php';
    }

    private function createControllerName(SmartFileInfo $smartFileInfo): string
    {
        $camelCaseName = $this->stringFormatConverter->underscoreToCamelCase(
            $smartFileInfo->getBasenameWithoutSuffix()
        );

        return ucfirst($camelCaseName) . 'Controller';
    }

    /**
     * @param Expr[] $variables
     * @param Stmt[] $prependNodes
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

        if ($this->rootNodesToRenderMethod) {
            foreach ($this->rootNodesToRenderMethod as $key => $nodeToRenderMethod) {
                // make any changes in them permanent, e.g. foreach with value change → re-assign value "$values[$key] = $value;"
                if (in_array($nodeToRenderMethod, $this->nodesContainingEcho, true)) {
                    $this->rootNodesToRenderMethod[$key] = $this->makeChangePersistent($nodeToRenderMethod);
                }
            }

            $renderMethod->stmts = $this->rootNodesToRenderMethod;
        }

        $renderMethod->stmts[] = new Return_($array);

        return $renderMethod;
    }

    /**
     * @param Node[] $nodes
     */
    private function completeAndPrintControllerRenderMethod(Class_ $classController, array $nodes): string
    {
        if ($classController->name === null) {
            throw new ShouldNotHappenException();
        }

        $newController = new New_(new Name($classController->name->toString()));
        $renderMethodCall = new MethodCall($newController, 'render');

        $nodesToPrepend = [];

        $variables = new Variable('variables');
        $variablesAssign = new Assign($variables, $renderMethodCall);
        $nodesToPrepend[] = new Expression($variablesAssign);

        $extractVariables = new FuncCall(new Name('extract'), [new Arg($variables)]);
        $nodesToPrepend[] = new Expression($extractVariables);

        // print template file
        $fileContent = sprintf(
            '<?php%s%s%s?>%s%s',
            PHP_EOL,
            $this->print($nodesToPrepend),
            PHP_EOL,
            PHP_EOL,
            $this->printNodesToString($nodes)
        );

        // remove "? >...< ?php" leftovers
        return Strings::replace($fileContent, '#\?\>(\s+)\<\?php#s');
    }

    private function makeChangePersistent(Node $node): Node
    {
        $nodes = $this->callableNodeTraverser->traverseNodesWithCallable([$node], function (Node $node) {

            // complete assign to foreach if not recorded yet
            if ($node instanceof Foreach_) { // @todo or anything with "stmts" property
                $foreach = $node;
                foreach ($foreach->stmts as $key => $foreachStmt) {
                    $foreachStmt = $foreachStmt instanceof Expression ? $foreachStmt->expr : $foreachStmt;

                    if ($foreachStmt instanceof Echo_) {
                        // silence echoes
                        unset($foreach->stmts[$key]);
                        return $foreach;
                    }

                    if ($foreachStmt instanceof AssignOp) {
                        if (! $this->areNodesEqual($foreach->valueVar, $foreachStmt->var)) {
                            return $foreach;
                        }

                        if (! $this->areNodesEqual($foreach->valueVar, $foreachStmt->var)) {
                            return $node;
                        }

                        $assign = $this->createForeachKeyAssign($foreach, $foreachStmt->var);
                        $foreach->stmts[] = new Expression($assign);
                    }
                }
            }

            return $node;
        });

        // only 1 node passed → 1 node is returned
        return array_pop($nodes);
    }

    private function createForeachKeyAssign(Foreach_ $foreach, Expr $expr): Assign
    {
        if ($foreach->keyVar === null) {
            $foreach->keyVar = new Variable('key'); // @todo check for duplicit name in nested foreaches
        }

        $keyVariable = $foreach->keyVar;

        $arrayDimFetch = new ArrayDimFetch($foreach->expr, $keyVariable);

        return new Assign($arrayDimFetch, $expr);
    }
}
