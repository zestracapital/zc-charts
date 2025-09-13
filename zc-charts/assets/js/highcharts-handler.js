/**
 * ZC Charts Highcharts Handler
 * Handles rendering charts using Highcharts library
 */

class ZCHighchartsHandler {
    constructor(container, data, config) {
        this.container = container;
        this.data = data;
        this.config = config;
        this.chartInstance = null;
    }
    
    /**
     * Render chart using Highcharts
     */
    async render() {
        // Destroy existing chart if it exists
        if (this.chartInstance) {
            try {
                this.chartInstance.destroy();
            } catch (e) {
                // Ignore destruction errors
            }
        }
        
        // Prepare chart data
        const chartData = this.prepareChartData();
        
        // Prepare chart options
        const chartOptions = this.prepareChartOptions(chartData);
        
        // Create chart
        this.chartInstance = Highcharts.chart(this.container, chartOptions);
    }
    
    /**
     * Prepare chart data for Highcharts
     */
    prepareChartData() {
        const seriesData = [];
        const labels = [];
        
        if (this.data && this.data.data && Array.isArray(this.data.data)) {
            // Sort data by date
            const sortedData = this.data.data.sort((a, b) => {
                return new Date(a.obs_date) - new Date(b.obs_date);
            });
            
            sortedData.forEach(point => {
                if (point.obs_date && point.value !== undefined) {
                    // Convert date to timestamp for Highcharts
                    const timestamp = new Date(point.obs_date).getTime();
                    seriesData.push([timestamp, parseFloat(point.value)]);
                    labels.push(point.obs_date);
                }
            });
        }
        
        return {
            labels: labels,
            series: [{
                name: this.data && this.data.indicator ? this.data.indicator.name : 'Indicator',
                data: seriesData,
                color: '#0073aa'
            }]
        };
    }
    
    /**
     * Prepare chart options for Highcharts
     */
    prepareChartOptions(chartData) {
        return {
            chart: {
                type: 'line',
                zoomType: 'x',
                panning: true,
                panKey: 'shift',
                backgroundColor: this.isDarkMode() ? '#1e1e1e' : '#ffffff'
            },
            title: {
                text: this.data && this.data.indicator ? this.data.indicator.name : 'Indicator',
                style: {
                    color: this.isDarkMode() ? '#ffffff' : '#333333'
                }
            },
            xAxis: {
                type: 'datetime',
                title: {
                    text: 'Date',
                    style: {
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
                    }
                },
                labels: {
                    style: {
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
                    }
                },
                gridLineColor: this.isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
            },
            yAxis: {
                title: {
                    text: this.data && this.data.indicator && this.data.indicator.units ? 
                          this.data.indicator.units : 'Value',
                    style: {
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
                    }
                },
                labels: {
                    style: {
                        color: this.isDarkMode() ? '#cccccc' : '#666666'
                    }
                },
                gridLineColor: this.isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
            },
            legend: {
                enabled: true,
                itemStyle: {
                    color: this.isDarkMode() ? '#ffffff' : '#333333'
                },
                itemHoverStyle: {
                    color: this.isDarkMode() ? '#cccccc' : '#000000'
                }
            },
            plotOptions: {
                line: {
                    lineWidth: 2,
                    states: {
                        hover: {
                            lineWidth: 3
                        }
                    },
                    marker: {
                        radius: 4,
                        states: {
                            hover: {
                                radius: 6
                            }
                        }
                    }
                },
                series: {
                    color: '#0073aa'
                }
            },
            series: chartData.series,
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
            credits: {
                enabled: false
            },
            tooltip: {
                backgroundColor: this.isDarkMode() ? 'rgba(30, 30, 30, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                style: {
                    color: this.isDarkMode() ? '#ffffff' : '#000000'
                },
                borderColor: this.isDarkMode() ? '#555555' : '#cccccc',
                borderRadius: 4,
                shadow: true
            }
        };
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
        
        // Update chart series
        this.chartInstance.series[0].setData(chartData.series[0].data);
        this.chartInstance.setTitle({
            text: chartData.series[0].name
        });
    }
    
    /**
     * Change chart type
     */
    changeChartType(type) {
        if (!this.chartInstance) {
            return;
        }
        
        // Update chart type
        this.chartInstance.update({
            chart: {
                type: type
            }
        });
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
        this.updateData({  filteredData });
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
                // Highcharts has built-in export functionality
                const imageUrl = this.chartInstance.createCanvas();
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
            try {
                this.chartInstance.destroy();
            } catch (e) {
                // Ignore destruction errors
            }
            this.chartInstance = null;
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ZCHighchartsHandler;
}