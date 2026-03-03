<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/app/{any?}', function () {
    $mimeTypeByExtension = static function (string $path): array {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'js' => ['Content-Type' => 'application/javascript; charset=utf-8'],
            'css' => ['Content-Type' => 'text/css; charset=utf-8'],
            'json', 'map' => ['Content-Type' => 'application/json; charset=utf-8'],
            'ico' => ['Content-Type' => 'image/x-icon'],
            'svg' => ['Content-Type' => 'image/svg+xml'],
            default => [],
        };
    };

    $requestedPath = trim((string) request()->route('any', ''), '/');

    if ($requestedPath !== '') {
        $directAssetPath = public_path('app/'.$requestedPath);

        if (File::isFile($directAssetPath)) {
            return response()->file($directAssetPath, $mimeTypeByExtension($directAssetPath));
        }

        $browserAssetPath = public_path('app/browser/'.$requestedPath);

        if (File::isFile($browserAssetPath)) {
            return response()->file($browserAssetPath, $mimeTypeByExtension($browserAssetPath));
        }
    }

    $indexPath = public_path('app/browser/index.html');

    abort_unless(File::exists($indexPath), 404, 'Angular UI is not built yet. Run: cd resources/ui && npm run build:laravel');

    return response()->file($indexPath);
})->where('any', '.*');
