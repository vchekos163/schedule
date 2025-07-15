<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('{module?}/{controller?}/{action?}/{params?}', function (
    $module = 'index',
    $controller = 'index',
    $action = 'index',
    $params = null
) {
    $controllerClass = 'App\\Http\\Controllers\\' . ucfirst($module) . '\\' . ucfirst($controller) . 'Controller';

    if (!class_exists($controllerClass)) {
        abort(404, "Контроллер $controllerClass не найден");
    }

    if (!method_exists($controllerClass, $action)) {
        abort(404, "Метод $action не найден в $controllerClass");
    }

    // Parse /key/value/key/value from URL into associative array
    $paramArray = [];
    if ($params) {
        $segments = explode('/', $params);
        for ($i = 0; $i < count($segments); $i += 2) {
            $key = $segments[$i];
            $value = $segments[$i + 1] ?? null;
            $paramArray[$key] = $value;
        }
    }

    return app()->call("$controllerClass@$action", $paramArray);
})->where('params', '.*');
