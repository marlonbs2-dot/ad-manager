@echo off
echo ========================================
echo  AD Manager - Logs Docker
echo ========================================
echo.
echo Pressione Ctrl+C para sair
echo.

docker-compose logs -f web
