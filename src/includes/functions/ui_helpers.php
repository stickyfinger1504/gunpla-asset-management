<?php

/**
 * Get a consistent color palette class based on an ID.
 * @param int $id The ID to hash/modulo
 * @return string Tailwind CSS class string
 */
function get_brand_color_palette($id) {
    $palette = [
        'bg-blue-100 text-blue-800',
        'bg-green-100 text-green-800',
        'bg-yellow-100 text-yellow-800',
        'bg-red-100 text-red-800',
        'bg-purple-100 text-purple-800',
        'bg-pink-100 text-pink-800',
        'bg-cyan-100 text-cyan-800',
        'bg-lime-100 text-lime-800',
        'bg-orange-100 text-orange-800',
        'bg-indigo-100 text-indigo-800',
    ];
    
    return $palette[($id ?? 0) % count($palette)];
}

/**
 * Get the color class for paint amount levels.
 * @param string $amount_label The amount string (e.g. "Full", "Empty")
 * @return string Tailwind CSS class string
 */
function get_paint_amount_class($amount_label) {
    if (empty($amount_label) || $amount_label === '-') {
        return 'bg-gray-100 text-gray-800';
    }
    
    $amount_lower = strtolower($amount_label);
    
    if (str_contains($amount_lower, 'full')) {
        return 'bg-green-100 text-green-800';
    } elseif (str_contains($amount_lower, 'high') || str_contains($amount_lower, '75') || str_contains($amount_lower, 'most')) {
        return 'bg-blue-100 text-blue-800';
    } elseif (str_contains($amount_lower, 'half') || str_contains($amount_lower, 'mid') || str_contains($amount_lower, '50')) {
        return 'bg-yellow-100 text-yellow-800';
    } elseif (str_contains($amount_lower, 'low') || str_contains($amount_lower, '25')) {
        return 'bg-orange-100 text-orange-800';
    } elseif (str_contains($amount_lower, 'empty') || str_contains($amount_lower, '0')) {
        return 'bg-red-100 text-red-800';
    } else {
        return 'bg-gray-100 text-gray-800';
    }
}
