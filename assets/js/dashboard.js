/**
 * Dashboard JavaScript module
 * Handles all dashboard functionality including API integration, chart rendering, and user interactions.
 */

import { Chart, registerables } from 'chart.js';
/* global bootstrap */

// Register Chart.js components
Chart.register(...registerables);

/**
 * Dashboard state management
 */
class DashboardState {
    constructor() {
        this.selectedRange = '24h';
        this.isLoading = false;
        this.metricsData = null;
        this.statusData = null;
        this.isStale = false;
        this.charts = {
            cpu: null,
            ram: null,
            disk: null,
            io: null,
            network: null
        };
        this.ramUnit = 'GB'; // Default unit for RAM
    }

    setSelectedRange(range) {
        this.selectedRange = range;
    }

    setLoading(loading) {
        this.isLoading = loading;
    }

    setMetricsData(data) {
        this.metricsData = data;
    }

    setStatusData(data) {
        this.statusData = data;
    }

    setStale(stale) {
        this.isStale = stale;
    }

    setChart(type, chart) {
        this.charts[type] = chart;
    }

    getChart(type) {
        return this.charts[type];
    }

    setRamUnit(unit) {
        this.ramUnit = unit;
    }
}

/**
 * API helper functions
 */
class DashboardAPI {
    /**
     * Fetch metrics for a given time range
     * @param {string} range - Time range: '1h', '6h', '24h', '7d', '30d'
     * @returns {Promise<Object>} Metrics response
     */
    static async fetchMetrics(range) {
        const response = await fetch(`/api/metrics?range=${range}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });

        if (response.status === 401) {
            window.location.href = '/login?expired=1';
            throw new Error('Authentication required');
        }

        if (!response.ok) {
            const error = await response.json().catch(() => ({ error: 'Unknown error' }));
            throw new Error(error.error || `HTTP ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Fetch latest metrics
     * @returns {Promise<Object>} Latest metrics response
     */
    static async fetchLatestMetrics() {
        const response = await fetch('/api/metrics/latest', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });

        if (response.status === 401) {
            window.location.href = '/login?expired=1';
            throw new Error('Authentication required');
        }

        if (!response.ok) {
            const error = await response.json().catch(() => ({ error: 'Unknown error' }));
            throw new Error(error.error || `HTTP ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Fetch system status
     * @returns {Promise<Object>} Status response
     */
    static async fetchStatus() {
        const response = await fetch('/api/status', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });

        if (response.status === 401) {
            window.location.href = '/login?expired=1';
            throw new Error('Authentication required');
        }

        if (!response.ok) {
            const error = await response.json().catch(() => ({ error: 'Unknown error' }));
            throw new Error(error.error || `HTTP ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Logout user
     * @returns {Promise<void>}
     */
    static async logout() {
        const response = await fetch('/api/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({ error: 'Unknown error' }));
            throw new Error(error.error || `HTTP ${response.status}`);
        }

        window.location.href = '/login';
    }
}

/**
 * Utility functions for formatting and data conversion
 */
class DashboardUtils {
    /**
     * Format timestamp based on time range
     * @param {string} timestamp - ISO 8601 UTC timestamp
     * @param {string} range - Time range
     * @returns {string} Formatted timestamp
     */
    static formatTimestamp(timestamp, range) {
        const date = new Date(timestamp);
        const isShortRange = range === '1h' || range === '6h';
        
        if (isShortRange) {
            return date.toLocaleTimeString('pl-PL', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        } else {
            return date.toLocaleString('pl-PL', { 
                day: '2-digit', 
                month: '2-digit', 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    }

    /**
     * Format relative time
     * @param {string} timestamp - ISO 8601 UTC timestamp
     * @returns {string} Relative time string
     */
    static formatRelativeTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) {
            return 'przed chwilą';
        } else if (diffMins < 60) {
            return `${diffMins} ${diffMins === 1 ? 'minutę' : diffMins < 5 ? 'minuty' : 'minut'} temu`;
        } else if (diffHours < 24) {
            return `${diffHours} ${diffHours === 1 ? 'godzinę' : diffHours < 5 ? 'godziny' : 'godzin'} temu`;
        } else {
            return `${diffDays} ${diffDays === 1 ? 'dzień' : 'dni'} temu`;
        }
    }

    /**
     * Format bytes to human readable format
     * @param {number} bytes - Bytes value
     * @returns {string} Formatted string
     */
    static formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Convert GB to MB
     * @param {number} gb - GB value
     * @returns {number} MB value
     */
    static convertGBToMB(gb) {
        return gb * 1024;
    }

    /**
     * Convert MB to GB
     * @param {number} mb - MB value
     * @returns {number} GB value
     */
    static convertMBToGB(mb) {
        return mb / 1024;
    }

    /**
     * Update Bootstrap tooltip for an element
     * @param {HTMLElement} element - Element with tooltip
     * @param {string} text - Tooltip text
     */
    static updateTooltip(element, text) {
        if (!element) return;
        
        element.setAttribute('title', text);
        element.setAttribute('data-bs-original-title', text);
        
        // Update existing tooltip instance if available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipInstance = bootstrap.Tooltip.getInstance(element);
            if (tooltipInstance) {
                tooltipInstance.setContent({ '.tooltip-inner': text });
            }
        }
    }
}

/**
 * StickyHeader component
 */
class StickyHeader {
    constructor(state, onRangeChange, onRefresh, onLogout) {
        this.state = state;
        this.onRangeChange = onRangeChange;
        this.onRefresh = onRefresh;
        this.onLogout = onLogout;
        this.init();
    }

    init() {
        // TimeRangeSelector
        const timeRangeButtons = document.querySelectorAll('#time-range-selector button');
        timeRangeButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                if (this.state.isLoading) return;
                
                const range = e.target.dataset.range;
                if (!['1h', '6h', '24h', '7d', '30d'].includes(range)) {
                    console.error('Invalid range:', range);
                    return;
                }

                // Update active button
                timeRangeButtons.forEach(btn => {
                    btn.classList.remove('active', 'btn-primary');
                    btn.classList.add('btn-outline-primary');
                });
                e.target.classList.add('active', 'btn-primary');
                e.target.classList.remove('btn-outline-primary');

                this.state.setSelectedRange(range);
                this.onRangeChange(range);
            });
        });

        // RefreshButton
        const refreshButton = document.getElementById('refresh-button');
        refreshButton.addEventListener('click', () => {
            if (this.state.isLoading) return;
            this.onRefresh();
        });

        // LogoutButton
        const logoutButton = document.getElementById('logout-button');
        logoutButton.addEventListener('click', () => {
            this.onLogout();
        });
    }

    updateLoadingState(isLoading) {
        const spinner = document.getElementById('loading-spinner');
        const refreshButton = document.getElementById('refresh-button');
        const timeRangeButtons = document.querySelectorAll('#time-range-selector button');

        if (isLoading) {
            spinner.classList.remove('d-none');
            refreshButton.disabled = true;
            refreshButton.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Odświeżanie...';
            timeRangeButtons.forEach(btn => btn.disabled = true);
        } else {
            spinner.classList.add('d-none');
            refreshButton.disabled = false;
            refreshButton.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Odśwież';
            timeRangeButtons.forEach(btn => btn.disabled = false);
        }
    }
}

/**
 * AlertBanner component
 */
class AlertBanner {
    constructor() {
        this.container = document.getElementById('alert-banner-container');
        this.banner = document.getElementById('alert-banner');
        this.message = document.getElementById('alert-message');
    }

    /**
     * Show warning banner (SSH error)
     * @param {string} message - Warning message
     * @param {string|null} lastUpdateTimestamp - Last update timestamp (optional)
     */
    showWarning(message, lastUpdateTimestamp = null) {
        this.banner.className = 'alert alert-warning alert-dismissible fade show';
        let msg = message;
        
        if (lastUpdateTimestamp) {
            const relativeTime = DashboardUtils.formatRelativeTime(lastUpdateTimestamp);
            msg += ` Ostatnia aktualizacja: ${relativeTime}`;
        }
        
        this.message.textContent = msg;
        this.container.style.display = 'block';
    }

    /**
     * Show danger banner (critical error)
     * @param {string} message - Error message
     */
    showDanger(message) {
        this.banner.className = 'alert alert-danger alert-dismissible fade show';
        this.message.textContent = message;
        this.container.style.display = 'block';
    }

    /**
     * Hide banner
     */
    hide() {
        this.container.style.display = 'none';
    }
}

/**
 * CPUMetricSection component
 */
class CPUMetricSection {
    constructor(state, canvasId, noDataId, warningIconId) {
        this.state = state;
        this.canvasId = canvasId;
        this.noDataId = noDataId;
        this.warningIconId = warningIconId;
        this.chart = null;
    }

    /**
     * Initialize or update CPU chart
     * @param {Array} data - Metrics data array
     * @param {string} range - Time range
     * @param {boolean} isStale - Whether data is stale
     * @param {string|null} lastUpdateTimestamp - Last update timestamp
     */
    update(data, range, isStale, lastUpdateTimestamp) {
        const canvas = document.getElementById(this.canvasId);
        const noDataElement = document.getElementById(this.noDataId);
        const warningIcon = document.getElementById(this.warningIconId);

        if (!data || data.length === 0) {
            // Show "No data" message
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
            canvas.style.display = 'none';
            noDataElement.classList.remove('d-none');
            warningIcon.classList.add('d-none');
            return;
        }

        // Hide "No data" message
        noDataElement.classList.add('d-none');
        canvas.style.display = 'block';

        // Prepare chart data
        const labels = data.map(item => DashboardUtils.formatTimestamp(item.timestamp, range));
        const cpuValues = data.map(item => {
            const value = parseFloat(item.cpu_usage);
            return isNaN(value) ? 0 : Math.max(0, Math.min(100, value));
        });

        // Show/hide warning icon
        if (isStale && lastUpdateTimestamp) {
            warningIcon.classList.remove('d-none');
            const tooltipText = `Ostatnia aktualizacja: ${DashboardUtils.formatRelativeTime(lastUpdateTimestamp)}`;
            DashboardUtils.updateTooltip(warningIcon, tooltipText);
        } else {
            warningIcon.classList.add('d-none');
        }

        // Initialize or update chart
        if (!this.chart) {
            if (!canvas) {
                console.error(`Canvas element not found: ${this.canvasId}`);
                return;
            }
            
            try {
                const ctx = canvas.getContext('2d');
                this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'CPU %',
                        data: cpuValues,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    return `CPU: ${context.parsed.y.toFixed(2)}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: (value) => `${value}%`
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
                this.state.setChart('cpu', this.chart);
            } catch (error) {
                console.error('Error initializing CPU chart:', error);
                canvas.style.display = 'none';
                noDataElement.classList.remove('d-none');
                return;
            }
        } else {
            this.chart.data.labels = labels;
            this.chart.data.datasets[0].data = cpuValues;
            this.chart.update('none');
        }
    }
}

/**
 * RAMMetricSection component
 */
class RAMMetricSection {
    constructor(state, canvasId, noDataId, warningIconId) {
        this.state = state;
        this.canvasId = canvasId;
        this.noDataId = noDataId;
        this.warningIconId = warningIconId;
        this.chart = null;
        this.initUnitToggle();
    }

    /**
     * Initialize unit toggle button
     */
    initUnitToggle() {
        const toggleButtons = document.querySelectorAll('#ram-unit-toggle button');
        toggleButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const unit = e.target.dataset.unit;
                if (!['GB', 'MB'].includes(unit)) return;

                // Update active button
                toggleButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                e.target.classList.add('active');

                // Update state and refresh chart
                this.state.setRamUnit(unit);
                if (this.state.metricsData) {
                    this.update(this.state.metricsData, this.state.selectedRange, 
                               this.state.isStale, this.state.statusData?.last_collection);
                }
            });
        });
    }

    /**
     * Initialize or update RAM chart
     * @param {Array} data - Metrics data array (values in GB)
     * @param {string} range - Time range
     * @param {boolean} isStale - Whether data is stale
     * @param {string|null} lastUpdateTimestamp - Last update timestamp
     */
    update(data, range, isStale, lastUpdateTimestamp) {
        const canvas = document.getElementById(this.canvasId);
        const noDataElement = document.getElementById(this.noDataId);
        const warningIcon = document.getElementById(this.warningIconId);

        if (!data || data.length === 0) {
            // Show "No data" message
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
            canvas.style.display = 'none';
            noDataElement.classList.remove('d-none');
            warningIcon.classList.add('d-none');
            return;
        }

        // Hide "No data" message
        noDataElement.classList.add('d-none');
        canvas.style.display = 'block';

        // Prepare chart data
        const labels = data.map(item => DashboardUtils.formatTimestamp(item.timestamp, range));
        const unit = this.state.ramUnit;
        const ramValues = data.map(item => {
            const valueGB = parseFloat(item.ram_usage);
            if (isNaN(valueGB) || valueGB < 0) return 0;
            return unit === 'MB' ? DashboardUtils.convertGBToMB(valueGB) : valueGB;
        });

        // Show/hide warning icon
        if (isStale && lastUpdateTimestamp) {
            warningIcon.classList.remove('d-none');
            const tooltipText = `Ostatnia aktualizacja: ${DashboardUtils.formatRelativeTime(lastUpdateTimestamp)}`;
            DashboardUtils.updateTooltip(warningIcon, tooltipText);
        } else {
            warningIcon.classList.add('d-none');
        }

        // Initialize or update chart
        if (!this.chart) {
            if (!canvas) {
                console.error(`Canvas element not found: ${this.canvasId}`);
                return;
            }
            
            try {
                const ctx = canvas.getContext('2d');
                this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: `RAM (${unit})`,
                        data: ramValues,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    return `RAM: ${context.parsed.y.toFixed(2)} ${unit}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => `${value.toFixed(2)} ${unit}`
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
                this.state.setChart('ram', this.chart);
            } catch (error) {
                console.error('Error initializing RAM chart:', error);
                canvas.style.display = 'none';
                noDataElement.classList.remove('d-none');
                return;
            }
        } else {
            this.chart.data.labels = labels;
            this.chart.data.datasets[0].data = ramValues;
            this.chart.data.datasets[0].label = `RAM (${unit})`;
            this.chart.options.scales.y.ticks.callback = (value) => `${value.toFixed(2)} ${unit}`;
            this.chart.update('none');
        }
    }
}

/**
 * DiskMetricSection component
 */
class DiskMetricSection {
    constructor(state, canvasId, noDataId, warningIconId) {
        this.state = state;
        this.canvasId = canvasId;
        this.noDataId = noDataId;
        this.warningIconId = warningIconId;
        this.chart = null;
    }

    /**
     * Initialize or update Disk chart
     * @param {Array} data - Metrics data array (values in GB)
     * @param {string} range - Time range
     * @param {boolean} isStale - Whether data is stale
     * @param {string|null} lastUpdateTimestamp - Last update timestamp
     */
    update(data, range, isStale, lastUpdateTimestamp) {
        const canvas = document.getElementById(this.canvasId);
        const noDataElement = document.getElementById(this.noDataId);
        const warningIcon = document.getElementById(this.warningIconId);

        if (!data || data.length === 0) {
            // Show "No data" message
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
            canvas.style.display = 'none';
            noDataElement.classList.remove('d-none');
            warningIcon.classList.add('d-none');
            return;
        }

        // Hide "No data" message
        noDataElement.classList.add('d-none');
        canvas.style.display = 'block';

        // Prepare chart data
        const labels = data.map(item => DashboardUtils.formatTimestamp(item.timestamp, range));
        const diskValues = data.map(item => {
            const value = parseFloat(item.disk_usage);
            return isNaN(value) || value < 0 ? 0 : value;
        });

        // Show/hide warning icon
        if (isStale && lastUpdateTimestamp) {
            warningIcon.classList.remove('d-none');
            const tooltipText = `Ostatnia aktualizacja: ${DashboardUtils.formatRelativeTime(lastUpdateTimestamp)}`;
            DashboardUtils.updateTooltip(warningIcon, tooltipText);
        } else {
            warningIcon.classList.add('d-none');
        }

        // Initialize or update chart
        if (!this.chart) {
            if (!canvas) {
                console.error(`Canvas element not found: ${this.canvasId}`);
                return;
            }
            
            try {
                const ctx = canvas.getContext('2d');
                this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Disk (GB)',
                        data: diskValues,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    return `Disk: ${context.parsed.y.toFixed(2)} GB`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => `${value.toFixed(2)} GB`
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
                this.state.setChart('disk', this.chart);
            } catch (error) {
                console.error('Error initializing Disk chart:', error);
                canvas.style.display = 'none';
                noDataElement.classList.remove('d-none');
                return;
            }
        } else {
            this.chart.data.labels = labels;
            this.chart.data.datasets[0].data = diskValues;
            this.chart.update('none');
        }
    }
}

/**
 * IOMetricSection component
 */
class IOMetricSection {
    constructor(state, canvasId, noDataId, warningIconId) {
        this.state = state;
        this.canvasId = canvasId;
        this.noDataId = noDataId;
        this.warningIconId = warningIconId;
        this.chart = null;
    }

    /**
     * Calculate differences between consecutive measurements for I/O data
     * @param {Array} data - Metrics data array with cumulative values
     * @returns {Array} Array of {timestamp, read_diff, write_diff}
     */
    calculateIODifferences(data) {
        if (!data || data.length === 0) return [];

        const processed = [];
        for (let i = 0; i < data.length; i++) {
            const current = data[i];
            const timestamp = current.timestamp;
            
            if (i === 0) {
                // First point: no difference, use 0
                processed.push({
                    timestamp: timestamp,
                    read_diff: 0,
                    write_diff: 0
                });
            } else {
                const previous = data[i - 1];
                const readCurrent = parseFloat(current.io_read_bytes) || 0;
                const writeCurrent = parseFloat(current.io_write_bytes) || 0;
                const readPrevious = parseFloat(previous.io_read_bytes) || 0;
                const writePrevious = parseFloat(previous.io_write_bytes) || 0;

                // Calculate differences
                let readDiff = readCurrent - readPrevious;
                let writeDiff = writeCurrent - writePrevious;

                // Handle counter reset (value drops) - use 0 instead of negative
                if (readDiff < 0) {
                    readDiff = 0;
                }
                if (writeDiff < 0) {
                    writeDiff = 0;
                }

                processed.push({
                    timestamp: timestamp,
                    read_diff: readDiff,
                    write_diff: writeDiff
                });
            }
        }

        return processed;
    }

    /**
     * Format bytes value for Y axis
     * @param {number} bytes - Bytes value
     * @returns {string} Formatted string
     */
    formatBytesForAxis(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        const value = bytes / Math.pow(k, i);
        return `${value.toFixed(i === 0 ? 0 : 2)} ${sizes[i]}`;
    }

    /**
     * Initialize or update I/O chart
     * @param {Array} data - Metrics data array (cumulative values)
     * @param {string} range - Time range
     * @param {boolean} isStale - Whether data is stale
     * @param {string|null} lastUpdateTimestamp - Last update timestamp
     */
    update(data, range, isStale, lastUpdateTimestamp) {
        const canvas = document.getElementById(this.canvasId);
        const noDataElement = document.getElementById(this.noDataId);
        const warningIcon = document.getElementById(this.warningIconId);

        if (!data || data.length === 0) {
            // Show "No data" message
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
            canvas.style.display = 'none';
            noDataElement.classList.remove('d-none');
            warningIcon.classList.add('d-none');
            return;
        }

        // Hide "No data" message
        noDataElement.classList.add('d-none');
        canvas.style.display = 'block';

        // Calculate differences
        let processedData;
        try {
            processedData = this.calculateIODifferences(data);
            if (!processedData || processedData.length === 0) {
                throw new Error('No processed data');
            }
        } catch (error) {
            console.error('Error calculating I/O differences:', error);
            canvas.style.display = 'none';
            noDataElement.classList.remove('d-none');
            warningIcon.classList.add('d-none');
            return;
        }

        const labels = processedData.map(item => DashboardUtils.formatTimestamp(item.timestamp, range));
        const readValues = processedData.map(item => {
            const value = parseFloat(item.read_diff);
            return isNaN(value) || value < 0 ? 0 : value;
        });
        const writeValues = processedData.map(item => {
            const value = parseFloat(item.write_diff);
            return isNaN(value) || value < 0 ? 0 : value;
        });

        // Show/hide warning icon
        if (isStale && lastUpdateTimestamp) {
            warningIcon.classList.remove('d-none');
            const tooltipText = `Ostatnia aktualizacja: ${DashboardUtils.formatRelativeTime(lastUpdateTimestamp)}`;
            DashboardUtils.updateTooltip(warningIcon, tooltipText);
        } else {
            warningIcon.classList.add('d-none');
        }

        // Initialize or update chart
        if (!this.chart) {
            if (!canvas) {
                console.error(`Canvas element not found: ${this.canvasId}`);
                return;
            }
            
            try {
                const ctx = canvas.getContext('2d');
                this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Read',
                            data: readValues,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Write',
                            data: writeValues,
                            borderColor: 'rgb(255, 159, 64)',
                            backgroundColor: 'rgba(255, 159, 64, 0.2)',
                            tension: 0.1,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed.y;
                                    const label = context.dataset.label;
                                    return `${label}: ${DashboardUtils.formatBytes(value)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatBytesForAxis(value)
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
                this.state.setChart('io', this.chart);
            } catch (error) {
                console.error('Error initializing I/O chart:', error);
                canvas.style.display = 'none';
                noDataElement.classList.remove('d-none');
                return;
            }
        } else {
            this.chart.data.labels = labels;
            this.chart.data.datasets[0].data = readValues;
            this.chart.data.datasets[1].data = writeValues;
            this.chart.update('none');
        }
    }
}

/**
 * NetworkMetricSection component
 */
class NetworkMetricSection {
    constructor(state, canvasId, noDataId, warningIconId) {
        this.state = state;
        this.canvasId = canvasId;
        this.noDataId = noDataId;
        this.warningIconId = warningIconId;
        this.chart = null;
    }

    /**
     * Calculate network rates (MB/s or GB/h) from cumulative bytes
     * @param {Array} data - Metrics data array with cumulative values
     * @param {string} range - Time range
     * @returns {Array} Array of {timestamp, sent_rate, received_rate, unit}
     */
    calculateNetworkRates(data, range) {
        if (!data || data.length === 0) return [];

        // Determine unit based on range
        const useMBs = range === '1h' || range === '6h' || range === '24h';
        const unit = useMBs ? 'MB/s' : 'GB/h';

        const processed = [];
        for (let i = 0; i < data.length; i++) {
            const current = data[i];
            const timestamp = current.timestamp;
            
            if (i === 0) {
                // First point: no rate, use 0
                processed.push({
                    timestamp: timestamp,
                    sent_rate: 0,
                    received_rate: 0,
                    unit: unit
                });
            } else {
                const previous = data[i - 1];
                const sentCurrent = parseFloat(current.network_sent_bytes) || 0;
                const receivedCurrent = parseFloat(current.network_received_bytes) || 0;
                const sentPrevious = parseFloat(previous.network_sent_bytes) || 0;
                const receivedPrevious = parseFloat(previous.network_received_bytes) || 0;

                // Calculate time difference in seconds
                const currentTime = new Date(current.timestamp).getTime();
                const previousTime = new Date(previous.timestamp).getTime();
                const timeDiffSeconds = (currentTime - previousTime) / 1000;

                if (timeDiffSeconds <= 0) {
                    // Invalid time difference, use 0
                    processed.push({
                        timestamp: timestamp,
                        sent_rate: 0,
                        received_rate: 0,
                        unit: unit
                    });
                    continue;
                }

                // Calculate byte differences
                let sentDiff = sentCurrent - sentPrevious;
                let receivedDiff = receivedCurrent - receivedPrevious;

                // Handle counter reset (value drops) - use 0 instead of negative
                if (sentDiff < 0) {
                    sentDiff = 0;
                }
                if (receivedDiff < 0) {
                    receivedDiff = 0;
                }

                // Calculate rate
                let sentRate, receivedRate;
                if (useMBs) {
                    // MB/s: bytes / (1024 * 1024) / seconds
                    sentRate = (sentDiff / (1024 * 1024)) / timeDiffSeconds;
                    receivedRate = (receivedDiff / (1024 * 1024)) / timeDiffSeconds;
                } else {
                    // GB/h: bytes / (1024 * 1024 * 1024) * (3600 / seconds)
                    sentRate = (sentDiff / (1024 * 1024 * 1024)) * (3600 / timeDiffSeconds);
                    receivedRate = (receivedDiff / (1024 * 1024 * 1024)) * (3600 / timeDiffSeconds);
                }

                // Validate rates (not NaN, not Infinity)
                if (isNaN(sentRate) || !isFinite(sentRate)) {
                    sentRate = 0;
                }
                if (isNaN(receivedRate) || !isFinite(receivedRate)) {
                    receivedRate = 0;
                }

                processed.push({
                    timestamp: timestamp,
                    sent_rate: sentRate,
                    received_rate: receivedRate,
                    unit: unit
                });
            }
        }

        return processed;
    }

    /**
     * Initialize or update Network chart
     * @param {Array} data - Metrics data array (cumulative values)
     * @param {string} range - Time range
     * @param {boolean} isStale - Whether data is stale
     * @param {string|null} lastUpdateTimestamp - Last update timestamp
     */
    update(data, range, isStale, lastUpdateTimestamp) {
        const canvas = document.getElementById(this.canvasId);
        const noDataElement = document.getElementById(this.noDataId);
        const warningIcon = document.getElementById(this.warningIconId);
        const unitElement = document.getElementById('network-unit');

        if (!data || data.length === 0) {
            // Show "No data" message
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
            canvas.style.display = 'none';
            noDataElement.classList.remove('d-none');
            warningIcon.classList.add('d-none');
            return;
        }

        // Hide "No data" message
        noDataElement.classList.add('d-none');
        canvas.style.display = 'block';

        // Calculate rates
        let processedData;
        try {
            processedData = this.calculateNetworkRates(data, range);
            if (!processedData || processedData.length === 0) {
                throw new Error('No processed data');
            }
        } catch (error) {
            console.error('Error calculating network rates:', error);
            canvas.style.display = 'none';
            noDataElement.classList.remove('d-none');
            warningIcon.classList.add('d-none');
            return;
        }

        const labels = processedData.map(item => DashboardUtils.formatTimestamp(item.timestamp, range));
        const sentValues = processedData.map(item => {
            const value = parseFloat(item.sent_rate);
            return isNaN(value) || !isFinite(value) || value < 0 ? 0 : value;
        });
        const receivedValues = processedData.map(item => {
            const value = parseFloat(item.received_rate);
            return isNaN(value) || !isFinite(value) || value < 0 ? 0 : value;
        });
        const unit = processedData.length > 0 ? processedData[0].unit : 'MB/s';

        // Update unit display
        if (unitElement) {
            unitElement.textContent = unit;
        }

        // Show/hide warning icon
        if (isStale && lastUpdateTimestamp) {
            warningIcon.classList.remove('d-none');
            const tooltipText = `Ostatnia aktualizacja: ${DashboardUtils.formatRelativeTime(lastUpdateTimestamp)}`;
            DashboardUtils.updateTooltip(warningIcon, tooltipText);
        } else {
            warningIcon.classList.add('d-none');
        }

        // Initialize or update chart
        if (!this.chart) {
            if (!canvas) {
                console.error(`Canvas element not found: ${this.canvasId}`);
                return;
            }
            
            try {
                const ctx = canvas.getContext('2d');
                this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Sent',
                            data: sentValues,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'Received',
                            data: receivedValues,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed.y;
                                    const label = context.dataset.label;
                                    return `${label}: ${value.toFixed(2)} ${unit}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => `${value.toFixed(2)} ${unit}`
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
                this.state.setChart('network', this.chart);
            } catch (error) {
                console.error('Error initializing Network chart:', error);
                canvas.style.display = 'none';
                noDataElement.classList.remove('d-none');
                return;
            }
        } else {
            this.chart.data.labels = labels;
            this.chart.data.datasets[0].data = sentValues;
            this.chart.data.datasets[1].data = receivedValues;
            this.chart.options.scales.y.ticks.callback = (value) => `${value.toFixed(2)} ${unit}`;
            this.chart.update('none');
        }
    }
}

/**
 * Main Dashboard class
 */
class Dashboard {
    constructor() {
        this.state = new DashboardState();
        this.alertBanner = new AlertBanner();
        this.stickyHeader = new StickyHeader(
            this.state,
            (range) => this.handleRangeChange(range),
            () => this.handleRefresh(),
            () => this.handleLogout()
        );
        
        // Initialize metric sections
        this.cpuSection = new CPUMetricSection(
            this.state,
            'cpu-chart',
            'cpu-no-data',
            'cpu-warning-icon'
        );
        this.ramSection = new RAMMetricSection(
            this.state,
            'ram-chart',
            'ram-no-data',
            'ram-warning-icon'
        );
        this.diskSection = new DiskMetricSection(
            this.state,
            'disk-chart',
            'disk-no-data',
            'disk-warning-icon'
        );
        this.ioSection = new IOMetricSection(
            this.state,
            'io-chart',
            'io-no-data',
            'io-warning-icon'
        );
        this.networkSection = new NetworkMetricSection(
            this.state,
            'network-chart',
            'network-no-data',
            'network-warning-icon'
        );
    }

    /**
     * Initialize dashboard
     */
    async init() {
        try {
            // Initialize Bootstrap tooltips
            this.initTooltips();
            
            // Small delay to ensure session cookie is set after redirect
            await new Promise(resolve => setTimeout(resolve, 100));
            
            // Fetch status first
            await this.loadStatus();
            
            // Load initial metrics
            await this.loadMetrics(this.state.selectedRange);
        } catch (error) {
            console.error('Error initializing dashboard:', error);
            this.alertBanner.showDanger('Wystąpił błąd podczas ładowania dashboardu. Spróbuj odświeżyć stronę.');
        }
    }

    /**
     * Initialize Bootstrap tooltips for warning icons
     */
    initTooltips() {
        // Check if Bootstrap is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                // Destroy existing tooltip if any
                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                // Create new tooltip
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    /**
     * Load system status
     */
    async loadStatus() {
        try {
            const response = await DashboardAPI.fetchStatus();
            if (response.success && response.data) {
                this.state.setStatusData(response.data);
                
                // Check if data is stale
                const isStale = !response.data.ssh_connected || response.data.last_collection_status !== 'success';
                this.state.setStale(isStale);

                // Show warning banner if SSH not connected
                if (!response.data.ssh_connected && response.data.last_collection) {
                    this.alertBanner.showWarning(
                        'Brak połączenia z serwerem.',
                        response.data.last_collection
                    );
                } else {
                    this.alertBanner.hide();
                }
            }
        } catch (error) {
            console.error('Error loading status:', error);
            // Don't show error banner for status errors, just log
        }
    }

    /**
     * Load metrics for given range
     * @param {string} range - Time range
     */
    async loadMetrics(range) {
        this.state.setLoading(true);
        this.stickyHeader.updateLoadingState(true);

        try {
            const response = await DashboardAPI.fetchMetrics(range);
            
            if (response.success && response.data) {
                this.state.setMetricsData(response.data);
                
                // Update all metric sections
                const lastUpdateTimestamp = this.state.statusData?.last_collection || null;
                this.cpuSection.update(
                    response.data,
                    range,
                    this.state.isStale,
                    lastUpdateTimestamp
                );
                this.ramSection.update(
                    response.data,
                    range,
                    this.state.isStale,
                    lastUpdateTimestamp
                );
                this.diskSection.update(
                    response.data,
                    range,
                    this.state.isStale,
                    lastUpdateTimestamp
                );
                this.ioSection.update(
                    response.data,
                    range,
                    this.state.isStale,
                    lastUpdateTimestamp
                );
                this.networkSection.update(
                    response.data,
                    range,
                    this.state.isStale,
                    lastUpdateTimestamp
                );
            } else {
                throw new Error('Invalid response format');
            }
        } catch (error) {
            console.error('Error loading metrics:', error);
            
            if (error.message.includes('Authentication required')) {
                // Already redirected
                return;
            }
            
            this.alertBanner.showDanger('Wystąpił błąd podczas ładowania danych. Spróbuj odświeżyć stronę.');
        } finally {
            this.state.setLoading(false);
            this.stickyHeader.updateLoadingState(false);
        }
    }

    /**
     * Handle range change
     * @param {string} range - New time range
     */
    async handleRangeChange(range) {
        await this.loadMetrics(range);
    }

    /**
     * Handle refresh
     */
    async handleRefresh() {
        await this.loadMetrics(this.state.selectedRange);
        await this.loadStatus();
    }

    /**
     * Handle logout
     */
    async handleLogout() {
        try {
            await DashboardAPI.logout();
        } catch (error) {
            console.error('Error logging out:', error);
            this.alertBanner.showDanger('Wystąpił błąd podczas wylogowywania. Spróbuj ponownie.');
        }
    }
}

// Initialize dashboard when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        const dashboard = new Dashboard();
        dashboard.init();
        window.dashboard = dashboard; // For debugging
    });
} else {
    const dashboard = new Dashboard();
    dashboard.init();
    window.dashboard = dashboard; // For debugging
}

export { Dashboard, DashboardState, DashboardAPI, DashboardUtils, StickyHeader, AlertBanner, CPUMetricSection, RAMMetricSection, DiskMetricSection, IOMetricSection, NetworkMetricSection };

