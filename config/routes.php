<?php
/**
 * Application routes.  $router is provided by the front controller.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\StudentController;
use App\Controllers\HostelController;
use App\Controllers\RoomController;
use App\Controllers\ApplicationController;
use App\Controllers\AllocationController;
use App\Controllers\PaymentController;
use App\Controllers\ComplaintController;
use App\Controllers\NoticeController;
use App\Controllers\ProfileController;
use App\Controllers\VisitorController;
use App\Controllers\InventoryController;
use App\Controllers\ReportController;
use App\Controllers\AuditController;
use App\Controllers\SettingsController;
use App\Controllers\ExportController;
use App\Controllers\BlockController;
use App\Controllers\TransferController;
use App\Controllers\NotificationController;
use App\Controllers\AcademicController;
use App\Controllers\UserController;
use App\Controllers\PasswordResetController;
use App\Controllers\DebtorController;

// --- Public / Auth ----------------------------------------------------------
$router->get('/',                 [AuthController::class, 'showLogin']);
$router->get('/login',            [AuthController::class, 'showLogin']);
$router->post('/login',           [AuthController::class, 'login']);
$router->get('/login/verify',        [AuthController::class, 'showTwoFactor']);
$router->post('/login/verify',       [AuthController::class, 'verifyTwoFactor']);
$router->post('/login/verify/resend',[AuthController::class, 'resendTwoFactor']);
$router->get('/login/verify/cancel', [AuthController::class, 'cancelTwoFactor']);
$router->get('/register',         [AuthController::class, 'showRegister']);
$router->post('/register',        [AuthController::class, 'register']);
$router->get('/forgot-password',         [AuthController::class, 'showForgot']);
$router->post('/forgot-password/verify', [AuthController::class, 'verifyIdentity']);
$router->get('/forgot-password/reset',   [AuthController::class, 'showReset']);
$router->post('/forgot-password/reset',  [AuthController::class, 'resetSelf']);
$router->post('/forgot-password/request',[AuthController::class, 'requestReset']);
$router->get('/logout',           [AuthController::class, 'logout']);

// --- Dashboard --------------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index']);

// --- Notifications ----------------------------------------------------------
$router->get('/notifications',           [NotificationController::class, 'index']);
$router->get('/notifications/{id}/read', [NotificationController::class, 'read']);
$router->post('/notifications/read-all',  [NotificationController::class, 'readAll']);

// --- Profile ----------------------------------------------------------------
$router->get('/profile',  [ProfileController::class, 'edit']);
$router->post('/profile', [ProfileController::class, 'update']);
$router->post('/profile/password', [ProfileController::class, 'password']);

// --- Students ---------------------------------------------------------------
$router->get('/students',            [StudentController::class, 'index']);
$router->get('/students/create',     [StudentController::class, 'create']);
$router->get('/students/import',     [StudentController::class, 'importForm']);
$router->post('/students/import',    [StudentController::class, 'import']);
$router->post('/students',           [StudentController::class, 'store']);
$router->get('/students/{id}',       [StudentController::class, 'show']);
$router->get('/students/{id}/edit',  [StudentController::class, 'edit']);
$router->get('/students/{id}/statement', [StudentController::class, 'statement']);
$router->post('/students/{id}',      [StudentController::class, 'update']);
$router->post('/students/{id}/delete', [StudentController::class, 'destroy']);

// --- Hostels & Rooms --------------------------------------------------------
$router->get('/hostels',            [HostelController::class, 'index']);
$router->get('/hostels/create',     [HostelController::class, 'create']);
$router->post('/hostels',           [HostelController::class, 'store']);
$router->get('/hostels/{id}',       [HostelController::class, 'show']);
$router->get('/hostels/{id}/edit',  [HostelController::class, 'edit']);
$router->post('/hostels/{id}',      [HostelController::class, 'update']);
$router->post('/hostels/{id}/delete', [HostelController::class, 'destroy']);

$router->get('/rooms',            [RoomController::class, 'index']);
$router->get('/rooms/create',     [RoomController::class, 'create']);
$router->get('/rooms/import',     [RoomController::class, 'importForm']);
$router->post('/rooms/import',    [RoomController::class, 'import']);
$router->post('/rooms',           [RoomController::class, 'store']);
$router->get('/rooms/{id}/edit',  [RoomController::class, 'edit']);
$router->post('/rooms/{id}',      [RoomController::class, 'update']);
$router->post('/rooms/{id}/delete', [RoomController::class, 'destroy']);

// --- Applications -----------------------------------------------------------
$router->get('/applications',            [ApplicationController::class, 'index']);
$router->post('/applications/toggle',    [ApplicationController::class, 'toggleOpen']);
$router->get('/applications/create',     [ApplicationController::class, 'create']);
$router->post('/applications',           [ApplicationController::class, 'store']);
$router->post('/applications/{id}/approve', [ApplicationController::class, 'approve']);
$router->post('/applications/{id}/reject',  [ApplicationController::class, 'reject']);
$router->post('/applications/{id}/waiting', [ApplicationController::class, 'waiting']);
$router->post('/applications/{id}/cancel',  [ApplicationController::class, 'cancel']);
$router->post('/applications/{id}/verify-payment', [ApplicationController::class, 'verifyPayment']);

// --- Allocations ------------------------------------------------------------
$router->get('/allocations',            [AllocationController::class, 'index']);
$router->get('/allocations/create',     [AllocationController::class, 'create']);
$router->post('/allocations',           [AllocationController::class, 'store']);
$router->post('/allocations/{id}/checkin',  [AllocationController::class, 'checkin']);
$router->post('/allocations/{id}/checkout', [AllocationController::class, 'checkout']);
$router->post('/allocations/{id}/cancel',   [AllocationController::class, 'cancel']);

// --- Room Transfers ---------------------------------------------------------
$router->get('/transfers',        [TransferController::class, 'index']);
$router->get('/transfers/create', [TransferController::class, 'create']);
$router->post('/transfers',       [TransferController::class, 'store']);

// --- Payments ---------------------------------------------------------------
$router->get('/payments',            [PaymentController::class, 'index']);
$router->get('/payments/callback',   [PaymentController::class, 'callback']);
$router->get('/payments/create',     [PaymentController::class, 'create']);
$router->post('/payments',           [PaymentController::class, 'store']);
$router->get('/payments/{id}/receipt', [PaymentController::class, 'receipt']);
$router->get('/payments/{id}/receipt-pdf', [PaymentController::class, 'receiptPdf']);
$router->get('/invoices',            [PaymentController::class, 'invoices']);
$router->post('/invoices/{id}/pay',  [PaymentController::class, 'payInvoice']);
$router->get('/charges/create',      [PaymentController::class, 'chargeForm']);
$router->post('/charges',            [PaymentController::class, 'storeCharge']);
$router->get('/fees',                [PaymentController::class, 'feeSchedule']);
$router->post('/fees',               [PaymentController::class, 'saveFeeSchedule']);
$router->post('/fees/dues-account',  [PaymentController::class, 'saveDuesAccount']);
$router->post('/fees/dues-notice',   [PaymentController::class, 'saveDuesNotice']);

// --- Hall dues debtors (arrears carried over from previous semesters) --------
// The literal paths are registered before the {id} ones: the router takes the
// first pattern that matches, so "/debtors/upload" must not be read as an id.
$router->get('/debtors',                      [DebtorController::class, 'index']);
$router->get('/debtors/upload',               [DebtorController::class, 'uploadForm']);
$router->post('/debtors/upload',              [DebtorController::class, 'upload']);
$router->get('/debtors/create',               [DebtorController::class, 'create']);
$router->post('/debtors',                     [DebtorController::class, 'store']);
$router->post('/debtors/batches/{id}/delete', [DebtorController::class, 'deleteBatch']);
$router->get('/debtors/{id}/edit',            [DebtorController::class, 'edit']);
$router->post('/debtors/{id}/clear',          [DebtorController::class, 'clear']);
$router->post('/debtors/{id}/restore',        [DebtorController::class, 'restore']);
$router->post('/debtors/{id}/delete',         [DebtorController::class, 'destroy']);
$router->post('/debtors/{id}',                [DebtorController::class, 'update']);

// --- Complaints -------------------------------------------------------------
$router->get('/complaints',            [ComplaintController::class, 'index']);
$router->get('/complaints/create',     [ComplaintController::class, 'create']);
$router->post('/complaints',           [ComplaintController::class, 'store']);
$router->post('/complaints/{id}/status', [ComplaintController::class, 'updateStatus']);

// --- Notices ----------------------------------------------------------------
$router->get('/notices',            [NoticeController::class, 'index']);
$router->get('/notices/create',     [NoticeController::class, 'create']);
$router->post('/notices',           [NoticeController::class, 'store']);
$router->post('/notices/{id}/delete', [NoticeController::class, 'destroy']);

// --- Visitors ---------------------------------------------------------------
$router->get('/visitors',            [VisitorController::class, 'index']);
$router->get('/visitors/create',     [VisitorController::class, 'create']);
$router->post('/visitors',           [VisitorController::class, 'store']);
$router->post('/visitors/{id}/approve',   [VisitorController::class, 'approve']);
$router->post('/visitors/{id}/checkin',   [VisitorController::class, 'checkin']);
$router->post('/visitors/{id}/checkout',  [VisitorController::class, 'checkout']);
$router->post('/visitors/{id}/blacklist', [VisitorController::class, 'blacklist']);

// --- Inventory --------------------------------------------------------------
$router->get('/inventory',            [InventoryController::class, 'index']);
$router->get('/inventory/create',     [InventoryController::class, 'create']);
$router->post('/inventory',           [InventoryController::class, 'store']);
$router->get('/inventory/{id}/edit',  [InventoryController::class, 'edit']);
$router->post('/inventory/{id}',      [InventoryController::class, 'update']);
$router->post('/inventory/{id}/delete', [InventoryController::class, 'destroy']);

// --- Users & Staff ----------------------------------------------------------
$router->get('/password-requests',              [PasswordResetController::class, 'index']);
$router->post('/password-requests/{id}/resolve', [PasswordResetController::class, 'resolve']);
$router->post('/password-requests/{id}/reject',  [PasswordResetController::class, 'reject']);

$router->get('/users',              [UserController::class, 'index']);
$router->get('/users/create',       [UserController::class, 'create']);
$router->post('/users',             [UserController::class, 'store']);
$router->get('/users/{id}/edit',    [UserController::class, 'edit']);
$router->post('/users/{id}',        [UserController::class, 'update']);
$router->post('/users/{id}/delete', [UserController::class, 'destroy']);
$router->post('/users/{id}/impersonate', [UserController::class, 'impersonate']);
$router->get('/impersonate/stop',        [UserController::class, 'stopImpersonating']);

// --- Reports / Audit / Settings ---------------------------------------------
$router->get('/reports',  [ReportController::class, 'index']);
$router->get('/reports/pdf', [ReportController::class, 'pdf']);
$router->get('/academic',  [AcademicController::class, 'index']);
$router->post('/academic', [AcademicController::class, 'update']);
$router->get('/audit',    [AuditController::class, 'index']);
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings',[SettingsController::class, 'update']);
$router->post('/settings/test-email', [SettingsController::class, 'testEmail']);

// --- CSV Exports ------------------------------------------------------------
$router->get('/export/students',     [ExportController::class, 'students']);
$router->get('/export/payments',     [ExportController::class, 'payments']);
$router->get('/export/invoices',     [ExportController::class, 'invoices']);
$router->get('/export/applications', [ExportController::class, 'applications']);
$router->get('/export/complaints',   [ExportController::class, 'complaints']);
$router->get('/export/occupancy',    [ExportController::class, 'occupancy']);
$router->get('/export/audit',        [ExportController::class, 'audit']);

// --- Hostel structure: blocks & floors --------------------------------------
$router->get('/hostels/{hostelId}/blocks',  [BlockController::class, 'index']);
$router->post('/hostels/{hostelId}/blocks', [BlockController::class, 'store']);
$router->post('/blocks/{id}',               [BlockController::class, 'update']);
$router->post('/blocks/{id}/delete',        [BlockController::class, 'destroy']);
$router->get('/blocks/{blockId}/floors',    [BlockController::class, 'floors']);
$router->post('/blocks/{blockId}/floors',   [BlockController::class, 'storeFloor']);
$router->post('/floors/{id}',               [BlockController::class, 'updateFloor']);
$router->post('/floors/{id}/delete',        [BlockController::class, 'destroyFloor']);
