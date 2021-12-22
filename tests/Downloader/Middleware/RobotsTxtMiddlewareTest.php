<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/roach-php/roach
 */

namespace RoachPHP\Tests\Downloader\Middleware;

use RoachPHP\Core\Engine;
use RoachPHP\Core\Run;
use RoachPHP\Downloader\Downloader;
use RoachPHP\Downloader\DownloaderMiddlewareInterface;
use RoachPHP\Downloader\Middleware\DownloaderMiddlewareAdapter;
use RoachPHP\Downloader\Middleware\RobotsTxtMiddleware;
use RoachPHP\Events\FakeDispatcher;
use RoachPHP\Http\Client;
use RoachPHP\Http\Request;
use RoachPHP\ItemPipeline\ItemPipeline;
use RoachPHP\ResponseProcessing\ParseResult;
use RoachPHP\ResponseProcessing\Processor;
use RoachPHP\Scheduling\ArrayRequestScheduler;
use RoachPHP\Scheduling\Timing\FakeClock;
use RoachPHP\Tests\IntegrationTest;
use RoachPHP\Tests\InteractsWithRequestsAndResponses;

/**
 * @internal
 */
final class RobotsTxtMiddlewareTest extends IntegrationTest
{
    use InteractsWithRequestsAndResponses;

    private Engine $engine;

    private DownloaderMiddlewareInterface $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = new FakeDispatcher();
        $this->engine = new Engine(
            new ArrayRequestScheduler(new FakeClock()),
            new Downloader(new Client(), $dispatcher),
            new ItemPipeline($dispatcher),
            new Processor($dispatcher),
            $dispatcher,
        );

        $middleware = new RobotsTxtMiddleware();
        $middleware->configure(['fileName' => 'robots']);
        $this->middleware = new DownloaderMiddlewareAdapter($middleware);
    }

    public function testOnlyRequestsRobotsTxtOnceForRequestsToSameDomain(): void
    {
        $parseCallback = fn () => yield ParseResult::fromValue($this->makeRequest('http://localhost:8000/test2'));
        $run = new Run(
            [new Request('http://localhost:8000/test1', $parseCallback)],
            downloaderMiddleware: [$this->middleware],
        );

        $this->engine->start($run);

        $this->assertRouteWasCrawledTimes('/robots', 1);
    }

    public function testAllowsRequestIfAllowedByRobotsTxt(): void
    {
        $run = new Run(
            [$this->makeRequest('http://localhost:8000/test1')],
            downloaderMiddleware: [$this->middleware],
        );

        $this->engine->start($run);

        $this->assertRouteWasCrawled('/test1');
    }

    public function testDropRequestIfForbiddenByRobotsTxt(): void
    {
        $run = new Run(
            [$this->makeRequest('http://localhost:8000/test2')],
            downloaderMiddleware: [$this->middleware],
        );

        $this->engine->start($run);

        $this->assertRouteWasNotCrawled('/test2');
    }
}
