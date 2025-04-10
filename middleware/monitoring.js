const promClient = require('prom-client');

// Create a Registry to register metrics
const register = new promClient.Registry();

// Add default metrics
promClient.collectDefaultMetrics({ register });

// Custom metrics
const httpRequestDurationMicroseconds = new promClient.Histogram({
  name: 'http_request_duration_seconds',
  help: 'Duration of HTTP requests in seconds',
  labelNames: ['method', 'route', 'status_code'],
  buckets: [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 2, 5]
});

const httpRequestCounter = new promClient.Counter({
  name: 'http_requests_total',
  help: 'Total number of HTTP requests',
  labelNames: ['method', 'route', 'status_code']
});

const tcpConnectionCounter = new promClient.Counter({
  name: 'tcp_connections_total',
  help: 'Total number of TCP connections'
});

const tcpConnectionGauge = new promClient.Gauge({
  name: 'tcp_connections_active',
  help: 'Number of active TCP connections'
});

const reportGenerationCounter = new promClient.Counter({
  name: 'report_generation_total',
  help: 'Total number of report generation attempts',
  labelNames: ['status']
});

const reportGenerationDuration = new promClient.Histogram({
  name: 'report_generation_duration_seconds',
  help: 'Duration of report generation in seconds',
  labelNames: ['status'],
  buckets: [0.1, 0.5, 1, 2, 5, 10, 30, 60, 120]
});

// Register custom metrics
register.registerMetric(httpRequestDurationMicroseconds);
register.registerMetric(httpRequestCounter);
register.registerMetric(tcpConnectionCounter);
register.registerMetric(tcpConnectionGauge);
register.registerMetric(reportGenerationCounter);
register.registerMetric(reportGenerationDuration);

/**
 * HTTP request monitoring middleware
 */
const monitorRequest = (req, res, next) => {
  // Skip monitoring endpoint itself
  if (req.path === '/metrics') {
    return next();
  }
  
  const end = httpRequestDurationMicroseconds.startTimer();
  
  // Record the response
  const originalSend = res.send;
  res.send = function() {
    originalSend.apply(res, arguments);
    
    const route = req.route ? req.route.path : req.path;
    const method = req.method;
    const statusCode = res.statusCode;
    
    // Increase counter
    httpRequestCounter.inc({
      method,
      route,
      status_code: statusCode
    });
    
    // Record duration
    end({
      method,
      route,
      status_code: statusCode
    });
  };
  
  next();
};

/**
 * Metrics endpoint to expose Prometheus metrics
 */
const metricsEndpoint = async (req, res) => {
  res.set('Content-Type', register.contentType);
  res.end(await register.metrics());
};

module.exports = {
  register,
  monitorRequest,
  metricsEndpoint,
  metrics: {
    httpRequestDurationMicroseconds,
    httpRequestCounter,
    tcpConnectionCounter,
    tcpConnectionGauge,
    reportGenerationCounter,
    reportGenerationDuration
  }
}; 