<?php

namespace Penelope\Tests\TestListener;

use PHPUnit\Event\Test\BeforeFirstTestMethodCalled;
use PHPUnit\Event\Test\BeforeFirstTestMethodCalledSubscriber;
use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;
use PHPUnit\Event\TestSuite\Finished as SuiteFinished;
use PHPUnit\Event\TestSuite\FinishedSubscriber;

class PenelopeEventSubscriber implements 
    BeforeFirstTestMethodCalledSubscriber,
    BeforeTestMethodCalledSubscriber,
    FinishedSubscriber,
    StartedSubscriber,
    FinishedSubscriber
{
    private array $performanceResults = [];
    private ?string $currentSuite = null;
    private int $testCount = 0;
    private int $currentTestNumber = 0;

    public function notify(BeforeFirstTestMethodCalled $event): void
    {
        echo "\n🚀 Starting Penelope Test Suite\n";
        echo str_repeat('=', 80) . "\n";
    }

    public function notify(BeforeTestMethodCalled $event): void
    {
        $this->currentTestNumber++;
        $name = $this->formatTestName($event->methodName());
        printf("[%d/%d] ⚡ %s\n", $this->currentTestNumber, $this->testCount, $name);
    }

    public function notify(Finished $event): void
    {
        if (str_contains($event->test()->className(), 'Performance')) {
            $this->performanceResults[] = [
                'test' => $event->test()->name(),
                'time' => $event->telemetry()->duration()->asFloat()
            ];
        }
    }

    public function notify(Started $event): void
    {
        if ($event->testSuite()->name() !== '') {
            $this->currentSuite = $event->testSuite()->name();
            echo "\n🔍 Running Test Suite: {$this->currentSuite}\n";
            echo str_repeat('-', 80) . "\n";
            $this->testCount = $event->testSuite()->count();
            $this->currentTestNumber = 0;
        }
    }

    public function notify(SuiteFinished $event): void
    {
        if (!empty($this->performanceResults) && $event->testSuite()->name() === 'Performance') {
            $this->displayPerformanceResults();
            $this->performanceResults = [];
        }
    }

    private function formatTestName(string $name): string
    {
        // Convert camelCase to readable format
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
        $name = ucfirst(strtolower($name));
        return $name;
    }

    private function displayPerformanceResults(): void
    {
        echo "\n📊 Performance Test Results\n";
        echo str_repeat('=', 80) . "\n";
        
        foreach ($this->performanceResults as $result) {
            $testName = $this->formatTestName($result['test']);
            $time = number_format($result['time'], 3);
            echo sprintf("%-50s %10s seconds\n", $testName, $time);
        }
        
        echo str_repeat('=', 80) . "\n\n";
    }
}
