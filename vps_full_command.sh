#!/bin/bash
# FULL VPS DEPLOY SEQUENCE - /var/www/html/

cd /var/www/html/

# Pull from GitHub
git pull origin main

# Run archive database migration
mysql -u pharma -p'ALLYSA' pharmacy_pos < database/archive_migration.sql

# Set archive folder permissions
chmod 755 app/dashboard/archive/
chmod 755 app/dashboard/archive/ajax/
chmod 755 assets/js/archive.js

# Reload web server
service apache2 reload || service nginx reload || echo "RELOAD MANUALLY"

# Verify archive exists
ls -la app/dashboard/archive/index.php
