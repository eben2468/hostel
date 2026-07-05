<?php
namespace App\Core;

use RuntimeException;

/** Renders PHP view templates with a shared layout. */
class View
{
    /**
     * Render a view inside a layout.
     *
     * @param string $view   dot/slash path under app/views (e.g. "students/index")
     * @param array  $data   variables exposed to the view
     * @param string $layout layout file under app/views/layouts (default "app")
     */
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        // Capture the inner view into $content for the layout.
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null || $layout === '') {
            echo $content;
            return;
        }

        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout not found: {$layout}");
        }
        require $layoutFile;
    }
}
