# AGENTS.md — Memory Logger Pro

## Projecte
- **Plugin:** Memory Logger Pro — auditor rendiment WP (memòria, temps, CPU, SQL, pàgina, bots, plugins, BD, cache)
- **Path:** `D:\Documents\Programació\memory-logger-pro`
- **Versió actual:** `13.3.6` (2026-09-06) — veure `memory-logger.php:6,35` i `CHANGELOG.md`
- **Requisits:** WordPress 6.2+, PHP 8.2+, instal·lació per FTP a `wp-content/plugins/memory-logger-pro` (no zip)
- **Text Domain:** `memory-logger` — `languages/`

## Stack
- PHP 8.2+ (tipat, `declare` no necessari), WordPress Hooks, Transients, Options API
- JS: `assets/admin-script.js` (jQuery + Chart.js 4 via CDN), CSS `admin-styles.css`
- Logs: `WP_CONTENT_DIR/memory-usage.log` + `memory-logger-errors.log` protegits per `.htaccess` (`Require all denied`)

## Estructura
```
memory-logger.php        # Bootstrap: constants MLP_*, require includes, hooks, shortcode, widget
includes/
  utils.php              # Helpers sanitització, conversió memòria
  core-logic.php         # Monitoratge, logs, CPU, hosting detection
  security-logic.php     # Escaneig vulnerabilitats
  cache-logic.php        # [13.3.6] mlp_analyze_cache_config(): WP Rocket + SG Optimizer + TEC
  admin-logic.php        # Menús, pàgines, export JSON/HTML/TXT/CSV (cache_analysis inclòs)
  ajax-logic.php         # Handlers AJAX: mlp_analyze_cache, chart_data, clear_all...
  views/
    tab-dashboard.php    # P1 Dashboard
    tab-statistics.php   # P2 Estadístiques
    tab-diagnostic.php   # P3 Diagnòstic (inclou ⚡ Anàlisi de Cache)
assets/
  admin-script.js        # Mapa mlp-tool-cache -> mlp_analyze_cache
  admin-styles.css
```

## Funcionalitats (tabs)
1. **Dashboard:** mètriques live, historial últimes peticions (2000 entry max), hosting type/provider, accions ràpides
2. **Estadísticas:** promitjos/màxims, Chart.js (mem vs SQL, time vs CPU), distribució mem, picos risc, ranking URLs
3. **Diagnóstico:** score 0-100; tools: plugins, errors (14d), core integrity, DB (autoload/overhead), hosting recs, **⚡ Anàlisi de Cache** (13.3.6)

## Anàlisi de Cache (13.3.6)
- **Funció:** `includes/cache-logic.php:18 mlp_analyze_cache_config(): array` — cache 5min `mlp_cache_analysis_*`, exportable via `admin-logic.php:703 report_data['cache_analysis']`
- **Detecta:** WP Rocket (`wp-rocket/wp-rocket.php`), SG (`sg-optimizer/sg-optimizer.php` / `siteground-optimizer/sg-optimizer.php` / `sg-cachepress/sg-cachepress.php` + MU fallback `SiteGround_Optimizer\Options\Options`), LiteSpeed, W3TC, SuperCache, WP Fastest, Autoptimize. Unifica `sg-cachepress` antic + nou com 1 sol.
- **Issues:** calendari exclòs (`/calendari-de-mogudes/|/mogudes/` → -30), Lifespan 0/10h (-10), doble CDN RocketCDN+SG (-10), Delay/RUCSS (-5), cache_mobile OFF (-5), Memcached OFF (-20), doble page-cache ≥2 (-25)
- **Recomanació òptima SG+Rocket (cas bcnswing.org):** SG Dynamic ON, File-Based OFF, Memcached ON, Frontend OFF; WP Rocket minify ON, `cdn:0` (usar SG CDN Premium TTL 12h), `cdn_type:""` (residu `rocketcdn` amb `cdn:0` és inactiu), `cache_reject_uri:[]`, `purge_cron_interval:4 HOUR_IN_SECONDS`
- **UI:** `views/tab-diagnostic.php:167 mlp-tool-cache`, `ajax-logic.php:31 mlp_analyze_cache`, `assets/admin-script.js:272 map cache`

## Regles de codi
- Entrada segura: `if (!defined('ABSPATH')) exit;` a tots els includes
- Nonce + `current_user_can('manage_options')` a tot AJAX/admin-post
- `LOCK_EX` en `file_put_contents` de logs; no sobreescriure `.htaccess` existent
- Neteja unificada `mlp_purge_cache()`; 1 handler export, 1 nonce
- Score clamped `0..100` amb colors `#00a32a/#f0b849/#d63638`

## Workflow
- **Canvis:** editar fitxer → `git status/diff` → `php -l` si disponible → pujar per FTP (canvi versió obliga)
- **Versió:** bump a `memory-logger.php` header (`Version:`) + `MLP_VERSION` + `CHANGELOG.md` + `README.md` + `estructura.txt`
- **Commit:** `git add <fitxers> && git commit -m "vX.Y.Z: descripció"` (CA/ES), no push automàtic si no es demana
- **Export:** `cache_analysis` ja inclòs a JSON/HTML/TXT/CSV — verificar després de cada nou camp

## Historial recent
- `13.3.6` — Nou `cache-logic.php` + AJAX + tool cache + export; fix doble comptatge SG mu/plugin; cas bcnswing calendari (`cache_reject_uri []`, `lifespan 4h`, `cdn 0`, triple cache → SG Memcached ON/File-Based OFF)
- `13.3.5` — Fix `avg_mem/avg_time` a tab-statistics
- `13.3.4` — Fix botó Limpiar Logs & Caché

## Notes operatives
- `E:\OpenCode` no és repo git; projecte git és `D:\Documents\Programació\memory-logger-pro` (branch `main`, remote `origin/main`)
- `conda run` no usar (espais `JM DJ`); si cal `php` usar path complet. Verificació headers cache: `curl.exe -I https://www.bcnswing.org/calendari-de-mogudes/` → `HIT + max-age=14400`
