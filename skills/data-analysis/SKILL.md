---
name: data-analysis
description: "Analyze datasets, generate statistics, create data pipelines, and produce insights. Use when asked to analyze data, create reports, or process CSV/JSON data."
license: MIT
metadata:
  author: claude-php-agent
  version: "1.0.0"
  tags: [data, analysis, statistics, csv, json]
---

# Data Analysis

## Overview

Analyze datasets and generate actionable insights. Supports CSV, JSON, and database data sources. Produces statistical summaries, trend analysis, and formatted reports.

## Capabilities

### 1. Data Loading
- CSV file parsing with header detection
- JSON file and API response parsing
- Database query result processing
- Stream processing for large datasets

### 2. Statistical Analysis
- Descriptive statistics (mean, median, mode, std dev)
- Distribution analysis
- Correlation analysis
- Outlier detection using IQR method
- Time series trend analysis

### 3. Data Transformation
- Filtering and sorting
- Grouping and aggregation
- Pivot tables
- Data normalization
- Missing value handling

### 4. Report Generation
- Summary tables
- Key findings with supporting data
- Comparison analysis
- Trend identification

## PHP Implementation Pattern

```php
// Load CSV data
$data = array_map('str_getcsv', file('data.csv'));
$headers = array_shift($data);

// Calculate statistics
$values = array_column($data, $columnIndex);
$mean = array_sum($values) / count($values);
$sorted = $values;
sort($sorted);
$median = $sorted[intdiv(count($sorted), 2)];

// Group and aggregate
$grouped = [];
foreach ($data as $row) {
    $key = $row[$groupByIndex];
    $grouped[$key][] = $row;
}
```

## Output Format

Provide analysis results as:
1. **Executive Summary** - Key findings in 2-3 sentences
2. **Detailed Statistics** - Tables with relevant metrics
3. **Insights** - Patterns and anomalies discovered
4. **Recommendations** - Actionable next steps based on data
