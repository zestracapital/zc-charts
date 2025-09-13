/**
 * ZC Charts Loading Engine
 * Main controller for initializing and managing charts
 */

class ZCChartLoader {
    constructor() {
        this.charts = new Map();
        this.config = {
            defaultLibrary: 'chartjs',
            apiTimeout: 15000,
            retryAttempts: 2
        };
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    /**
     * Initialize the chart loader
     */
    init() {
        // Find all chart containers and initialize them
        const chartContainers = document.querySelectorAll('.zc-chart-container[data-config]');
        
        chartContainers.forEach(container => {
            try {
                const config = JSON.parse(container.dataset.config);
                this.loadChart(container, config);
            } catch (error) {
                console.error('Failed to parse chart configuration:', error);
                this.renderError(container, 'Invalid chart configuration');
            }
        });
    }
    
    /**
     * Load a chart with the given configuration
     */
    async loadChart(container, config) {
        const chartId = container.id || 'zc-chart-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        container.id = chartId;
        
        // Show loading state
        this.showLoading(container);
        
        try {
            // Validate configuration
            if (!config.slug) {
                throw new Error('Indicator slug is required');
            }
            
            // Set default values
            config.library = config.library || this.config.defaultLibrary;
            config.timeframe = config.timeframe || '1y';
            config.height = config.height || '400px';
            
            // Store chart instance
            this.charts.set(chartId, {
                container: container,
                config: config,
                status: 'loading'
            });
            
            // Fetch data
            const data = await this.fetchChartData(config);
            
            // Render chart
            await this.renderChart(container, data, config);
            
            // Update chart status
            this.charts.set(chartId, {
                container: container,
                config: config,
                status: 'loaded',
                data: data
            });
            
            // Hide loading
            this.hideLoading(container);
            
            // Show fallback notice if needed
            if (data.source === 'backup') {
                this.showFallbackNotice(container);
            }
            
        } catch (error) {
            console.error('Chart loading failed:', error);
            
            // Hide loading
            this.hideLoading(container);
            
            // Try fallback
            try {
                const fallbackData = await this.fetchFallbackData(config);
                await this.renderChart(container, fallbackData, config);
                
                // Show fallback notice
                this.showFallbackNotice(container);
                
                // Update chart status
                this.charts.set(chartId, {
                    container: container,
                    config: config,
                    status: 'loaded-fallback',
                    data: fallbackData
                });
            } catch (fallbackError) {
                console.error('Fallback also failed:', fallbackError);
                this.renderError(container, 'Live and backup data unavailable.');
                
                // Update chart status
                this.charts.set(chartId, {
                    container: container,
                    config: config,
                    status: 'error',
                    error: fallbackError.message
                });
            }
        }
    }
    
    /**
     * Fetch chart data from DMT plugin
     */
    async fetchChartData(config) {
        const apiKey = this.getApiKey();
        if (!apiKey) {
            throw new Error('API key not configured');
        }
        
        const url = `${zcChartsConfig.restUrl}zc-dmt/v1/data/${config.slug}?access_key=${apiKey}`;
        
        const response = await this.fetchWithRetry(url, {
            method: 'GET',
            timeout: this.config.apiTimeout
        }, this.config.retryAttempts);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        return {
            ...data,
            source: 'live'
        };
    }
    
    /**
     * Fetch fallback data from DMT plugin
     */
    async fetchFallbackData(config) {
        const apiKey = this.getApiKey();
        if (!apiKey) {
            throw new Error('API key not configured');
        }
        
        const url = `${zcChartsConfig.restUrl}zc-dmt/v1/backup/${config.slug}?access_key=${apiKey}`;
        
        const response = await this.fetchWithRetry(url, {
            method: 'GET',
            timeout: this.config.apiTimeout
        }, this.config.retryAttempts);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        return {
            ...data,
            source: 'backup'
        };
    }
    
    /**
     * Render chart with the appropriate library handler
     */
    async renderChart(container, data, config) {
        // Clear container
        container.innerHTML = '';
        
        // Get chart library handler
        let handler;
        if (config.library === 'highcharts') {
            handler = new ZCHighchartsHandler(container, data, config);
        } else {
            handler = new ZCChartJSHandler(container, data, config);
        }
        
        // Render the chart
        await handler.render();
        
        // Store handler reference
        const chartId = container.id;
        const chartInstance = this.charts.get(chartId);
        if (chartInstance) {
            chartInstance.handler = handler;
            this.charts.set(chartId, chartInstance);
        }
    }
    
    /**
     * Show loading state
     */
    showLoading(container) {
        container.innerHTML = `
            <div class="zc-chart-loading">
                <div class="zc-chart-loading-spinner"></div>
                <p>Loading chart data...</p>
            </div>
        `;
    }
    
    /**
     * Hide loading state
     */
    hideLoading(container) {
        const loadingElement = container.querySelector('.zc-chart-loading');
        if (loadingElement) {
            loadingElement.remove();
        }
    }
    
    /**
     * Show fallback notice
     */
    showFallbackNotice(container) {
        const notice = document.createElement('div');
        notice.className = 'zc-chart-notice';
        notice.textContent = 'Displaying cached data';
        container.parentNode.insertBefore(notice, container.nextSibling);
    }
    
    /**
     * Render error message
     */
    renderError(container, message) {
        container.innerHTML = `
            <div class="zc-chart-error">
                <div class="error-icon">⚠️</div>
                <div class="error-message">${message}</div>
                <div class="error-details">Please check your configuration and try again.</div>
            </div>
        `;
    }
    
    /**
     * Get API key from configuration
     */
    getApiKey() {
        // In a real implementation, this would come from WordPress settings
        return zcChartsConfig.apiKey || '';
    }
    
    /**
     * Fetch with retry logic
     */
    async fetchWithRetry(url, options, retries) {
        for (let i = 0; i <= retries; i++) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), options.timeout || 15000);
                
                const response = await fetch(url, {
                    ...options,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                return response;
            } catch (error) {
                if (i === retries) {
                    throw error;
                }
                
                // Wait before retry (exponential backoff)
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 1000));
            }
        }
    }
    
    /**
     * Update chart with new configuration
     */
    async updateChart(chartId, newConfig) {
        const chartInstance = this.charts.get(chartId);
        if (!chartInstance) {
            console.error('Chart not found:', chartId);
            return;
        }
        
        const { container, config } = chartInstance;
        const updatedConfig = { ...config, ...newConfig };
        
        // Reload chart with new configuration
        await this.loadChart(container, updatedConfig);
    }
    
    /**
     * Refresh chart data
     */
    async refreshChart(chartId) {
        const chartInstance = this.charts.get(chartId);
        if (!chartInstance) {
            console.error('Chart not found:', chartId);
            return;
        }
        
        const { container, config } = chartInstance;
        
        // Reload chart with same configuration
        await this.loadChart(container, config);
    }
    
    /**
     * Destroy chart
     */
    destroyChart(chartId) {
        const chartInstance = this.charts.get(chartId);
        if (!chartInstance) {
            return;
        }
        
        // Destroy chart if handler has destroy method
        if (chartInstance.handler && typeof chartInstance.handler.destroy === 'function') {
            chartInstance.handler.destroy();
        }
        
        // Remove from map
        this.charts.delete(chartId);
        
        // Clear container
        if (chartInstance.container) {
            chartInstance.container.innerHTML = '';
        }
    }
    
    /**
     * Get all chart instances
     */
    getAllCharts() {
        return Array.from(this.charts.entries()).map(([id, chart]) => ({
            id,
            ...chart
        }));
    }
    
    /**
     * Get chart by ID
     */
    getChart(chartId) {
        return this.charts.get(chartId);
    }
}

// Initialize chart loader when script is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.zcChartLoader = new ZCChartLoader();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ZCChartLoader;
}