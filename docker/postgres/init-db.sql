-- The 'app' database is created by the POSTGRES_DB environment variable; the
-- test suite needs a second one. This script runs once, as the postgres
-- superuser, on first initialisation of the data volume.
CREATE DATABASE testing;
GRANT ALL PRIVILEGES ON DATABASE testing TO app;
