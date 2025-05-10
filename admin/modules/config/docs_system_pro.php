<?php
// Admin module for Docs System Pro

if(!defined("IN_MYBB") || !defined("IN_ADMINCP")) {
    die("Direct access not allowed.");
}

$page->add_breadcrumb_item("Docs System Pro", "index.php?module=config-docs_system_pro");

$sub_tabs['categories'] = [
    'title' => 'Categories',
    'link' => 'index.php?module=config-docs_system_pro&action=categories',
    'description' => 'Manage documentation categories.'
];

$sub_tabs['pages'] = [
    'title' => 'Pages',
    'link' => 'index.php?module=config-docs_system_pro&action=pages',
    'description' => 'Manage documentation pages.'
];

$mybb->input['action'] = $mybb->get_input('action');

if ($mybb->input['action'] == 'categories') {
    $page->output_header("Docs Categories");
    $page->output_nav_tabs($sub_tabs, 'categories');
    echo "<div class='alert alert-info'>Category management UI coming soon.</div>";
    $page->output_footer();
} elseif ($mybb->input['action'] == 'pages') {
    $page->output_header("Docs Pages");
    $page->output_nav_tabs($sub_tabs, 'pages');
    echo "<div class='alert alert-info'>Page management UI coming soon.</div>";
    $page->output_footer();
} else {
    $page->output_header("Docs System Pro");
    $page->output_nav_tabs($sub_tabs, 'categories');
    echo "<div class='alert alert-info'>Select a section to manage.</div>";
    $page->output_footer();
}
?>
