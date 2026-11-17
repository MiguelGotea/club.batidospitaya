# 🏆 Club Pitaya — Portal de Miembros

> Portal web para clientes del Club Batidos Pitaya. Consulta de puntos acumulados,
> historial de compras y análisis nutricional personalizado con IA.

**URL Producción:** https://club.batidospitaya.com
**Repo:** https://github.com/MiguelGotea/club.batidospitaya
**DB:** `u839374897_erp` en Hostinger (compartida con ERP)

---

## 📁 Estructura del Proyecto

```
club.batidospitaya.com/
│
├── index.php                    → Redirige a login.php
├── login.php                    → Autenticación bcrypt (membresía + cédula)
├── logout.php                   → Destruye sesión y redirige a login
├── dashboard.php                → Panel principal del miembro
├── ai_nutrition_api.php         → Endpoint JSON: análisis nutricional con IA
│
├── header_club.php              → Navbar Bootstrap 5 (incluir en <body>)
├── footer_club.php              → Footer (incluir antes de </body>)
│
├── auth/
│   └── auth_club.php            → Guard de sesión — incluir en páginas protegidas
│
├── css/
│   └── club_global.css          → Estilos corporativos completos (sin gradientes)
│
├── vendor/                      → Librerías JS/CSS locales (sin CDN externo)
│   ├── bootstrap/
│   │   ├── css/bootstrap.min.css      Bootstrap 5.3.3
│   │   └── js/bootstrap.bundle.min.js
│   └── fontawesome/
│       ├── css/all.min.css            FontAwesome 6.5.0
│       └── webfonts/                  Fuentes de íconos
│
├── core/                        → Sincronizado desde PitayaCore (Core Ligero)
│   ├── ai/
│   │   └── AIService.php        → Servicio IA multi-proveedor con rotación de keys
│   ├── database/
│   │   ├── conexion.php         → Conexión PDO ($conn), timezone Nicaragua
│   │   └── env.php              → ⚠️ NO en git — credenciales del entorno
│   ├── email/
│   │   └── EmailService.php     → Servicio de correo (PHPMailer)
│   ├── utils/
│   │   └── DocumentParser.php   → Utilidades de parsing
│   └── assets/
│       ├── img/Logo.svg         → Logo oficial (usado como favicon)
│       └── ...                  → CSS/JS/imágenes del core
│
├── .github/
│   └── workflows/
│       └── deploy-club.yml      → CI/CD: rsync a Hostinger vía SSH en cada push
│
├── .scripts/
│   └── gitpush_club.ps1         → Push seguro PowerShell (Script Tanque v7)
│
├── .htaccess                    → HTTPS forzado, seguridad, cache de assets
├── .gitignore                   → Excluye env.php, *.sql y archivos del sistema
├── _setup_database.sql          → ⚠️ Setup inicial DB (ejecución manual única)
└── README.md                    → Este archivo
```

---

## 🗄️ Base de Datos

Opera sobre `u839374897_erp` (MySQL) en Hostinger.

| Tabla | Propósito |
|---|---|
| `clientesclub` | Datos del miembro: nombre, apellido, cédula, membresía, sucursal, puntos |
| `club_credenciales` | Autenticación bcrypt — hash de contraseña por membresía |
| `VentasGlobalesAccessCSV` | Historial completo de compras por cliente |
| `DBBatidos` | Catálogo de productos (batidos, bowls, waffles) |
| `GrupoProductosVenta` | Categorías: Batido, Bowl, Waffles, Pitaya Store |
| `SubReceta` | Ingredientes por producto |
| `DBIngredientes` | Info nutricional de ingredientes (Cocina, Unidad, etc.) |
| `ia_proveedores_api` | API Keys de IA con rotación automática y límites diarios |

### Columnas clave de `clientesclub`
`id_clienteclub`, `sucursal`, `membresia`, `nombre`, `apellido`, `cedula`,
`celular`, `fecha_nacimiento`, `correo`, `fecha_registro`, `puntos_iniciales`, `nombre_sucursal`

---

## 🔐 Autenticación

| Aspecto | Detalle |
|---|---|
| **Campo usuario** | Número de membresía |
| **Campo contraseña** | Número de cédula (contraseña inicial) |
| **Hash** | `bcrypt` vía `password_hash()` / `password_verify()` |
| **Migración** | Transparente: si no hay hash en DB se crea automáticamente al primer login |
| **Rate limiting** | Máximo 5 intentos fallidos → bloqueo 15 min (tabla `club_credenciales`) |
| **CSRF** | Token en sesión validado en cada POST del login |

**Keys de sesión** (prefijo `club_` para no colisionar con ERP):

```php
$_SESSION['club_logged_in']  // bool — guard principal
$_SESSION['club_membresia']  // string — número de membresía
$_SESSION['club_nombre']     // string — nombre del miembro
$_SESSION['club_apellido']   // string — apellido del miembro
```

> [!IMPORTANT]
> **LiteSpeed quirk:** `session_start()` debe ejecutarse **antes** de `require_once conexion.php`
> en todos los archivos. Si se invierte el orden, la sesión queda vacía tras el redirect.

---

## 🤖 IA Nutricional (`ai_nutrition_api.php`)

Endpoint `POST` que recibe los productos del usuario y retorna análisis HTML.

**Request:**
```json
{
  "productos_con_ingredientes": [
    {
      "producto": { "DBBatidos_Nombre": "Triple Berry", "CodProducto": "B001" },
      "ingredientes": [{ "ingrediente": "Fresa", "Unidad": "g" }]
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "analysis": "<div class='producto-analysis'>...</div>",
  "hasAI": true,
  "proveedor": "google",
  "productos_analizados": 3
}
```

**Cascada de proveedores** — usa el primero con `activa=1` en `ia_proveedores_api`:
```
groq → google → openai → deepseek → cerebras → openrouter → huggingface → mistral
```

Si ningún proveedor tiene keys activas → retorna análisis estático básico (`hasAI: false`).

**Activar una key:**
```sql
UPDATE ia_proveedores_api SET activa = 1 WHERE id = X AND proveedor = 'groq';
```

---

## 🎨 Estándares UI/UX

| Elemento | Valor |
|---|---|
| **Framework CSS** | Bootstrap 5.3.3 (local en `vendor/`) |
| **Iconos** | FontAwesome 6.5.0 (local en `vendor/`) |
| **Color primario** | `#51B8AC` |
| **Color secundario** | `#0E544C` |
| **Color acción** | `#218838` / `#1d6f42` hover |
| **Fondo** | `#F6F6F6` |
| **Fuente** | Calibri, Segoe UI, Arial (nativas — sin Google Fonts) |
| **Gradientes** | ❌ Prohibidos (estándar corporativo) |
| **Favicon** | `core/assets/img/Logo.svg` |

---

## 🚀 Deploy

El deploy es automático vía **GitHub Actions** al hacer push a `main`.

```powershell
# Push + deploy automático (recomendado)
.\.scripts\gitpush_club.ps1
```

El script verifica archivos vacíos, hace pull con rebase y push en un solo comando.

### Workflow: `.github/workflows/deploy-club.yml`

1. **Checkout** del código
2. **Validación PHP** — bloquea si detecta `CREATE/DROP/ALTER TABLE` en archivos modificados
3. **rsync → Hostinger** (SSH puerto 65002) — excluye `env.php`, `*.sql`, `.git/`
4. **chmod** de directorios y archivos

### Secrets requeridos en GitHub (`Settings › Secrets › Actions`):

| Secret | Descripción |
|---|---|
| `HOSTINGER_HOST` | IP o hostname del servidor |
| `HOSTINGER_USER` | Usuario SSH (`u839374897`) |
| `HOSTINGER_SSH_KEY` | Llave privada SSH (configurada en Hostinger) |
| `HOSTINGER_PATH_CLUB` | Ruta absoluta en servidor del dominio club |

---

## ⚙️ Core Ligero (PitayaCore Sync)

La carpeta `core/` es sincronizada automáticamente desde el repo **PitayaCore**
vía el workflow `sync-to-public-domains.yml` cuando hay push a `main` en PitayaCore.

**Carpetas sincronizadas:**
`core/ai/`, `core/database/` (excl. `env.php`), `core/email/`, `core/assets/`, `core/utils/`, `core/vendor/`

**Carpetas NO sincronizadas** (solo para ERP interno):
`core/auth/`, `core/layout/`, `core/permissions/`, `core/helpers/`, `core/components/`

> No usar `core/auth/auth.php` del ERP en el club. Usar `auth/auth_club.php`.

---

## 🛡️ Reglas del Proyecto

- `env.php` **nunca** en git ni subido por el workflow
- Sin gradientes en CSS (estándar corporativo)
- Sin `menu_lateral.php`, `header_universal.php` ni auth ERP
- Bootstrap 5 + FontAwesome 6 locales en `vendor/` — sin dependencias de CDN externo
- Cambios de DB únicamente vía `.sql` de ejecución manual
- Variables de sesión siempre con prefijo `club_`
- `session_start()` siempre **antes** de `require_once conexion.php`

---

## 🔧 Setup Local

1. Clonar el repo
2. Crear `core/database/env.php` con las constantes de BD:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u839374897_erp');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');
define('APP_ENV', 'local');
```

3. Ejecutar `_setup_database.sql` en la BD (una sola vez)
4. Apuntar el vhost a la raíz del proyecto
