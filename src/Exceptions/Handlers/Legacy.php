<?php

declare(strict_types=1);

namespace Engelsystem\Exceptions\Handlers;

use Engelsystem\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;

class Legacy implements HandlerInterface
{
    protected ?LoggerInterface $log = null;

    public function render(Request $request, Throwable $e): void
    {
        if ($this->isCli()) {
            return;
        }
        // echo 'An <del>un</del>expected error occurred. A team of untrained monkeys has been dispatched to fix it.';
        // New error 500 template. To see the errors, set in the config `environment = development`
        $server_error = file_get_contents(__DIR__ . '/../../../resources/views/layouts/500.html');
        echo $server_error;
    }

    public function report(Throwable $e): void
    {
        $previous = $e->getPrevious();
        error_log(sprintf(
            'Exception: Code: %s, Message: %s, File: %s:%u, Previous: %s, Trace: %s',
            $e->getCode(),
            $e->getMessage(),
            $this->stripBasePath($e->getFile()),
            $e->getLine(),
            $previous ? $previous->getMessage() : 'None',
            json_encode($e->getTrace())
        ));

        if (is_null($this->log)) {
            return;
        }

        try {
            $this->log->critical('', ['exception' => $e]);
        } catch (Throwable) {
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->log = $logger;
    }

    protected function stripBasePath(string $path): string
    {
        $basePath = realpath(__DIR__ . '/../../..') . '/';
        return str_replace($basePath, '', $path);
    }

    /**
     * Test if is called from cli
     * @codeCoverageIgnore
     */
    protected function isCli(): bool
    {
        return PHP_SAPI == 'cli' || PHP_SAPI == 'phpdbg';
    }
}
