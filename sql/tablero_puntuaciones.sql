-- Base de datos: tablero_puntuaciones (completo con estudiantes, retos y puntuaciones)
CREATE DATABASE IF NOT EXISTS tablero_puntuaciones CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tablero_puntuaciones;

-- Usuarios administradores del tablero
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Actividades
CREATE TABLE IF NOT EXISTS actividades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Estudiantes (asociados a actividad)
CREATE TABLE IF NOT EXISTS estudiantes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  avatar VARCHAR(255) DEFAULT 'default.png',
  actividad_id INT NOT NULL,
  CONSTRAINT fk_est_act FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Habilidades
CREATE TABLE IF NOT EXISTS habilidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Puntuaciones por habilidad y estudiante
CREATE TABLE IF NOT EXISTS puntuaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  estudiante_id INT NOT NULL,
  habilidad_id INT NOT NULL,
  puntaje INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_punt_est FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
  CONSTRAINT fk_punt_hab FOREIGN KEY (habilidad_id) REFERENCES habilidades(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_est_hab (estudiante_id, habilidad_id)
) ENGINE=InnoDB;

-- Retos por actividad (solo informativos en esta versión)
CREATE TABLE IF NOT EXISTS retos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actividad_id INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  CONSTRAINT fk_reto_act FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Semillas de habilidades
INSERT INTO habilidades (nombre) VALUES
('Trabajo en equipo'),
('Asistencia'),
('Ayuda a otros'),
('Hizo la tarea'),
('Participa'),
('Disciplina')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);
