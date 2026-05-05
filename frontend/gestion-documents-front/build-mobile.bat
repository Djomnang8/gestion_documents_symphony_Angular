@echo off
echo.
echo ===================================================
echo  BUILD MOBILE - Angular + Capacitor Android
echo ===================================================
echo.

echo === ETAPE 1 : ng build --configuration production ===
call ng build --configuration mobile
IF %ERRORLEVEL% NEQ 0 ( echo [ERREUR] Build Angular echoue & pause & exit /b 1 )

echo.
echo === ETAPE 2 : Copie index.csr.html vers index.html ===
copy dist\gestion-documents-front\browser\index.csr.html dist\gestion-documents-front\browser\index.html
IF %ERRORLEVEL% NEQ 0 ( echo [ERREUR] Copie index.html echoue & pause & exit /b 1 )

echo.
echo === ETAPE 3 : npx cap copy android ===
call npx cap copy android
IF %ERRORLEVEL% NEQ 0 ( echo [ERREUR] cap copy echoue & pause & exit /b 1 )

echo.
echo === ETAPE 4 : npx cap sync android ===
call npx cap sync android
IF %ERRORLEVEL% NEQ 0 ( echo [ERREUR] cap sync echoue & pause & exit /b 1 )

echo.
echo === ETAPE 5 : npx cap open android ===
call npx cap open android

echo.
echo ===================================================
echo  Build OK !
echo  Lancer le backend avec :
echo    dotnet run --urls "http://0.0.0.0:5252"
echo ===================================================
pause