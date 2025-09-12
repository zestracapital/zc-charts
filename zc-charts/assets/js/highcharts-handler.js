/**
 * HighchartsHandler
 * JavaScript class for rendering charts using Highcharts in the ZC Charts plugin.
 * This class handles data formatting and chart options specific to Highcharts.
 */

class HighchartsHandler {
    /**
     * Constructor for the HighchartsHandler.
     * 
     * @param {HTMLElement} container - The HTML element where the chart will be rendered.
     * @param {Object} data - The data to render in the chart.
     * @param {Object} options - Configuration options for the chart.
     */
    constructor(container, data, options) {
        this.container = container;
        this.data = data || {};
        this.options = options || {};
        
        // Chart instance placeholder
        this.chartInstance = null;
    }

    /**
     * Render the chart using Highcharts.
     */
    render() {
        // Check if Highcharts is loaded
        if (typeof Highcharts === 'undefined') {
            console.error('HighchartsHandler: Highcharts library is not loaded.');
            // You might want to trigger a fallback or display an error message
            return;
        }

        // Destroy any existing chart instance to prevent memory leaks
        this.destroy();

        // Ensure the container is a div element (Highcharts typically renders to a div)
        let targetElement;
        if (this.container.tagName.toLowerCase() === 'div') {
            targetElement = this.container;
        } else {
            // If it's not a div, we should ideally replace it or handle it differently.
            // For simplicity in this example, we'll assume it's a div.
            // A more robust implementation might replace a canvas with a div.
            console.warn('HighchartsHandler: Expected a div container. Using as-is.');
            targetElement = this.container;
        }

        // Get chart configuration
        const config = this.getChartConfig();

        // Create the Highcharts instance
        this.chartInstance = Highcharts.chart(targetElement, config);
    }

    /**
     * Get the complete Highcharts configuration object.
     * 
     * @returns {Object} The Highcharts configuration.
     */
    getChartConfig() {
        const type = this.getHighchartsChartType(this.options.type || 'line');
        const formattedData = this.formatData();
        const chartOptions = this.getOptions();

        // Merge base config with options
        const config = {
            chart: {
                type: type,
                zoomType: 'x', // Enable zooming by default
                panning: true,
                panKey: 'shift',
                ...chartOptions.chart
            },
            title: {
                text: this.options.title || (this.data.name ? `${this.data.name}` : null),
                ...chartOptions.title
            },
            subtitle: {
                text: this.options.subtitle || null,
                ...chartOptions.subtitle
            },
            xAxis: {
                type: 'datetime',
                title: {
                    text: this.options.xAxisTitle || 'Date',
                    ...chartOptions.xAxis?.title
                },
                ...chartOptions.xAxis
            },
            yAxis: {
                title: {
                    text: this.options.yAxisTitle || 'Value',
                    ...chartOptions.yAxis?.title
                },
                ...chartOptions.yAxis
            },
            legend: {
                enabled: this.options.showLegend !== false, // Default to true
                ...chartOptions.legend
            },
            plotOptions: {
                series: {
                    ...chartOptions.plotOptions?.series
                },
                area: {
                    ...chartOptions.plotOptions?.area
                },
                line: {
                    ...chartOptions.plotOptions?.line
                },
                column: {
                    ...chartOptions.plotOptions?.column
                }
                // Add other plot options as needed
            },
            series: [{
                name: this.data.name || this.options.slug || 'Series',
                data: formattedData,
                ...chartOptions.series?.[0] // Allow overriding series options
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
            },
            ...chartOptions // Merge any top-level options
        };

        return config;
    }

    /**
     * Format the raw data into a Highcharts compatible format.
     * 
     * @returns {Array} The formatted data array for Highcharts (e.g., [[timestamp, value], ...]).
     */
    formatData() {
        // The PHP Charts class should have already processed the data into a Highcharts compatible format
        // But we'll provide a basic conversion as a fallback
        
        // If data is already an array of [timestamp, value] pairs, use it directly
        // This check is simplistic; a more robust check might be needed.
        if (Array.isArray(this.data) && this.data.length > 0 && Array.isArray(this.data[0])) {
            return this.data;
        }
        
        // If data has a 'data' property that is an array, process that
        const rawDataArray = (this.data && this.data.data && Array.isArray(this.data.data)) ? this.data.data : [];
        
        // Convert to Highcharts format: [timestamp, value]
        const seriesData = [];
        
        rawDataArray.forEach(point => {
            if (point.date !== undefined && point.value !== undefined) {
                // Convert date string to timestamp (in milliseconds for Highcharts)
                const timestamp = new Date(point.date).getTime();
                const value = parseFloat(point.value);
                seriesData.push([timestamp, value]);
            }
        });

        return seriesData;
    }

    /**
     * Get Highcharts options based on the configuration.
     * 
     * @returns {Object} The Highcharts options object.
     */
    getOptions() {
        // Start with default options
        const defaultOptions = {
            chart: {
                // type is set in getChartConfig
                // zoomType is set in getChartConfig
            },
            title: {
                // text is set in getChartConfig
            },
            xAxis: {
                // type is set in getChartConfig
                title: {
                    // text is set in getChartConfig
                }
            },
            yAxis: {
                title: {
                    // text is set in getChartConfig
                }
            },
            legend: {
                // enabled is set in getChartConfig
            },
            plotOptions: {
                series: {
                    marker: {
                        enabled: false // Disable markers by default for cleaner lines
                    },
                    dataLabels: {
                        enabled: false // Disable data labels by default
                    }
                },
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
            series: [
                {
                    // name is set in getChartConfig
                    // data is set in getChartConfig
                }
            ],
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
        };

        // Allow options to override default options
        // This is a shallow merge. For deep merging, a dedicated utility is recommended.
        const finalOptions = { ...defaultOptions };
        
        // Merge nested objects if they exist in this.options
        for (const key in this.options) {
            if (this.options.hasOwnProperty(key)) {
                if (typeof this.options[key] === 'object' && this.options[key] !== null && !Array.isArray(this.options[key])) {
                    // If it's an object, merge it with the default
                    finalOptions[key] = { ...defaultOptions[key], ...this.options[key] };
                } else {
                    // Otherwise, overwrite the default
                    finalOptions[key] = this.options[key];
                }
            }
        }

        return finalOptions;
    }

    /**
     * Map a generic chart type to a Highcharts chart type.
     * 
     * @param {string} type - The generic chart type (e.g., 'line', 'bar', 'area').
     * @returns {string} The corresponding Highcharts chart type.
     */
    getHighchartsChartType(type) {
        const map = {
            'line': 'line',
            'bar': 'column', // Highcharts uses 'column' for vertical bar charts
            'area': 'area',
            'column': 'column', // Allow direct specification
            'spline': 'spline'  // Highcharts specific type
        };
        
        return map[type] || 'line'; // Default to 'line'
    }

    /**
     * Update the chart with new data.
     * 
     * @param {Object|Array} newData - The new data to update the chart with.
     */
    updateData(newData) {
        if (!this.chartInstance) {
            console.warn('HighchartsHandler: No chart instance to update.');
            return;
        }

        const formattedData = this.formatData(newData);
        
        // Update the chart series data
        // Highcharts series.setData() method
        this.chartInstance.series[0].setData(formattedData);
    }

    /**
     * Destroy the Highcharts instance to free up resources.
     */
    destroy() {
        if (this.chartInstance) {
            this.chartInstance.destroy();
            this.chartInstance = null;
        }
    }
    
    /**
     * Export the chart.
     * 
     * @param {Object} options - Export options (e.g., { type: 'image/png', filename: 'chart' }).
     */
    exportChart(options = {}) {
        if (!this.chartInstance) {
            console.warn('HighchartsHandler: No chart instance to export.');
            return;
        }
        
        const defaultExportOptions = {
            type: 'image/png',
            filename: this.data.name || this.options.slug || 'chart'
        };
        
        const exportOptions = { ...defaultExportOptions, ...options };
        
        // Use Highcharts' built-in export functionality
        this.chartInstance.exportChart(exportOptions);
    }
}

// Expose the class to the global scope if needed by other scripts
// In a module system, you might use `export default HighchartsHandler;`
// window.HighchartsHandler = HighchartsHandler;
