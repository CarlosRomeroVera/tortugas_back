<?php

use Illuminate\Support\Facades\Route;

// El backoffice vive en el panel de Filament (/admin)
Route::redirect('/', '/admin');
