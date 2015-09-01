.PHONY: test generate

test:
	./composer.phar dump-autoload
#	./vendor/phpunit/phpunit/phpunit --bootstrap ./vendor/autoload.php ./test
	./vendor/phpunit/phpunit/phpunit -c phpunit.xml

generate:
	 php -f scripts/generate.php
