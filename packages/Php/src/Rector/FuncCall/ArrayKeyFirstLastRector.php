<?php declare(strict_types=1);

namespace Rector\Php\Rector\FuncCall;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @see https://www.tomasvotruba.cz/blog/2018/08/16/whats-new-in-php-73-in-30-seconds-in-diffs/#2-first-and-last-array-key
 *
 * This needs to removed 1 floor above, because only nodes in arrays can be removed why traversing,
 * see https://github.com/nikic/PHP-Parser/issues/389
 */
final class ArrayKeyFirstLastRector extends AbstractRector
{
    /**
     * @var string[]
     */
    private $previousToNewFunctions = [
        'reset' => 'array_key_first',
        'end' => 'array_key_last',
    ];

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Make use of array_key_first() and array_key_last()',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
reset($items);
$firstKey = key($items);
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
$firstKey = array_key_first($items);
CODE_SAMPLE
                ),
                new CodeSample(
<<<'CODE_SAMPLE'
end($items);
$lastKey = key($items);
CODE_SAMPLE
                    ,
<<<'CODE_SAMPLE'
$lastKey = array_key_last($items);
CODE_SAMPLE
                ),

            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /**
     * @param FuncCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isAtLeastPhpVersion('7.3')) {
            return null;
        }

        if (! $this->isNames($node, ['reset', 'end'])) {
            return null;
        }

        $nextExpression = $this->getNextExpression($node);
        if ($nextExpression === null) {
            return null;
        }

        $resetOrEndFuncCall = $node;

        /** @var FuncCall|null $keyFuncCall */
        $keyFuncCall = $this->betterNodeFinder->findFirst($nextExpression, function (Node $node) use (
            $resetOrEndFuncCall
        ) {
            if (! $node instanceof FuncCall) {
                return false;
            }

            if (! $this->isName($node, 'key')) {
                return false;
            }

            return $this->areNodesEqual($resetOrEndFuncCall->args[0], $node->args[0]);
        });

        if ($keyFuncCall === null) {
            return null;
        }

        $newName = $this->previousToNewFunctions[$this->getName($node)];
        $keyFuncCall->name = new Name($newName);

        $this->removeNode($node);

        return $node;
    }

    private function getNextExpression(Node $node): ?Node
    {
        /** @var Expression|null $currentExpression */
        $currentExpression = $node->getAttribute(Attribute::CURRENT_EXPRESSION);
        if ($currentExpression === null) {
            return null;
        }

        return $currentExpression->getAttribute(Attribute::NEXT_NODE);
    }
}
