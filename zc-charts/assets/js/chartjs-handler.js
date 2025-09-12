/**
 * ChartJSHandler
 * JavaScript class for rendering charts using Chart.js in the ZC Charts plugin.
 * This class handles data formatting and chart options specific to Chart.js.
 */

class ChartJSHandler {
    /**
     * Constructor for the ChartJSHandler.
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
     * Render the chart using Chart.js.
     */
    render() {
        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.error('ChartJSHandler: Chart.js library is not loaded.');
            // You might want to trigger a fallback or display an error message
            return;
        }

        // Destroy any existing chart instance to prevent memory leaks
        this.destroy();

        // Ensure the container is a canvas element
        let canvas;
        if (this.container.tagName.toLowerCase() === 'canvas') {
            canvas = this.container;
        } else {
            // Clear the container and create a canvas element
            this.container.innerHTML = '';
            canvas = document.createElement('canvas');
            this.container.appendChild(canvas);
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            console.error('ChartJSHandler: Unable to get 2D context from canvas.');
            return;
        }

        // Get chart configuration
        const config = this.getChartConfig();

        // Create the Chart.js instance
        this.chartInstance = new Chart(ctx, config);
    }

    /**
     * Get the complete Chart.js configuration object.
     * 
     * @returns {Object} The Chart.js configuration.
     */
    getChartConfig() {
        const type = this.options.type || 'line';
        const formattedData = this.formatData();
        const chartOptions = this.getOptions();

        return {
            type: type,
            data: formattedData,
            options: chartOptions
        };
    }

    /**
     * Format the raw data into a Chart.js compatible format.
     * 
     * @returns {Object} The formatted data object for Chart.js.
     */
    formatData() {
        // The PHP Charts class should have already processed the data into a Chart.js compatible format
        // But we'll provide a basic conversion as a fallback
        
        // If data is already in Chart.js format (has labels and datasets), use it directly
        if (this.data && this.data.labels && this.data.datasets) {
            return this.data;
        }

        // Otherwise, perform a basic conversion
        // Assumes this.data.data is an array of {date, value} objects
        const labels = [];
        const values = [];

        if (this.data && this.data.data && Array.isArray(this.data.data)) {
            this.data.data.forEach(point => {
                if (point.date !== undefined && point.value !== undefined) {
                    // Chart.js can work with date strings directly on the x-axis with proper configuration
                    labels.push(point.date); 
                    values.push(parseFloat(point.value));
                }
            });
        }

        // Create a default dataset configuration
        const defaultDataset = {
            label: this.data.name || this.options.slug || 'Dataset',
            data: values,
            borderColor: this.options.borderColor || 'rgb(54, 162, 235)', // Blue
            backgroundColor: this.options.backgroundColor || 'rgba(54, 162, 235, 0.1)',
            borderWidth: this.options.borderWidth || 2,
            fill: this.options.type === 'area' || false, // Fill for area charts
            tension: this.options.type === 'line' ? (this.options.tension || 0.1) : 0 // Curve tension for lines
        };

        // Allow options to override dataset properties
        if (this.options.dataset) {
            Object.assign(defaultDataset, this.options.dataset);
        }

        return {
            labels: labels,
            datasets: [defaultDataset]
        };
    }

    /**
     * Get Chart.js options based on the configuration.
     * 
     * @returns {Object} The Chart.js options object.
     */
    getOptions() {
        // Start with default options
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false, // Important for container-based sizing
            plugins: {
                legend: {
                    display: this.options.showLegend !== false, // Default to true
                    position: this.options.legendPosition || 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        // You can customize tooltip labels here if needed
                        // label: function(context) {
                        //     let label = context.dataset.label || '';
                        //     if (label) {
                        //         label += ': ';
                        //     }
                        //     if (context.parsed.y !== null) {
                        //         label += new Intl.NumberFormat('en-US').format(context.parsed.y);
                        //     }
                        //     return label;
                        // }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time', // Use time scale for date handling
                    time: {
                        // Adjust unit based on data frequency or options
                        unit: this.options.timeUnit || 'month',
                        tooltipFormat: 'MMM d, yyyy', // Format for tooltips
                        displayFormats: {
                            'day': 'MMM d',
                            'week': 'MMM d',
                            'month': 'MMM yyyy',
                            'quarter': 'MMM yyyy',
                            'year': 'yyyy'
                        }
                    },
                    title: {
                        display: this.options.showXAxisTitle !== false, // Default to true
                        text: this.options.xAxisTitle || 'Date'
                    },
                    // grid: {
                    //     display: this.options.showXGrid !== false
                    // }
                },
                y: {
                    title: {
                        display: this.options.showYAxisTitle !== false, // Default to true
                        text: this.options.yAxisTitle || 'Value'
                    },
                    // grid: {
                    //     display: this.options.showYGrid !== false
                    // }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            animation: {
                duration: this.options.animationDuration !== undefined ? this.options.animationDuration : 1000 // ms
            }
        };

        // Allow options to override default options
        // A simple merge might not be sufficient for nested objects,
        // but for basic customization, it works.
        // For a deep merge, a dedicated function would be better.
        const finalOptions = { ...defaultOptions };
        
        // Merge plugins
        if (this.options.plugins) {
            finalOptions.plugins = { ...defaultOptions.plugins, ...this.options.plugins };
        }
        
        // Merge scales
        if (this.options.scales) {
            finalOptions.scales = { ...defaultOptions.scales, ...this.options.scales };
        }
        
        // Merge other top-level options
        for (const key in this.options) {
            if (!(key in ['type', 'dataset', 'plugins', 'scales', 'data'])) { // Exclude certain keys
                finalOptions[key] = this.options[key];
            }
        }

        return finalOptions;
    }

    /**
     * Update the chart with new data.
     * 
     * @param {Object} newData - The new data to update the chart with.
     */
    updateData(newData) {
        if (!this.chartInstance) {
            console.warn('ChartJSHandler: No chart instance to update.');
            return;
        }

        const formattedData = this.formatData(newData);
        
        // Update the chart data
        this.chartInstance.data.labels = formattedData.labels;
        this.chartInstance.data.datasets[0].data = formattedData.datasets[0].data;
        
        // Update chart
        this.chartInstance.update();
    }

    /**
     * Destroy the Chart.js instance to free up resources.
     */
    destroy() {
        if (this.chartInstance) {
            this.chartInstance.destroy();
            this.chartInstance = null;
        }
    }
}

// Expose the class to the global scope if needed by other scripts
// In a module system, you might use `export default ChartJSHandler;`
// window.ChartJSHandler = ChartJSHandler;
