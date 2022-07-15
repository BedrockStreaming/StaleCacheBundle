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
all: install quality test tool-ci-all

.PHONY: ci
ci: install quality test tool-ci-all

.PHONY: install
install: clean-vendor composer-install

.PHONY: quality
quality: cs-ci phpstan

.PHONY: quality-fix
quality-fix: cs-fix

.PHONY: test
test: cs rector phpstan phpunit

# Coding Style

.PHONY: cs
cs:
	$(call printSection,Check coding style)
	${BIN_DIR}/php-cs-fixer fix --dry-run --stop-on-violation --diff

.PHONY: cs-fix
cs-fix:
	${BIN_DIR}/php-cs-fixer fix

.PHONY: cs-ci
cs-ci:
	${BIN_DIR}/php-cs-fixer fix --ansi --dry-run --using-cache=no --verbose

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

# CI TOOLS
${CI_DIR}:
	git clone --depth=1 https://github.m6web.fr/m6web/tool-php-ci.git ${CI_DIR}

tool-ci-%: ${CI_DIR}
	make -e SOURCE_DIR=${SOURCE_DIR} -e CI_DIR=${CI_DIR} -f ${CI_DIR}/Makefile $@

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
