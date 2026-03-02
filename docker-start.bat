@echo off
echo ========================================
echo  AD Manager - Docker Setup
echo ========================================
echo.

REM Verificar se Docker está rodando
docker info >nul 2>&1
if errorlevel 1 (
    echo [ERRO] Docker nao esta rodando!
    echo Por favor, inicie o Docker Desktop e tente novamente.
    pause
    exit /b 1
)

echo [1/5] Copiando arquivo de configuracao...
if not exist .env.docker (
    echo [ERRO] Arquivo .env.docker nao encontrado!
    echo Criando .env.docker...
    copy .env.example .env.docker
)

if not exist .env (
    copy .env.docker .env
    echo Arquivo .env criado com configuracoes Docker
) else (
    echo Arquivo .env ja existe, mantendo configuracao atual
)

echo.
echo [2/5] Parando containers existentes...
docker-compose down

echo.
echo [3/5] Construindo imagens Docker...
docker-compose build

echo.
echo [4/5] Iniciando containers...
docker-compose up -d

echo.
echo [5/5] Aguardando servicos iniciarem...
timeout /t 10 /nobreak >nul

echo.
echo ========================================
echo  Servicos Iniciados!
echo ========================================
echo.
echo Aplicacao:  http://localhost:8080
echo phpMyAdmin: http://localhost:8081
echo.
echo Usuario padrao: admin
echo Senha padrao:   admin123
echo.
echo Para ver logs:    docker-compose logs -f web
echo Para parar:       docker-compose down
echo Para reiniciar:   docker-compose restart
echo.
echo Aguarde alguns segundos para o banco inicializar...
echo.
pause

REM Abrir navegador
start http://localhost:8080
