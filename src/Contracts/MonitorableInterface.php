<?php

declare(strict_types=1);

namespace ClaudeAgents\Contracts;

use ClaudeAgents\Monitoring\Metric;

/**
 * Interface for monitorable data sources.
 */
interface MonitorableInterface
{
    /**
     * Get current metrics from the data source.
     *
     * @return array<Metric>
     */
    public function getMetrics(): array;

    /**
     * Get the name/identifier of this monitorable source.
     */
    public function getName(): string;
}
