# Makefile para el Búnker de UNIT3D_Docker (NOBS)
.PHONY: help install up stop restart status backup health logs clean

help:
	@echo "--- 🛡️ UNIT3D BÚNKER CONTROL ---"
	@echo "make install  - Instalación fresca (Carpetas, Permisos, Build)"
	@echo "make up       - Levantar contenedores (Producción)"
	@echo "make stop     - Parar contenedores"
	@echo "make restart  - Reiniciar app y web"
	@echo "make status   - Ver estado de los contenedores"
	@echo "make backup   - Ejecutar backup quirúrgico (Búnker manual)"
	@echo "make health   - Verificar salud HTTP (Puerto 8008)"
	@echo "make logs     - Ver logs de la app en vivo"
	@echo "make clean    - Limpiar caché de Laravel (Producción)"
	@echo "make meilisearch - Configurar Meilisearch (Producción)"
	@echo "make meilisearch-fix - Reparar Meilisearch de cero (Producción)"

install:
	@echo "🎬 Inicializando el búnker..."
	mkdir -p storage/framework/{cache/data,sessions,views} storage/app/public storage/logs bootstrap/cache backups
	chmod -R 775 storage bootstrap/cache
	docker compose build
	docker compose up -d
	@echo "✅ Búnker listo. Usa 'make up' para levantar."

up:
	docker compose up -d

stop:
	docker compose stop

restart:
	docker compose restart app web

status:
	docker compose ps

backup:
	sudo ./backup.sh

health:
	./health_check.sh

logs:
	docker compose logs -f app

clean:
	docker exec unit3d-app php artisan config:clear
	docker exec unit3d-app php artisan route:clear
	docker exec unit3d-app php artisan view:clear
	docker exec unit3d-app php artisan config:cache
	docker exec unit3d-app php artisan route:cache
	docker exec unit3d-app php artisan view:cache

meilisearch:
	@echo "⚙️  Configurando Meilisearch (Producción)..."
	bash ./NO_BS_meilisearch.sh docker

meilisearch-fix:
	@echo "🔧 Reparando Meilisearch de cero (Producción)..."
	docker compose stop meilisearch
	sudo rm -rf .docker/data/meilisearch
	docker compose up -d meilisearch
	@echo "⏳ Esperando a que Meilisearch inicie..."
	sleep 5
	bash ./NO_BS_meilisearch.sh docker
	docker exec unit3d-app php artisan view:cache
