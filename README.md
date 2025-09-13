<img width="1919" height="817" alt="image" src="https://github.com/user-attachments/assets/829a67c4-48b4-4dd9-8c67-80a91d5bfa79" />
Creado por hfabra@gmail.com
Tablero de Puntuaciones (PHP + MySQL + Bootstrap) — COMPLETO (estudiantes + retos + puntuación)

1) Importe la BD desde phpMyAdmin:
   - Archivo: sql/tablero_puntuaciones.sql
   - BD: tablero_puntuaciones

2) Copie la carpeta 'tablero_puntuaciones_full' a htdocs (XAMPP).
3) Ajuste credenciales en includes/db.php si es necesario.
4) Abra: http://localhost/tablero_puntuaciones_full/

Flujo:
- Cree una ACTIVIDAD en actividades.php.
- Enlace "Retos" para agregar los retos a la actividad (se listan en la parte superior del tablero).
- En "Estudiantes" agregue estudiantes (con avatar) asociados a esa actividad.
- En "Habilidades" agregue/edite habilidades si lo necesita.
- En "Abrir tablero" verá tarjetas de estudiantes con su total de puntos.
- Botón "Puntuar" permite +1/-1 por habilidad para el estudiante.

Notas:
- Los retos son informativos en el tablero en esta versión. Si desea que otorguen puntos y se marquen por estudiante (completado/no completado), se puede añadir fácilmente con una tabla 'retos_estudiantes' y una columna 'puntos' en retos.
