# Estado inicial del proyecto

Este documento explica de dónde viene este repositorio, por qué su primer
commit **no arranca limpio**, y qué hoja de ruta sigue el trabajo de rescate.

Si has llegado al README y te has preguntado por qué hay un aviso señalando a
este archivo, aquí está el contexto.

---

## De dónde viene esto

Este proyecto es el rescate de una práctica universitaria realizada durante
el curso 2024/25 en la asignatura **SWAP (Servidores Web de Altas
Prestaciones)** del Grado en Ingeniería Informática de la UGR.

La asignatura se planteó como una serie de cinco prácticas iterativas
(**P1 → P5**) en las que cada entrega construía sobre la imagen y la
configuración de la anterior:

```
P1  Apache + PHP en Ubuntu                    (imagen base)
P2  Pruebas con varios balanceadores          (sobre la imagen P1)
P3  Añade SSL                                 (FROM jorgelpz-apache-image:p1)
P4  Añade iptables y certificados copiados    (FROM jorgelpz-apache-image:p3)
P5  Granja Apache + CMS + tests de carga      (FROM jorgelpz-apache-image:p3)
```

El planteamiento iterativo, **mal guiado en su momento**, generó una
dependencia oculta: la entrega P5 (este repositorio) **no contiene la
imagen base de la que depende**. Para arrancarla había que tener
construidas previamente, en local, las imágenes de P3 (y por tanto P1).

Más de un año después, ya al final de mis estudios, decido retomar el
proyecto para convertirlo en lo que debería haber sido desde el principio:
**un prototipo didáctico, autocontenido y completamente funcional**, con
documentación en condiciones, apto para enseñar lo que enseña (granja web,
balanceo, terminación TLS, hardening con iptables, pruebas de carga) sin
exigir al lector reconstruir paso a paso un encadenamiento de prácticas
universitarias.

---

## Por qué este snapshot inicial no funciona

Si clonas el primer commit (`chore: snapshot inicial del estado académico`)
y haces `docker compose up`, **no funciona**. Los motivos concretos:

1. **Imagen base inexistente.** El [Dockerfile de Apache](web-farm/apache/Dockerfile)
   declara `FROM jorgelpz-apache-image:p3`, una imagen que se construía en la
   práctica P3 y que **no está en este repositorio**. Sin ella, el `docker build`
   falla en la primera línea.

2. **Entrypoint roto.** El script
   [web-farm/apache/firewall/entrypoint.sh](web-farm/apache/firewall/entrypoint.sh)
   invoca `./jorgelpz-iptables-web.sh`, pero el Dockerfile copia el script
   con un nombre distinto (`/iptables-web.sh`). El reglaje de iptables no se
   aplica al arrancar el contenedor. En P4 esto sí funcionaba porque ambos
   nombres coincidían; al renombrarse el archivo en P5, la referencia quedó
   desincronizada.

3. **Configuración SSL desincronizada.**
   [web-farm/apache/apache-ssl.conf](web-farm/apache/apache-ssl.conf)
   apunta a `/etc/apache2/ssl/chain_apache.crt` y `server.key`, pero el
   Dockerfile copia los certificados con otros nombres
   (`certificado_jorgelpz.crt/.key`). Esto venía heredado tal cual desde
   P3/P4 y nunca llegó a converger.

4. **Dependencia implícita de un volumen.** Los `docker-compose.yml` montan
   `./apache/web-content` sobre `/var/www/html`, ocultando lo que la imagen
   pudiera traer. Si por cualquier motivo el volumen no se monta, el
   `DocumentRoot` queda vacío.

5. **Coexistencia ambigua.** Los stacks `web-farm/` y `cms/` reclaman ambos
   la IP `192.168.10.50` en la red `red_web`. Solo se pueden levantar uno a
   la vez, pero esto no está documentado de forma evidente.

6. **Identidad personal incrustada.** Los certificados X.509 incluidos
   contienen mi correo de la UGR en su `Subject` (`emailAddress=jorgelpz@correo.ugr.es`),
   y el sufijo `_jorgelpz` aparece en nombres de imagen, contenedor, red,
   upstream Nginx y endpoints (`/estadisticas_jorgelpz`). Todo eso queda
   conservado en este snapshot y se neutraliza en MVPs posteriores.

7. **Restos académicos.** Comentarios tipo `<!-- Esto es GPT made -->` en
   `index.php`, bloques de configuración comentados en
   [cms/nginx/nginx.conf](cms/nginx/nginx.conf), `__pycache__/` no
   ignorado, títulos como `SWAP - P1`, comentarios mezclados en español
   sin homogeneidad.

Nada de esto se arregla en el snapshot inicial. **Lo importante es que
quede capturado tal cual estaba**, para que la historia del repositorio
muestre con claridad de dónde se parte y a dónde se llega.

---

## Lo que está en el repositorio y lo que no

**Sí está en el repo:**

- Los `Dockerfile`, `docker-compose.yml`, configuraciones (`nginx.conf`,
  `apache-ssl.conf`), scripts de iptables, contenido PHP de la granja,
  `locustfile.py` y los `README.md` / `DEPLOYMENT.md` originales del
  entregable.
- Los certificados públicos `*.crt` (un certificado X.509 es público por
  definición; lo sensible es la clave privada).

**No está en el repo (excluido vía `.gitignore`):**

- `dependences/` — copia local de las prácticas P1..P4 que era necesaria
  para reconstruir la cadena. Es deuda técnica: se reemplaza en el MVP-0
  aplanando todo en un único Dockerfile autocontenido.
- `*.key` y `*.pem` — claves privadas. Aunque eran de un certificado
  autofirmado de laboratorio, una clave privada no debe vivir nunca en un
  repositorio público.
- Artefactos de Python (`__pycache__/`), entornos, archivos de IDE y
  similares.

---

## Hoja de ruta del rescate

El trabajo se organiza en MVPs incrementales. Cada uno cierra una capa de
problemas y se publica como una serie de commits con mensajes descriptivos.

- **MVP-0 — Que arranque, autocontenido.**
  Aplanar la cadena P1→P3→P4→P5 en un único Dockerfile self-contained
  para Apache (y otro para Nginx). Eliminar la dependencia de imágenes
  externas. Arreglar el entrypoint y la configuración SSL para que sean
  coherentes. Versiones pineadas (`ubuntu:22.04`, `nginx:1.27`, etc.).
  Borrar `dependences/`. Verificar end-to-end con `docker compose up`.

- **MVP-1 — Limpieza y normalización.**
  Quitar restos académicos (comentarios, títulos, `__pycache__`). Renombrar
  variables y endpoints con sufijo `_jorgelpz` a nombres neutros.
  Regenerar certificados con `Subject` neutro mediante un script
  reproducible (`make certs` o equivalente). Mover credenciales de DB a
  `.env` + `.env.example`. Aplicar formateo consistente.

- **MVP-2 — Reestructuración y documentación.**
  Reorganizar la jerarquía de carpetas si procede. Reescribir el
  `README.md` en clave de proyecto (no de práctica), sin frases tipo
  "esto fue una práctica de la asignatura X". Documentar la arquitectura,
  el porqué de las decisiones técnicas, las limitaciones conocidas.

- **MVP-3+ — Mejoras incrementales.**
  Mejoras concretas, una a una, justificadas. P. ej.: health checks de
  los backends, `least_conn` en lugar de round-robin, métricas Prometheus,
  un `Makefile` que orqueste los comandos habituales, GitHub Actions con
  `docker compose config` y un smoke test mínimo.

Cada paso queda reflejado en commits siguiendo
[Conventional Commits](https://www.conventionalcommits.org/) (`feat:`,
`fix:`, `chore:`, `docs:`, `refactor:`, `test:`).

---

## Cómo leer este repositorio

- Si quieres **ver el resultado final**, lee la última versión del
  `README.md` y sigue las instrucciones que indique.
- Si quieres **entender cómo se llegó hasta ahí**, recorre la historia de
  commits desde el primero. Cada MVP cuenta una parte de la historia.
- Si has llegado aquí buscando una práctica de SWAP UGR para inspirarte
  en tu propia entrega, **ten cuidado**: este repositorio ya no se
  parece a la entrega original. La entrega original es exactamente lo
  capturado en el primer commit, con todos sus problemas. El resto del
  repositorio no es código académico: es lo que hace falta hacer
  *después* de la asignatura para que el proyecto sea presentable.
