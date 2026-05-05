@echo off
echo ============================================
echo  DEMARRAGE DE L'APPLICATION GESTION DOCUMENTS
echo ============================================
echo.

REM Vérification rapide de XAMPP (optionnel)
echo [INFO] Assurez-vous que XAMPP (Apache + MySQL) est en cours d'execution.
echo       Si ce n'est pas le cas, lancez le panneau de controle XAMPP et démarrez Apache et MySQL.
echo.
pause

REM 1. Lancer le serveur Symfony
echo [1/3] Lancement du serveur Symfony...
cd /d C:\TP_PROJET_B1_a_B3\projet_Angular\gestion_documents_Symphony\backend
start "Symfony Server" cmd /k "symfony server:start --no-tls"

REM 2. Lancer le worker Messenger (envoi des emails)
echo [2/3] Lancement du worker Messenger...
start "Messenger Worker" cmd /k "php bin/console messenger:consume async"

REM 3. Lancer le frontend Angular
echo [3/3] Lancement du serveur Angular...
cd /d C:\TP_PROJET_B1_a_B3\projet_Angular\gestion_documents_Symphony\frontend\gestion-documents-front
start "Angular Server" cmd /k "ng serve"

echo.
echo ============================================
echo  Tous les services sont en cours de demarrage.
echo  - Symfony API : http://127.0.0.1:8001
echo  - Application Angular : http://localhost:4200
echo ============================================
echo.
echo Appuyez sur une touche pour fermer cette fenetre (les services continueront de tourner).
pause >nul