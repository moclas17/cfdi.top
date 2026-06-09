# AutoFactura

Sistema de autofacturación para negocios. Permite generar links públicos donde los clientes capturan sus datos fiscales para la emisión de facturas.

## Requisitos

- PHP 8.2+
- MySQL / MariaDB
- Apache con mod_rewrite
- XAMPP / LAMP

## Instalación

### 1. Clonar o copiar el proyecto

```bash
# El proyecto debe estar en:
/htdocs/autofactura/
```

### 2. Configurar el entorno

```bash
cp .env.example .env
# Editar .env con los datos de tu base de datos
```

### 3. Crear la base de datos

```bash
# Desde MySQL CLI o phpMyAdmin:
mysql -u root < database/schema.sql
mysql -u root < database/seed.sql
```

### 4. Verificar Apache

Asegúrate de que `mod_rewrite` esté habilitado y que `AllowOverride All` esté configurado para el directorio.

### 5. Acceder

```
http://localhost/autofactura/login
```

**Credenciales demo:**
- Email: `admin@demo.com`
- Password: `admin123`

## Estructura del Proyecto

```
/autofactura
  /app
    /Controllers       → Controladores MVC
    /Models             → Modelos de BD (BaseModel + hijos)
    /Services           → Servicios (DB, EFOS, EfectosFiscales)
    /Validators         → Validadores (futuro)
    /Middleware          → Auth, CSRF
    /Helpers             → Router, funciones globales
  /config               → Configuración (app, db, env)
  /database             → schema.sql, seed.sql
  /public_html
    /autofactura        → Front controller (index.php) y assets públicos
  /resources/views      → Vistas PHP
  /routes               → Definición de rutas
  /storage              → Logs, archivos generados
  .env                  → Variables de entorno
```

## Fases de Desarrollo

- [x] **Fase 1**: Estructura, schema, seed, router, config
- [ ] **Fase 2**: Login, middleware, dashboard
- [ ] **Fase 3**: Configuración negocio, conceptos
- [ ] **Fase 4**: Solicitudes autofactura
- [ ] **Fase 5**: Flujo público, EFOS, facturación mock
- [ ] **Fase 6**: UI polish, logs, README final

## Credenciales de Prueba

| Campo    | Valor           |
|----------|-----------------|
| Email    | admin@demo.com  |
| Password | admin123        |

## Tecnologías

- PHP 8.2 (sin Composer)
- MySQL / MariaDB
- Bootstrap 5
- Bootstrap Icons
- Google Fonts (Inter)
