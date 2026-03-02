@echo off
echo ========================================
echo  AD Manager - Parando Docker
echo ========================================
echo.

docker-compose down

echo.
echo Containers parados!
echo.
echo Para iniciar novamente: docker-start.bat
echo Para limpar tudo:      docker-compose down -v
echo.
pause
