from setuptools import setup, find_packages

setup(
    name="report_server",
    version="1.0.0",
    packages=find_packages(),
    install_requires=[
        'fastapi>=0.68.0',
        'uvicorn>=0.15.0',
        'python-multipart>=0.0.5',
        'aiofiles>=0.7.0',
        'python-dotenv>=0.19.0',
        'asyncio>=3.4.3',
        'aiohttp>=3.8.1',
        'mysqlclient>=2.0.3',
        'SQLAlchemy>=1.4.23',
        'pydantic>=1.8.2',
        'cryptography>=3.4.7',
        'PyYAML>=5.4.1',
        'aiomysql>=0.1.1'
    ],
) 