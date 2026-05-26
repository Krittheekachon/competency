// routes/api.php
use App\Http\Controllers\RoleController;

Route::middleware('auth:sanctum')->group(function () {

    // ดู role ของตัวเอง
    Route::get('/me', fn() => response()->json(auth()->user()->load('role')));

    // Admin เท่านั้น
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [RoleController::class, 'index']);
        Route::put('/users/{id}/role', [RoleController::class, 'updateRole']);
    });

    // HR + Admin
    Route::middleware('role:hr,admin')->group(function () {
        Route::get('/users/department/{dept}', [RoleController::class, 'byDepartment']);
    });
});