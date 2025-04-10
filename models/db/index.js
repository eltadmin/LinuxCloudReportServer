const User = require('./User');
const Client = require('./Client');
const Report = require('./Report');
const { sequelize } = require('../../config/database');

// Define associations
User.hasMany(Report, { foreignKey: 'requestedBy', as: 'reports' });
Report.belongsTo(User, { foreignKey: 'requestedBy', as: 'requester' });

// Export models
const db = {
  sequelize,
  User,
  Client,
  Report
};

module.exports = db; 