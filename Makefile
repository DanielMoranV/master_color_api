# Makefile para Master Color API Docker

.PHONY: help build up down restart logs shell test clean backup restore

# Variables
DOCKER_COMPOSE = docker-compose
APP_SERVICE = app
DB_SERVICE = db

# Ayuda por defecto
help: ## Mostrar ayuda
	@echo "Master Color API - Comandos Docker"
	@echo "=================================="
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Comandos de construcción y ejecución
build: ## Construir imágenes Docker
	$(DOCKER_COMPOSE) build --no-cache

up: ## Iniciar todos los servicios
	$(DOCKER_COMPOSE) up -d

down: ## Parar todos los servicios
	$(DOCKER_COMPOSE) down

restart: ## Reiniciar todos los servicios
	$(DOCKER_COMPOSE) restart

# Comandos de desarrollo
dev: ## Iniciar entorno de desarrollo
	cp .env.docker .env
	$(DOCKER_COMPOSE) up --build -d
	@echo "🚀 Entorno de desarrollo iniciado en http://localhost:8000"
	@echo "📊 PhpMyAdmin disponible en http://localhost:8080"
	@echo "📧 MailHog disponible en http://localhost:8025"

fresh: ## Reconstruir todo desde cero
	$(DOCKER_COMPOSE) down -v
	$(DOCKER_COMPOSE) build --no-cache
	$(DOCKER_COMPOSE) up -d

# Comandos de logs y monitoreo
logs: ## Ver logs de todos los servicios
	$(DOCKER_COMPOSE) logs -f

logs-app: ## Ver logs de la aplicación
	$(DOCKER_COMPOSE) logs -f $(APP_SERVICE)

logs-db: ## Ver logs de la base de datos
	$(DOCKER_COMPOSE) logs -f $(DB_SERVICE)

status: ## Ver estado de los servicios
	$(DOCKER_COMPOSE) ps

# Comandos de acceso
shell: ## Acceder al shell del contenedor de la aplicación
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) bash

shell-db: ## Acceder al shell de MySQL
	$(DOCKER_COMPOSE) exec $(DB_SERVICE) mysql -u laravel -ppassword master_color_api

# Comandos de Laravel
migrate: ## Ejecutar migraciones
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan migrate

migrate-fresh: ## Reiniciar migraciones con seeders
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan migrate:fresh --seed

seed: ## Ejecutar seeders
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan db:seed

cache-clear: ## Limpiar cache de Laravel
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan cache:clear
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan config:clear
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan route:clear
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan view:clear

optimize: ## Optimizar Laravel para producción
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan config:cache
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan route:cache
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan view:cache

# Comandos de testing
test: ## Ejecutar tests
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan test

test-coverage: ## Ejecutar tests con cobertura
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan test --coverage

# Comandos de backup
backup: ## Crear backup de la base de datos
	@mkdir -p backups
	$(DOCKER_COMPOSE) exec $(DB_SERVICE) mysqldump -u laravel -ppassword master_color_api > backups/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "✅ Backup creado en backups/"

restore: ## Restaurar backup (usar: make restore FILE=backup.sql)
	@if [ -z "$(FILE)" ]; then echo "❌ Especifica el archivo: make restore FILE=backup.sql"; exit 1; fi
	$(DOCKER_COMPOSE) exec -T $(DB_SERVICE) mysql -u laravel -ppassword master_color_api < $(FILE)
	@echo "✅ Backup restaurado"

# Comandos de limpieza
clean: ## Limpiar contenedores, imágenes y volúmenes no utilizados
	docker system prune -f
	docker volume prune -f

clean-all: ## Limpiar todo incluyendo volúmenes de la aplicación
	$(DOCKER_COMPOSE) down -v
	docker system prune -af
	docker volume prune -f

# Comandos de utilidad
install: ## Instalar dependencias
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) composer install
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) npm install

update: ## Actualizar dependencias
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) composer update
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) npm update

artisan: ## Ejecutar comando artisan personalizado (usar: make artisan CMD="queue:work")
	@if [ -z "$(CMD)" ]; then echo "❌ Especifica el comando: make artisan CMD='migrate'"; exit 1; fi
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan $(CMD)

# Comandos de producción
prod-build: ## Construir para producción
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml build

prod-up: ## Iniciar en modo producción
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml up -d

# Comandos de información
info: ## Mostrar información del sistema
	@echo "🐳 Docker Information"
	@echo "===================="
	@docker --version
	@docker-compose --version
	@echo ""
	@echo "📊 Container Status"
	@echo "=================="
	@$(DOCKER_COMPOSE) ps
	@echo ""
	@echo "💾 Volume Usage"
	@echo "==============="
	@docker volume ls | grep master-color

health: ## Verificar salud de los servicios
	@echo "🏥 Health Check"
	@echo "==============="
	@curl -s http://localhost:8000/health || echo "❌ API not responding"
	@echo ""
	@$(DOCKER_COMPOSE) exec $(DB_SERVICE) mysqladmin -u laravel -ppassword ping || echo "❌ Database not responding"