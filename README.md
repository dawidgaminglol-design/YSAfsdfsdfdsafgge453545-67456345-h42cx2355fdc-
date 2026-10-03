# Luckyware C&C Control Panel

PHP-based control panel for managing remote clients.

## Features
- Client management dashboard
- Real-time command execution
- Activity logging
- PostgreSQL database backend

## Deployment

This application is configured to run on Render.com with Neon PostgreSQL.

### Required Environment Variables:
- `PGHOST` - PostgreSQL host
- `PGDATABASE` - Database name
- `PGUSER` - Database user
- `PGPASSWORD` - Database password

### Default Login:
- Username: `admin`
- Password: `change_this_password_123`

**⚠️ Change the default password in production!**
