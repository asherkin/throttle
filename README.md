## Install Instructions
    curl -sS https://getcomposer.org/installer | php
    php composer.phar create-project --keep-vcs -s dev asherkin/throttle
    cd throttle
    cp app/config.dist.php app/config.php
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
        ServerName throttle.example.com:80
        DocumentRoot "/path/to/throttle/web"

        <Directory /path/to/throttle/web>
            Options -MultiViews

            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule ^ index.php [QSA,L]
        </Directory>
    </VirtualHost>

