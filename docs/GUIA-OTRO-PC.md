# Guía para instalar el proyecto en tu computador (Windows 10/11)

Tiempo estimado: 30 a 40 minutos la primera vez. Todo se hace dentro de **Ubuntu** (la terminal que instala WSL), salvo la sección 1.

## 1. Programas que necesitas
En **PowerShell como administrador** (una sola vez):

```powershell
wsl --install -d Ubuntu     # instala Ubuntu dentro de Windows; reinicia si lo pide
```
Instala también **Docker Desktop** (docker.com) y marca «Use WSL 2» y, en Settings → Resources → WSL integration, tu Ubuntu. Luego, en Ubuntu:

```bash
sudo apt update && sudo apt install -y git curl mkcert libnss3-tools   # git y certificados locales
curl -fsSL https://ddev.com/install.sh | bash                          # instala DDEV (el proyecto pide v1.25 o más nueva)
sudo apt install -y gh                                                 # GitHub en la terminal (si falla, mira cli.github.com)
mkcert -install                                                        # confía en el certificado https local
```
Comprueba cada uno (todos deben mostrar una versión, sin error):

```bash
git --version && ddev --version && gh --version && docker ps
```

## 2. Entrar a GitHub
Acepta la invitación que te llegó al correo (o en github.com → campana de notificaciones). Después:

```bash
gh auth login          # elige GitHub.com → HTTPS → «Login with a web browser»
```

## 3. Bajar el proyecto
**Dentro de Ubuntu, nunca en `/mnt/c`** (ahí Docker va lento y falla):

```bash
mkdir -p ~/proyectos && cd ~/proyectos                                   # carpeta de trabajo
gh repo clone Bengi-fish/AUTOEVALUACION_Unillanos && cd AUTOEVALUACION_Unillanos   # descarga el proyecto
git config user.name "Tu Nombre"                                         # TU nombre, no el de otra persona
git config user.email "tu-correo@ejemplo.com"                            # el correo de tu cuenta de GitHub
```

## 4. Instalar (un solo comando)
Si te pasaron una carpeta de archivos, ponla **en la raíz del proyecto con el nombre `archivos-sitio`** antes de seguir (si no te pasaron nada, sáltate esto; el sitio no la necesita para verse completo).

```bash
bash scripts/instalar.sh     # arranca DDEV, instala, carga los 40 programas y Electrónica
```
Es seguro repetirlo: no borra nada si ya está instalado. Al final imprime las direcciones a abrir.

## 5. Cómo saber que quedó bien
Abre la dirección que imprimió el script (`https://autoeval-unillanos.ddev.site`):

| Dirección | Debes ver |
|---|---|
| `/` | La portada con cifras y programas |
| `/programas` | 40 programas |
| Programa «Ingeniería Electrónica» | Su autoevaluación con 12 factores |
| `/documentos` | El informe, el plan y los 2 acuerdos CESU |
| `/acceso-acreditacion` | La entrada para el equipo de Acreditación |

## 6. Tu usuario administrador local
Cambia `tunombre` y la clave por los tuyos (la clave no queda en ningún archivo del proyecto):

```bash
ddev drush user:create tunombre --mail="tu-correo@ejemplo.com" --password="UNA-CLAVE-TUYA"   # crea tu usuario
ddev drush user:role:add administrator tunombre                                              # le da permisos de administrador
```
Entra por `/user/login` con ese usuario. (La clave queda en el historial de tu terminal; es solo local, pero no reutilices una clave real.) Si olvidas la clave: `ddev drush user:password tunombre "OTRA-CLAVE"`.

## 7. Trabajo diario
```bash
git checkout main && git pull                     # 1. trae lo último (siempre desde main)
ddev composer install && ddev drush updb -y       # 2. dependencias y actualizaciones de base
ddev drush cim -y && ddev drush cr                # 3. aplica la configuración de los demás y limpia caché
git checkout -b mi-tarea                          # 4. una rama nueva por tarea (NUNCA trabajes en main)
# ... trabajas; corre las pruebas (el número nunca debe bajar) ...
ddev drush cex -y                                 # 5. solo si cambiaste configuración desde la interfaz
git add ruta/exacta/archivo && git commit -m "Describe el cambio"   # 6. rutas explícitas, nunca «git add .»
git push -u origin mi-tarea && gh pr create       # 7. sube la rama y abre el PR; el merge lo hace quien revisa
```
Los respaldos de la base van **fuera** del proyecto: `ddev export-db --file=$HOME/respaldos/nombre.sql.gz`.

## 8. Problemas comunes
- **«Port 80/443 is already in use»**: otro programa usa el puerto (IIS, Skype, otro servidor). Ciérralo o corre `ddev poweroff` y vuelve a intentar.
- **El navegador dice «conexión no segura»**: corre `mkcert -install` en Ubuntu y reinicia el navegador.
- **«Cannot connect to the Docker daemon»**: abre Docker Desktop, espera a que diga «running» y repite el comando.
- **Permisos o lentitud en WSL**: el proyecto está en `/mnt/c`; vuelve a clonarlo en `~/proyectos`.
- **Errores raros en `.sh` o `.yml` («\r», «bad interpreter»)**: fin de línea de Windows; vuelve a clonar dentro de Ubuntu (el proyecto ya fuerza el fin de línea correcto).
- **El sitio sale en blanco o viejo**: `ddev drush cr`. Si pide actualizar la base: `ddev drush updb -y`.
