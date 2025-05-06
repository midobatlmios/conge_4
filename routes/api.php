Route::middleware('auth:sanctum')->group(function () {
    Route::post('/demandes', [DemandesController::class, 'store']);
});
