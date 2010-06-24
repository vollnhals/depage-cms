<?php
/**
 * @file    home.php
 *
 * index file
 *
 *
 * copyright (c) 2002-2010 Frank Hellenkamp [jonas@depagecms.net]
 *
 * @author    Frank Hellenkamp [jonas@depagecms.net]
 */
 
    define('IS_IN_CONTOOL', true);

    require_once('../lib/lib_global.php');
    require_once('lib_auth.php');
    require_once('lib_html.php');
    require_once('lib_files.php');
    require_once('lib_tpl_xslt.php');
    require_once('lib_pocket_server.php');
    require_once('lib_tasks.php');

    $project->user->auth_http();

    if ($_COOKIE["depage-import-project"] != "" && $_COOKIE["depage-import-project"] != "deleted") {
        $_SESSION["depage-import-project"] = $_COOKIE["depage-import-project"];
        $_SESSION["depage-import-filename"] = $_COOKIE["depage-import-filename"];

        setcookie("depage-import-project", "", time() - 3600, $_SERVER['HTTP_HOST']);
        setcookie("depage-import-filename", "", time() - 3600, $_SERVER['HTTP_HOST']);
    }

    $log->add_varinfo($_SESSION);

    $html = new html();

    $html->head();
    ?>
    <body>
        <?php
            echo($html->close_edit());
            $projectpath = $project->get_project_path($_SESSION["depage-import-project"]);
            $importpath = "$projectpath/import/";
            $importfile = "{$importpath}{$_SESSION["depage-import-filename"]}";

            $log->add_entry("importfile: $importfile");

            if ($_SESSION["depage-import-filename"] != "" && $_SESSION["depage-import-project"] != "") {
                echo($html->box_start("import", "first big"));
                include("{$importpath}import.php");
                echo($html->box_end());
            } else {
                die_error("%inhtml_no_import%");
            }
        ?>
    </body>
<?php
    $html->end();
?>
