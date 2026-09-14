SELECT 'CREATE DATABASE dz_signage_testing OWNER dz_signage'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'dz_signage_testing')\gexec
