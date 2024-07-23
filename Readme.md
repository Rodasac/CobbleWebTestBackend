# CobbleWebTestBackend - A simple backend with Symfony 5.4

# Requirements
- PHP 8.0
- Composer
- Symfony CLI
- Docker

# Installation

1. Clone the repository
```bash
git clone git@github.com:Rodasac/CobbleWebTestBackend.git
```

2. Install dependencies
```bash
composer install
```

3. Create the jwt keys
```bash
mkdir config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

4. Start the mailer and database containers
```bash
docker-compose up -d
```

5. Apply the migrations
```bash
php bin/console doctrine:migrations:migrate
```

6. Configure the cron jobs
```bash
crontab -e

# Add the following lines
0 0 * * 0 php /path/to/project/bin/console app:users:newsletter
```

Or you can run the command manually
```bash
php bin/console app:users:newsletter
```

7. Start the test server
```bash
symfony server:start
```
