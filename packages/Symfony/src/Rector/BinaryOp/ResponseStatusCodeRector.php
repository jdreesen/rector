<?php declare(strict_types=1);

namespace Rector\Symfony\Rector\BinaryOp;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\NodeDumper;
use Rector\PhpParser\Node\Manipulator\BinaryOpManipulator;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class ResponseStatusCodeRector extends AbstractRector
{
    private const RESPONSE_CLASS = 'Symfony\Component\HttpFoundation\Response';

    /**
     * @var array
     */
    private const CODE_TO_CONST = [
        100 => 'HTTP_CONTINUE',
        101 => 'HTTP_SWITCHING_PROTOCOLS',
        102 => 'HTTP_PROCESSING',
        103 => 'HTTP_EARLY_HINTS',
        200 => 'HTTP_OK',
        201 => 'HTTP_CREATED',
        202 => 'HTTP_ACCEPTED',
        203 => 'HTTP_NON_AUTHORITATIVE_INFORMATION',
        204 => 'HTTP_NO_CONTENT',
        205 => 'HTTP_RESET_CONTENT',
        206 => 'HTTP_PARTIAL_CONTENT',
        207 => 'HTTP_MULTI_STATUS',
        208 => 'HTTP_ALREADY_REPORTED',
        226 => 'HTTP_IM_USED',
        300 => 'HTTP_MULTIPLE_CHOICES',
        301 => 'HTTP_MOVED_PERMANENTLY',
        302 => 'HTTP_FOUND',
        303 => 'HTTP_SEE_OTHER',
        304 => 'HTTP_NOT_MODIFIED',
        305 => 'HTTP_USE_PROXY',
        306 => 'HTTP_RESERVED',
        307 => 'HTTP_TEMPORARY_REDIRECT',
        308 => 'HTTP_PERMANENTLY_REDIRECT',
        400 => 'HTTP_BAD_REQUEST',
        401 => 'HTTP_UNAUTHORIZED',
        402 => 'HTTP_PAYMENT_REQUIRED',
        403 => 'HTTP_FORBIDDEN',
        404 => 'HTTP_NOT_FOUND',
        405 => 'HTTP_METHOD_NOT_ALLOWED',
        406 => 'HTTP_NOT_ACCEPTABLE',
        407 => 'HTTP_PROXY_AUTHENTICATION_REQUIRED',
        408 => 'HTTP_REQUEST_TIMEOUT',
        409 => 'HTTP_CONFLICT',
        410 => 'HTTP_GONE',
        411 => 'HTTP_LENGTH_REQUIRED',
        412 => 'HTTP_PRECONDITION_FAILED',
        413 => 'HTTP_REQUEST_ENTITY_TOO_LARGE',
        414 => 'HTTP_REQUEST_URI_TOO_LONG',
        415 => 'HTTP_UNSUPPORTED_MEDIA_TYPE',
        416 => 'HTTP_REQUESTED_RANGE_NOT_SATISFIABLE',
        417 => 'HTTP_EXPECTATION_FAILED',
        418 => 'HTTP_I_AM_A_TEAPOT',
        421 => 'HTTP_MISDIRECTED_REQUEST',
        422 => 'HTTP_UNPROCESSABLE_ENTITY',
        423 => 'HTTP_LOCKED',
        424 => 'HTTP_FAILED_DEPENDENCY',
        425 => 'HTTP_TOO_EARLY',
        426 => 'HTTP_UPGRADE_REQUIRED',
        428 => 'HTTP_PRECONDITION_REQUIRED',
        429 => 'HTTP_TOO_MANY_REQUESTS',
        431 => 'HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE',
        451 => 'HTTP_UNAVAILABLE_FOR_LEGAL_REASONS',
        500 => 'HTTP_INTERNAL_SERVER_ERROR',
        501 => 'HTTP_NOT_IMPLEMENTED',
        502 => 'HTTP_BAD_GATEWAY',
        503 => 'HTTP_SERVICE_UNAVAILABLE',
        504 => 'HTTP_GATEWAY_TIMEOUT',
        505 => 'HTTP_VERSION_NOT_SUPPORTED',
        506 => 'HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL',
        507 => 'HTTP_INSUFFICIENT_STORAGE',
        508 => 'HTTP_LOOP_DETECTED',
        510 => 'HTTP_NOT_EXTENDED',
        511 => 'HTTP_NETWORK_AUTHENTICATION_REQUIRED',
    ];

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Turns status code numbers to constants', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeController
{
    public function index()
    {
        $response = new \Symfony\Component\HttpFoundation\Response();
        $response->setStatusCode(200);
        
        if ($response->getStatusCode() === 200) {}
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeController
{
    public function index()
    {
        $response = new \Symfony\Component\HttpFoundation\Response();
        $response->setStatusCode(\Symfony\Component\HttpFoundation\Response::HTTP_OK);
        
        if ($response->getStatusCode() === \Symfony\Component\HttpFoundation\Response::HTTP_OK) {}
    }
}
CODE_SAMPLE

            )
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Node\Expr\BinaryOp::class, Node\Expr\MethodCall::class];
    }

    /**
     * @param Node\Expr\BinaryOp|Node\Expr\MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Node\Expr\MethodCall) {
            return $this->processMethodCall($node);
        }

        if ($node instanceof Node\Expr\BinaryOp) {
            return $this->processBinaryOp($node);
        }

        return $node;
    }

    private function processMethodCall(Node\Expr\MethodCall $methodCall): ?Node\Expr\MethodCall
    {
        if (!$this->isType($methodCall->var, self::RESPONSE_CLASS)) {
            return null;
        }

        if (!$this->isName($methodCall, 'setStatusCode')) {
            return null;
        }

        $statusCode = $methodCall->args[0]->value;

        if (! $statusCode instanceof Node\Scalar\LNumber) {
            return null;
        }

        if (!isset(self::CODE_TO_CONST[$statusCode->value])) {
            return null;
        }

        $methodCall->args[0] = new Node\Arg($this->createClassConstant(self::RESPONSE_CLASS, self::CODE_TO_CONST[$statusCode->value]));


        return $methodCall;
    }

    private function processBinaryOp(Node\Expr\BinaryOp $node): ?Node\Expr\BinaryOp
    {
        if (!$this->isGetStatusMethod($node->left) && !$this->isGetStatusMethod($node->right)) {
            return null;
        }

        if ($node->right instanceof Node\Scalar\LNumber && $this->isGetStatusMethod($node->left)) {
            $node->right = $this->convertNumberToConstant($node->right);

            return $node;
        }

        if ($node->left instanceof Node\Scalar\LNumber && $this->isGetStatusMethod($node->right)) {
            $node->left = $this->convertNumberToConstant($node->left);

            return $node;
        }

        return null;
    }

    private function isGetStatusMethod(Node $node): bool
    {
        if (! $node instanceof Node\Expr\MethodCall) {
            return false;
        }

        if (!$this->isType($node, self::RESPONSE_CLASS)) {
            return false;
        }

        return $this->isName($node, 'getStatusCode');
    }

    /**
     * @return ClassConstFetch|Node\Scalar\LNumber
     */
    private function convertNumberToConstant(Node\Scalar\LNumber $number)
    {
        if (!isset(self::CODE_TO_CONST[$number->value])) {
            return $number;
        }

        return $this->createClassConstant(self::RESPONSE_CLASS, self::CODE_TO_CONST[$number->value]);
    }
}
