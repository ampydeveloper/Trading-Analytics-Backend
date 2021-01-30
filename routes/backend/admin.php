<?php

use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\Card\CardController;
use App\Http\Controllers\Backend\Ebay\EbayController;

// All route names are prefixed with 'admin.'.
Route::redirect('/', '/admin/dashboard', 301);
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Card Management
Route::group(['prefix' => 'card', 'as' => 'card.'], function () {
    Route::get('/', [CardController::class, 'index'])->name('index');
    Route::get('create', [CardController::class, 'create'])->name('create');
    Route::post('store', [CardController::class, 'store'])->name('store');
    Route::get('import', [CardController::class, 'import'])->name('import');
    Route::post('excel-upload', [CardController::class, 'importFromExcel'])->name('excelUpload');

    Route::group(['prefix' => '{card}'], function () {
        Route::get('edit', [CardController::class, 'edit'])->name('edit');
        Route::patch('/', [CardController::class, 'update'])->name('update');
        Route::delete('/', [CardController::class, 'destroy'])->name('destroy');
    });

    //Datatable API
    Route::group(['prefix' => 'datatable', 'as' => 'datatable.'], function () {
        Route::post('list', [CardController::class, 'dataTableList'])->name('list');
    });
});

// Ebay Management
Route::group(['prefix' => 'ebay', 'as' => 'ebay.'], function () {
    Route::get('/', [EbayController::class, 'index'])->name('index');
   
    Route::group(['prefix' => '{card}'], function () {
        Route::delete('/', [EbayController::class, 'destroy'])->name('destroy');
    });

    //Datatable API
    Route::group(['prefix' => 'datatable', 'as' => 'datatable.'], function () {
        Route::post('list', [EbayController::class, 'dataTableList'])->name('list');
    });
});
