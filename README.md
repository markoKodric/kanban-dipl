# Installation instructions

## Minimum System Requirements

* PHP version 7.2 or higher
* PDO PHP Extension (and relevant driver for the database you want to connect to)
* cURL PHP Extension
* OpenSSL PHP Extension
* Mbstring PHP Extension
* ZipArchive PHP Extension
* GD PHP Extension
* SimpleXML PHP Extension

Some OS distributions may require you to manually install some of the required PHP extensions.

When using Ubuntu, the following command can be run to install all required extensions:

```bash
sudo apt-get update &&
sudo apt-get install php php-ctype php-curl php-xml php-fileinfo php-gd php-json php-mbstring php-mysql php-sqlite3 php-zip
```

--

## Application setup

1. Create new database on your server.
2. Clone this repository to your local device.
    ```bash
    git clone https://github.com/markoKodric/kanban-dipl.git
    ```
3. Copy .env.example file to .env file and edit environment variables.
    - Set APP_URL to your hosting url
    - Set DB_HOST to url of your hosting environment
    - Set DB_DATABASE to database name that you created in the first step
    - Set DB_USERNAME and DB_PASSWORD to your database username and password

4. Install dependencies by running command (you must have composer install on your system)
    ```bash
    composer install
    ```
5. Run DB migrations
    ```bash
    php artisan october:up
    ```
    ```bash
    php artisan plugin:refresh Kanban.Custom
    ```
    
6. Setup NodeJS server for real time updates inside application. Instructions can be found on <https://github.com/markoKodric/kanban-dipl-node>.
   After installation change environment variable {SOCKET_IO_URL} in your .env file.
    
## Information

- Admin panel is accessible on url {APP_URL}/admin (user:admin, password:admin)
- Application is accessible on url {APP_URL}
