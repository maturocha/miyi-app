# F1-14 — Compresión gzip del JSON de la API (propuesta, NO aplicada)

## Cómo se sirve la API en producción
- `Procfile`: `web: vendor/bin/heroku-php-nginx -C nginx_app.conf /public` → Heroku con buildpack `heroku/php`.
- `nginx_app.conf` se incluye **dentro del `server {}`** del config del buildpack. `nginx.conf` del repo no se usa en prod ni en docker local.
- El config del buildpack (`heroku-buildpack-php/conf/nginx/heroku.conf.php`) trae `#gzip on;` **comentado**: el buildpack no comprime respuestas dinámicas.
- El router de Heroku no comprime y agrega `Via: 1.1 vegur`: nginx ve la request como proxied, por eso hace falta `gzip_proxied any;` (por defecto `off` → no comprimiría).
- No verificado: si hay un CDN/proxy (Cloudflare, etc.) delante del dominio de la API que ya comprima.

## Paso previo obligatorio (persona con acceso)
```bash
curl -sS -o /dev/null -D - -H 'Accept-Encoding: gzip' -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>' 'https://<host-api>/api/v1/products?perPage=40' | grep -iE 'content-encoding|server|via|cf-'
```
Si ya viene `Content-Encoding: gzip` (o `br`), cerrar F1-14 sin cambios.

## Diff propuesto (producción) — `nginx_app.conf`
```diff
+gzip on;
+gzip_types application/json text/plain application/javascript text/css;
+gzip_min_length 1024;
+gzip_comp_level 5;
+gzip_vary on;
+gzip_proxied any;
+
 location / {
     # try to serve file directly, fallback to rewrite
     try_files $uri @rewriteapp;
 }
```
(Todas estas directivas son válidas en contexto `server`.)

## Diff opcional (local) — `docker/nginx/conf.d/default.conf`
Las mismas seis líneas dentro de `server {}`; recargar con `docker exec miyi-web nginx -s reload`.

## Verificación tras desplegar
La misma request de arriba debe traer `Content-Encoding: gzip` y `Vary: Accept-Encoding`; tamaño transferido ≈ 15-25 % del original (comparar `curl --compressed -w '%{size_download}'` con y sin `Accept-Encoding`).
