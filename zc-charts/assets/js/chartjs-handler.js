/**
 * ZC Charts Chart.js Handler
 * Handles rendering charts using Chart.js library
 */

class ZCChartJSHandler {
    constructor(container, data, config) {
        this.container = container;
        this.data = data;
        this.config = config;
        this.chartInstance = null;
    }
    
    /**
     * Render chart using Chart.js
     */
    async render() {
        // Destroy existing chart if it exists
        if (this.chartInstance) {
            this.chartInstance.destroy();
        }
        
        // Get canvas context
        const canvas = document.createElement('canvas');
        this.container.appendChild(canvas);
        const ctx = canvas.getContext('2d');
        
        // Prepare chart data
        const chartData = this.prepareChartData();
        
        // Prepare chart options
        const chartOptions = this.prepareChartOptions();
        
        // Create chart
        this.chartInstance = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: chartOptions
        });
        
        // Add resize listener
        this.addResizeListener();
    }
    
    /**
     * Prepare chart data for Chart.js
     */
    prepareChartData() {
        const labels = [];
        const values = [];
        
        if (this.data && this.data.data && Array.isArray(this.data.data)) {
            // Sort data by date
            const sortedData = this.data.data.sort((a, b) => {
                return new Date(a.obs_date) - new Date(b.obs_date);
            });
            
            sortedData.forEach(point => {
                if (point.obs_date && point.value !== undefined) {
                    labels.push(point.obs_date);
                    values.push(parseFloat(point.value));
                }
            });
        }
        
        return {
            labels: labels,
            datasets: [{
                label: this.data && this.data.indicator ? this.data.indicator.name : 'Indicator',
                data: values,
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                fill: false,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6
            }]
        };
    }
    
    /**
     * Prepare chart options for Chart.js
     */
    prepareChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: this.isDarkMode() ? '#ffffff' : '#000000'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: this.isDarkMode() ? 'rgba(30, 30, 30, 0.9)' : 'rgba(0, 0, 0, 0.8)',
                    titleColor: this.isDarkMode() ? '#ffffff' : '#ffffff',
                    bodyColor: this.isDarkMode() ? '#cccccc' : '#ffffff',
                    borderColor: this.isDarkMode() ? '#555555' : '#cccccc',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Date',
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
                    },
                    grid: {
                        color: this.isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: this.data && this.data.indicator && this.data.indicator.units ? 
                              this.data.indicator.units : 'Value',
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
                    },
                    grid: {
                        color: this.isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
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
        };
    }
    
    /**
     * Add resize listener to handle responsive behavior
     */
    addResizeListener() {
        const resizeObserver = new ResizeObserver(entries => {
            if (this.chartInstance) {
                this.chartInstance.resize();
            }
        });
        
        resizeObserver.observe(this.container);
    }
    
    /**
     * Check if dark mode is enabled
     */
    isDarkMode() {
        // Check for prefers-color-scheme media query
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return true;
        }
        
        // Check for dark mode class on body (if used by theme)
        if (document.body.classList.contains('dark-mode') || 
            document.body.classList.contains('dark')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Update chart with new data
     */
    updateData(newData) {
        if (!this.chartInstance) {
            return;
        }
        
        // Prepare new chart data
        const chartData = this.prepareChartData(newData);
        
        // Update chart data
        this.chartInstance.data.labels = chartData.labels;
        this.chartInstance.data.datasets[0].data = chartData.datasets[0].data;
        this.chartInstance.data.datasets[0].label = chartData.datasets[0].label;
        
        // Update chart
        this.chartInstance.update();
    }
    
    /**
     * Change chart type
     */
    changeChartType(type) {
        if (!this.chartInstance) {
            return;
        }
        
        // Update chart type
        this.chartInstance.config.type = type;
        this.chartInstance.update();
    }
    
    /**
     * Apply timeframe filter
     */
    applyTimeframeFilter(timeframe) {
        if (!this.chartInstance || !this.data || !this.data.data) {
            return;
        }
        
        // Filter data based on timeframe
        const filteredData = this.filterDataByTimeframe(this.data.data, timeframe);
        
        // Update chart with filtered data
        this.updateData({ data: filteredData });
    }
    
    /**
     * Filter data by timeframe
     */
    filterDataByTimeframe(data, timeframe) {
        if (!data || !Array.isArray(data)) {
            return data;
        }
        
        // Determine cutoff date based on timeframe
        const cutoffDate = new Date();
        switch (timeframe) {
            case '3m':
                cutoffDate.setMonth(cutoffDate.getMonth() - 3);
                break;
            case '6m':
                cutoffDate.setMonth(cutoffDate.getMonth() - 6);
                break;
            case '1y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 1);
                break;
            case '2y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 2);
                break;
            case '3y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 3);
                break;
            case '5y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 5);
                break;
            case '10y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 10);
                break;
            case '15y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 15);
                break;
            case '20y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 20);
                break;
            case '25y':
                cutoffDate.setFullYear(cutoffDate.getFullYear() - 25);
                break;
            default:
                return data; // 'all' or unknown timeframe
        }
        
        // Filter data
        return data.filter(point => {
            if (!point.obs_date) return false;
            const pointDate = new Date(point.obs_date);
            return pointDate >= cutoffDate;
        });
    }
    
    /**
     * Export chart as image
     */
    exportAsImage(format = 'png') {
        if (!this.chartInstance) {
            return Promise.reject(new Error('Chart not initialized'));
        }
        
        return new Promise((resolve, reject) => {
            try {
                const imageUrl = this.chartInstance.toBase64Image(format, 1);
                resolve(imageUrl);
            } catch (error) {
                reject(error);
            }
        });
    }
    
    /**
     * Toggle fullscreen mode
     */
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            this.container.requestFullscreen().catch(err => {
                console.error('Error attempting to enable fullscreen:', err);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }
    
    /**
     * Destroy chart instance
     */
    destroy() {
        if (this.chartInstance) {
            this.chartInstance.destroy();
            this.chartInstance = null;
        }
        
        // Remove resize listener if needed
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ZCChartJSHandler;
}