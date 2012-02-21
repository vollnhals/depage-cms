<?php

require_once("framework/depage/depage.php");
// TODO: convert to autoloader
require_once("framework/websocket/lib/WebSocket/Application/Application.php");

class JsTreeApplication extends \Websocket\Application\Application {
    private $clients = array();
    private $delta_updates = array();
    protected $defaults = array(
        "db" => null,
        "auth" => null,
        'env' => "development",
        'timezone' => "UST",
    );

    function __construct() {
        parent::__construct();

        $conf = new config();
        $conf->readConfig(__DIR__ . "/../../../conf/dpconf.php");
        $this->options = $conf->getFromDefaults($this->defaults);

        // get database instance
        $this->pdo = new \db_pdo (
            $this->options->db->dsn, // dsn
            $this->options->db->user, // user
            $this->options->db->password, // password
            array(
                'prefix' => $this->options->db->prefix, // database prefix
            )
        );

        // TODO: websocket needs authentication.
        // when using auth_http_cookie, then the cookie can be transmitted in
        // the first message and authentication can happen in on_data().
        
        /* get auth object
        $this->auth = \auth::factory(
            $this->pdo, // db_pdo 
            $this->options->auth->realm, // auth realm
            DEPAGE_BASE, // domain
            $this->options->auth->method // method
        ); */
    }

    public function onConnect($client)
    {
        // TODO: authentication ? beware of timeouts
        // $client->param is a string of the format "{$project_name}/{$doc_id}"

        if (empty($this->clients[$client->param])) {
            $this->clients[$client->param] = array();

            list($project_name, $doc_id) = explode("/", $client->param);
            $prefix = "{$this->pdo->prefix}_{$project_name}";
            $xmldb = new \depage\xmldb\xmldb ($prefix, $this->pdo, \depage\cache\cache::factory($prefix));

            $this->delta_updates[$client->param] = new \depage\websocket\jstree\jstree_delta_updates($prefix, $this->pdo, $xmldb, $doc_id);
        }

        $this->clients[$client->param][] = $client;
    }

    public function onDisconnect($client)
    {
        $key = array_search($client, $this->clients[$client->param]);
        if ($key) {
            unset($this->clients[$client->param][$key]);

            if (empty($this->clients[$client->param])) {
                unset($this->delta_updates[$client->param]);
            }
        }
    }

    public function onTick() {
        foreach ($this->clients as $project_name_and_doc_id => $clients) {
            $data = $this->delta_updates[$project_name_and_doc_id]->encodedDeltaUpdate();

            if (!empty($data)) {
                // send to clients
                foreach ($clients as $client) {
                    $client->send($data);
                }
            }
        }

        // do not sleep too long, this impacts new incoming connections
        usleep(50 * 1000);
    }

    public function onData($raw_data, $client)
    {
        // do nothing, only send data in onTick() because fallback clients do not support websockets
    }
}

?>
