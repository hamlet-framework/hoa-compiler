<?php

namespace Hoa\Compiler\Llk\Rules;

use Hoa\Compiler\Llk\TreeNode;
use Hoa\Compiler\Visitor\Dump;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function test_constructor(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 0;

        $token = new TokenRule($name, $tokenName, $nodeId, $unification);

        $this->assertEquals($name, $token->getName());
        $this->assertEquals($tokenName, $token->getTokenName());
        $this->assertEquals($nodeId, $token->getNodeId());
        $this->assertEquals($unification, $token->getUnificationIndex());
        $this->assertFalse($token->isKept());
    }

    public function test_constructor_with_kept_flag(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 0;
        $kept = true;

        $token = new TokenRule($name, $tokenName, $nodeId, $unification, $kept);

        $this->assertEquals($name, $token->getName());
        $this->assertEquals($tokenName, $token->getTokenName());
        $this->assertEquals($nodeId, $token->getNodeId());
        $this->assertEquals($unification, $token->getUnificationIndex());
        $this->assertTrue($token->isKept());
    }

    public function test_set_namespace(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 0;
        $namespace = 'qux';
        $token = new TokenRule($name, $tokenName, $nodeId, $unification);

        $this->assertNull($token->setNamespace($namespace));
        $this->assertEquals($namespace, $token->getNamespace());

        $namespace2 = 'soop';
        $this->assertEquals($namespace, $token->setNamespace($namespace2));
        $this->assertEquals($namespace2, $token->getNamespace());
    }

    public function test_set_and_get_representation(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 0;
        $representation = 'qux';
        $token = new TokenRule($name, $tokenName, $nodeId, $unification);

        $this->assertNull($token->setRepresentation($representation));
        $this->assertEquals($representation, $token->getRepresentation());
    }

    public function test_get_ast(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 0;
        $representation = 'qux';
        $token = new TokenRule($name, $tokenName, $nodeId, $unification);
        $token->setRepresentation($representation);

        $ast = $token->getAST();
        $this->assertInstanceOf(TreeNode::class, $ast);
        $this->assertEquals(
            '>  #expression' . "\n" .
            '>  >  #concatenation' . "\n" .
            '>  >  >  token(literal, q)' . "\n" .
            '>  >  >  token(literal, u)' . "\n" .
            '>  >  >  token(literal, x)' . "\n",
            (new Dump)->visit($ast)
        );
    }

    public function test_set_and_get_value(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 0;
        $value = 'qux';
        $token = new TokenRule($name, $tokenName, $nodeId, $unification);

        $this->assertNull($token->setValue($value));
        $this->assertEquals($value, $token->getValue());
    }

    public function test_set_and_get_kept(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 0;
        $token = new TokenRule($name, $tokenName, $nodeId, $unification);

        $this->assertFalse($token->isKept());
        $this->assertFalse($token->setKept(true));
        $this->assertTrue($token->isKept());
    }

    public function test_get_unification_index(): void
    {
        $name = 'foo';
        $tokenName = 'bar';
        $nodeId = 'baz';
        $unification = 42;

        $token = new TokenRule($name, $tokenName, $nodeId, $unification);
        $this->assertEquals($unification, $token->getUnificationIndex());
    }
}
