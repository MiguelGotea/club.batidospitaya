# 🏆 Club Pitaya — Portal de Miembros

Portal web para miembros del Club Batidos Pitaya. Consulta de puntos acumulados,
historial de compras y análisis nutricional con IA.

**URL:** https://club.batidospitaya.com

---

## 📁 Estructura

```
club.batidospitaya.com/
├── index.php                   → Redirige a login
├── login.php                   → Autenticación bcrypt (membresía + cédula)
├── logout.php                  → Destruye sesión
├── dashboard.php               → Panel del miembro: puntos, historial, IA
├── header_club.php             → Navbar Bootstrap 5 reutilizable
├── footer_club.php             → Footer reutilizable
├── ai_nutrition_api.php        → API JSON de análisis nutricional (AIService)
├── auth/
│   └── auth_club.php           → Guard de sesión para páginas protegidas
├── css/
│   └── club_global.css         → Estilos corporativos (sin gradientes)
├── vendor/
│   ├── bootstrap/              → Bootstrap 5.3.3 (local, sin CDN)
│   └── fontawesome/            → FontAwesome 6.5.0 (local, sin CDN)
├── core/                       → Sincronizado desde PitayaCore (Core Ligero)
│   ├── ai/AIService.php        → Servicio IA multi-proveedor con rotación de keys
│   ├── database/conexion.php   → Conexión PDO ($conn)
│   ├── database/env.php        → ⚠️ NO en git — credenciales locales/producción
│   ├── email/EmailService.php  → Servicio de correo
│   └── assets/                 → Logo SVG, imágenes, CSS/JS del core
├── .github/workflows/
│   └── deploy-club.yml         → CI/CD rsync → Hostinger vía SSH
├── .scripts/
│   └── gitpush_club.ps1        → Push seguro PowerShell
├── .htaccess                   → HTTPS forzado, headers de seguridad
├── .gitignore                  → Excluye env.php, *.sql, secrets
└── _setup_database.sql         → Setup inicial DB (ejecución manual)
```

---

## 🗄️ Base de Datos

Opera sobre `u839374897_erp` en Hostinger:

| Tabla | Uso |
|---|---|
| `clientesclub` | Datos del miembro (nombre, cédula, membresía, sucursal) |
| `club_credenciales` | Autenticación bcrypt — hash de contraseña por membresía |
| `VentasGlobalesAccessCSV` | Historial de compras por cliente |
| `DBBatidos` | Catálogo de productos |
| `GrupoProductosVenta` | Categorías (Batido, Bowl, Waffles, Pitaya Store) |
| `SubReceta` | Ingredientes por producto |
| `DBIngredientes` | Detalle nutricional de ingredientes |
| `ia_proveedores_api` | API Keys de IA con rotación automática |

---

## 🔐 Autenticación

- **Usuario:** Número de membresía
- **Contraseña inicial:** Número de cédula (migración transparente con bcrypt)
- **Sesión:** Keys `club_logged_in`, `club_membresia`, `club_nombre`, `club_apellido`
- **LiteSpeed quirk:** `session_start()` debe ir **antes** de `require_once conexion.php`

---

## 🤖 IA Nutricional

`ai_nutrition_api.php` usa `AIService` del Core con cascada de proveedores:

```
groq → google → openai → deepseek → cerebras → openrouter → huggingface → mistral
```

Se usa el primer proveedor con keys `activa=1` en `ia_proveedores_api`.
Si ninguno está disponible, retorna análisis estático.

---

## 🚀 Deploy

```powershell
# Push y deploy automático vía GitHub Actions
.\.scripts\gitpush_club.ps1
```

### Secrets requeridos en GitHub (`Settings > Secrets > Actions`):

| Secret | Descripción |
|---|---|
| `HOSTINGER_HOST` | IP o host del servidor |
| `HOSTINGER_USER` | Usuario SSH (`u839374897`) |
| `HOSTINGER_SSH_KEY` | Llave privada SSH |
| `HOSTINGER_PATH_CLUB` | Ruta absoluta en servidor |

---

## ⚠️ Reglas del Proyecto

- `env.php` **nunca** en git ni en deploy
- Sin gradientes (estándar corporativo)
- Sin `menu_lateral.php` ni auth ERP en el club
- Bootstrap 5 + FontAwesome 6 **locales** en `vendor/` (sin CDN)
- Cambios de DB solo vía `.sql` de ejecución manual
