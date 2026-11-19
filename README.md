# Wex Technology API (`wex-api`)

REST API for Wex Technology, a laptop/phone/accessories retail shop located in Omdurman, Sudan.

## Quick Start (Local Development)

```bash
# 1. Clone & Enter project
cd ~/projects/wex-api

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env

# 4. Run database migrations
php spark migrate

# 5. Seed initial data
php spark db:seed DatabaseSeeder

# 6. Start the API server
php spark serve --port 8081
```

The API will be accessible at: `http://localhost:8081/api/v1`

## Documentation
- [API Endpoints Reference](docs/API.md)
- [Architecture & Design Decisions](docs/DECISIONS.md)
- [Database Schema & Relations](docs/DATABASE.md)
- [Deployment & Shared Hosting Guide](docs/DEPLOYMENT.md)
- [Curl Usage Examples](docs/curl-examples.md)
