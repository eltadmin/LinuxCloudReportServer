# Use an official Python runtime as a parent image
FROM python:3.11-slim-bookworm

# Set the working directory in the container
WORKDIR /app

# Copy the configuration file and the server script into the container
COPY config.ini server.py ./

# Make port 8016 available to the world outside this container (from config.ini)
EXPOSE 8016

# Define environment variable (optional, could be used for configuration)
ENV NAME World

# Run server.py when the container launches
CMD ["python", "./server.py"] 