@echo off
echo ========================================
echo   Reconstruindo Containers Docker
echo ========================================
echo.

echo Parando containers...
docker-compose down

echo.
echo Criando diretorio de sessoes...
if not exist "storage\sessions" mkdir storage\sessions

echo.
echo Reconstruindo imagens...
docker-compose build --no-cache

echo.
echo Iniciando containers...
docker-compose up -d

echo.
echo Aguardando inicializacao...
timeout /t 10 /nobreak

echo.
echo ========================================
echo   Containers reconstruidos!
echo ========================================
echo.
echo Acesse: http://localhost:8080
echo phpMyAdmin: http://localhost:8081
echo.
pause
