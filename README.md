## Install Instructions
    composer.phar create-project -s dev asherkin/throttle
    cd throttle
    mv app/config.dist.php app/config.php
    vim app/config.php
    ./app/console.php migrations:migrate
    chmod -R a+w logs cache dumps symbols/public

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

