const express = require('express');
const jwt = require('jsonwebtoken');
const { User } = require('../models/db');
const { authenticate, requireAdmin } = require('../middleware/auth');
const { HTTP_ERRORS } = require('../utils/constants');

/**
 * Create authentication router
 * @returns {express.Router} - Express router
 */
module.exports = function() {
  const router = express.Router();
  
  // Simple authentication middleware
  const simpleAuthenticate = (req, res, next) => {
    const { username, password } = req.body;
    
    // Simple hardcoded authentication to match the original server
    if (username === 'user' && password === 'pass$123') {
      next();
    } else {
      res.status(401).json({
        success: false,
        error: {
          code: HTTP_ERRORS.LOGIN_INCORRECT,
          message: 'Invalid username or password'
        }
      });
    }
  };
  
  /**
   * Login endpoint
   */
  router.post('/login', (req, res) => {
    const { username, password } = req.body;
    
    // Validate parameters
    if (!username || !password) {
      return res.status(400).json({
        success: false,
        error: {
          code: HTTP_ERRORS.MISSING_LOGIN_INFO,
          message: 'Missing username or password'
        }
      });
    }
    
    // Check login
    if (username === 'user' && password === 'pass$123') {
      res.status(200).json({
        success: true,
        token: 'sample-token-' + Date.now()
      });
    } else {
      res.status(401).json({
        success: false,
        error: {
          code: HTTP_ERRORS.LOGIN_INCORRECT,
          message: 'Invalid username or password'
        }
      });
    }
  });
  
  /**
   * Health check endpoint
   */
  router.get('/health', (req, res) => {
    res.status(200).json({
      status: 'ok',
      version: '1.0.0'
    });
  });
  
  /**
   * Get current user info
   */
  router.get('/me', authenticate, (req, res) => {
    res.status(200).json({
      success: true,
      user: {
        id: req.user.id,
        username: req.user.username,
        email: req.user.email,
        role: req.user.role
      }
    });
  });
  
  /**
   * List all users (admin only)
   */
  router.get('/users', authenticate, requireAdmin, async (req, res) => {
    try {
      const users = await User.findAll({
        attributes: ['id', 'username', 'email', 'role', 'active', 'lastLogin', 'createdAt']
      });
      
      res.status(200).json({
        success: true,
        count: users.length,
        users
      });
      
    } catch (error) {
      console.error('List users error:', error);
      res.status(500).json({
        success: false,
        error: {
          message: 'Internal Server Error'
        }
      });
    }
  });
  
  /**
   * Create new user (admin only)
   */
  router.post('/users', authenticate, requireAdmin, async (req, res) => {
    try {
      const { username, email, password, role } = req.body;
      
      // Validate parameters
      if (!username || !email || !password) {
        return res.status(400).json({
          success: false,
          error: {
            message: 'Missing required fields'
          }
        });
      }
      
      // Check if user already exists
      const existingUser = await User.findOne({
        where: {
          [User.sequelize.Op.or]: [
            { username },
            { email }
          ]
        }
      });
      
      if (existingUser) {
        return res.status(409).json({
          success: false,
          error: {
            message: 'Username or email already exists'
          }
        });
      }
      
      // Create user
      const user = await User.create({
        username,
        email,
        password,
        role: role || 'user',
        active: true
      });
      
      res.status(201).json({
        success: true,
        user: {
          id: user.id,
          username: user.username,
          email: user.email,
          role: user.role,
          active: user.active
        }
      });
      
    } catch (error) {
      console.error('Create user error:', error);
      res.status(500).json({
        success: false,
        error: {
          message: 'Internal Server Error'
        }
      });
    }
  });
  
  return router;
}; 