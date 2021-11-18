<?php declare(strict_types=1);

namespace Hoa\Compiler\Llk;

use Generator;
use Hoa\Compiler;
use Hoa\Compiler\Exceptions\UnrecognizedTokenException;
use SplStack;

class Lexer
{
    /**
     * Lexer state.
     * @var array|string
     */
    protected array|string $_lexerState;

    /**
     * Text.
     */
    protected ?string $_text = null;

    /**
     * Tokens.
     */
    protected array $_tokens = [];

    /**
     * Namespace stacks.
     */
    protected ?SplStack $_nsStack = null;

    /**
     * PCRE options.
     */
    protected ?string $_pcreOptions = null;

    /**
     * @param array $pragmas Pragmas.
     */
    public function __construct(array $pragmas = [])
    {
        if (!isset($pragmas['lexer.unicode']) || true === $pragmas['lexer.unicode']) {
            $this->_pcreOptions .= 'u';
        }
    }

    /**
     * Text tokenizer: splits the text in parameter in an ordered array of tokens.
     *
     * @param string $text Text to tokenize.
     * @param array $tokens Tokens to be returned.
     * @return  Generator
     * @throws  UnrecognizedTokenException
     */
    public function lexMe($text, array $tokens)
    {
        $this->_text = $text;
        $this->_tokens = $tokens;
        $this->_nsStack = null;
        $offset = 0;
        $maxOffset = strlen($this->_text);
        $this->_lexerState = 'default';
        $stack = false;

        foreach ($this->_tokens as &$tokens) {
            $_tokens = [];

            foreach ($tokens as $fullLexeme => $regex) {
                if (false === strpos($fullLexeme, ':')) {
                    $_tokens[$fullLexeme] = [$regex, null];

                    continue;
                }

                list($lexeme, $namespace) = explode(':', $fullLexeme, 2);

                $stack = $stack || str_starts_with($namespace, '__shift__');

                unset($tokens[$fullLexeme]);
                $_tokens[$lexeme] = [$regex, $namespace];
            }

            $tokens = $_tokens;
        }

        if (true == $stack) {
            $this->_nsStack = new SplStack();
        }

        while ($offset < $maxOffset) {
            $nextToken = $this->nextToken($offset);

            if (null === $nextToken) {
                $message = sprintf('Unrecognized token "%s" at line 1 and column %d:' .
                    "\n" . '%s' . "\n" .
                    str_repeat(' ', mb_strlen(substr($text, 0, $offset))) . '↑', mb_substr(substr($text, $offset), 0, 1),
                    $offset + 1,
                    $text);
                throw new UnrecognizedTokenException($message, 0, 1, $offset);
            }

            if (true === $nextToken['keep']) {
                $nextToken['offset'] = $offset;
                yield $nextToken;
            }

            $offset += strlen($nextToken['value']);
        }

        yield [
            'token' => 'EOF',
            'value' => 'EOF',
            'length' => 0,
            'namespace' => 'default',
            'keep' => true,
            'offset' => $offset
        ];
    }

    /**
     * Compute the next token recognized at the beginning of the string.
     * @param int $offset Offset.
     * @throws Compiler\Exceptions\LexerException
     */
    protected function nextToken(int $offset): array|null
    {
        assert(is_string($this->_lexerState));
        $tokenArray = &$this->_tokens[$this->_lexerState];

        foreach ($tokenArray as $lexeme => $bucket) {
            list($regex, $nextState) = $bucket;

            if (null === $nextState) {
                $nextState = $this->_lexerState;
            }

            $out = $this->matchLexeme($lexeme, $regex, $offset);

            if (null !== $out) {
                $out['namespace'] = $this->_lexerState;
                $out['keep'] = 'skip' !== $lexeme;

                if ($nextState !== $this->_lexerState) {
                    $shift = false;

                    if (null !== $this->_nsStack &&
                        0 !== preg_match('#^__shift__(?:\s*\*\s*(\d+))?$#', $nextState, $matches)) {
                        $i = isset($matches[1]) ? intval($matches[1]) : 1;

                        if ($i > ($c = count($this->_nsStack))) {
                            $message = sprintf('Cannot shift namespace %d-times, from token ' .
                                '%s in namespace %s, because the stack ' .
                                'contains only %d namespaces.', $i, $lexeme, $this->_lexerState, $c);
                            throw new Compiler\Exceptions\LexerException($message, 1);
                        }

                        $previousNamespace = null;
                        while (1 <= $i--) {
                            $previousNamespace = $this->_nsStack->pop();
                        }

                        $nextState = $previousNamespace;
                        $shift = true;
                    }

                    if (!isset($this->_tokens[$nextState])) {
                        $message = sprintf('Namespace %s does not exist, called by token %s ' .
                            'in namespace %s.', $nextState ?? 'unknown',
                            $lexeme,
                            $this->_lexerState);
                        throw new Compiler\Exceptions\LexerException($message, 2);
                    }

                    if (null !== $this->_nsStack && false === $shift) {
                        $this->_nsStack->push($this->_lexerState);
                    }

                    $this->_lexerState = $nextState;
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
     * @throws Compiler\Exceptions\LexerException
     */
    protected function matchLexeme(string $lexeme, string $regex, int $offset): array|null
    {
        $_regex = str_replace('#', '\#', $regex);
        $preg = preg_match(
            '#\G(?|' . $_regex . ')#' . $this->_pcreOptions,
            $this->_text ?? '',
            $matches,
            0,
            $offset
        );

        if (0 === $preg) {
            return null;
        }

        if ('' === $matches[0]) {
            $message = sprintf('A lexeme must not match an empty value, which is the case of "%s" (%s).', $lexeme, $regex);
            throw new Compiler\Exceptions\LexerException($message, 3);
        }

        return [
            'token' => $lexeme,
            'value' => $matches[0],
            'length' => mb_strlen($matches[0])
        ];
    }
}
