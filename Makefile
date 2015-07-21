build:
	./composer.phar dump-autoload
	./vendor/phpunit/phpunit/phpunit --bootstrap ./vendor/autoload.php ./test

generate:
	 php -f scripts/generate.php
