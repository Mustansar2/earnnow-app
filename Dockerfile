FROM php:8.2-cli

# Install SQLite extensions (already built into php:8.2-cli)
WORKDIR /app

# Copy project files
COPY . /app

# Ensure writable permissions for SQLite database and uploads
RUN mkdir -p /app/uploads/deposits && chmod -R 777 /app/uploads && chmod -R 777 /app

# Expose Railway dynamic port
EXPOSE 8080

# Start PHP built-in server bound to Railway dynamic PORT
CMD php -S 0.0.0.0:${PORT:-8080}
