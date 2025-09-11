/**
 * ZC Charts - Main Chart Loader
 * Handles chart initialization, data fetching, and error handling
 */

class ZCChartLoader {
    constructor(containerId, config) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.config = config;
        
        // Validate configuration
        if (!this.container) {
            console.error('ZC Charts: Container not found -', containerId);
            return;
        }
        
        if (!this.config.slug) {
            console.error('ZC Charts: Indicator slug is required');
            this.renderError('Indicator slug is required');
            return;
        }
        
        if (!this.config.api_key) {
            console.error('ZC Charts: API key is required');
            this.renderError('API key not configured');
            return;
        }
        
        // Initialize properties
        this.chart = null;
        this.data = null;
        this.isLoading = false;
        this.retryCount = 0;
        this.maxRetries = 2;
        
        // Bind methods
        this.loadChart = this.loadChart.bind(this);
        this.handleTimeframeChange = this.handleTimeframeChange.bind(this);
        this.handleChartTypeChange = this.handleChartTypeChange.bind(this);
        this.handleExport = this.handleExport.bind(this);
        
        // Setup event listeners if controls are enabled
        if (this.config.controls) {
            this.setupControls();
        }
    }
    
    /**
     * Main method to load and render chart
     */
    async loadChart() {
        if (this.isLoading) {
            return;
        }
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            // Attempt to load live data
            const result = await this.fetchLiveData();
            
            if (result.success) {
                this.data = result.data;
                await this.renderChart('live');
                this.updateMetadata(result.data.meta || {});
            } else {
                // Try fallback if enabled
                if (zcChartsConfig.fallback_enabled) {
                    console.warn('Live data failed, trying fallback:', result.message);
                    await this.tryFallback();
                } else {
                    throw new Error(result.message || 'Failed to load data');
                }
            }
        } catch (error) {
            console.error('Chart loading failed:', error);
            this.renderError(error.message || 'Failed to load chart data');
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }
    
    /**
     * Fetch live data from DMT API
     */
    async fetchLiveData() {
        const url = zcChartsConfig.dmt_api_url + '/data/' + encodeURIComponent(this.config.slug);
        const params = new URLSearchParams({
            access_key: this.config.api_key
        });
        
        // Add timeframe parameter for dynamic charts
        if (this.config.type === 'dynamic' && this.config.timeframe) {
            params.set('timeframe', this.config.timeframe);
        }
        
        try {
            const response = await fetch(url + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': this.config.api_key
                }
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                let errorMessage;
                
                switch (response.status) {
                    case 401:
                        errorMessage = zcChartsConfig.strings.unauthorized;
                        break;
                    case 404:
                        errorMessage = zcChartsConfig.strings.data_not_found;
                        break;
                    default:
                        errorMessage = `HTTP ${response.status}: ${errorText}`;
                }
                
                return {
                    success: false,
                    message: errorMessage,
                    status: response.status
                };
            }
            
            const data = await response.json();
            
            return {
                success: true,
                data: data
            };
            
        } catch (error) {
            return {
                success: false,
                message: error.message || zcChartsConfig.strings.network_error,
                error: error
            };
        }
    }
    
    /**
     * Try fallback data
     */
    async tryFallback() {
        console.log('Attempting to load fallback data...');
        
        const url = zcChartsConfig.dmt_api_url + '/backup/' + encodeURIComponent(this.config.slug);
        const params = new URLSearchParams({
            access_key: this.config.api_key
        });
        
        try {
            const response = await fetch(url + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': this.config.api_key
                }
            });
            
            if (!response.ok) {
                throw new Error(`Backup data not available (HTTP ${response.status})`);
            }
            
            const data = await response.json();
            
            this.data = data;
            await this.renderChart('fallback');
            this.updateMetadata(data.meta || {});
            this.showFallbackNotice();
            
        } catch (error) {
            console.error('Fallback data failed:', error);
            throw new Error('Both live and backup data are unavailable');
        }
    }
    
    /**
     * Render chart using appropriate library
     */
    async renderChart(dataSource = 'live') {
        if (!this.data || !this.data.data || !this.data.labels) {
            throw new Error('Invalid chart data format');
        }
        
        // Clear existing chart
        if (this.chart) {
            this.destroyChart();
        }
        
        // Load chart library if not already loaded
        await this.loadChartLibrary();
        
        // Create chart based on library
        try {
            if (this.config.library === 'highcharts') {
                this.chart = this.createHighchartsChart();
            } else {
                this.chart = this.createChartJSChart();
            }
            
            // Mark container as loaded
            this.container.classList.add('zc-chart-loaded');
            this.container.classList.toggle('zc-chart-fallback', dataSource === 'fallback');
            
        } catch (error) {
            console.error('Chart rendering failed:', error);
            throw new Error('Failed to render chart: ' + error.message);
        }
    }
    
    /**
     * Create Chart.js chart
     */
    createChartJSChart() {
        if (typeof Chart === 'undefined') {
            throw new Error('Chart.js library not loaded');
        }
        
        const canvas = document.createElement('canvas');
        canvas.style.maxHeight = this.config.height || '400px';
        
        // Clear container and add canvas
        this.container.innerHTML = '';
        this.container.appendChild(canvas);
        
        const ctx = canvas.getContext('2d');
        
        const chartConfig = {
            type: this.getChartType(),
            data: {
                labels: this.data.labels,
                datasets: [{
                    label: this.config.title || this.config.slug,
                    data: this.data.data,
                    borderColor: '#2271b1',
                    backgroundColor: this.getChartType() === 'line' 
                        ? 'rgba(34, 113, 177, 0.1)' 
                        : 'rgba(34, 113, 177, 0.8)',
                    borderWidth: 2,
                    fill: this.getChartType() === 'area',
                    tension: 0.1
                }]
            },
            options: {
                responsive: this.config.responsive !== false,
                maintainAspectRatio: false,
                animation: {
                    duration: this.config.animation !== false ? 750 : 0
                },
                plugins: {
                    legend: {
                        display: !!this.config.title
                    },
                    title: {
                        display: !!this.config.title,
                        text: this.config.title
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        };
        
        return new Chart(ctx, chartConfig);
    }
    
    /**
     * Create Highcharts chart
     */
    createHighchartsChart() {
        if (typeof Highcharts === 'undefined') {
            throw new Error('Highcharts library not loaded');
        }
        
        // Clear container
        this.container.innerHTML = '';
        
        const chartConfig = {
            chart: {
                type: this.getHighchartsType(),
                height: parseInt(this.config.height) || 400,
                animation: this.config.animation !== false
            },
            title: {
                text: this.config.title || null
            },
            subtitle: {
                text: this.config.subtitle || null
            },
            xAxis: {
                categories: this.data.labels,
                crosshair: true
            },
            yAxis: {
                title: {
                    text: this.data.meta?.units || 'Value'
                }
            },
            series: [{
                name: this.config.title || this.config.slug,
                data: this.data.data,
                color: '#2271b1'
            }],
            legend: {
                enabled: !!this.config.title
            },
            credits: {
                enabled: false
            },
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
        
        return Highcharts.chart(this.container, chartConfig);
    }
    
    /**
     * Get chart type for Chart.js
     */
    getChartType() {
        const typeMap = {
            'bar': 'bar',
            'area': 'line',
            'line': 'line'
        };
        
        return typeMap[this.config.chart_type || 'line'] || 'line';
    }
    
    /**
     * Get chart type for Highcharts
     */
    getHighchartsType() {
        const typeMap = {
            'bar': 'column',
            'area': 'area',
            'line': 'line'
        };
        
        return typeMap[this.config.chart_type || 'line'] || 'line';
    }
    
    /**
     * Load chart library dynamically
     */
    async loadChartLibrary() {
        if (this.config.library === 'highcharts') {
            if (typeof Highcharts === 'undefined') {
                await this.loadScript('https://code.highcharts.com/highcharts.js');
            }
        } else {
            if (typeof Chart === 'undefined') {
                await this.loadScript('https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js');
            }
        }
    }
    
    /**
     * Dynamically load external script
     */
    loadScript(src) {
        return new Promise((resolve, reject) => {
            // Check if already loaded
            if (document.querySelector(`script[src="${src}"]`)) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    /**
     * Setup interactive controls
     */
    setupControls() {
        const wrapper = this.container.closest('.zc-chart-wrapper');
        if (!wrapper) return;
        
        // Timeframe controls
        const timeframeButtons = wrapper.querySelectorAll('.zc-timeframe-btn');
        timeframeButtons.forEach(btn => {
            btn.addEventListener('click', this.handleTimeframeChange);
        });
        
        // Chart type selector
        const typeSelector = wrapper.querySelector('.zc-chart-type-selector');
        if (typeSelector) {
            typeSelector.addEventListener('change', this.handleChartTypeChange);
        }
        
        // Export button
        const exportBtn = wrapper.querySelector('.zc-export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', this.handleExport);
        }
        
        // Fullscreen button
        const fullscreenBtn = wrapper.querySelector('.zc-fullscreen-btn');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', this.toggleFullscreen.bind(this));
        }
    }
    
    /**
     * Handle timeframe change
     */
    async handleTimeframeChange(event) {
        const button = event.target;
        const timeframe = button.dataset.range;
        
        if (timeframe === this.config.timeframe) {
            return; // No change
        }
        
        // Update active button
        const wrapper = this.container.closest('.zc-chart-wrapper');
        wrapper.querySelectorAll('.zc-timeframe-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');
        
        // Update config and reload chart
        this.config.timeframe = timeframe;
        await this.loadChart();
    }
    
    /**
     * Handle chart type change
     */
    async handleChartTypeChange(event) {
        const chartType = event.target.value;
        
        if (chartType === this.config.chart_type) {
            return; // No change
        }
        
        this.config.chart_type = chartType;
        
        // Re-render chart with new type
        if (this.data) {
            await this.renderChart();
        }
    }
    
    /**
     * Handle chart export
     */
    handleExport(event) {
        event.preventDefault();
        
        if (!this.chart) {
            alert('No chart to export');
            return;
        }
        
        try {
            if (this.config.library === 'highcharts' && this.chart.exportChart) {
                this.chart.exportChart({
                    type: 'image/png',
                    filename: `chart-${this.config.slug}-${Date.now()}`
                });
            } else if (typeof Chart !== 'undefined' && this.chart.toBase64Image) {
                const link = document.createElement('a');
                link.download = `chart-${this.config.slug}-${Date.now()}.png`;
                link.href = this.chart.toBase64Image();
                link.click();
            } else {
                alert('Export not supported for this chart type');
            }
        } catch (error) {
            console.error('Export failed:', error);
            alert('Export failed: ' + error.message);
        }
    }
    
    /**
     * Toggle fullscreen mode
     */
    toggleFullscreen() {
        const wrapper = this.container.closest('.zc-chart-wrapper');
        
        if (!wrapper.classList.contains('zc-fullscreen')) {
            wrapper.classList.add('zc-fullscreen');
            
            if (wrapper.requestFullscreen) {
                wrapper.requestFullscreen();
            }
        } else {
            wrapper.classList.remove('zc-fullscreen');
            
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }
    
    /**
     * Show loading indicator
     */
    showLoading() {
        const loading = this.container.querySelector('.zc-chart-loading');
        if (loading) {
            loading.style.display = 'flex';
        }
    }
    
    /**
     * Hide loading indicator
     */
    hideLoading() {
        const loading = this.container.querySelector('.zc-chart-loading');
        if (loading) {
            loading.style.display = 'none';
        }
    }
    
    /**
     * Show fallback data notice
     */
    showFallbackNotice() {
        const wrapper = this.container.closest('.zc-chart-wrapper');
        
        let notice = wrapper.querySelector('.zc-fallback-notice');
        if (!notice) {
            notice = document.createElement('div');
            notice.className = 'zc-fallback-notice';
            notice.innerHTML = `
                <span class="dashicons dashicons-backup"></span>
                ${zcChartsConfig.strings.fallback_data}
            `;
            wrapper.insertBefore(notice, this.container);
        }
        
        notice.style.display = 'flex';
    }
    
    /**
     * Update metadata display
     */
    updateMetadata(meta) {
        const wrapper = this.container.closest('.zc-chart-wrapper');
        if (!wrapper) return;
        
        const sourceElement = wrapper.querySelector('.zc-source-name');
        if (sourceElement && meta.source) {
            sourceElement.textContent = meta.source;
        }
        
        const updateElement = wrapper.querySelector('.zc-update-time');
        if (updateElement && meta.last_updated) {
            updateElement.textContent = this.formatDate(meta.last_updated);
        }
    }
    
    /**
     * Format date for display
     */
    formatDate(dateString) {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        } catch (error) {
            return dateString;
        }
    }
    
    /**
     * Render error message
     */
    renderError(message) {
        this.container.innerHTML = `
            <div class="zc-chart-error">
                <div class="zc-error-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="zc-error-content">
                    <div class="zc-error-message">${message}</div>
                </div>
            </div>
        `;
        
        this.container.classList.add('zc-chart-error-state');
    }
    
    /**
     * Destroy existing chart
     */
    destroyChart() {
        if (this.chart) {
            try {
                if (typeof this.chart.destroy === 'function') {
                    this.chart.destroy();
                } else if (typeof this.chart.remove === 'function') {
                    this.chart.remove();
                }
            } catch (error) {
                console.warn('Error destroying chart:', error);
            }
            this.chart = null;
        }
    }
    
    /**
     * Cleanup method
     */
    destroy() {
        this.destroyChart();
        
        // Remove event listeners
        const wrapper = this.container.closest('.zc-chart-wrapper');
        if (wrapper) {
            wrapper.removeEventListener('click', this.handleTimeframeChange);
            wrapper.removeEventListener('change', this.handleChartTypeChange);
            wrapper.removeEventListener('click', this.handleExport);
        }
    }
}

// Make available globally
window.ZCChartLoader = ZCChartLoader;

// Auto-initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    const chartWrappers = document.querySelectorAll('.zc-chart-wrapper[data-chart-config]');
    
    chartWrappers.forEach(wrapper => {
        try {
            const configData = wrapper.dataset.chartConfig;
            const config = JSON.parse(configData);
            const container = wrapper.querySelector('.zc-chart-container');
            
            if (container && config) {
                const loader = new ZCChartLoader(container.id, config);
                loader.loadChart();
                
                // Store reference for potential cleanup
                container._chartLoader = loader;
            }
        } catch (error) {
            console.error('Failed to initialize chart:', error);
        }
    });
});
