# Makefile for Laravel Docker Setup

# 環境変数
DB_HOST ?= mysql
DB_ROOT_PASSWORD ?= root

setup:
	@echo "=== Laravel Setup Start ==="

	# ─── Composer Install ───
	composer install --no-interaction --prefer-dist

	# ─── NPM Install (if needed) ───
	@if [ ! -d node_modules ]; then \
		echo "Installing npm dependencies..."; \
		npm install --loglevel=error; \
	fi

	# ─── .env & APP_KEY ───
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		php artisan key:generate --ansi; \
	fi

	# ─── .env.testing ───
	@if [ ! -f .env.testing ]; then \
		cp .env.testing.example .env.testing 2>/dev/null || cp .env .env.testing; \
		php artisan key:generate --env=testing --ansi; \
	fi

	# ─── .env.dusk.local ───
	@if [ ! -f .env.dusk.local ]; then \
		cp .env.dusk.local.example .env.dusk.local 2>/dev/null || cp .env .env.dusk.local; \
		php artisan key:generate --env=dusk.local --ansi; \
	fi

	# ─── wait for MySQL ───
	@echo "Waiting for MySQL at $(DB_HOST)..."
	@until mysqladmin ping -h$(DB_HOST) -uroot -p$(DB_ROOT_PASSWORD) --silent; do \
		sleep 2; \
	done

	# ─── migrate & seed (production) ───
	php artisan migrate --force
	php artisan db:seed --force

	# ─── migrate & seed (testing) ───
	mysql -h$(DB_HOST) -uroot -p$(DB_ROOT_PASSWORD) \
		-e "CREATE DATABASE IF NOT EXISTS \`demo_test\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
	php artisan migrate --env=testing --force
	php artisan db:seed --env=testing --force
	php artisan config:clear

	# ─── storage link & permissions ───
	-php artisan storage:link
	chown -R www-data:www-data storage bootstrap/cache

	@echo "=== Laravel Setup Complete ==="
