const express = require('express');
const jwt = require('jsonwebtoken');
const { User } = require('../models/db');
const { authenticate, requireAdmin } = require('../middleware/auth');

/**
 * Create authentication router
 * @returns {express.Router} - Express router
 */
module.exports = function() {
  const router = express.Router();
  
  /**
   * Login endpoint
   */
  router.post('/login', async (req, res) => {
    try {
      const { username, password } = req.body;
      
      // Validate parameters
      if (!username || !password) {
        return res.status(400).json({
          success: false,
          error: {
            message: 'Missing username or password'
          }
        });
      }
      
      // Find user
      const user = await User.findOne({ where: { username } });
      
      if (!user || !user.active) {
        return res.status(401).json({
          success: false,
          error: {
            message: 'Invalid username or password'
          }
        });
      }
      
      // Check password
      const isPasswordValid = await user.isValidPassword(password);
      
      if (!isPasswordValid) {
        return res.status(401).json({
          success: false,
          error: {
            message: 'Invalid username or password'
          }
        });
      }
      
      // Generate token
      const token = jwt.sign(
        { id: user.id, username: user.username, role: user.role },
        process.env.JWT_SECRET || 'your_default_secret',
        { expiresIn: process.env.JWT_EXPIRES_IN || '1d' }
      );
      
      // Update last login
      user.lastLogin = new Date();
      await user.save();
      
      // Return token
      res.status(200).json({
        success: true,
        token,
        user: {
          id: user.id,
          username: user.username,
          email: user.email,
          role: user.role
        }
      });
      
    } catch (error) {
      console.error('Login error:', error);
      res.status(500).json({
        success: false,
        error: {
          message: 'Internal Server Error'
        }
      });
    }
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