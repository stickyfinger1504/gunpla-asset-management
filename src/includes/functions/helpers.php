<?php
/**
 * Helper functions
 * Shared utilities for formatting, validation, and common operations
 */

function format_currency($amount, $currency = 'Rp.') {
    if (!$amount) return '-';
    return $currency . ' ' . number_format($amount, 0, ',', '.');
}

function format_date($date, $format = 'd M Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function page_url($page) {
    return '/' . ltrim($page, '/');
}

/**
 * Calculate statistics from a kits array
 * Works with filtered or unfiltered data
 * 
 * @param array $kits Array of kit records from get_kit_inventory()
 * @return array Calculated statistics
 */
function calculate_kit_stats($kits) {
    $stats = [
        'total_kits' => count($kits),
        'total_spent' => 0,
        'avg_price' => 0,
        'max_price' => 0,
        'min_price' => PHP_INT_MAX,
        'status_counts' => [],
        'brand_counts' => [],
        'monthly_spending' => [],
        'monthly_purchases' => [],
        'yearly_purchases' => [],
    ];
    
    if (empty($kits)) {
        $stats['min_price'] = 0;
        return $stats;
    }
    
    foreach ($kits as $kit) {
        $price = (int)($kit['pricebought'] ?? 0);
        
        $stats['total_spent'] += $price;
        if ($price > 0) {
            $stats['max_price'] = max($stats['max_price'], $price);
            $stats['min_price'] = min($stats['min_price'], $price);
        }
        
        $status = $kit['status'] ?? 'Unknown';
        $stats['status_counts'][$status] = ($stats['status_counts'][$status] ?? 0) + 1;
        
        $brand = $kit['brand'] ?? 'Unknown';
        $stats['brand_counts'][$brand] = ($stats['brand_counts'][$brand] ?? 0) + 1;
        
        if (!empty($kit['datebought'])) {
            $month_key = date('Y-m', strtotime($kit['datebought']));
            $year_key = date('Y', strtotime($kit['datebought']));
            $stats['monthly_spending'][] = [
                'x' => $kit['datebought'],
                'y' => $price,
                'name' => $kit['name'] ?? 'Unknown'
            ];
            $stats['monthly_purchases'][$month_key] = ($stats['monthly_purchases'][$month_key] ?? 0) + 1;
            $stats['yearly_purchases'][$year_key] = ($stats['yearly_purchases'][$year_key] ?? 0) + 1;
        }
    }
    
    usort($stats['monthly_spending'], fn($a, $b) => strcmp($a['x'], $b['x']));
    ksort($stats['monthly_purchases']);
    ksort($stats['yearly_purchases']);
    
    $stats['avg_price'] = $stats['total_kits'] > 0 

        ? round($stats['total_spent'] / $stats['total_kits']) 
        : 0;
    
    if ($stats['min_price'] === PHP_INT_MAX) {
        $stats['min_price'] = 0;
    }
    
    return $stats;
}
