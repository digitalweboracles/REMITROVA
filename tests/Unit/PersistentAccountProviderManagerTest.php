<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\Payments\PersistentAccountProviderInterface;
use App\Services\Payments\PersistentAccountProviderManager;
use App\Services\Payments\ProviderAccountResult;
use App\Services\Payments\ProviderHealthTracker;
use App\Services\Payments\ProviderRequestFailedException;
use PHPUnit\Framework\TestCase;

/**
 * A fake provider whose behavior (succeed/fail) is controlled per test,
 * so the manager's failover logic can be tested without hitting any
 * real payment API.
 */
class FakeProvider implements PersistentAccountProviderInterface
{
    public int $callCount = 0;

    public function __construct(private readonly string $providerName, private $shouldFail = false)
    {
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function createPersistentAccount(Customer $customer, string $accountReference, string $callbackUrl): ProviderAccountResult
    {
        $this->callCount++;
        $fail = is_callable($this->shouldFail) ? ($this->shouldFail)() : $this->shouldFail;

        if ($fail) {
            throw new ProviderRequestFailedException($this->providerName, 'simulated failure');
        }

        return new ProviderAccountResult($this->providerName, 'ACC123', 'Fake Bank', []);
    }
}

/**
 * In-memory stand-in for ProviderHealthTracker, used ONLY in these
 * tests. The real ProviderHealthTracker calls Laravel's Cache facade,
 * which requires a booted Laravel app to resolve — using the real one
 * here would make these "unit" tests secretly require the framework,
 * and they'd error out (not fail — error) if ever run via plain
 * `phpunit` rather than through Laravel's test harness. Subclassing
 * and overriding with a plain array keeps these tests true unit tests:
 * they verify PersistentAccountProviderManager's control flow in
 * isolation, independent of how health state happens to be stored.
 */
class FakeHealthTracker extends ProviderHealthTracker
{
    private array $failures = [];
    private array $down = [];

    public function isHealthy(string $providerName): bool
    {
        return !($this->down[$providerName] ?? false);
    }

    public function recordSuccess(string $providerName): void
    {
        unset($this->failures[$providerName], $this->down[$providerName]);
    }

    public function recordFailure(string $providerName): void
    {
        $this->failures[$providerName] = ($this->failures[$providerName] ?? 0) + 1;
        if ($this->failures[$providerName] >= 3) {
            $this->down[$providerName] = true;
        }
    }
}

class PersistentAccountProviderManagerTest extends TestCase
{
    public function test_primary_provider_succeeds_without_touching_fallback(): void
    {
        $paga = new FakeProvider('paga', false);
        $hitchpay = new FakeProvider('hitchpay', false);
        $manager = new PersistentAccountProviderManager([$paga, $hitchpay], new FakeHealthTracker());

        $result = $manager->createPersistentAccount(new Customer(), 'REF1', 'https://cb');

        $this->assertSame('paga', $result->providerName);
        $this->assertSame(1, $paga->callCount);
        $this->assertSame(0, $hitchpay->callCount);
    }

    public function test_falls_back_to_second_provider_when_first_fails(): void
    {
        $paga = new FakeProvider('paga', true);
        $hitchpay = new FakeProvider('hitchpay', false);
        $manager = new PersistentAccountProviderManager([$paga, $hitchpay], new FakeHealthTracker());

        $result = $manager->createPersistentAccount(new Customer(), 'REF1', 'https://cb');

        $this->assertSame('hitchpay', $result->providerName);
        $this->assertSame(1, $paga->callCount);
        $this->assertSame(1, $hitchpay->callCount);
    }

    public function test_throws_when_all_providers_fail(): void
    {
        $paga = new FakeProvider('paga', true);
        $hitchpay = new FakeProvider('hitchpay', true);
        $manager = new PersistentAccountProviderManager([$paga, $hitchpay], new FakeHealthTracker());

        $this->expectException(\RuntimeException::class);
        $manager->createPersistentAccount(new Customer(), 'REF1', 'https://cb');
    }

    public function test_circuit_breaker_skips_provider_marked_down_after_repeated_failures(): void
    {
        $paga = new FakeProvider('paga', true);
        $hitchpay = new FakeProvider('hitchpay', false);
        $health = new FakeHealthTracker();
        $manager = new PersistentAccountProviderManager([$paga, $hitchpay], $health);

        // Trip paga's circuit breaker (3 consecutive failures).
        for ($i = 0; $i < 3; $i++) {
            $manager->createPersistentAccount(new Customer(), 'REF'.$i, 'https://cb');
        }
        $this->assertFalse($health->isHealthy('paga'));
        $this->assertSame(3, $paga->callCount);

        // Next call should skip paga entirely (no new call to it) and go straight to hitchpay.
        $callsBefore = $paga->callCount;
        $manager->createPersistentAccount(new Customer(), 'REF-final', 'https://cb');
        $this->assertSame($callsBefore, $paga->callCount, 'paga should not have been called again once circuit-broken');
    }
}

