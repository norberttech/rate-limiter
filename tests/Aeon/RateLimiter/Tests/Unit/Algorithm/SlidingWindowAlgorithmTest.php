<?php

declare(strict_types=1);

namespace Aeon\RateLimiter\Tests\Unit\Algorithm;

use Aeon\Calendar\Gregorian\DateTime;
use Aeon\Calendar\Gregorian\GregorianCalendarStub;
use Aeon\Calendar\Gregorian\TimeZone;
use Aeon\Calendar\TimeUnit;
use Aeon\RateLimiter\Algorithm\SlidingWindowAlgorithm;
use Aeon\RateLimiter\Exception\RateLimitException;
use Aeon\RateLimiter\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;

final class SlidingWindowAlgorithmTest extends TestCase
{
    public function test_with_available_hits() : void
    {
        $algorithm = new SlidingWindowAlgorithm($calendar = new GregorianCalendarStub(TimeZone::UTC()), 2, TimeUnit::minute());
        $algorithm->hit('hit_id', $memoryStorage = new MemoryStorage($calendar));

        $this->assertSame($algorithm->nextHit('hit_id', $memoryStorage)->inSeconds(), 0);
    }

    public function test_without_available_hits() : void
    {
        $algorithm = new SlidingWindowAlgorithm($calendar = new GregorianCalendarStub(TimeZone::UTC()), 1, TimeUnit::minute());
        $algorithm->hit('hit_id', $memoryStorage = new MemoryStorage($calendar));

        $this->assertSame($algorithm->nextHit('hit_id', $memoryStorage)->inSeconds(), 59);
    }

    public function test_hit_without_available_hits() : void
    {
        $algorithm = new SlidingWindowAlgorithm($calendar = new GregorianCalendarStub(TimeZone::UTC()), 1, TimeUnit::minute());
        $calendar->setNow(DateTime::fromString('2020-01-01 00:00:00 UTC'));

        $algorithm->hit('hit_id', $memoryStorage = new MemoryStorage($calendar));

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Execution "hit_id" was limited for the next 60.000000 seconds');
        $this->expectExceptionCode(0);

        $algorithm->hit('hit_id', $memoryStorage);
    }

    public function test_hit_without_available_hits_exception() : void
    {
        $algorithm = new SlidingWindowAlgorithm($calendar = new GregorianCalendarStub(TimeZone::UTC()), 1, TimeUnit::minute());
        $calendar->setNow(DateTime::fromString('2020-01-01 00:00:00 UTC'));

        $algorithm->hit('hit_id', $memoryStorage = new MemoryStorage($calendar));

        try {
            $algorithm->hit('hit_id', $memoryStorage);
        } catch (RateLimitException $e) {
            $this->assertSame('hit_id', $e->id());
            $this->assertEquals(TimeUnit::minute(), $e->cooldown());
        }
    }

    public function test_resetting_hits() : void
    {
        $algorithm = new SlidingWindowAlgorithm($calendar = new GregorianCalendarStub(TimeZone::UTC()), 1, TimeUnit::minute());

        $calendar->setNow(DateTime::fromString('2020-01-01 00:00:00 UTC'));

        $algorithm->hit('hit_id', $memoryStorage = new MemoryStorage($calendar));

        $this->assertSame($algorithm->nextHit('hit_id', $memoryStorage)->inSeconds(), 60);

        $calendar->setNow($calendar->now()->add(TimeUnit::seconds(61)));

        $this->assertSame($algorithm->nextHit('hit_id', $memoryStorage)->inSeconds(), 0);

        $algorithm->hit('hit_id', $memoryStorage);

        $this->assertSame($algorithm->nextHit('hit_id', $memoryStorage)->inSeconds(), 60);

        $calendar->setNow($calendar->now()->add(TimeUnit::seconds(61)));

        $this->assertSame($algorithm->nextHit('hit_id', $memoryStorage)->inSeconds(), 0);

        $algorithm->hit('hit_id', $memoryStorage);
    }
}
