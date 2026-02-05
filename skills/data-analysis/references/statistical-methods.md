# Statistical Methods Reference

## Descriptive Statistics

### Mean (Average)
```
mean = sum(values) / count(values)
```

### Median
Sort values, take the middle value (or average of two middle values for even count).

### Standard Deviation
```
std_dev = sqrt(sum((x - mean)^2) / (n - 1))
```

### Interquartile Range (IQR)
```
Q1 = 25th percentile
Q3 = 75th percentile
IQR = Q3 - Q1
Lower fence = Q1 - 1.5 * IQR
Upper fence = Q3 + 1.5 * IQR
```

## Correlation

### Pearson Correlation
Measures linear relationship between two variables.
Range: -1 (perfect negative) to +1 (perfect positive).

### Interpretation Guide
- |r| > 0.7: Strong correlation
- 0.4 < |r| < 0.7: Moderate correlation
- |r| < 0.4: Weak correlation

## Time Series

### Moving Average
Smooth data by averaging over a window of N periods.

### Trend Detection
Use linear regression to identify upward/downward trends.
