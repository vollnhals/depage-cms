<!DOCTYPE html
PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>assets</title>

    <base href="<?php html::base(); ?>" />

    <?php // TODO: use jquery 1.4.4
        $this->include_js("jquery", array(
        "../framework/shared/jquery-1.4.2.js",
        "../framework/shared/jquery.cookie.js",
        "../framework/shared/jquery.hotkeys.js",
    )); ?>
    <?php $this->include_js("jstree", array(
        "../framework/cms/js/jstree.js",
        "../framework/cms/js/jquery.jstree.js",
        "../framework/cms/js/jquery.jstree.plugins.js",
        "../framework/shared/jquery.json-2.2.js",
        "../framework/shared/jquery.gracefulWebSocket.js",
    )); ?>

</head>
<body>
<div id="container">

    <form method="get">
        <input type="text" name="query" size="40" value="<?php echo $_GET["query"]; ?>"/><br />
        <input type="radio" name="filters[filetype]" value="jpg">jpg</input>
        <input type="radio" name="filters[filetype]" value="png">png</input>
        <input type="submit" value="Suchen" />
    </form>

    <!-- the tree container (notice NOT an UL node) -->
    <div
        id="node_<?php echo $this->root_id; ?>"
        class="jstree-container"
        data-doc-id = "<?php echo $this->doc_id; ?>"
        data-seq-nr = "<?php echo $this->seq_nr; ?>"
        data-selected-nodes = ""
        data-open-nodes = "all"
        data-theme = "../framework/cms/css/assets.css"
        data-delta-updates-websocket-url = "ws://127.0.0.1:8000/jstree/"
        data-delta-updates-fallback-poll-url = "./fallback/updates/"
        data-delta-updates-post-url = "./"
        data-types-settings-url = "./types_settings/"
        data-add-marker-special-children = "folder separator"
    >
        <?php echo $this->nodes; ?>
    </div>

</div>

</body>
</html>
