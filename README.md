## Install Instructions
    curl -sS https://getcomposer.org/installer | php
    php composer.phar create-project --keep-vcs -s dev asherkin/throttle
    cd throttle
    cp app/config.base.php app/config.php
    vim app/config.php
    php app/console.php migrations:migrate
    chmod -R a+w logs cache dumps symbols/public

## Update Instructions
    cd throttle
    git pull
    php ../composer.phar install
    php app/console.php migrations:migrate
    rm -rf cache/*

## Virtual Host Configuration
    <VirtualHost *:80>
        ServerName throttle.example.com
        DocumentRoot "/path/to/throttle/web"

        <Location />
            Options -MultiViews

            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule ^ index.php [QSA,L]
        </Location>
    </VirtualHost>

## Cron
    * * * * * root /var/www/throttle/app/console.php crash:clean > /dev/null; /var/www/throttle/app/console.php crash:process -l 250 -u > /dev/null
    0 * * * * root /var/www/throttle/app/console.php user:update > /dev/null
    15 */3 * * * root /var/www/throttle/app/console.php symbols:update > /dev/null
    30 0 * * * root /var/www/throttle/app/console.php symbols:download > /dev/null
    30 0 * * * root /var/www/throttle/app/console.php symbols:mozilla:download > /dev/null
