@props([
    'type',
    'labels',
    'series',
    'colors' => [],
    'formatter' => 'number',
    'height' => '260px',
    'ariaLabel' => '',
    'maxTicks' => 8,
])

@php
    $series = is_array($series) && (empty($series) || ! is_array(reset($series))) ? [$series] : $series;
@endphp

<div
    x-data="{
        chart: null,
        config: {
            type: @js($type),
            labels: @js($labels),
            series: @js($series),
            colors: @js($colors),
            formatter: @js($formatter),
            maxTicks: @js($maxTicks),
        },
        init() {
            if (typeof Chart !== 'function') {
                return;
            }

            const styles = getComputedStyle(document.documentElement);
            const isDark = document.documentElement.classList.contains('dark');
            const labelColor = isDark ? '#a1a1aa' : '#78716c';
            const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';

            Chart.getChart(this.$refs.canvas)?.destroy();

            this.chart = new Chart(this.$refs.canvas, {
                type: this.config.type,
                data: {
                    labels: this.config.labels,
                    datasets: this.datasets(styles),
                },
                options: this.options(labelColor, gridColor),
            });
        },
        destroy() {
            this.chart?.destroy();
        },
        datasets(styles) {
            if (this.config.type === 'doughnut') {
                return [{
                    data: this.config.series[0] ?? [],
                    backgroundColor: this.config.colors.map((color) => this.resolveColor(color, styles)),
                    borderWidth: 0,
                }];
            }

            if (this.config.type === 'bar') {
                return [{
                    data: this.config.series[0] ?? [],
                    backgroundColor: this.resolveColor(this.config.colors[0] ?? 'brand-600', styles, '#059669'),
                    borderRadius: 10,
                    borderSkipped: false,
                }];
            }

            return [{
                data: this.config.series[0] ?? [],
                borderColor: this.resolveColor(this.config.colors[0] ?? 'brand-600', styles, '#059669'),
                backgroundColor: this.config.colors[1] ?? 'rgba(5,150,105,0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2,
            }];
        },
        options(labelColor, gridColor) {
            if (this.config.type === 'doughnut') {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: labelColor,
                                usePointStyle: true,
                                boxWidth: 10,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.label}: ${this.formatNumber(ctx.parsed ?? 0)}`,
                            },
                        },
                    },
                };
            }

            return {
                responsive: true,
                maintainAspectRatio: false,
                interaction: this.config.type === 'line'
                    ? { mode: 'index', intersect: false }
                    : undefined,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        displayColors: false,
                        callbacks: {
                            label: (ctx) => this.formatValue(ctx.parsed.y ?? 0, true),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: labelColor,
                            maxTicksLimit: this.config.maxTicks,
                        },
                    },
                    y: {
                        beginAtZero: this.config.type === 'bar' ? true : undefined,
                        grid: { color: gridColor },
                        ticks: {
                            color: labelColor,
                            precision: this.config.formatter === 'currency' ? undefined : 0,
                            callback: (value) => this.formatValue(value, false),
                        },
                    },
                },
            };
        },
        resolveColor(color, styles, fallback = color) {
            if (typeof color !== 'string') {
                return fallback;
            }

            if (color.startsWith('brand-')) {
                return styles.getPropertyValue(`--${color}`).trim() || fallback;
            }

            return color;
        },
        formatValue(value, tooltip) {
            if (this.config.formatter === 'currency') {
                return '\u20b1' + Number(value).toLocaleString('en-PH', {
                    minimumFractionDigits: tooltip ? 2 : 0,
                    maximumFractionDigits: tooltip ? 2 : 0,
                });
            }

            return this.formatNumber(value);
        },
        formatNumber(value) {
            return Number(value).toLocaleString('en-PH');
        },
    }"
    {{ $attributes->merge(['class' => 'mt-6']) }}
    style="height: {{ $height }}; position: relative;"
>
    <canvas x-ref="canvas" role="img" aria-label="{{ $ariaLabel }}"></canvas>
</div>
