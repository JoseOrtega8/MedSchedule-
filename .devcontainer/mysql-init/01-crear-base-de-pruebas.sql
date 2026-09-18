-- MySQL ejecuta este archivo la primera vez que inicializa su directorio de datos.
--
-- phpunit.xml fuerza DB_DATABASE=medschedule_test, una base distinta de la que usa
-- la aplicacion. Sin crearla aqui, la suite de PHPUnit falla entera por no tener
-- donde correr, y el fallo se confunde con un problema de las pruebas.
CREATE DATABASE IF NOT EXISTS medschedule_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON medschedule_test.* TO 'medschedule'@'%';
FLUSH PRIVILEGES;
