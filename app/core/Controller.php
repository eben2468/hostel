<?php
namespace App\Core;

/** Base controller with shared view/redirect/auth helpers. */
abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        $url = str_starts_with($path, 'http') ? $path : BASE_URL . $path;
        header('Location: ' . $url);
        exit;
    }

    protected function back(): void
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /** Require authentication; optionally restrict to roles. */
    protected function requireAuth(string ...$roles): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in to continue.');
            $this->redirect('/login');
        }
        if (!empty($roles) && !Auth::hasRole(...$roles)) {
            http_response_code(403);
            $this->view('errors/403', [], 'blank');
            exit;
        }
    }

    /**
     * Block access to a record that belongs to another hostel.
     *
     * Global users (the super admin) pass through. Hostel-bound users may only
     * touch records whose hostel matches their own; anything else is a 403.
     * Pass the record's resolved hostel id (direct column, or via student/room).
     */
    protected function guardHostel(?int $recordHostelId): void
    {
        if (Scope::isGlobal()) {
            return;
        }
        if ($recordHostelId === null || $recordHostelId !== Scope::hostelId()) {
            http_response_code(403);
            $this->view('errors/403', [], 'blank');
            exit;
        }
    }

    /** Read a POST field with optional trimming. */
    protected function input(string $key, $default = null)
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    /** Validate required fields are present and non-empty. */
    protected function validate(array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $label) {
            if (trim((string) ($_POST[$field] ?? '')) === '') {
                $errors[$field] = "{$label} is required.";
            }
        }
        return $errors;
    }
}
