SHELL=bash
SOURCE_DIR = $(shell pwd)
BIN_DIR = ${SOURCE_DIR}/bin
COMPOSER_BIN ?= composer2
CI_DIR = ${BIN_DIR}/tool-php-ci

define printSection
	@printf "\033[36m\n==================================================\n\033[0m"
	@printf "\033[36m $1 \033[0m"
	@printf "\033[36m\n==================================================\n\033[0m"
endef

.PHONY: all
all: install quality test

.PHONY: install
install: clean-vendor composer-install

.PHONY: quality
quality: rector cs phpstan

.PHONY: quality-fix
quality-fix: rector-fix cs-fix

.PHONY: test
test: phpunit

# Coding Style

.PHONY: cs
cs:
	$(call printSection,Check coding style)
	${BIN_DIR}/php-cs-fixer fix --dry-run --stop-on-violation --diff

.PHONY: cs-fix
cs-fix:
	${BIN_DIR}/php-cs-fixer fix

#COMPOSER

.PHONY: clean-vendor
clean-vendor:
	$(call printSection,CLEAN-VENDOR)
	rm -rf ${SOURCE_DIR}/composer.lock
	rm -rf ${SOURCE_DIR}/vendor
	rm -rf ${SOURCE_DIR}/bin/*

.PHONY: composer-install
composer-install:
	$(call printSection,COMPOSER INSTALL)
	${COMPOSER_BIN} update --no-interaction --ansi --no-progress

# TEST
.PHONY: phpunit
phpunit:
	$(call printSection,Test PHPUnit)
	${BIN_DIR}/phpunit

.PHONY: phpstan
phpstan:
	$(call printSection,Test PHPStan)
	${BIN_DIR}/phpstan.phar analyse

.PHONY: rector
rector:
	$(call printSection,Test Rector)
	${BIN_DIR}/rector process --dry-run

.PHONY: rector-fix
rector-fix:
	$(call printSection,Execute Rector)
	${BIN_DIR}/rector process
