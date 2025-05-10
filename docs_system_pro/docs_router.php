<?php
// Frontend router for Docs System Pro

define('THIS_SCRIPT', 'docs.php');

require_once './global.php';
require_once MYBB_ROOT . 'docs_system_pro/markdown/Parsedown.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $path);

$category_slug = $parts[1] ?? '';
$page_slug = $parts[2] ?? '';

$category = $db->fetch_array($db->simple_select('docs_categories', '*', "slug='" . $db->escape_string($category_slug) . "'"));
if (!$category) {
    error("Category not found");
}

$page_sql = "category_id='{$category['id']}'";
if ($page_slug) {
    $page_sql .= " AND slug='" . $db->escape_string($page_slug) . "'";
}

$page = $db->fetch_array($db->simple_select('docs_pages', '*', $page_sql));
if (!$page) {
    error("Page not found");
}

$parser = new Parsedown();
$parsed_content = $parser->text($page['content']);

eval("\$content = "" . $templates->get("docs_page_view") . "";");
output_page($content);
?>
