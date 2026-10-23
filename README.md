# 🍓 Club Pitaya — Portal de Miembros

Portal web para miembros del Club Pitaya. Permite a los clientes consultar sus puntos acumulados, historial de compras, catálogo de beneficios y análisis nutricional con IA.

**URL de Producción:** https://club.batidospitaya.com

---

## 📁 Estructura del Proyecto

| Archivo / Carpeta | Descripción |
|---|---|
| `index.php` | Redirección automática a login |
| `login.php` | Autenticación de clientes por número de membresía |
| `logout.php` | Cierre de sesión y limpieza de tokens |
| `dashboard.php` | Panel principal del miembro: puntos, historial de visitas y estadísticas |
| `config.php` | Conexión segura a base de datos (carga variables de `.env`) |
| `ai_nutrition_api.php` | API de análisis nutricional con IA (Groq) |
| `.env.example` | Plantilla de variables de entorno para producción |
| `.htaccess` | Reglas del servidor web Apache |
| `.github/workflows/deploy-club.yml` | Pipeline de CI/CD para despliegue automatizado a Hostinger |
| `.scripts/gitpush_club.ps1` | Script PowerShell Tanque v7 anti-choque para push seguro |

---

## 🔒 Variables de Entorno en el Servidor

Crear un archivo `.env` en la raíz del dominio en el servidor Hostinger (`/home/u839374897/domains/club.batidospitaya.com/public_html/.env`):

```env
DB_HOST=localhost
DB_NAME=u839374897_erp
DB_USER=u839374897_usuario
DB_PASS=tu_contraseña_aqui
GROQ_API_KEY=tu_api_key_de_groq
```

> [!IMPORTANT]
> El archivo `.env` **NUNCA** debe subirse al repositorio Git. Está explícitamente ignorado por `.gitignore` y protegido de sobreescritura en el workflow de despliegue `rsync`.

---

## 🗄️ Base de Datos

El portal opera sobre la base de datos `u839374897_erp`:
- `clientesclub`: Datos de miembros y número de afiliado.
- `VentasGlobalesAccessCSV`: Historial de tickets y compras por cliente.
- `DBBatidos`: Catálogo de productos y formulaciones.
- `GrupoProductosVenta`: Agrupación de categorías y productos.

---

## 🚀 Despliegue Continuo (GitHub Actions)

El flujo `.github/workflows/deploy-club.yml` se dispara automáticamente en cada `push` a la rama `main`.

### Secrets requeridos en GitHub:
En el repositorio **https://github.com/MiguelGotea/club.batidospitaya** ir a **Settings > Secrets and variables > Actions** y configurar:

| Secret | Descripción | Valor Ejemplo |
|---|---|---|
| `HOSTINGER_HOST` | Host o IP del servidor Hostinger | `batidospitaya.com` (o IP del servidor) |
| `HOSTINGER_USER` | Usuario SSH de Hostinger | `u839374897` |
| `HOSTINGER_SSH_KEY` | Llave privada SSH configurada en Hostinger | `-----BEGIN OPENSSH PRIVATE KEY----- ...` |
| `HOSTINGER_PATH_CLUB` | Ruta absoluta del dominio en Hostinger | `/home/u839374897/domains/club.batidospitaya.com/public_html` |

---

## 🛠️ Push Seguro a Producción

Para subir cambios sin riesgo de conflictos o subida accidental de archivos vacíos, puedes ejecutar desde PowerShell:

```powershell
.\.scripts\gitpush_club.ps1
```
