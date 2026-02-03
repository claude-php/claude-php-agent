<?php

/**
 * Tutorial 5: Custom Events
 * 
 * Learn how to:
 * - Create custom event types
 * - Register custom event handlers
 * - Emit custom events from agents
 * - Build domain-specific event systems
 * 
 * Estimated time: 15 minutes
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use ClaudeAgents\Events\EventQueue;
use ClaudeAgents\Events\FlowEvent;
use ClaudeAgents\Events\FlowEventManager;

echo "=======================\n";
echo "Tutorial 5: Custom Events\n";
echo "=======================\n\n";

// Step 1: Define custom event types
echo "Step 1: Defining custom event types\n";
echo "------------------------------------\n";

/**
 * Custom FlowEvent subclass for domain-specific events
 */
class CustomFlowEvent extends FlowEvent
{
    // Custom event types for a hypothetical trading system
    public const TRADE_EXECUTED = 'trade.executed';
    public const PRICE_ALERT = 'price.alert';
    public const RISK_WARNING = 'risk.warning';
    public const PORTFOLIO_UPDATE = 'portfolio.update';
    
    /**
     * Create a trade executed event
     */
    public static function tradeExecuted(string $symbol, float $quantity, float $price): self
    {
        return new self(
            type: self::TRADE_EXECUTED,
            data: [
                'symbol' => $symbol,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $quantity * $price,
            ],
            timestamp: microtime(true)
        );
    }
    
    /**
     * Create a price alert event
     */
    public static function priceAlert(string $symbol, float $price, string $condition): self
    {
        return new self(
            type: self::PRICE_ALERT,
            data: [
                'symbol' => $symbol,
                'price' => $price,
                'condition' => $condition,
            ],
            timestamp: microtime(true)
        );
    }
    
    /**
     * Create a risk warning event
     */
    public static function riskWarning(string $message, string $severity): self
    {
        return new self(
            type: self::RISK_WARNING,
            data: [
                'message' => $message,
                'severity' => $severity,
            ],
            timestamp: microtime(true)
        );
    }
}

echo "✅ Custom event types defined:\n";
echo "   - TRADE_EXECUTED\n";
echo "   - PRICE_ALERT\n";
echo "   - RISK_WARNING\n";
echo "   - PORTFOLIO_UPDATE\n\n";

// Step 2: Create event manager with custom handlers
echo "Step 2: Registering custom event handlers\n";
echo "------------------------------------------\n";

$eventQueue = new EventQueue(maxSize: 100);
$eventManager = new FlowEventManager($eventQueue);

// Register handler for trade executed events
$eventManager->registerEvent(
    'on_trade_executed',
    CustomFlowEvent::TRADE_EXECUTED,
    function(FlowEvent $event) {
        $data = $event->data;
        printf(
            "💰 TRADE: %s %.2f @ $%.2f = $%.2f\n",
            $data['symbol'],
            $data['quantity'],
            $data['price'],
            $data['total']
        );
    }
);

// Register handler for price alerts
$eventManager->registerEvent(
    'on_price_alert',
    CustomFlowEvent::PRICE_ALERT,
    function(FlowEvent $event) {
        $data = $event->data;
        printf(
            "🔔 ALERT: %s at $%.2f (%s)\n",
            $data['symbol'],
            $data['price'],
            $data['condition']
        );
    }
);

// Register handler for risk warnings
$eventManager->registerEvent(
    'on_risk_warning',
    CustomFlowEvent::RISK_WARNING,
    function(FlowEvent $event) {
        $data = $event->data;
        $icon = $data['severity'] === 'high' ? '🚨' : '⚠️';
        printf(
            "%s RISK [%s]: %s\n",
            $icon,
            strtoupper($data['severity']),
            $data['message']
        );
    }
);

echo "✅ Custom event handlers registered\n\n";

// Step 3: Emit custom events
echo "Step 3: Emitting custom events\n";
echo "-------------------------------\n\n";

echo "Simulating trading activity...\n\n";

// Trade 1
$event1 = CustomFlowEvent::tradeExecuted('AAPL', 100, 175.50);
$eventManager->emit($event1->type, $event1->data);
usleep(100000);

// Price alert
$event2 = CustomFlowEvent::priceAlert('TSLA', 245.75, 'above target');
$eventManager->emit($event2->type, $event2->data);
usleep(100000);

// Trade 2
$event3 = CustomFlowEvent::tradeExecuted('GOOGL', 50, 140.25);
$eventManager->emit($event3->type, $event3->data);
usleep(100000);

// Risk warning
$event4 = CustomFlowEvent::riskWarning('Portfolio volatility increased', 'medium');
$eventManager->emit($event4->type, $event4->data);
usleep(100000);

// Trade 3
$event5 = CustomFlowEvent::tradeExecuted('MSFT', 75, 385.00);
$eventManager->emit($event5->type, $event5->data);
usleep(100000);

// Critical risk warning
$event6 = CustomFlowEvent::riskWarning('Stop loss triggered', 'high');
$eventManager->emit($event6->type, $event6->data);

echo "\n";

// Step 4: Subscribe additional listeners
echo "Step 4: Adding aggregate listeners\n";
echo "-----------------------------------\n";

$tradeStats = ['total_value' => 0, 'trade_count' => 0];
$alertStats = ['alerts' => 0, 'warnings' => 0];

$aggregateListener = function(FlowEvent $event) use (&$tradeStats, &$alertStats) {
    if ($event->type === CustomFlowEvent::TRADE_EXECUTED) {
        $tradeStats['total_value'] += $event->data['total'];
        $tradeStats['trade_count']++;
    } elseif ($event->type === CustomFlowEvent::PRICE_ALERT) {
        $alertStats['alerts']++;
    } elseif ($event->type === CustomFlowEvent::RISK_WARNING) {
        $alertStats['warnings']++;
    }
};

$eventManager->subscribe($aggregateListener);
echo "✅ Aggregate statistics listener added\n\n";

// Re-process events from queue
echo "Step 5: Processing queued events\n";
echo "---------------------------------\n\n";

$processedEvents = 0;
while (!$eventQueue->isEmpty()) {
    $event = $eventQueue->dequeue();
    if ($event) {
        // Events automatically handled by listeners
        $processedEvents++;
    }
}

echo "Processed {$processedEvents} events\n\n";

// Step 6: Display statistics
echo "Step 6: Statistics Summary\n";
echo "--------------------------\n\n";

echo "Trade Statistics:\n";
echo "  Total Trades: {$tradeStats['trade_count']}\n";
echo "  Total Value: $" . number_format($tradeStats['total_value'], 2) . "\n\n";

echo "Alert Statistics:\n";
echo "  Price Alerts: {$alertStats['alerts']}\n";
echo "  Risk Warnings: {$alertStats['warnings']}\n";

echo "\n";
echo "=======================\n";
echo "Tutorial Complete! ✅\n";
echo "=======================\n\n";

echo "What you learned:\n";
echo "- How to define custom event types\n";
echo "- How to create domain-specific events\n";
echo "- How to register custom event handlers\n";
echo "- How to build aggregate listeners\n\n";

echo "Next: Try tutorial 06-error-handling.php\n";
