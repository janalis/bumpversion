.PHONY: test lint fmt analyse audit build smoke docs docs-dev verify

test:
	composer test
lint:
	composer lint
fmt:
	composer format
analyse:
	composer analyse
audit:
	composer audit
build:
	composer build
smoke: build
	composer smoke
docs:
	npm run build --prefix docs
docs-dev:
	npm run dev --prefix docs
verify:
	composer verify
	$(MAKE) smoke
	$(MAKE) docs
	npm audit --prefix docs
