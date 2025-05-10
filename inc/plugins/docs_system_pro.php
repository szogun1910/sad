<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// docs_system_pro.php - Poprawiona wersja

if (!defined("IN_MYBB")) die("Bezpośredni dostęp zabroniony.");

function docs_system_pro_info()
{
    return [
        "name" => "Docs System Pro",
        "description" => "Zaawansowany system dokumentacji z kategoriami, markdown i wyszukiwarką.",
        "author" => "szogun",
        "version" => "1.0.2",
        "compatibility" => "18*"
    ];
}

// Funkcja logująca błędy
function docs_system_pro_log($message)
{
    global $mybb;
    if (docs_system_pro_is_debug_mode()) {
        $log_file = MYBB_ROOT . 'docs_system_pro_error.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

// Sprawdź tryb debugowania
function docs_system_pro_is_debug_mode()
{
    global $mybb;
    return isset($mybb->settings['docs_system_pro_debug_mode']) && $mybb->settings['docs_system_pro_debug_mode'] == 1;
}

function docs_system_pro_install()
{
    global $db, $mybb, $lang;

    $lang->load('docs_system_pro');

    // Sprawdź silnik bazy danych
    if ($db->engine != 'innodb') {
        flash_message("Błąd: Silnik bazy danych musi być InnoDB", "error");
        return false;
    }

    // Utwórz tabelę kategorii z obsługą błędów
    if (!$db->table_exists("docs_categories")) {
        $query = "
            CREATE TABLE " . TABLE_PREFIX . "docs_categories (
                id INT(11) NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT NULL,
                disporder INT(11) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $db->write_query($query);

        if ($db->error_number()) {
            docs_system_pro_log("Błąd tworzenia tabeli kategorii: " . $db->error_string());
            flash_message("Błąd tworzenia tabeli kategorii: " . $db->error_string(), "error");
            return false;
        }
    }

    // Utwórz tabelę stron z obsługą błędów
    if (!$db->table_exists("docs_pages")) {
        $query = "
            CREATE TABLE " . TABLE_PREFIX . "docs_pages (
                id INT(11) NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                category_id INT(11) NOT NULL,
                disporder INT(11) DEFAULT 0,
                visible_to TEXT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug_category (slug, category_id),
                FOREIGN KEY (category_id) 
                    REFERENCES " . TABLE_PREFIX . "docs_categories(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $db->write_query($query);

        if ($db->error_number()) {
            docs_system_pro_log("Błąd tworzenia tabeli stron: " . $db->error_string());
            $db->drop_table(TABLE_PREFIX . "docs_pages");
            flash_message("Błąd tworzenia tabeli stron: " . $db->error_string(), "error");
            return false;
        }
    }

    // Dodaj grupę ustawień
    $setting_group = [
        "name" => "docs_system_pro",
        "title" => "Ustawienia Docs System Pro",
        "description" => "Konfiguracja systemu dokumentacji",
        "disporder" => 100,
        "isdefault" => 0
    ];

    $gid = $db->insert_query("settinggroups", $setting_group);

    // Dodaj podstawowe ustawienia
    $settings = [
        [
            "name" => "docs_system_pro_enabled",
            "title" => "Włącz system dokumentacji",
            "description" => "Czy system dokumentacji ma być aktywny?",
            "optionscode" => "yesno",
            "value" => "1",
            "disporder" => 1,
            "gid" => $gid
        ],
        [
            "name" => "docs_system_pro_default_category",
            "title" => "Domyślna kategoria",
            "description" => "Slug domyślnej kategorii dokumentacji",
            "optionscode" => "text",
            "value" => "getting-started",
            "disporder" => 2,
            "gid" => $gid
        ],
        [
            "name" => "docs_system_pro_debug_mode",
            "title" => "Tryb debugowania",
            "description" => "Czy włączyć tryb debugowania?",
            "optionscode" => "yesno",
            "value" => "0",
            "disporder" => 3,
            "gid" => $gid
        ]
    ];

    foreach ($settings as $setting) {
        $db->insert_query("settings", $setting);
    }

    rebuild_settings();

    flash_message("Wtyczka Docs System Pro została pomyślnie zainstalowana.", "success");
}

function docs_system_pro_is_installed()
{
    global $db;
    $tables_exist = $db->table_exists(TABLE_PREFIX . "docs_categories") &&
                    $db->table_exists(TABLE_PREFIX . "docs_pages");

    $group_exists = $db->fetch_field(
        $db->simple_select("settinggroups", "gid", "name='docs_system_pro'"),
        "gid"
    );

    return $tables_exist && $group_exists;
}

function docs_system_pro_uninstall()
{
    global $db, $cache;

    // Usuń tabele z obsługą błędów
    $errors = [];

    if ($db->table_exists(TABLE_PREFIX . "docs_pages")) {
        if (!$db->drop_table(TABLE_PREFIX . "docs_pages")) {
            $errors[] = "Błąd usuwania tabeli docs_pages: " . $db->error_string();
            docs_system_pro_log($errors[count($errors) - 1]);
        }
    }

    if ($db->table_exists(TABLE_PREFIX . "docs_categories")) {
        if (!$db->drop_table(TABLE_PREFIX . "docs_categories")) {
            $errors[] = "Błąd usuwania tabeli docs_categories: " . $db->error_string();
            docs_system_pro_log($errors[count($errors) - 1]);
        }
    }

    // Usuń ustawienia
    $db->delete_query("settings", "gid IN (SELECT gid FROM " . TABLE_PREFIX . "settinggroups WHERE name='docs_system_pro')");
    $db->delete_query("settinggroups", "name='docs_system_pro'");

    // Usuń szablony
    $db->delete_query("templates", "title IN ('docs_nav_sidebar','docs_page_view','docs_search_results') AND sid='-1'");

    // Oczyść cache
    $cache->update_docs();

    if (!empty($errors)) {
        foreach ($errors as $error) {
            flash_message($error, "error");
        }
        return false;
    }

    return true;
}

function docs_system_pro_activate()
{
    global $db, $mybb, $lang, $cache;

    $lang->load('docs_system_pro');

    // Sprawdź czy tabele istnieją
    if (!docs_system_pro_is_installed()) {
        flash_message("Błąd: Wymagane tabele nie istnieją w bazie danych", "error");
        return false;
    }

    // Dodaj szablony z obsługą błędów
    $template_files = [
        'docs_nav_sidebar' => 'docs_nav_sidebar.html',
        'docs_page_view' => 'docs_page_view.html',
        'docs_search_results' => 'docs_search_results.html'
    ];

    foreach ($template_files as $title => $file) {
        $path = MYBB_ROOT . "inc/plugins/docs_system_pro/{$file}";

        if (!file_exists($path)) {
            docs_system_pro_log("Brak pliku szablonu: {$file}");
            flash_message("Brak pliku szablonu: {$file}", "error");
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            docs_system_pro_log("Nie można odczytać pliku: {$file}");
            flash_message("Nie można odczytać pliku: {$file}", "error");
            continue;
        }

        $insert = [
            "title" => $title,
            "template" => $db->escape_string($content),
            "sid" => "-1",
            "version" => $mybb->version_code,
            "dateline" => TIME_NOW
        ];

        $db->insert_query("templates", $insert);

        if ($db->error_number()) {
            docs_system_pro_log("Błąd SQL przy dodawaniu szablonu {$title}: " . $db->error_string());
            flash_message("Błąd SQL przy dodawaniu szablonu {$title}: " . $db->error_string(), "error");
        }
    }

    // Dodaj dokumentację do cache
    $cache->update_docs();

    // Zmodyfikuj header
    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
    find_replace_templatesets(
        "header",
        "#".preg_quote('{$header}')."#i",
        '{$header}{$docs_navbar}'
    );

    flash_message($lang->docs_system_pro_activate_success, "success");
    return true;
}

function docs_system_pro_deactivate()
{
    global $db;

    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

    // Remove navbar from header
    find_replace_templatesets("header", "#".preg_quote('{$docs_navbar}')."#i", "");

    // Remove templates
    $db->delete_query("templates", "title IN ('docs_nav_sidebar','docs_page_view') AND sid='-1'");
}

function docs_system_pro_route(&$page)
{
    global $mybb;

    if (strpos($mybb->get_input('REQUEST_URI'), "/docs") !== false) {
        define("IN_DOCS_SYSTEM", 1);

        // Loguj informacje o żądaniu
        $request_info = "Routing request: " . $mybb->get_input('REQUEST_URI');
        docs_system_pro_log($request_info);

        require_once MYBB_ROOT . "inc/plugins/docs_system_pro/docs_router.php";
        exit;
    }

    return $page;
}