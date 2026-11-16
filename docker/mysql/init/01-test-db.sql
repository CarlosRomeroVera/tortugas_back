-- Base de datos para la suite de pruebas
CREATE DATABASE IF NOT EXISTS tortugas_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON tortugas_test.* TO 'tortugas'@'%';
