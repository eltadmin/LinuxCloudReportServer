const request = require('supertest');
const { app } = require('../server');
const { exec } = require('child_process');
const path = require('path');

describe('Linux Cloud Report Server', () => {
  describe('Health Check', () => {
    it('should return 200 and status ok', async () => {
      const response = await request(app).get('/health');
      expect(response.status).toBe(200);
      expect(response.body).toEqual({ status: 'ok' });
    });
  });

  describe('API Routes', () => {
    it('should return 200 for root endpoint', async () => {
      const response = await request(app).get('/');
      expect(response.status).toBe(200);
    });

    it('should return 200 for API routes', async () => {
      const response = await request(app).get('/api');
      expect(response.status).toBe(200);
    });
  });

  describe('Server Initialization', () => {
    it('should start HTTP server on configured port', async () => {
      const response = await request(app).get('/health');
      expect(response.status).toBe(200);
    });
  });

  describe('GET /api/reports', () => {
    it('should return 200 and list of reports', async () => {
      const response = await request(app).get('/api/reports');
      expect(response.status).toBe(200);
      expect(Array.isArray(response.body)).toBe(true);
    });
  });

  describe('POST /api/reports', () => {
    it('should create a new report and return 201', async () => {
      const reportData = {
        title: 'Test Report',
        content: 'This is a test report',
        type: 'test'
      };

      const response = await request(app)
        .post('/api/reports')
        .send(reportData);

      expect(response.status).toBe(201);
      expect(response.body).toHaveProperty('id');
      expect(response.body.title).toBe(reportData.title);
    });

    it('should return 400 for invalid report data', async () => {
      const invalidData = {
        title: '', // Invalid empty title
        content: 'Test content'
      };

      const response = await request(app)
        .post('/api/reports')
        .send(invalidData);

      expect(response.status).toBe(400);
    });
  });

  describe('GET /api/reports/:id', () => {
    it('should return 200 and report details for valid ID', async () => {
      // First create a report
      const reportData = {
        title: 'Test Report for GET',
        content: 'Test content',
        type: 'test'
      };

      const createResponse = await request(app)
        .post('/api/reports')
        .send(reportData);

      const reportId = createResponse.body.id;

      // Then get the report
      const response = await request(app).get(`/api/reports/${reportId}`);
      expect(response.status).toBe(200);
      expect(response.body.id).toBe(reportId);
      expect(response.body.title).toBe(reportData.title);
    });

    it('should return 404 for non-existent report ID', async () => {
      const response = await request(app).get('/api/reports/nonexistent-id');
      expect(response.status).toBe(404);
    });
  });
}); 