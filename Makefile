# make install
install:
	@docker run --rm -it -v$(PWD):/app composer install --ignore-platform-req=ext-xdebug

# unit tests
phpunit:
		@docker run --rm -it -v$(PWD):/app --workdir=/app php:8.3.0RC4-zts-alpine3.18 vendor/bin/phpunit

# phpstan
phpstan:
	@docker run --rm -it -v$(PWD):/app --workdir=/app php:8.3.0RC4-zts-alpine3.18 vendor/bin/phpstan analyse -l max -c phpstan.neon src --ansi

.PHONY: install phpunit phpstan
