/**
 * ZCChartLoader
 * Main JavaScript class for loading and rendering charts in the ZC Charts plugin.
 * Handles data fetching, library loading, rendering, and fallback mechanisms.
 */

class ZCChartLoader {
    /**
     * Constructor for the ZCChartLoader.
     * 
     * @param {string} containerId - The ID of the HTML element where the chart will be rendered.
     * @param {Object} config - Configuration object passed from the PHP backend.
     */
    constructor(containerId, config) {
        // Store references to the container element
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`ZCChartLoader: Container with ID '${containerId}' not found.`);
            return;
        }

        // Store configuration
        this.config = config || {};
        this.slug = this.config.slug || '';
        this.library = this.config.library || 'chartjs';
        this.type = this.config.type || 'line';
        this.controls = this.config.controls || false;
        this.timeframe = this.config.timeframe || '1y';
        this.isFallback = this.config.isFallback || false;
        this.isStatic = this.config.isStatic || false;
        this.data = this.config.data || null; // Pre-fetched data for static charts or initial load

        // Get API configuration from localized script (passed from PHP)
        this.apiKey = typeof zcChartsConfig !== 'undefined' ? zcChartsConfig.apiKey : '';
        this.dmtApiUrl = typeof zcChartsConfig !== 'undefined' ? zcChartsConfig.dmtApiUrl : '';

        // State variables
        this.chartInstance = null; // Will hold the Chart.js or Highcharts instance
        this.isLoading = false;
    }

    /**
     * Main method to load and render the chart.
     * Orchestrates the entire process: library loading, data fetching, rendering.
     */
    async loadChart() {
        if (!this.container) return;

        // Show loading indicator
        this.showLoadingIndicator();

        try {
            // 1. Load the required chart library if not already loaded
            await this.loadChartLibrary(this.library);

            let chartData;

            // 2. Use pre-fetched data if available (for static charts or initial load with data)
            if (this.data) {
                chartData = this.data;
                console.log(`ZCChartLoader: Using pre-fetched data for chart '${this.slug}'.`);
            } else {
                // 3. Otherwise, fetch live data
                chartData = await this.fetchLiveData();
                console.log(`ZCChartLoader: Fetched live data for chart '${this.slug}'.`);
            }

            // 4. Render the chart with the fetched data
            this.renderChart(chartData, 'live');

            // 5. If this is a dynamic chart, set up event listeners for controls
            if (this.controls && !this.isStatic) {
                this.setupEventListeners();
            }

        } catch (error) {
            console.warn(`ZCChartLoader: Live data fetch failed for chart '${this.slug}'. Attempting fallback. Error:`, error);

            // 6. If live data fails, attempt to fetch backup data (if enabled)
            const enableFallback = typeof zcChartsConfig !== 'undefined' ? 
                (zcChartsConfig.enableFallback !== undefined ? zcChartsConfig.enableFallback : true) : true;

            if (enableFallback) {
                try {
                    const backupData = await this.fetchBackupData();
                    console.log(`ZCChartLoader: Fetched backup data for chart '${this.slug}'.`);
                    this.renderChart(backupData, 'backup');
                    
                    // Setup controls for backup data if it's a dynamic chart
                    if (this.controls && !this.isStatic) {
                        this.setupEventListeners();
                    }
                    
                } catch (backupError) {
                    console.error(`ZCChartLoader: Backup data also failed for chart '${this.slug}'. Error:`, backupError);
                    this.renderError('Unable to load data. Both live and backup data sources are unavailable.');
                }
            } else {
                // Fallback is disabled, show the error
                this.renderError('Unable to load data.');
            }
        } finally {
            // Hide loading indicator
            this.hideLoadingIndicator();
        }
    }

    /**
     * Load the specified chart library dynamically if it's not already loaded.
     * 
     * @param {string} library - The chart library to load ('chartjs' or 'highcharts').
     * @returns {Promise} A promise that resolves when the library is loaded.
     */
    loadChartLibrary(library) {
        return new Promise((resolve, reject) => {
            if (library === 'highcharts') {
                // Check if Highcharts is already loaded
                if (typeof Highcharts !== 'undefined') {
                    resolve();
                    return;
                }

                // Load Highcharts from CDN
                const script = document.createElement('script');
                script.src = 'https://code.highcharts.com/highcharts.js';
                script.onload = () => {
                    console.log('ZCChartLoader: Highcharts library loaded.');
                    resolve();
                };
                script.onerror = () => {
                    console.error('ZCChartLoader: Failed to load Highcharts library.');
                    reject(new Error('Failed to load Highcharts library.'));
                };
                document.head.appendChild(script);

            } else {
                // Default to Chart.js
                // Check if Chart.js is already loaded
                if (typeof Chart !== 'undefined') {
                    resolve();
                    return;
                }

                // Load Chart.js from CDN
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.onload = () => {
                    console.log('ZCChartLoader: Chart.js library loaded.');
                    resolve();
                };
                script.onerror = () => {
                    console.error('ZCChartLoader: Failed to load Chart.js library.');
                    reject(new Error('Failed to load Chart.js library.'));
                };
                document.head.appendChild(script);
            }
        });
    }

    /**
     * Fetch live data for the indicator from the DMT plugin's REST API.
     * 
     * @returns {Promise<Object>} A promise that resolves with the fetched data.
     */
    async fetchLiveData() {
        if (!this.dmtApiUrl || !this.slug || !this.apiKey) {
            throw new Error('Missing required parameters for live data fetch.');
        }

        // Prepare query parameters
        const params = new URLSearchParams({
            access_key: this.apiKey
        });

        // Add timeframe filter for dynamic charts
        if (!this.isStatic && this.timeframe && this.timeframe !== 'all') {
            const startDate = this.calculateStartDate(this.timeframe);
            if (startDate) {
                params.append('start_date', startDate);
            }
        }

        const url = `${this.dmtApiUrl}data/${this.slug}?${params.toString()}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                // 'X-WP-Nonce': zcChartsConfig.nonce // If nonce-based authentication is needed
            },
            // credentials: 'same-origin' // If cookies/credentials are needed
        });

        if (!response.ok) {
            // Try to get error message from response body
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                // If parsing JSON fails, use the generic message
            }
            throw new Error(errorMessage);
        }

        const data = await response.json();
        return data;
    }

    /**
     * Fetch backup data for the indicator from the DMT plugin's REST API.
     * 
     * @returns {Promise<Object>} A promise that resolves with the fetched backup data.
     */
    async fetchBackupData() {
        if (!this.dmtApiUrl || !this.slug || !this.apiKey) {
            throw new Error('Missing required parameters for backup data fetch.');
        }

        const url = `${this.dmtApiUrl}backup/${this.slug}?access_key=${encodeURIComponent(this.apiKey)}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                // 'X-WP-Nonce': zcChartsConfig.nonce
            },
            // credentials: 'same-origin'
        });

        if (!response.ok) {
            let errorMessage = `Backup HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                // If parsing JSON fails, use the generic message
            }
            throw new Error(errorMessage);
        }

        const data = await response.json();
        return data;
    }

    /**
     * Render the chart using the appropriate library.
     * 
     * @param {Object} data - The data to render in the chart.
     * @param {string} source - The source of the data ('live' or 'backup').
     */
    renderChart(data, source) {
        if (!this.container) return;

        // Clear any previous chart instance
        this.destroyChart();

        // Clear container content
        this.container.innerHTML = '';

        // If it's backup data, show a notice
        if (source === 'backup') {
            const notice = document.createElement('div');
            notice.className = 'zc-chart-notice';
            notice.textContent = 'Displaying cached data';
            this.container.parentNode.insertBefore(notice, this.container);
        }

        // Format data for the specific library
        let formattedData;
        if (this.library === 'highcharts') {
            formattedData = this.formatDataForHighcharts(data);
            this.renderWithHighcharts(formattedData);
        } else {
            // Default to Chart.js
            formattedData = this.formatDataForChartJS(data);
            this.renderWithChartJS(formattedData);
        }
    }

    /**
     * Format data for Chart.js.
     * 
     * @param {Object} rawData - The raw data from the API.
     * @returns {Object} The data formatted for Chart.js.
     */
    formatDataForChartJS(rawData) {
        // The PHP Charts class should have already processed the data into a Chart.js compatible format
        // But we'll do a basic check here
        if (rawData && rawData.labels && rawData.datasets) {
            return rawData;
        }

        // If not already formatted, do a basic conversion
        // This assumes rawData.data is an array of {date, value} objects
        const labels = [];
        const values = [];

        if (rawData && rawData.data && Array.isArray(rawData.data)) {
            rawData.data.forEach(point => {
                if (point.date !== undefined && point.value !== undefined) {
                    labels.push(point.date);
                    values.push(parseFloat(point.value));
                }
            });
        }

        return {
            labels: labels,
            datasets: [{
                label: rawData.name || this.slug,
                data: values,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1 // For line charts
            }]
        };
    }

    /**
     * Format data for Highcharts.
     * 
     * @param {Object} rawData - The raw data from the API.
     * @returns {Array} The data formatted for Highcharts (array of [timestamp, value] pairs).
     */
    formatDataForHighcharts(rawData) {
        // The PHP Charts class should have already processed the data
        // But we'll do a basic conversion if needed
        // Highcharts typically expects [[timestamp, value], ...] for date-based series
        const seriesData = [];

        if (rawData && rawData.data && Array.isArray(rawData.data)) {
            rawData.data.forEach(point => {
                if (point.date !== undefined && point.value !== undefined) {
                    // Convert date string to timestamp (in milliseconds for Highcharts)
                    const timestamp = new Date(point.date).getTime();
                    const value = parseFloat(point.value);
                    seriesData.push([timestamp, value]);
                }
            });
        }

        return seriesData;
    }

    /**
     * Render the chart using Chart.js.
     * 
     * @param {Object} formattedData - The data formatted for Chart.js.
     */
    renderWithChartJS(formattedData) {
        if (typeof Chart === 'undefined') {
            console.error('ZCChartLoader: Chart.js library is not loaded.');
            this.renderError('Chart library failed to load.');
            return;
        }

        const ctx = this.container.getContext('2d');
        if (!ctx) {
            // If the container is not a canvas, create one
            this.container.innerHTML = '<canvas></canvas>';
            const canvas = this.container.querySelector('canvas');
            const newCtx = canvas.getContext('2d');
            
            this.chartInstance = new Chart(newCtx, {
                type: this.type, // 'line', 'bar', etc.
                data: formattedData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            type: 'time', // If using date labels
                            time: {
                                unit: 'month' // Adjust based on data frequency
                            },
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Value'
                            }
                        }
                    }
                }
            });
        } else {
            // If ctx exists, it means a canvas was already provided
            this.chartInstance = new Chart(ctx, {
                type: this.type,
                data: formattedData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'month'
                            },
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Value'
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Render the chart using Highcharts.
     * 
     * @param {Array} formattedData - The data formatted for Highcharts.
     */
    renderWithHighcharts(formattedData) {
        if (typeof Highcharts === 'undefined') {
            console.error('ZCChartLoader: Highcharts library is not loaded.');
            this.renderError('Chart library failed to load.');
            return;
        }

        // Ensure the container is a div for Highcharts
        if (this.container.tagName.toLowerCase() !== 'div') {
            console.warn('ZCChartLoader: Highcharts requires a div container. Replacing canvas with div.');
            const newDiv = document.createElement('div');
            newDiv.id = this.container.id;
            newDiv.className = this.container.className;
            newDiv.style.cssText = this.container.style.cssText;
            this.container.parentNode.replaceChild(newDiv, this.container);
            this.container = newDiv;
        }

        this.chartInstance = Highcharts.chart(this.container, {
            chart: {
                type: this.type === 'area' ? 'area' : (this.type === 'bar' ? 'column' : 'line'),
                zoomType: 'x' // Enable zooming
            },
            title: {
                text: null // We'll handle titles via data or CSS
            },
            subtitle: {
                text: null
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
                name: this.config.data && this.config.data.name ? this.config.data.name : this.slug,
                data: formattedData
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

    /**
     * Render an error message in the chart container.
     * 
     * @param {string} message - The error message to display.
     */
    renderError(message) {
        if (!this.container) return;

        // Destroy any existing chart
        this.destroyChart();

        // Create error HTML
        this.container.innerHTML = `
            <div class="zc-chart-error-content">
                <div class="error-icon">⚠️</div>
                <div class="error-message">${message}</div>
                <div class="error-details">Please try again later or contact the site administrator.</div>
            </div>
        `;
        // Add a class to the container for styling
        this.container.className = 'zc-chart-container zc-chart-error';
    }

    /**
     * Show a loading indicator in the chart container.
     */
    showLoadingIndicator() {
        if (!this.container) return;
        this.container.innerHTML = '<div class="zc-chart-loading">Loading chart...</div>';
    }

    /**
     * Hide the loading indicator.
     */
    hideLoadingIndicator() {
        // The loading indicator will be replaced when the chart is rendered or an error is shown
        // So no specific action is needed here unless we want to add a delay or animation
    }

    /**
     * Destroy the current chart instance to free up resources.
     */
    destroyChart() {
        if (this.chartInstance) {
            if (this.library === 'highcharts' && this.chartInstance.destroy) {
                this.chartInstance.destroy();
            } else if (this.chartInstance.destroy) {
                // Chart.js destroy
                this.chartInstance.destroy();
            }
            this.chartInstance = null;
        }
    }

    /**
     * Calculate the start date based on a timeframe string.
     * 
     * @param {string} timeframe - The timeframe (e.g., '1y', '6m').
     * @returns {string|null} The start date in 'YYYY-MM-DD' format, or null on failure.
     */
    calculateStartDate(timeframe) {
        const map = {
            '1d': 1,
            '3d': 3,
            '1w': 7,
            '2w': 14,
            '1m': 30,
            '3m': 90,
            '6m': 180,
            '1y': 365,
            '2y': 2 * 365,
            '3y': 3 * 365,
            '5y': 5 * 365,
            '10y': 10 * 365
            // 'all' is not handled here as it means no start date filter
        };

        if (map[timeframe]) {
            const daysAgo = map[timeframe];
            const date = new Date();
            date.setDate(date.getDate() - daysAgo);
            // Format as YYYY-MM-DD
            return date.toISOString().split('T')[0];
        }

        // Handle 'all' or unknown timeframes
        return null;
    }

    /**
     * Set up event listeners for chart controls (for dynamic charts).
     */
    setupEventListeners() {
        // Find the controls container associated with this chart
        // It should be a sibling element with class 'zc-chart-controls'
        const controlsContainer = this.container.parentNode.querySelector('.zc-chart-controls');
        
        if (!controlsContainer) {
            console.warn('ZCChartLoader: Controls container not found for chart.');
            return;
        }

        // Timeframe buttons
        const timeframeButtons = controlsContainer.querySelectorAll('.timeframe-controls button');
        timeframeButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const range = e.target.getAttribute('data-range');
                if (range) {
                    this.updateTimeframe(range);
                }
            });
        });

        // Chart type selector
        const typeSelector = controlsContainer.querySelector('.chart-type-selector');
        if (typeSelector) {
            typeSelector.addEventListener('change', (e) => {
                const newType = e.target.value;
                this.updateChartType(newType);
            });
        }

        // Export button
        const exportBtn = controlsContainer.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportChart();
            });
        }
    }

    /**
     * Update the chart based on a new timeframe.
     * 
     * @param {string} newTimeframe - The new timeframe (e.g., '1y', '6m').
     */
    async updateTimeframe(newTimeframe) {
        if (this.isLoading) return; // Prevent multiple simultaneous updates

        this.isLoading = true;
        this.showLoadingIndicator();

        try {
            // Update the timeframe in config
            this.timeframe = newTimeframe;

            // Fetch new data with the updated timeframe
            const newData = await this.fetchLiveData();

            // Re-render the chart
            this.renderChart(newData, 'live');

            // Update active button state
            const controlsContainer = this.container.parentNode.querySelector('.zc-chart-controls');
            if (controlsContainer) {
                const buttons = controlsContainer.querySelectorAll('.timeframe-controls button');
                buttons.forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.getAttribute('data-range') === newTimeframe) {
                        btn.classList.add('active');
                    }
                });
            }

        } catch (error) {
            console.error('ZCChartLoader: Failed to update timeframe.', error);
            this.renderError('Failed to update chart data.');
        } finally {
            this.isLoading = false;
            this.hideLoadingIndicator();
        }
    }

    /**
     * Update the chart type.
     * 
     * @param {string} newType - The new chart type (e.g., 'line', 'bar').
     */
    updateChartType(newType) {
        this.type = newType;
        // Re-render the chart with the new type
        // We need to re-fetch or re-process the data for the new type
        // For simplicity, we'll just destroy and re-render
        // In a more complex implementation, you might update the chart instance directly
        if (this.config.data) {
            this.renderChart(this.config.data, this.isFallback ? 'backup' : 'live');
        }
    }

    /**
     * Export the chart (placeholder implementation).
     */
    exportChart() {
        // This is a placeholder. Actual export implementation would depend on the library
        // and desired export formats (PNG, SVG, CSV, etc.)
        alert('Export functionality would be implemented here.\n\n' +
              'For Chart.js: Use chart.toBase64Image()\n' +
              'For Highcharts: Use chart.exportChart()');
        
        /*
        // Example for Chart.js PNG export:
        if (this.chartInstance && this.library === 'chartjs') {
            const url = this.chartInstance.toBase64Image();
            const a = document.createElement('a');
            a.href = url;
            a.download = `chart-${this.slug}.png`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        // Example for Highcharts export:
        if (this.chartInstance && this.library === 'highcharts') {
            this.chartInstance.exportChart({
                type: 'image/png',
                filename: `chart-${this.slug}`
            });
        }
        */
    }
}

// Expose the class to the global scope if needed by other scripts
// In a module system, you might use `export default ZCChartLoader;`
window.ZCChartLoader = ZCChartLoader;

// Auto-initialize charts when the DOM is loaded
// This part is handled by the PHP render_chart method which creates a new instance
// and calls loadChart() on DOMContentLoaded.
// If you wanted a more automatic approach, you could query for specific elements
// and initialize them here, but the current approach is more explicit and controlled.
