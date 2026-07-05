<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;

class AuditController extends Controller
{
    public function index(): void
    {
        $this->requireAuth('admin');

        $module = trim($_GET['module'] ?? '');
        $where = $module !== '' ? ' WHERE a.module = ?' : '';
        $params = $module !== '' ? [$module] : [];

        $pager = Paginator::make(
            "SELECT COUNT(*) FROM audit_logs a{$where}",
            "SELECT a.*, u.name AS user_name
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id{$where}
             ORDER BY a.created_at DESC",
            $params, Paginator::currentPage(), 25
        );

        $modules = Database::all("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module");

        $this->view('audit/index', [
            'pageTitle' => 'Audit Logs',
            'logs'      => $pager['rows'],
            'pager'     => $pager,
            'modules'   => array_column($modules, 'module'),
            'module'    => $module,
        ]);
    }
}
