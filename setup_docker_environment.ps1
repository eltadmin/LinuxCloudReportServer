# Setup script for Docker environment (Windows PowerShell)
Write-Host "Setting up Docker environment for Linux Cloud Report Server..." -ForegroundColor Green

# Create necessary directories
Write-Host "Creating required directories..." -ForegroundColor Cyan
New-Item -ItemType Directory -Path "logs" -Force | Out-Null
New-Item -ItemType Directory -Path "uploads" -Force | Out-Null
New-Item -ItemType Directory -Path "static" -Force | Out-Null
New-Item -ItemType Directory -Path "templates" -Force | Out-Null

# Check if requirements.txt exists
if (-not (Test-Path "requirements.txt")) {
    Write-Host "Creating requirements.txt..." -ForegroundColor Cyan
    @"
fastapi>=0.68.0
uvicorn>=0.15.0
python-multipart>=0.0.5
aiofiles>=0.7.0
python-dotenv>=0.19.0
asyncio>=3.4.3
aiohttp>=3.8.1
mysqlclient>=2.0.3
SQLAlchemy>=1.4.23
pydantic>=1.8.2
cryptography>=3.4.7
PyYAML>=5.4.1
aiomysql>=0.1.1
pycryptodome>=3.15.0
psycopg2-binary>=2.9.1
jinja2>=3.0.1
"@ | Out-File -FilePath "requirements.txt" -Encoding utf8
    Write-Host "requirements.txt created." -ForegroundColor Green
} else {
    Write-Host "requirements.txt already exists." -ForegroundColor Yellow
}

# Check if config directory exists
if (-not (Test-Path "config")) {
    Write-Host "Creating config directory and sample configuration..." -ForegroundColor Cyan
    New-Item -ItemType Directory -Path "config" -Force | Out-Null
    
    # Create a sample config file if it doesn't exist
    if (-not (Test-Path "config/eboCloudReportServer.ini")) {
        @"
[server]
host = 0.0.0.0
port = 8000
debug = true

[database]
host = db
port = 5432
user = report
password = reportpass
name = reports

[logging]
level = DEBUG
file = logs/server.log
"@ | Out-File -FilePath "config/eboCloudReportServer.ini" -Encoding utf8
        Write-Host "Sample config created." -ForegroundColor Green
    }
} else {
    Write-Host "Config directory exists." -ForegroundColor Yellow
}

# Fix Go module dependencies
Write-Host "Checking and fixing Go module dependencies..." -ForegroundColor Cyan
try {
    $goVersion = & go version
    Write-Host "Go is installed: $goVersion" -ForegroundColor Green
    Write-Host "Updating go.sum..." -ForegroundColor Cyan
    & go mod tidy
    Write-Host "Go modules updated." -ForegroundColor Green
} catch {
    Write-Host "Go is not installed locally. The go.sum will be updated during Docker build." -ForegroundColor Yellow
}

# Check Docker and Docker Compose installation
Write-Host "Checking Docker installation..." -ForegroundColor Cyan
try {
    $dockerVersion = docker --version
    Write-Host "Docker is installed: $dockerVersion" -ForegroundColor Green
} catch {
    Write-Host "Docker not found. Please install Docker Desktop for Windows first." -ForegroundColor Red
    exit 1
}

try {
    $composeVersion = docker-compose --version
    Write-Host "Docker Compose is installed: $composeVersion" -ForegroundColor Green
} catch {
    Write-Host "Docker Compose not found. Please make sure it's installed with Docker Desktop." -ForegroundColor Red
    exit 1
}

# Final steps
Write-Host "`nEnvironment setup complete. You can now build and run Docker containers:" -ForegroundColor Green
Write-Host "  docker-compose build --no-cache" -ForegroundColor White
Write-Host "  docker-compose up -d" -ForegroundColor White
Write-Host "Done!" -ForegroundColor Green 