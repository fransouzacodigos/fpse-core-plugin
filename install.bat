@echo off
REM #################################################################################
REM # FPSE Core Plugin - Automatic Installation Script (Windows)
REM #
REM # Use este script para instalar automaticamente o plugin FPSE Core no Windows.
REM # Funciona com ou sem Composer.
REM #
REM # Uso: install.bat
REM #      install.bat C:\xampp\htdocs\wordpress
REM #################################################################################

setlocal enabledelayedexpansion

echo ====================================================
echo FPSE Core Plugin - Instalacao Automatica (Windows)
echo ====================================================
echo.

REM Detectar o caminho do WordPress
if "%1"=="" (
    set WP_PATH=.
    echo [*] Usando diretorio atual como WordPress path
) else (
    set WP_PATH=%1
)

REM Verificar se é um WordPress válido
if not exist "%WP_PATH%\wp-config.php" (
    echo [ERRO] wp-config.php nao encontrado em %WP_PATH%
    echo Uso: install.bat C:\caminho\para\wordpress
    pause
    exit /b 1
)

set PLUGINS_PATH=%WP_PATH%\wp-content\plugins

echo.
echo [1/4] Copiando plugin para wp-content\plugins...
if exist "%PLUGINS_PATH%\fpse-core" (
    echo [AVISO] Plugin ja existe. Atualizando...
    rmdir /s /q "%PLUGINS_PATH%\fpse-core"
)

xcopy "." "%PLUGINS_PATH%\fpse-core" /E /I /Y >nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] Plugin copiado com sucesso
) else (
    echo [ERRO] Falha ao copiar plugin
    pause
    exit /b 1
)

echo.
echo [2/4] Verificando Composer...
if exist "%PLUGINS_PATH%\fpse-core\composer.json" (
    where composer >nul 2>nul
    if %ERRORLEVEL% EQU 0 (
        echo [OK] Composer encontrado. Instalando dependencias...
        cd "%PLUGINS_PATH%\fpse-core"
        call composer install
        if %ERRORLEVEL% EQU 0 (
            echo [OK] Dependencias instaladas com sucesso
        ) else (
            echo [AVISO] Falha ao instalar composer. Usando autoload manual.
        )
        cd ..\..\..
    ) else (
        echo [INFO] Composer nao encontrado. Usando autoload manual.
    )
)

echo.
echo [3/4] Verificando estrutura...
if exist "%PLUGINS_PATH%\fpse-core\fpse-core.php" (
    echo [OK] Arquivo principal encontrado
) else (
    echo [ERRO] Arquivo principal nao encontrado
    pause
    exit /b 1
)

if exist "%PLUGINS_PATH%\fpse-core\autoload.php" (
    echo [OK] Autoload encontrado
) else (
    echo [ERRO] Autoload nao encontrado
    pause
    exit /b 1
)

if exist "%PLUGINS_PATH%\fpse-core\src" (
    echo [OK] Diretorio src encontrado
) else (
    echo [ERRO] Diretorio src nao encontrado
    pause
    exit /b 1
)

echo.
echo [4/4] Verificando WP CLI...
where wp >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] WP CLI encontrado
    call wp plugin activate fpse-core
    if %ERRORLEVEL% EQU 0 (
        echo [OK] Plugin ativado com sucesso
    ) else (
        echo [AVISO] Falha ao ativar plugin via WP CLI
        echo Ative manualmente: Dashboard ^> Plugins ^> FPSE Core ^> Ativar
    )
) else (
    echo [INFO] WP CLI nao encontrado
    echo Ative manualmente: Dashboard ^> Plugins ^> FPSE Core ^> Ativar
)

echo.
echo ====================================================
echo [SUCESSO] Instalacao Concluida!
echo ====================================================
echo.
echo Plugin instalado em: %PLUGINS_PATH%\fpse-core
echo.
echo Proximos passos:
echo 1. Ative o plugin no WordPress:
echo    wp plugin activate fpse-core
echo    OU
echo    Dashboard ^> Plugins ^> FPSE Core ^> Ativar
echo.
echo 2. Teste a API:
echo    curl http://localhost/wp-json/fpse/v1/nonce
echo.
echo 3. Consulte a documentacao:
echo    - README.md: Recursos e configuracao
echo    - QUICK_START.md: Guia rapido
echo    - API.md: Referencia da API
echo    - INSTALACAO-SEM-COMPOSER.md: Troubleshooting
echo.
echo [*] Documentacao: %PLUGINS_PATH%\fpse-core\README.md
echo.
pause
