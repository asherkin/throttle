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
    hg pull -u
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
    */5 * * * * root /path/to/throttle/app/console.php crash:clean > /dev/null; /path/to/throttle/app/console.php crash:process -l 50 -u > /dev/null
    0 * * * * root /path/to/throttle/app/console.php user:update > /dev/null
    10 0 * * * root /path/to/throttle/app/console.php symbols:download > /dev/null
