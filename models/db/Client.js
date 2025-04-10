const { DataTypes } = require('sequelize');
const { sequelize } = require('../../config/database');

const Client = sequelize.define('Client', {
  id: {
    type: DataTypes.INTEGER,
    primaryKey: true,
    autoIncrement: true
  },
  clientId: {
    type: DataTypes.STRING,
    allowNull: false,
    unique: true,
    validate: {
      notEmpty: true
    }
  },
  clientName: {
    type: DataTypes.STRING,
    allowNull: true
  },
  clientHost: {
    type: DataTypes.STRING,
    allowNull: true
  },
  appType: {
    type: DataTypes.STRING,
    allowNull: true
  },
  appVersion: {
    type: DataTypes.STRING,
    allowNull: true
  },
  dbType: {
    type: DataTypes.STRING,
    allowNull: true
  },
  lastConnected: {
    type: DataTypes.DATE,
    defaultValue: DataTypes.NOW
  },
  lastActivity: {
    type: DataTypes.DATE,
    defaultValue: DataTypes.NOW
  },
  isOnline: {
    type: DataTypes.BOOLEAN,
    defaultValue: false
  },
  connectionCount: {
    type: DataTypes.INTEGER,
    defaultValue: 0
  }
}, {
  tableName: 'clients',
  timestamps: true
});

module.exports = Client; 