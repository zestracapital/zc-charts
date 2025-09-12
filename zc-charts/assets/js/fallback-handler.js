/**
 * FallbackHandler
 * JavaScript class for handling backup data loading in the ZC Charts plugin.
 * This class is responsible for retrieving data from the DMT plugin's backup system
 * when the primary data source is unavailable.
 */

class FallbackHandler {
    /**
     * Constructor for the FallbackHandler.
     * 
     * @param {string} slug - The indicator slug.
     * @param {string} apiKey - The API key for authentication.
     * @param {string} dmtApiUrl - The base URL for the DMT REST API.
     */
    constructor(slug, apiKey, dmtApiUrl) {
        this.slug = slug;
        this.apiKey = apiKey;
        this.dmtApiUrl = dmtApiUrl;
    }

    /**
     * Attempt to load backup data for the indicator.
     * 
     * @returns {Promise<Object|null>} A promise that resolves with the backup data or null if failed.
     */
    async loadBackupData() {
        // Validate required parameters
        if (!this.slug || !this.apiKey || !this.dmtApiUrl) {
            console.error('FallbackHandler: Missing required parameters for backup data fetch.');
            return null;
        }

        try {
            // Construct the backup endpoint URL
            const url = `${this.dmtApiUrl}backup/${this.slug}?access_key=${encodeURIComponent(this.apiKey)}`;

            // Make the API request to the DMT backup endpoint
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    // 'X-WP-Nonce': nonce // If nonce-based authentication is needed
                },
                // credentials: 'same-origin' // If cookies/credentials are needed
            });

            // Check if the response is successful
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

            // Parse the JSON response
            const data = await response.json();
            
            // Check if the response contains the expected 'data' key
            if (!data || !data.data || !Array.isArray(data.data)) {
                throw new Error('Invalid backup data format received.');
            }

            console.log(`FallbackHandler: Successfully loaded backup data for indicator '${this.slug}'.`);
            return data;

        } catch (error) {
            console.error(`FallbackHandler: Failed to load backup data for indicator '${this.slug}'. Error:`, error);
            // Depending on requirements, you might want to re-throw the error
            // or return null to indicate failure.
            return null;
        }
    }

    /**
     * Process the loaded backup data to make it compatible with chart libraries.
     * This method can transform the data format if needed.
     * 
     * @param {Object} rawData - The raw backup data from the API.
     * @param {string} library - The target chart library ('chartjs' or 'highcharts').
     * @returns {Object|Array} The processed data compatible with the specified library.
     */
    processData(rawData, library) {
        if (!rawData || !rawData.data || !Array.isArray(rawData.data)) {
            console.error('FallbackHandler: Invalid raw data provided for processing.');
            return library === 'highcharts' ? [] : { labels: [], datasets: [] };
        }

        const dataPoints = rawData.data;

        if (library === 'highcharts') {
            // Highcharts typically expects [[timestamp, value], ...] for date-based series
            const seriesData = [];
            dataPoints.forEach(point => {
                if (point.date !== undefined && point.value !== undefined) {
                    // Convert date string to timestamp (in milliseconds for Highcharts)
                    const timestamp = new Date(point.date).getTime();
                    const value = parseFloat(point.value);
                    seriesData.push([timestamp, value]);
                }
            });
            return seriesData;
        } else {
            // Default to Chart.js format
            // Chart.js expects labels array and datasets array
            const labels = [];
            const values = [];
            dataPoints.forEach(point => {
                if (point.date !== undefined && point.value !== undefined) {
                    // Chart.js can work with date strings directly on the x-axis with proper configuration
                    labels.push(point.date);
                    values.push(parseFloat(point.value));
                }
            });
            
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
    }
}

// Expose the class to the global scope if needed by other scripts
// In a module system, you might use `export default FallbackHandler;`
// window.FallbackHandler = FallbackHandler;
