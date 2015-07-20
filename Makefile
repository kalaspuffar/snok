build:
	./composer.phar dump-autoload
	./vendor/phpunit/phpunit/phpunit --bootstrap ./vendor/autoload.php ./test
