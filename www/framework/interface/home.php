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

    $html = new html();

    $html->head();
    ?>
    <body>
        <?php
            echo($html->close_edit());
            echo($html->project_listing());

            echo($html->box("<div id=\"tasks\">" . $html->task_status() . "</div>", "tasks"));
            if ($project->user->get_level_by_sid() <= 2) {
                echo($html->box("<div id=\"users\">" . $html->user_status() . "</div>", "users"));
            }
            echo($html->copyright_footer());
        ?>
    </body>
<?php
    $html->end();
?>
