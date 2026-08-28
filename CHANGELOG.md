Memory Logger Pro - Changelog

## 13.3.0 - 2026-08-28
- Implementada Fase 2 de mejoras: refactor ligero de SoC
- Neteja de caché unificada: todas las limpiezas usan `mlp_purge_cache()`
- Eliminado código muerto: `mlp_log_plugin_errors()`, `mlp_check_large_error_log()`, `mlp_get_cached()`, profiling y stubs AJAX vacíos
- Parser de log único: `mlp_group_stats_by_visitor()` ahora usa `mlp_parse_log_line()` (corregido bug de etiquetas de gráficos vacías)
- Exportación unificada: 1 solo handler AJAX + 1 nonce (eliminados 3 handlers admin-post duplicados)
- Eliminada colisión de hooks en `admin_post_mlp_export_diagnostic_report`

## 13.2.0 - 2026-08-27
- Implementada Fase 1 de mejoras: datos reales y reducción de carga de servidor
- Eliminados valores hardcoded que mostraban datos falsos en el dashboard
- Añadido LOCK_EX para prevenir corrupción de archivos de log en entornos concurrentes
- Eliminada consulta innecesaria a la baza de datos en cada request frontal
- Mejorado el sistema de notificación de errores críticos con caché adecuada

## 13.0.0 - 2026-03-28

### Requisitos Actualizados
- WordPress mínimo: 6.2 (mejora seguridad y compatibilidad)
- PHP mínimo: 8.2 (mejora rendimiento y seguridad)

### Widget Dashboard Optimizado
- Añadido tipo de hosting (Cloud/VPS/Dedicated/Shared)
- Errores ahora usan caché (1 hora) en lugar de escaneo en vivo
- Eliminado Load Avg (poco fiable en algunos hosting)
- Interfaz más limpia y resumida

### Mejoras en Detección de Visitantes
- Añadidos más bots de búsqueda: AppleBot, TwitterBot, LinkedInBot, Pinterest, SlackBot, TelegramBot, DiscordBot
- Añadidos más bots SEO: BuzzSumo, OnCrawl, Sistrix, Searchmetrics, DeepCrawl, Botify, BrightEdge, WPScan
- Añadidas herramientas de monitorización: Zabbix, Nagios, Prometheus, Grafana, AWS CloudWatch, Azure Monitor
- Añadidos más scripts/herramientas: Python, OkHttp, HTTPie, Axios, Node Fetch, Jetpack, WP-Cron
- Mejorada detección de navegadores: Yandex, DuckDuckGo, Arc, Chromium, Waterfox, Pale Moon, Maxthon, UCBrowser, SamsungBrowser
- Añadida detección de dispositivos móviles

### Expansión de Proveedores de Hosting
- Cloud: Añadidos Bunny, Backblaze, Wasabi, MinIO, Netlify, Vercel, Render, Fly.io
- VPS: Contabo, InterServer, Virpus, BandwagonHost, RamNode
- Compartido Premium: RocketNet, GridHosting, WordOps, EasyEngine, MainWP, ManageWP
- Compartido: Servdiscount, Profibernet, FirstFind, Sered, Logal, Axarnet, Lugus, DonWeb, Neolo, Telmex, Kion, Infinitum, Megared

## 12.9.1 - 2026-03-28

### Fix en Detección de Errores
- Mensaje actualizado: ahora indica "Errors detected in system" en lugar de "Memory Logger Pro - X errores"
- Añadido filtro para ignorar errores de themes/plugins conocidos (Divi, Kirki, Elementor, WPBakery, Yoast, WooCommerce)
- Los errores fatals de estos plugins sí se muestran

## 12.9.0 - 2026-03-28

### Mejora en Detección de Servidores

**Nueva detección de tipo de hosting:**
- ☁️ **Cloud**: AWS, Google Cloud, Azure, DigitalOcean, Linode, Vultr, Heroku, Hetzner, etc.
- 🖥️ **VPS**: KVM, Docker, VMware, Proxmox, OpenVZ, LXC
- 🏢 **Dedicado**: Servidores dedicados (sin virtualización, alta memoria)
- ⭐ **Compartido Premium**: SiteGround, WP Engine, Kinsta, Flywheel, Cloudways
- 🌐 **Compartido**: Hostinger, BlueHost, GoDaddy, HostGator, WebEmpresa, etc.

**Mejoras visuales:**
- Dashboard ahora muestra tipo + proveedor (ej: "Cloud (DigitalOcean)")
- Diagnostic también muestra el proveedor detectado
- Ayuda actualizada con explicación de CPU Ratio

**Ratios de CPU actualizados:**
- Cloud: 80%, VPS: 60%, Dedicado: 90%, Compartido Premium: 30%, Compartido: 15%

## 12.8.9 - 2026-03-28

### Mejora visual en Métodos de Medición
- Eliminada línea verde distractiva
- Diseño más limpio y legible

## 12.8.8 - 2026-03-28

### Mejora en Métodos de Medición
- Explicación mejorada: ahora dice qué significa cada método (output_buffer, estimation, etc.)
- Añadida descripción debajo de cada método
- Explicación más clara al final: qué es verde vs amarillo

## 12.8.7 - 2026-03-28

### Mejoras en Estadísticas

- **Fix Top URLs**: Corregido error de variable undefined, ahora lee el log correctamente (límite 500 líneas)
- **Optimización Visitantes**: Ahora usa función helper `mlp_process_visitor_stats()` para evitar redundancia
- **Gráfico de distribución de memoria**: Verificado - barras horizontales con leyenda correcta

## 12.8.6 - 2026-03-28

### Mejoras de Rendimiento en Diagnóstico

**Caché añadida a todas las herramientas:**
- Análisis de Plugins - ya tenía caché (verificado)
- **Patrones de Error** - Nueva caché + filtro de errores últimos 24h (antes 7 días)
- **Integridad Core** - Nueva caché (1 hora)
- **Base de Datos** - Nueva caché (1 hora) - Evita queries repetitivas
- **Recomendaciones de Hosting** - Nueva caché (1 hora)

**Reducción de carga:**
- Filtro de errores: ahora solo analiza errores de las últimas 24h (antes sin límite)
- Límite de líneas en logs: reducido de 1000 a 500

## 12.8.5 - 2026-03-28

### Mejora en Detección de Errores
- La detección de errores ahora ignora: errores de más de 2 horas y errores del propio plugin

### Fix de Error de Sintaxis
- Corregido **Parse error** en tab-dashboard.php: había un div duplicado y un cierre huérfano

## 12.8.3 - 2026-03-28

### Fix de Error de Sintaxis
- Corregido **Parse error** en tab-dashboard.php: había un div duplicado que causaba error crítico

### Fixes y Mejoras de Estabilidad

#### Corrección de Versiones
- Corregida inconsistencia de versión en header del plugin (12.7.1 -> 12.8.0)
- Unificada versión en todas las constantes y documentación

#### Fix de Hosting Compartido
- **OPcache "restrict_api"**: Corregido error fatal en hosting compartidos donde OPcache está habilitado pero la API está restringida. Ahora se maneja de forma silenciosa y muestra 0% si no es accesible.
- Corregida segunda llamada a opcache_get_status() en recomendaciones de hosting que también causaba error.

#### Funciones Completadas
- **mlp_enhanced_error_detection()**: Implementada detección de errores desde múltiples fuentes (error_log, debug.log, MLP_ERROR_LOG_FILE)
- **mlp_execute_cron_security_scan()**: Implementado escaneo programado de seguridad con notificaciones por email
- **mlp_execute_cron_error_scan()**: Implementado escaneo de errores con alertas automáticas
- **mlp_log_plugin_errors()**: Implementado registro de errores de plugins específicos

#### Mejoras de CPU
- **mlp_get_cpu_calibration() mejorada**: Ahora calcula factor dinámico basado en:
  - Tipo de hosting (cloud, VPS, shared)
  - Carga actual del sistema (load average)
  - Memoria disponible

---

## 12.7.2 - 2026-01-30
### Dashboard Optimizado, Simplificado y Más Informativo

#### Reorganización del Dashboard (v12.7.2)
- Insight automático reposicionado: Después del botón de ayuda
- 5 botones unificados en una sola barra
- Quick Actions duplicado eliminado
- Warning de backup discreto y contextual

#### Insight Automático Mejorado (v12.7.2)
- Mensajes más informativos que explican qué afecta el score
- Listado de factores activos: fragmentación, autoload, overhead, tablas grandes
- Sugerencias contextuales según el problema principal
- Solo aparece si hay problemas (no molesta si todo va bien)

#### Quick Actions (v12.7.2)
- Optimizar BD Completa: Limpia transients + optimiza tablas + refresca caché
- Invalidación de caché mejorada con force refresh
- Feedback visual con estados de carga

#### Desglose de Factores DB (v12.7.2)
- Muestra qué está penalizando el health score
- Nota informativa: "* Score aproximado. Depende de múltiples factores."

#### Tendencias Visuales (v12.7.2)
- Indicadores de tendencia (↑↓) en métricas principales
- Compara con promedios históricos

#### Top URLs Problemáticas (v12.7.2)
- Ranking de páginas con peor rendimiento en Estadísticas
- Top 10 con URL, Memoria, Tiempo, CPU

#### Unified Visitor Chart (v12.7.2)
- Gráfico de distribución visitantes en Estadísticas
- Humanos vs Bots vs Desconocidos

## 12.7.1 - 2026-01-30
### Unificación de Métricas y Nuevas Tarjetas de Dashboard

#### Detección de Bots y Visitantes (ALTO IMPACTO)
- **Nueva función mlp_identify_visitor_type()**: Analiza User-Agent para identificar tipo de visitante
  - Bots de búsqueda: GoogleBot, BingBot, YandexBot, BaiduSpider, DuckDuckBot, etc.
  - Bots de SEO: AhrefsBot, SEMrush Bot, Majestic 12, Moz Rogerbot, Screaming Frog, etc.
  - Herramientas de monitorización: Pingdom, GTmetrix, UptimeRobot, Google PageSpeed, Lighthouse
  - Scripts y herramientas: cURL, Wget, Python, Java, etc.
  - Sistemas CMS: WordPress, lectores de feeds
- **Nueva función mlp_get_visitor_summary()**: Retorna tipo resumido (BOT/HUMAN/UNKNOWN)
- **Campo VISITOR en logs**: Nuevo campo añadido al log para registrar tipo de visitante
- **Estadísticas de visitantes en mlp_get_advanced_stats()**: 
  - Desglose por: human, bot, unknown
  - Promedios de memoria, tiempo, CPU, SQL por tipo
  - Máximos por tipo
  - Desglose por nombre de bot específico

#### Identificación de Plugins Lentos (ALTO IMPACTO)
- **Nueva función mlp_identify_plugin_by_request()**: Detecta plugin activo por URL
  - Reconoce: WooCommerce, Contact Form 7, WPForms, Elementor, Jetpack, Wordfence, Yoast SEO, Rank Math, WP Rocket, LiteSpeed Cache, etc.
  - Nivel de confianza: high, medium, low
- **Sistema de profiling ligero**:
  - mlp_start_plugin_profiling(): Inicia temporizador
  - mlp_end_plugin_profiling(): Finaliza y calcula tiempo en milisegundos
- Útil para identificar qué plugins consumen más recursos en cada petición

#### Detección de Hosting (MEJORADO v12.6.11)
- Sistema mejorado para distinguir tipos de hosting: cloud, VPS, dedicado y compartido
- Detección automática del proveedor de hosting (AWS, Google Cloud, Azure, DigitalOcean, SiteGround, GoDaddy, etc.)
- Ratio de CPU ajustado dinámicamente según tipo de hosting, memoria y carga del sistema
- Nuevas funciones: mlp_detect_throttling(), mlp_estimate_assigned_cpus(), mlp_detect_provider()
- Cálculo de CPU mejorado con nueva fórmula que considera throttling

## 12.6.11 - 2026-01-30
- Sistema de caché (mlp_get_cached) para análisis pesados
- Renderizado unificado de filas de historial (mlp_render_history_row)
- Gate simple de AJAX (mlp_ajax_require_auth)
- Wrappers de escaping (mlp_esc_html, mlp_esc_attr)

---

Notas:
- Este changelog resume todos los cambios aplicados para las versiones 12.6.11, 12.7.0 y 12.7.1
- Se recomienda ejecutar pruebas de humo en WP para validar UI, performance y seguridad
- Las funciones de detección de CPU y hosting son estimaciones y pueden variar según el proveedor
