<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A {@see Client} whose transport layer is replaced by a pre-scripted queue of
 * outcomes — each entry is either a {@see ResponseInterface} to return or a
 * {@see \Throwable} to throw. This lets the retry orchestration in
 * sendRequest() be tested deterministically, with zero network access, by
 * overriding the protected transport() seam.
 */
final class ScriptedClient extends Client
{
    /** @var list<ResponseInterface|\Throwable> */
    private array $script;

    public int $calls = 0;

    /**
     * @param list<ResponseInterface|\Throwable> $script
     */
    public function __construct(array $script)
    {
        parent::__construct();
        $this->script = $script;
    }

    protected function transport(RequestInterface $request): ResponseInterface
    {
        $this->calls++;
        if (empty($this->script)) {
            throw new \LogicException('ScriptedClient ran out of scripted transport outcomes.');
        }
        $outcome = \array_shift($this->script);
        if ($outcome instanceof \Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}
