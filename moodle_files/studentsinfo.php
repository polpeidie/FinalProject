<?php
// Este archivo es parte de Moodle - http://moodle.org/
//
// Moodle es software libre: puedes redistribuirlo y/o modificarlo
// bajo los términos de la Licencia Pública General de GNU según lo publicado por
// la Free Software Foundation, ya sea la versión 3 de la Licencia, o
// (a tu elección) cualquier versión posterior.
//
// Moodle se distribuye con la esperanza de que sea útil,
// pero SIN NINGUNA GARANTÍA; sin siquiera la garantía implícita de
// COMERCIABILIDAD o IDONEIDAD PARA UN PROPÓSITO PARTICULAR. Véase el
// Licencia Pública General de GNU para más detalles.
//
// Deberías haber recibido una copia de la Licencia Pública General de GNU
// junto con Moodle. Si no, véase <http://www.gnu.org/licenses/>.

/**
 * Información de los estudiantes
 *
 * @package    mod_attendance
 * @copyright  2024 Tu Nombre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 o posterior
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$courseid = required_param('courseid', PARAM_INT); // ID del curso
$attendanceid = required_param('attendanceid', PARAM_INT); // ID de la instancia de asistencia

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$att = $DB->get_record('attendance', array('id' => $attendanceid, 'course' => $courseid), '*', MUST_EXIST);

$cm = get_coursemodule_from_instance('attendance', $attendanceid, $courseid, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendance:takeattendances', $context);

$att = new mod_attendance_structure($att, $cm, $course, $context);

$PAGE->set_url('/mod/attendance/studentsinfo.php', array('courseid' => $courseid, 'attendanceid' => $attendanceid));
$PAGE->set_title($course->shortname . ": " . $att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);

$output = $PAGE->get_renderer('mod_attendance');

// Output starts here.
echo $output->header();
echo '<h2>Students info</h2>';

// Estilos CSS para la tabla
echo '<style>
.attendance-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.attendance-table th, .attendance-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.attendance-table th {
    background-color: #f2f2f2;
    color: black;
}

.attendance-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.attendance-table tr:hover {
    background-color: #f1f1f1;
}
</style>';

// Construir la tabla en una variable de cadena
$table_html = '<table class="attendance-table">';
$table_html .= '<thead>';
$table_html .= '<tr>';
$table_html .= '<th>Students</th>';
$table_html .= '<th>Total presents</th>';
$table_html .= '<th>Total absences</th>';
$table_html .= '<th>Total lates</th>';
$table_html .= '<th>Total excuses</th>';
$table_html .= '</tr>';
$table_html .= '</thead>';
$table_html .= '<tbody>';

// Obtener todos los estudiantes del curso
$users = get_enrolled_users($context);

// Obtener los IDs de los estados de asistencia para este attendanceid
$statuses = $DB->get_records_menu('attendance_statuses', array('attendanceid' => $attendanceid), '', 'acronym, id');
$present_id = $statuses['P'];
$absent_id = $statuses['A'];
$late_id = $statuses['L'];
$excused_id = $statuses['E'];

// Inicializar el array para almacenar datos de asistencia
$attendance_data = array();

foreach ($users as $user) {
    $attendance_data[$user->id] = (object) [
        'fullname' => fullname($user),
        'total_presents' => 0,
        'total_absences' => 0,
        'total_lates' => 0,
        'total_excuses' => 0,
    ];
}

// Obtener todas las sesiones de asistencia
$sessions = $DB->get_records('attendance_sessions', array('attendanceid' => $att->id));

// Recorrer cada sesión y acumular datos de asistencia
foreach ($sessions as $session) {
    $logs = $DB->get_records('attendance_log', array('sessionid' => $session->id));
    foreach ($logs as $log) {
        if (isset($attendance_data[$log->studentid])) {
            switch ($log->statusid) {
                case $present_id:
                    $attendance_data[$log->studentid]->total_presents++;
                    break;
                case $absent_id:
                    $attendance_data[$log->studentid]->total_absences++;
                    break;
                case $late_id:
                    $attendance_data[$log->studentid]->total_lates++;
                    break;
                case $excused_id:
                    $attendance_data[$log->studentid]->total_excuses++;
                    break;
            }
        }
    }
}

// Construir filas de la tabla con los datos de asistencia
foreach ($attendance_data as $data) {
    $table_html .= '<tr>';
    $table_html .= '<td>' . $data->fullname . '</td>';
    $table_html .= '<td>' . $data->total_presents . '</td>';
    $table_html .= '<td>' . $data->total_absences . '</td>';
    $table_html .= '<td>' . $data->total_lates . '</td>';
    $table_html .= '<td>' . $data->total_excuses . '</td>';
    $table_html .= '</tr>';
}

$table_html .= '</tbody>';
$table_html .= '</table>';

// Imprimir la tabla
echo $table_html;

echo $output->footer();
