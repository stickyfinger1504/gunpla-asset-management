<?php
/**
 * Fallback Router
 * 
 * This only runs when Nginx can't find a direct file match.
 * Use this for dynamic routes like /kit/123 or API endpoints.
 */

// Change to the src directory so relative paths work
chdir(__DIR__);

require 'includes/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// ============================================
// DYNAMIC ROUTES
// Add routes here that can't be simple files
// ============================================
$dynamicRoutes = [
    
    '#^/$#' => function($matches) {
        header('Location: /inventory');
        exit;
    },
    '#^/kit/(\d+)$#' => function($matches) {
        $_GET['id'] = $matches[1];
        return 'pages/kit_detail.php';
    },
    '#^/paint/(\d+)$#' => function($matches) {
        $_GET['id'] = $matches[1];
        return 'pages/paint_detail.php';
    },
    '#^/tool/(\d+)$#' => function($matches) {
        $_GET['id'] = $matches[1];
        return 'pages/tool_detail.php';
    },

];

// Try to match a dynamic route
foreach ($dynamicRoutes as $pattern => $handler) {
    if (preg_match($pattern, $uri, $matches)) {
        $result = $handler($matches);
        // If handler returned a file path, include it
        if ($result) {
            $file = __DIR__ . '/' . $result;
            if (file_exists($file)) {
                // Change to the file's directory so relative paths work
                chdir(dirname($file));
                require $file;
                exit;
            }
        }
    }
}

// ============================================
// 404 HANDLING
// ============================================
http_response_code(404);

$current_section = 'error';
$current_page = '404';
$page_title = 'Page Not Found';

if (file_exists(__DIR__ . '/pages/404.php')) {
    chdir(__DIR__ . '/pages');
    require __DIR__ . '/pages/404.php';
} else {
    include 'components/layout_header.php';
    echo "<div class='max-w-5xl mx-auto w-full text-center py-20'>";
    echo "<h1 class='text-6xl mb-4'>404</h1>";
    echo "<p class='text-xl text-gray-600 mb-8'>Page not found: <code>" . htmlspecialchars($uri) . "</code></p>";
    echo "<a href='/inventory' class='bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg'>Go to Inventory</a>";
    echo "</div>";
    include 'components/layout_footer.php';
}
