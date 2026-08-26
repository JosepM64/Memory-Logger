# Memory Logger Pro

Plugin de WordPress para auditar el rendimiento de tu sitio: memoria, tiempo de carga, CPU inteligente, consultas SQL, tamaño de página y detección de bots. Incluye gráficos interactivos, estadísticas históricas, diagnóstico del servidor, análisis de plugins y exportación de informes.

**Diseñado para ser ligero:** muestreo de peticiones, caché de análisis pesados y límites de retención de registros para no cargar el servidor.

## Características

### 📊 Dashboard (Resumen en tiempo real)
- Métricas en vivo: memoria, tiempo de ejecución, CPU, SQL y tamaño de página.
- Tabla de historial de peticiones con iconos de alerta (error fatal, memoria crítica, lentitud extrema, sobrecarga SQL...).
- Detección automática de tipo de hosting (Cloud, VPS, Dedicado, Compartido Premium, Compartido).
- Distribución de visitantes: humanos vs bots vs desconocidos.
- Configuración de umbrales de alerta y acciones rápidas (vaciar registro, optimizar BD, exportar CSV).

### 📈 Estadísticas (Análisis histórico)
- Promedios y máximos de memoria, tiempo, CPU, SQL y tamaño.
- Gráficos interactivos (Chart.js): memoria vs SQL, tiempo vs CPU, tamaño vs CPU.
- Distribución de consumo de memoria (<50 MB, 50-100 MB, 100-200 MB, 200+ MB).
- Historial de picos de riesgo y ranking de URLs con peor rendimiento.
- Análisis de visitantes por tipo: humanos, bots de búsqueda, bots SEO y herramientas de monitorización.

### 🔍 Diagnóstico & Seguridad (Auditoría bajo demanda)
- Auditoría global con score (0-100) basado en plugins, base de datos y errores.
- Análisis de plugins: actualizaciones pendientes, incompatibilidades PHP/WordPress.
- Patrones de error: lectura de `debug.log`/`error_log`, timeline de 14 días, culpables por plugin/tema.
- Integridad del core: permisos de escritura y archivos críticos.
- Análisis de base de datos: tamaño, overhead, autoload, transients caducados.
- Recomendaciones de hosting: PHP, `memory_limit`, OPCache, extensiones, SSL, HSTS.
- Exportación de informes en JSON, HTML, TXT y CSV.

## Requisitos

- WordPress 6.2 o superior
- PHP 8.2 o superior

## Instalación

1. Sube la carpeta `memory-logger-pro` a `wp-content/plugins/`.
2. Activa el plugin desde el menú *Plugins*.
3. Accede a **Memory Logger Pro** en el menú de administración de WordPress.

## Uso rápido

- **Dashboard:** consulta el estado actual del sistema y el historial de peticiones. Usa "Probar Métricas" para forzar una medición inmediata.
- **Estadísticas:** selecciona el rango de peticiones (10-500) y el tipo de gráfico (líneas/barras) para analizar tendencias.
- **Diagnóstico:** ejecuta la "Auditoría Global" para obtener un score de salud completo, o usa cada herramienta individualmente.

> ⚠️ Antes de usar "Optimizar BD", realiza una copia de seguridad de la base de datos.

## Cambios

Consulta el historial completo en [CHANGELOG.md](CHANGELOG.md).

## Licencia

GPL v2 o posterior — ver [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
