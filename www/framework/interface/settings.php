<?php /**
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

    $action = $_REQUEST["action"];
    $subaction = $_REQUEST["subaction"];
    $finished = false;
    $html = new html();

    if ($subaction == "cancel") {
        $finished = true;
    } else if ($action == "user_administer") {
        //$finished = true;
    } else if ($action == "project_add" && $subaction == "add") {
        $projectname = $_REQUEST["projectname"];

        //test name for validity
        if (preg_match("/^([a-zA-Z][a-zA-Z0-9]+)$/", $projectname)) {
            $projects = $project->get_projects();
            if (isset($projects[$projectname])) {
                $error = $html->lang["inhtml_project_add_exists"];
            } else {
                // add project
                $projects = $project->add_new_project($projectname);
                
                $finished = true;
            }
        } else {
            $error = $html->lang["inhtml_project_add_wrong"];
        }
    }

    if ($finished) {
        header("Location: home.php");
    }

    $html->head();
    ?>
    <body>
        <?php
            echo($html->close_edit());
            
            if ($action == "user_administer") {
                echo($html->user_administer());
            } else if ($action == "project_add") {
                echo($html->project_add($projectname, $error));
            }

            echo($html->copyright_footer());
        ?>
    </body>
<?php
    $html->end();
?>
