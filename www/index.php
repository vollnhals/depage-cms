<?php
/**
 * @file    index.php
 *
 * index file
 *
 *
 * copyright (c) 2002-2010 Frank Hellenkamp [jonas@depagecms.net]
 *
 * @author    Frank Hellenkamp [jonas@depagecms.net]
 */
 
    define('IS_IN_CONTOOL', true);

    require_once('framework/lib/lib_global.php');
    require_once('lib_auth.php');
    require_once('lib_html.php');
    require_once('lib_project.php');

    $html = new html();

    if ($_GET['logout'] == "true") {
        $project->user->auth_http(true);
        $html->head();
        $html->message($html->lang["inhtml_logout_headline"], str_replace("%app_name%", $conf->app_name, $html->lang["inhtml_logout_text"]), "<p class=\"bottom right\">" . $html->lang["inhtml_logout_relogin"] . "</p>");
        $html->end();
    } else if ($_GET['logout'] == "done") {
        $project->user->logout($_COOKIE[session_name()]);

        session_id($_COOKIE[session_name()]);
        session_start();

        setcookie(session_name(), "", time() - 3600, $_SERVER['HTTP_HOST']);
        setcookie("depageUser", "", time() - 3600, $_SERVER['HTTP_HOST']);

        session_destroy();

        $html->head();
        $html->message($html->lang["inhtml_logout_headline"], str_replace("%app_name%", $conf->app_name, $html->lang["inhtml_logout_text"]), "<p class=\"bottom right\">" . $html->lang["inhtml_logout_relogin"] . "</p>");
        $html->end();
    } else {
        $project->user->auth_http();

        $html->head();
        $html->preview_frame();
        $html->end();
    }
