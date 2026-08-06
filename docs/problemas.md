# Problemas y soluciones — MedSchedule U3

## 1. Conflictos de merge entre ramas
**Problema:** Al integrar backend y frontend hubo conflictos en routes/web.php y DashboardController
**Solución:** Resolver manualmente con git checkout --ours/--theirs según corresponda

## 2. Spatie Permission versión incompatible
**Problema:** spatie/laravel-permission 7.x requiere PHP 8.3, Railway usa 8.2
**Solución:** Downgrade a versión 6.x compatible con PHP 8.2

## 3. Vite manifest not found en CI
**Problema:** Tests fallaban porque Vite no compilaba assets en CI
**Solución:** Agregar npm run build antes de php artisan test en el workflow

## 4. Sesiones en producción
**Problema:** Login no mantenía sesión en Railway
**Solución:** Configurar SESSION_DRIVER=database y eliminar SESSION_DOMAIN
