<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn() => response()->json(['status' => 'GCF Madagascar API', 'version' => 'v1']));
