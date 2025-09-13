/**
 * ZC Charts Fallback Handler
 * Handles backup data loading when live data fails
 */

class ZCFallbackHandler {
    constructor() {
        this.config = {
            maxRetries: 2,
            retryDelay: 1000,
            timeout: 15000
        };
    }
    
    /**
     * Get backup data when live data fails
     */
    async getBackupData(indicatorSlug, apiKey) {
        if (!indicatorSlug || !apiKey) {
            throw new Error('Indicator slug and API key are required');
        }
        
        const backupUrl = `${zcChartsConfig.restUrl}zc-dmt/v1/backup/${indicatorSlug}?access_key=${apiKey}`;
        
        try {
            const response = await this.fetchWithRetry(backupUrl, {
                method: 'GET',
                timeout: this.config.timeout
            }, this.config.maxRetries);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const body = await response.json();
            
            if (!body || !body.data) {
                throw new Error('Invalid backup data format');
            }
            
            return {
                ...body,
                source: 'backup'
            };
        } catch (error) {
            console.error('Fallback data fetch failed:', error);
            throw new Error(`Backup data unavailable: ${error.message}`);
        }
    }
    
    /**
     * Check if backup data is available for an indicator
     */
    async isBackupAvailable(indicatorSlug, apiKey) {
        if (!indicatorSlug || !apiKey) {
            return false;
        }
        
        const checkUrl = `${zcChartsConfig.restUrl}zc-dmt/v1/backup/${indicatorSlug}/check?access_key=${apiKey}`;
        
        try {
            const response = await fetch(checkUrl, {
                method: 'GET',
                timeout: 10000
            });
            
            if (!response.ok) {
                return false;
            }
            
            const body = await response.json();
            return body && body.available === true;
        } catch (error) {
            console.warn('Failed to check backup availability:', error);
            return false;
        }
    }
    
    /**
     * Get the last backup timestamp for an indicator
     */
    async getLastBackupTimestamp(indicatorSlug, apiKey) {
        if (!indicatorSlug || !apiKey) {
            return null;
        }
        
        const infoUrl = `${zcChartsConfig.restUrl}zc-dmt/v1/backup/${indicatorSlug}/info?access_key=${apiKey}`;
        
        try {
            const response = await fetch(infoUrl, {
                method: 'GET',
                timeout: 10000
            });
            
            if (!response.ok) {
                return null;
            }
            
            const body = await response.json();
            
            if (body && body.last_backup) {
                return body.last_backup;
            }
            
            return null;
        } catch (error) {
            console.warn('Failed to get backup info:', error);
            return null;
        }
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
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * this.config.retryDelay));
            }
        }
    }
    
    /**
     * Handle fallback when live data fails
     */
    async handleFallback(indicatorSlug, apiKey, errorMessage = '') {
        try {
            // Log the fallback attempt
            this.logFallbackAttempt(indicatorSlug, errorMessage);
            
            // Check if backup is available
            const isAvailable = await this.isBackupAvailable(indicatorSlug, apiKey);
            
            if (!isAvailable) {
                throw new Error('No backup data available');
            }
            
            // Get backup data
            const backupData = await this.getBackupData(indicatorSlug, apiKey);
            
            // Log successful fallback
            this.logFallbackSuccess(indicatorSlug, backupData.data ? backupData.data.length : 0);
            
            return backupData;
        } catch (error) {
            // Log fallback failure
            this.logFallbackFailure(indicatorSlug, error.message);
            
            throw error;
        }
    }
    
    /**
     * Log fallback attempt
     */
    logFallbackAttempt(indicatorSlug, errorMessage) {
        if (typeof zcChartsConfig !== 'undefined' && zcChartsConfig.debug) {
            console.info(`[ZC Charts Fallback] Attempting to load backup data for: ${indicatorSlug}`, {
                indicator: indicatorSlug,
                error: errorMessage,
                timestamp: new Date().toISOString()
            });
        }
    }
    
    /**
     * Log fallback success
     */
    logFallbackSuccess(indicatorSlug, dataPoints) {
        if (typeof zcChartsConfig !== 'undefined' && zcChartsConfig.debug) {
            console.info(`[ZC Charts Fallback] Successfully loaded backup data for: ${indicatorSlug}`, {
                indicator: indicatorSlug,
                dataPoints: dataPoints,
                timestamp: new Date().toISOString()
            });
        }
    }
    
    /**
     * Log fallback failure
     */
    logFallbackFailure(indicatorSlug, errorMessage) {
        if (typeof zcChartsConfig !== 'undefined' && zcChartsConfig.debug) {
            console.error(`[ZC Charts Fallback] Failed to load backup data for: ${indicatorSlug}`, {
                indicator: indicatorSlug,
                error: errorMessage,
                timestamp: new Date().toISOString()
            });
        }
    }
    
    /**
     * Show fallback notice to user
     */
    showFallbackNotice(container) {
        // Check if notice already exists
        const existingNotice = container.querySelector('.zc-chart-fallback-notice');
        if (existingNotice) {
            return;
        }
        
        // Create notice element
        const notice = document.createElement('div');
        notice.className = 'zc-chart-fallback-notice';
        notice.innerHTML = `
            <div class="notice-content">
                <span class="notice-icon">ℹ️</span>
                <span class="notice-text">Displaying cached data</span>
            </div>
        `;
        
        // Insert notice before the chart container
        container.parentNode.insertBefore(notice, container);
    }
    
    /**
     * Hide fallback notice
     */
    hideFallbackNotice(container) {
        const notice = container.parentNode.querySelector('.zc-chart-fallback-notice');
        if (notice) {
            notice.remove();
        }
    }
    
    /**
     * Get fallback notice message
     */
    getFallbackNoticeMessage() {
        return 'Displaying cached data';
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ZCFallbackHandler;
}