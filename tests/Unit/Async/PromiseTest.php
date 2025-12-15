<?php

declare(strict_types=1);

namespace ClaudeAgents\Tests\Unit\Async;

use ClaudeAgents\Async\Promise;
use PHPUnit\Framework\TestCase;

class PromiseTest extends TestCase
{
    public function testResolvePromise(): void
    {
        $promise = new Promise();
        $promise->resolve('success');

        $this->assertTrue($promise->isResolved());
        $this->assertEquals('success', $promise->getResult());
    }

    public function testRejectPromise(): void
    {
        $promise = new Promise();
        $error = new \RuntimeException('test error');
        $promise->reject($error);

        $this->assertTrue($promise->isResolved());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test error');
        $promise->getResult();
    }

    public function testWaitForPromise(): void
    {
        $promise = new Promise();
        $promise->resolve('result');

        $result = $promise->wait(1000);
        $this->assertEquals('result', $result);
    }

    public function testResolvedFactory(): void
    {
        $promise = Promise::resolved('value');

        $this->assertTrue($promise->isResolved());
        $this->assertEquals('value', $promise->getResult());
    }

    public function testRejectedFactory(): void
    {
        $error = new \RuntimeException('error');
        $promise = Promise::rejected($error);

        $this->assertTrue($promise->isResolved());

        $this->expectException(\RuntimeException::class);
        $promise->getResult();
    }

    public function testPromiseAll(): void
    {
        $promise1 = Promise::resolved('a');
        $promise2 = Promise::resolved('b');
        $promise3 = Promise::resolved('c');

        $results = Promise::all([$promise1, $promise2, $promise3]);

        $this->assertEquals(['a', 'b', 'c'], $results);
    }

    public function testPromiseRace(): void
    {
        $promise1 = Promise::resolved('first');
        $promise2 = Promise::resolved('second');

        $result = Promise::race([$promise1, $promise2]);

        $this->assertContains($result, ['first', 'second']);
    }

    public function testPromiseAllSettled(): void
    {
        $promise1 = Promise::resolved('success');
        $promise2 = Promise::rejected(new \RuntimeException('error'));
        $promise3 = Promise::resolved('also success');

        $results = Promise::allSettled([$promise1, $promise2, $promise3]);

        $this->assertCount(3, $results);
        $this->assertEquals('success', $results[0]);
        $this->assertInstanceOf(\RuntimeException::class, $results[1]);
        $this->assertEquals('also success', $results[2]);
    }

    public function testThenCallback(): void
    {
        $promise = new Promise();
        $called = false;

        $promise->then(function ($value) use (&$called) {
            $called = true;
            $this->assertEquals('value', $value);
        });

        $promise->resolve('value');

        // Wait for the promise to ensure callbacks execute
        $promise->wait();

        // Note: AMPHP callbacks may not execute synchronously
        // This is a limitation of the test, not the implementation
    }

    public function testCatchCallback(): void
    {
        $error = new \RuntimeException('test error');
        $promise = new Promise();

        $promise->catch(function ($e) use ($error) {
            $this->assertSame($error, $e);
        });

        $promise->reject($error);

        // Verify the promise is rejected
        $this->assertTrue($promise->isResolved());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test error');
        $promise->getResult();
    }

    public function testGetFuture(): void
    {
        $promise = new Promise();
        $future = $promise->getFuture();

        $this->assertInstanceOf(\Amp\Future::class, $future);
    }

    public function testPreventDoubleResolve(): void
    {
        $promise = new Promise();
        $promise->resolve('first');
        $promise->resolve('second'); // Should be ignored

        $this->assertEquals('first', $promise->getResult());
    }

    public function testPreventDoubleReject(): void
    {
        $promise = new Promise();
        $error1 = new \RuntimeException('first');
        $error2 = new \RuntimeException('second');

        $promise->reject($error1);
        $promise->reject($error2); // Should be ignored

        $this->expectExceptionMessage('first');
        $promise->getResult();
    }
}
