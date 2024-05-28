<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Attendance module renderable component.
 *
 * @package    mod_attendance
 * @copyright  2022 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\output;

use renderable;
use mod_attendance_structure;
use moodle_url;
use stdClass;
use mod_attendance\local\url_helpers;

class take_data implements renderable {
    public $users;
    public $pageparams;
    public $groupmode;
    public $cm;
    public $statuses;
    public $sessioninfo;
    public $sessionlog;
    public $sessions4copy;
    public $updatemode;
    private $urlpath;
    private $urlparams;
    public $att;

    public function __construct(mod_attendance_structure $att) {
        if ($att->pageparams->grouptype) {
            $this->users = $att->get_users($att->pageparams->grouptype, $att->pageparams->page);
        } else {
            $this->users = $att->get_users($att->pageparams->group, $att->pageparams->page);
        }
    
        if ($this->users === null) {
            $this->users = []; // O manejar el error de alguna otra forma apropiada
        }

        $this->pageparams = $att->pageparams;
        $this->groupmode = $att->get_group_mode();
        $this->cm = $att->cm;
        $this->statuses = $att->get_statuses();
        $this->sessioninfo = $att->get_session_info($att->pageparams->sessionid);
        $this->updatemode = $this->sessioninfo->lasttaken > 0;

        if (isset($att->pageparams->copyfrom)) {
            $this->sessionlog = $att->get_session_log($att->pageparams->copyfrom);
        } else if ($this->updatemode) {
            $this->sessionlog = $att->get_session_log($att->pageparams->sessionid);
        } else {
            $this->sessionlog = array();
        }

        if (!$this->updatemode) {
            $this->sessions4copy = $att->get_today_sessions_for_copy($this->sessioninfo);
        }

        $this->urlpath = $att->url_take()->out_omit_querystring();
        $params = $att->pageparams->get_significant_params();
        $params['id'] = $att->cm->id;
        $this->urlparams = $params;

        $this->att = $att;
    }

    public function url($params=array(), $excludeparams=array()) {
        $params = array_merge($this->urlparams, $params);

        foreach ($excludeparams as $paramkey) {
            unset($params[$paramkey]);
        }

        return new moodle_url($this->urlpath, $params);
    }

    public function url_view($params=array()) {
        return url_helpers::url_view($this->att, $params);
    }

    public function url_path() {
        return $this->urlpath;
    }

    // Este es un archivo predeterminado de Moodle, a partir de aqui son funciones creadas por nosotros

    public function col_total_absences($user) {
        global $DB;

        $sql = "SELECT COUNT(*) 
                FROM {attendance_log} al 
                JOIN {attendance_statuses} ast ON al.statusid = ast.id 
                WHERE al.studentid = :userid AND ast.acronym = 'A'";
        $params = ['userid' => $user->id];
        return $DB->count_records_sql($sql, $params);
    }

    public function col_total_lates($user) {
        global $DB;

        $sql = "SELECT COUNT(*) 
                FROM {attendance_log} al 
                JOIN {attendance_statuses} ast ON al.statusid = ast.id 
                WHERE al.studentid = :userid AND ast.acronym = 'L'";
        $params = ['userid' => $user->id];
        return $DB->count_records_sql($sql, $params);
    }

    public function col_total_presents($user) {
        global $DB;

        $sql = "SELECT COUNT(*) 
                FROM {attendance_log} al 
                JOIN {attendance_statuses} ast ON al.statusid = ast.id 
                WHERE al.studentid = :userid AND ast.acronym = 'P'";
        $params = ['userid' => $user->id];
        return $DB->count_records_sql($sql, $params);
    }

    public function col_total_excuses($user) {
        global $DB;

        $sql = "SELECT COUNT(*) 
                FROM {attendance_log} al 
                JOIN {attendance_statuses} ast ON al.statusid = ast.id 
                WHERE al.studentid = :userid AND ast.acronym = 'E'";
        $params = ['userid' => $user->id];
        return $DB->count_records_sql($sql, $params);
    }

    public function get_user_columns($user) {
        $columns = new stdClass();
        $columns->fullname = fullname($user);
        $columns->total_absences = $this->col_total_absences($user);
        $columns->total_lates = $this->col_total_lates($user);
        $columns->total_presents = $this->col_total_presents($user);
        $columns->total_excuses = $this->col_total_excuses($user);
        return $columns;
    }

    public function get_rows() {
        $rows = [];

        foreach ($this->users as $user) {
            $rows[] = $user; // Aquí simplemente devuelve el usuario para luego obtener las columnas
        }

        return $rows;
    }
}
