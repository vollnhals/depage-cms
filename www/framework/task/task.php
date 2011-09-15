<?php

namespace depage\task;

class task {
    const STATUS_DONE = "done";
    const STATUS_FAILED = "failed";

    public function __construct($task_id_or_name, $table_prefix, $pdo) {
        $this->task_table = $table_prefix . "_tasks";
        $this->subtask_table = $table_prefix . "_subtasks";
        $this->pdo = $pdo;

        if (is_int($task_id_or_name))
            $this->task_id = $task_id_or_name;
        else
            $this->task_id = $this->create_task($task_id_or_name);

        $this->lock_name = sys_get_temp_dir() . '/' . $table_prefix . "." . $this->task_id . '.lock';

        $this->load_task();
        $this->load_subtasks();
    }

    /* get_status_info returns the current task status.
     * If this task instance has not run any subtasks by executing run_subtask(),
     * then $last_executed_subtask will only be set if the task failed,
     * $last_executed_subtask then contains the failed subtask.
     * $next_subtask will be set to the subtask that will be returned next by get_next_subtask().
     * This may be a dependent subtasks when resuming a task.
     */

    public function get_status_info(&$task_status, &$nr_subtasks_completed, &$nr_subtasks_total, &$last_executed_subtask, &$next_subtask) {
        $task_status = $this->task_status;
        $nr_subtasks_completed = $this->completed_subtask_count;
        $nr_subtasks_total = $this->total_subtask_count;
        $last_executed_subtask = $this->last_executed_subtask;
        $next_subtask = current($this->subtasks);
    }

    public function set_task_status($status) {
        $query = $this->pdo->prepare("UPDATE {$this->task_table} SET status = :status WHERE id = :id");
        $query->execute(array(
            "status" => $status,
            "id" => $this->task_id,
        ));
    }

    public function set_subtask_status($subtask, $status) {
        $query = $this->pdo->prepare("UPDATE {$this->subtask_table} SET status = :status WHERE id = :id");
        $query->execute(array(
            "status" => $status,
            "id" => $subtask->id,
        ));
    }

    public function get_next_subtask() {
        $subtask = current($this->subtasks);
        next($this->subtasks);

        return $subtask;
    }

    /* @return NULL|false returns NULL for no error, false for a parse error */
    public function run_subtask($subtask) {
        $this->last_executed_subtask = $subtask;
        return eval($subtask->php);
    }

    public function lock() {
        if (!empty($this->task_status))
            throw new \Exception("task was already run, status is: {$result->status}");

        $this->lock_file = fopen($this->lock_name, 'w');
        return flock($this->lock_file, LOCK_EX | LOCK_NB);
    }

    public function unlock() {
        flock($this->lock_file, LOCK_UN);
    }

    /* create_subtask only creates task in db.
     * the current instance is NOT modified.
     * reload task from the db if you want to execute subtasks.
     *
     * also see create_subtasks for more convenience.
     *
     * @return int return id of created subtask that can be used for depends_on
     */
    public function create_subtask($name, $php, $depends_on = NULL) {
        $query = $this->pdo->prepare("INSERT INTO {$this->subtask_table} (task_id, name, php, depends_on) VALUES (:task_id, :name, :php, :depends_on)");
        $query->execute(array(
            "task_id" => $this->task_id,
            "name" => $name,
            "php" => $php,
            "depends_on" => $depends_on,
        ));

        return $this->pdo->lastInsertId();
    }

    /* create_subtasks creates multiple subtasks.
     * specify tasks as an array of arrays containing name, php and depends_on keys.
     * depends_on references another task in this array by index.
     */
    public function create_subtasks($tasks) {
        foreach ($tasks as &$task) {
            if (!is_array($task))
                throw new \Exception ("malformed task array");

            if (isset($tasks[$task["depends_on"]]))
                $depends_on = $tasks[$task["depends_on"]]["id"];
            else
                $depends_on = NULL;

            $task["id"] = $this->create_subtask($task["name"], $task["php"], $depends_on);
        }
    }

    private function create_task($task_name) {
        $query = $this->pdo->prepare("INSERT INTO {$this->task_table} (name) VALUES (:name)");
        $query->execute(array(
            "name" => $task_name,
        ));

        return $this->pdo->lastInsertId();
    }

    private function load_task() {
        $query = $this->pdo->prepare("SELECT name, status FROM {$this->task_table} WHERE id = :id");
        $query->execute(array(
            "id" => $this->task_id,
        ));

        $result = $query->fetchObject();
        if (empty($result))
            throw new \Exception("no such task");

        $this->task_name = $result->name;
        $this->task_status = $result->status;
    }

    private function load_subtasks() {
        $query = $this->pdo->prepare(
            "SELECT *
            FROM {$this->subtask_table}
            WHERE task_id = :task_id
            ORDER BY id ASC"
        );
        $query->execute(array(
            "task_id" => $this->task_id,
        ));

        $subtasks = $query->fetchAll(\PDO::FETCH_OBJ);
        $this->total_subtask_count = count($subtasks);
        $this->completed_subtask_count = 0;
        $this->subtasks = array();
        $id_to_subtask = array();

        foreach ($subtasks as $subtask) {
            $id_to_subtask[$subtask->id] = $subtask;

            if (empty($subtask->status)) {
                $this->subtasks[$subtask->id] = $subtask;
                $this->include_dependent_subtasks($subtask, $id_to_subtask);
            } else if ($subtask->status == self::STATUS_DONE) {
                $this->completed_subtask_count += 1;
            } else if (substr($subtask->status, 0, strlen(self::STATUS_FAILED)) == self::STATUS_FAILED) {
                // this task failed, therefore set last executed subtask to failed one
                $this->last_executed_subtask = $subtask;
            }

        }

        ksort($this->subtasks);
    }

    private function include_dependent_subtasks($subtask, &$id_to_subtask) {
        while ($subtask->depends_on) {
            $subtask = $id_to_subtask[$subtask->depends_on];
            $this->subtasks[$subtask->id] = $subtask;
        }
    }
}


/* vim:set ft=php sw=4 sts=4 fdm=marker : */
