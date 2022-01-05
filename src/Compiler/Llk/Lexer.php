<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk;

use Generator;
use Hoa\Compiler;
use Hoa\Compiler\Exceptions\LexerException;
use Hoa\Compiler\Exceptions\UnrecognizedTokenException;
use SplStack;

class Lexer
{
    /**
     * Lexer state.
     */
    protected string $lexerState = 'default';

    /**
     * Text.
     */
    protected ?string $text = null;

    /**
     * @var array<string,array<string,string>>
     */
    protected array $tokens = [];

    /**
     * Namespace stack.
     * @var ?SplStack<string>
     */
    protected ?SplStack $namespaceStack = null;

    /**
     * PCRE options.
     */
    protected string $pcreOptions = '';

    /**
     * @param array $pragmas Pragmas.
     */
    public function __construct(array $pragmas = [])
    {
        if (!isset($pragmas['lexer.unicode']) || true === $pragmas['lexer.unicode']) {
            $this->pcreOptions .= 'u';
        }
    }

    /**
     * Text tokenizer: splits the text in parameter in an ordered array of tokens.
     *
     * @todo replace array{token:string,value:string,length:int,namespace:string,keep:bool,offset:int} with token
     * @param string $text Text to tokenize.
     * @param array<string,array<string,string>> $tokens Tokens to be returned.
     * @return Generator<Token>
     * @throws UnrecognizedTokenException
     */
    public function lexMe(string $text, array $tokens): Generator
    {
        $this->text = $text;
        $this->tokens = $tokens;
        $this->namespaceStack = null;
        $offset = 0;
        $maxOffset = strlen($this->text);
        $this->lexerState = 'default';
        $stack = false;

        foreach ($this->tokens as &$tokens) {
            $_tokens = [];
            foreach ($tokens as $fullLexeme => $regex) {
                if (str_contains($fullLexeme, ':')) {
                    [$lexeme, $namespace] = explode(':', $fullLexeme, 2);
                    $stack = $stack || str_starts_with($namespace, '__shift__');
                    unset($tokens[$fullLexeme]);
                    $_tokens[$lexeme] = [$regex, $namespace];
                } else {
                    $_tokens[$fullLexeme] = [$regex, null];
                }
            }
            $tokens = $_tokens;
        }

        if ($stack) {
            /**
             * @psalm-suppress MixedPropertyTypeCoercion
             */
            $this->namespaceStack = new SplStack;
        }

        while ($offset < $maxOffset) {
            $nextToken = $this->nextToken($offset);
            if ($nextToken === null) {
                $message = sprintf(
                    'Unrecognized token "%s" at line 1 and column %d:' . "\n" .
                    '%s' . "\n" .
                    str_repeat(' ', mb_strlen(substr($text, 0, $offset))) . '↑',
                    mb_substr(substr($text, $offset), 0, 1),
                    $offset + 1,
                    $text
                );
                throw new UnrecognizedTokenException($message, 0, 1, $offset);
            }
            if ($nextToken['keep'] === true) {
                $nextToken['offset'] = $offset;
                yield new Token($nextToken['token'], $nextToken['value'], $nextToken['length'], $nextToken['namespace'], $nextToken['keep'], $nextToken['offset']);
            }
            $offset += strlen($nextToken['value']);
        }

        yield Token::eof($offset);
    }

    /**
     * Compute the next token recognized at the beginning of the string.
     * @param int $offset Offset.
     * @return ?array{keep:bool,length:int,namespace:string,token:string,value:string}
     * @throws LexerException
     */
    protected function nextToken(int $offset): ?array
    {
        /**
         * @todo not clear how $this->tokens comes to be this new form of array
         * @var array<string,array{string,?string}> $tokenArray
         */
        $tokenArray = &$this->tokens[$this->lexerState];

        foreach ($tokenArray as $lexeme => [$regex, $nextState]) {
            if ($nextState === null) {
                $nextState = $this->lexerState;
            }

            $out = $this->matchLexeme($lexeme, $regex, $offset);

            if ($out !== null) {
                $out['namespace'] = $this->lexerState;
                $out['keep'] = 'skip' !== $lexeme;

                if ($nextState !== $this->lexerState) {
                    $shift = false;

                    if ($this->namespaceStack !== null &&
                        preg_match('#^__shift__(?:\s*\*\s*(\d+))?$#', $nextState, $matches) !== 0) {
                        $i = isset($matches[1]) ? intval($matches[1]) : 1;

                        if ($i > ($c = count($this->namespaceStack))) {
                            $message = sprintf('Cannot shift namespace %d-times, from token ' .
                                '%s in namespace %s, because the stack ' .
                                'contains only %d namespaces.', $i, $lexeme, $this->lexerState, $c);
                            throw new LexerException($message, 1);
                        }

                        $previousNamespace = null;
                        while (1 <= $i--) {
                            $previousNamespace = $this->namespaceStack->pop();
                        }

                        $nextState = $previousNamespace;
                        $shift = true;
                    }

                    assert(is_string($nextState));
                    if (!isset($this->tokens[$nextState])) {
                        $message = sprintf('Namespace %s does not exist, called by token %s in namespace %s.', $nextState, $lexeme, $this->lexerState);
                        throw new LexerException($message, 2);
                    }

                    if ($this->namespaceStack !== null && !$shift) {
                        $this->namespaceStack->push($this->lexerState);
                    }

                    $this->lexerState = $nextState;
                }

                return $out;
            }
        }

        return null;
    }

    /**
     * Check if a given lexeme is matched at the beginning of the text.
     * @param string $lexeme Name of the lexeme.
     * @param string $regex Regular expression describing the lexeme.
     * @param int $offset Offset.
     * @return array{token:string,value:string,length:int}|null
     * @throws LexerException
     */
    protected function matchLexeme(string $lexeme, string $regex, int $offset): array|null
    {
        if ($this->text === null) {
            return null;
        }

        $_regex = str_replace('#', '\#', $regex);
        $preg = preg_match('#\G(?|' . $_regex . ')#' . $this->pcreOptions, $this->text, $matches, 0, $offset);

        if ($preg === 0 || !isset($matches[0])) {
            return null;
        }

        if ($matches[0] === '') {
            $message = sprintf('A lexeme must not match an empty value, which is the case of "%s" (%s).', $lexeme, $regex);
            throw new LexerException($message, 3);
        }

        return [
            'token'  => $lexeme,
            'value'  => $matches[0],
            'length' => mb_strlen($matches[0])
        ];
    }
}
