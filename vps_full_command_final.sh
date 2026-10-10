#!/bin/bash
# FULL VPS SEQUENCE - run inside the actual site directory (e.g., /var/www/html/ or /var/www/nxadmin/pharmacy_pos)

# Pull from GitHub (includes archive, batch fix, discounts, login polish)
git pull origin main

# Database archive migration (credentials from config/database.php: pharma / ALLYSA)
mysql -u pharma -p'ALLYSA' pharmacy_pos < database/archive_migration.sql

# Archive folder permissions
chmod 755 app/dashboard/archive/
chmod 755 app/dashboard/archive/ajax/
chmod 755 assets/js/archive.js

# Reload web server (apache or nginx)
service apache2 reload || service nginx reload || echo "RELOAD MANUALLY - check which server runs"
