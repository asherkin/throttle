## Install Instructions
    composer.phar create-project -s dev asherkin/throttle
    cd throttle
    mv app/config.dist.php app/config.php
    vim app/config.php
    ./app/console.php migrations:migrate
    chmod -R a+w logs cache dumps symbols/public

## Virtual Host Configuration
    <VirtualHost *:80>
        ServerName throttle.limetech.org:80
        DocumentRoot "/var/www/throttle/web"

        <Directory /var/www/throttle/web>
            Options -MultiViews

            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule ^ index.php [QSA,L]
        </Directory>
    </VirtualHost>

