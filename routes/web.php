<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\TreatmentPlan\Approvals\TreatmentPlanApprovalManager;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Temporary route to test the Care Plan Approval Manager manually
Route::get('/tests/care-plan-approvals', TreatmentPlanApprovalManager::class)
    ->name('test.care-plan-approvals');
