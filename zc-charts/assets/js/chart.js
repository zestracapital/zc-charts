/**
 * ZC Charts JavaScript
 * Handles chart rendering for both Chart.js and Highcharts
 */

// Global function to initialize charts
function initZCChart(chartId, chartData, library) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    
    if (library === 'chartjs') {
        initChartJSChart(canvas, chartData);
    } else if (library === 'highcharts') {
        initHighchartsChart(canvas, chartData);
    }
}

// Initialize Chart.js chart
function initChartJSChart(canvas, chartData) {
    // Destroy existing chart if it exists
    if (canvas.chart) {
        canvas.chart.destroy();
    }
    
    // Create new chart
    canvas.chart = new Chart(canvas, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Value'
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            animation: {
                duration: 750
            }
        }
    });
}

// Initialize Highcharts chart
function initHighchartsChart(container, chartData) {
    // Convert data to Highcharts format
    const seriesData = chartData.datasets[0].data.map((value, index) => [
        new Date(chartData.labels[index]).getTime(),
        value
    ]);
    
    // Create Highcharts chart
    Highcharts.chart(container, {
        chart: {
            type: 'line',
            zoomType: 'x',
            panning: true,
            panKey: 'shift'
        },
        title: {
            text: chartData.datasets[0].label
        },
        xAxis: {
            type: 'datetime',
            title: {
                text: 'Date'
            }
        },
        yAxis: {
            title: {
                text: 'Value'
            }
        },
        legend: {
            enabled: true
        },
        plotOptions: {
            area: {
                fillColor: {
                    linearGradient: {
                        x1: 0,
                        y1: 0,
                        x2: 0,
                        y2: 1
                    },
                    stops: [
                        [0, Highcharts.getOptions().colors[0]],
                        [1, Highcharts.color(Highcharts.getOptions().colors[0]).setOpacity(0).get('rgba')]
                    ]
                },
                marker: {
                    radius: 2
                },
                lineWidth: 1,
                states: {
                    hover: {
                        lineWidth: 1
                    }
                },
                threshold: null
            }
        },
        series: [{
            name: chartData.datasets[0].label,
            data: seriesData,
            color: '#0073aa'
        }],
        responsive: {
            rules: [{
                condition: {
                    maxWidth: 500
                },
                chartOptions: {
                    legend: {
                        enabled: false
                    }
                }
            }]
        }
    });
}

// Document ready
jQuery(document).ready(function($) {
    // Chart preview functionality
    $('.zc-chart-preview-trigger').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const indicatorId = button.data('indicator-id');
        const chartContainer = button.closest('.zc-chart-preview-container');
        const chartWrapper = chartContainer.find('.zc-chart-wrapper');
        
        // Show loading
        chartWrapper.html('<p>Loading chart...</p>');
        
        // Fetch chart data
        const data = {
            action: 'zc_charts_get_chart_preview',
            indicator_id: indicatorId,
            nonce: zc_charts_ajax.nonce
        };
        
        $.post(zc_charts_ajax.ajax_url, data, function(response) {
            if (response.success) {
                // Render chart
                chartWrapper.html('<canvas id="zc-chart-preview-' + indicatorId + '"></canvas>');
                initZCChart('zc-chart-preview-' + indicatorId, response.data, 'chartjs');
            } else {
                chartWrapper.html('<p>Error loading chart: ' + response.data + '</p>');
            }
        }).fail(function() {
            chartWrapper.html('<p>Error loading chart. Please try again.</p>');
        });
    });
});