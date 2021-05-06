# Challenge Birras by @matur

## URL del challenge

https://santander.maturocha.com.ar

## Stack
Para el stack se eligió:
- Backend: PHP (Laravel)
- Frontend: ReactJs 
- Consistencia de los datos: Mysql

## Aclaraciones
Para simplificar el ejercicio y poder llegar con los tiempos se asumió lo siguiente:
- La temperatura que se toma es la máxima,
- La latitud y longitud del tiempo consultado se tomo la de mi ciudad, lo ideal es que sea dinámico en base a la ciudad sede de la meetup
- El calculo de los cajas de cervezas se optó por hacerlo mediante triggers que disparan un stored procedure desde base de datos para que sea más dinámico. Los triggers se disparan en cada inscripción / desinscripción de una meetup y cuando la temperatura es actualizada.
- La gestión de usuario y rol, es muy sencilla. Se puede mejorar
- El módulo de notificaciones solo notifica nuevas meetups.

## TODO
- Mejorar los datos de la meetup, agregar hora, ciudad
- Hacer un mejor manejo de selección de la temperatura
- Mejorar gestión de roles.
- Agregar más notificaciones, permitir marcarlas como leídas
- Agregar check in en meetup.

## Usuarios creados con el seed a modo de ejemplo
### Usuario admin
- User: admin
- Pass: santander

### Usuarios generales
- User: user1 | user2 | user3 | user4
- Pass: santander


## Para correr

1. En terminal: `composer install` y `npm install` para instalar dependencias.
2. Copiar `env.example` en `.env` y completar la configuración con su entorno local.
3. Correr `php artisan key:generate`
4. Correr migraciones `php artisan migrate`
5. Correr seeder `php artisan db:seed`
6. Instalación finalizada, correr: `php artisan serve` y luego `npm run watch`.
7. El challenge debería estar corriendo en: http://localhost:3000.