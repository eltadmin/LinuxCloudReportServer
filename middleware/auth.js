const jwt = require('jsonwebtoken');
const { User } = require('../models/db');

/**
 * Authenticate the user using JWT token
 */
const authenticate = async (req, res, next) => {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({
        success: false,
        error: {
          code: 401,
          message: 'Authentication failed. Token not provided.'
        }
      });
    }
    
    const token = authHeader.split(' ')[1];
    
    // Verify token
    const decoded = jwt.verify(token, process.env.JWT_SECRET || 'your_default_secret');
    
    // Find user
    const user = await User.findByPk(decoded.id);
    
    if (!user || !user.active) {
      return res.status(401).json({
        success: false,
        error: {
          code: 401,
          message: 'Authentication failed. User not found or inactive.'
        }
      });
    }
    
    // Add user to request
    req.user = user;
    next();
    
  } catch (error) {
    if (error.name === 'TokenExpiredError') {
      return res.status(401).json({
        success: false,
        error: {
          code: 401,
          message: 'Authentication failed. Token expired.'
        }
      });
    }
    
    return res.status(401).json({
      success: false,
      error: {
        code: 401,
        message: 'Authentication failed. Invalid token.'
      }
    });
  }
};

/**
 * Require admin role for access
 */
const requireAdmin = (req, res, next) => {
  if (!req.user || req.user.role !== 'admin') {
    return res.status(403).json({
      success: false,
      error: {
        code: 403,
        message: 'Access denied. Admin role required.'
      }
    });
  }
  
  next();
};

module.exports = {
  authenticate,
  requireAdmin
}; 