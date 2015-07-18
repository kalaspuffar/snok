test:
	./composer.phar dump-autoload
	./vendor/phpunit/phpunit/phpunit --bootstrap ./vendor/autoload.php ./tests
