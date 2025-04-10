'use strict';

module.exports = {
  up: async (queryInterface, Sequelize) => {
    await queryInterface.createTable('clients', {
      id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
      },
      clientId: {
        type: Sequelize.STRING,
        allowNull: false,
        unique: true
      },
      clientName: {
        type: Sequelize.STRING,
        allowNull: true
      },
      clientHost: {
        type: Sequelize.STRING,
        allowNull: true
      },
      appType: {
        type: Sequelize.STRING,
        allowNull: true
      },
      appVersion: {
        type: Sequelize.STRING,
        allowNull: true
      },
      dbType: {
        type: Sequelize.STRING,
        allowNull: true
      },
      lastConnected: {
        type: Sequelize.DATE,
        defaultValue: Sequelize.NOW
      },
      lastActivity: {
        type: Sequelize.DATE,
        defaultValue: Sequelize.NOW
      },
      isOnline: {
        type: Sequelize.BOOLEAN,
        defaultValue: false
      },
      connectionCount: {
        type: Sequelize.INTEGER,
        defaultValue: 0
      },
      createdAt: {
        type: Sequelize.DATE,
        allowNull: false
      },
      updatedAt: {
        type: Sequelize.DATE,
        allowNull: false
      }
    });
  },

  down: async (queryInterface, Sequelize) => {
    await queryInterface.dropTable('clients');
  }
}; 