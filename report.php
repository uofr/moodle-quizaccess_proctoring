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
 * Report for the quizaccess_proctoring plugin.
 *
 * @package    quizaccess_proctoring
 * @copyright  2020 Brain Station 23 <moodle@brainstation-23.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */



require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/lib/tablelib.php');

require_login();

global $CFG, $DB, $USER;

// Get vars.
$courseid = required_param('courseid',  PARAM_INT);
$cmid = required_param('cmid',  PARAM_INT);
$studentid = optional_param('studentid', '', PARAM_INT);
$reportid = optional_param('reportid', '', PARAM_INT);

$context = context_module::instance($cmid, MUST_EXIST);

$COURSE = $DB->get_record('course', array('id' => $courseid));
$quiz = $DB->get_record('quiz', array('id' => $cmid));

$url = new moodle_url(
    '/mod/quiz/accessrule/proctoring/report.php',
    array(
        'courseid' => $courseid,
        'userid' => $studentid,
        'quizid' => $cmid
    )
);

$PAGE->set_url($url);
$PAGE->set_pagelayout('course');
$PAGE->set_title($COURSE->shortname . ': ' . get_string('pluginname', 'quizaccess_proctoring'));
$PAGE->set_heading($COURSE->fullname . ': ' . get_string('pluginname', 'quizaccess_proctoring'));

$PAGE->navbar->add(get_string('administrationsite'), new \moodle_url('/admin/search.php'));
$PAGE->navbar->add(get_string('plugins', 'admin'), new \moodle_url('/admin/category.php?category=modules'));
$PAGE->navbar->add(get_string('activitymodules'), new \moodle_url('/admin/category.php?category=modsettings'));
$PAGE->navbar->add(
    get_string('pluginname', 'quiz'),
    new \moodle_url('/admin/category.php?category=modsettingsquizcat')
);
$PAGE->navbar->add(
    get_string('pluginname', 'quizaccess_seb'),
    new \moodle_url('/admin/settings.php?section=modsettingsquizcatseb')
);
$PAGE->navbar->add(get_string('manage_templates', 'quizaccess_seb'));

echo $OUTPUT->header();
echo '<div id="main">
<h2>' . get_string('eprotroringreports', 'quizaccess_proctoring') . '' . $quiz->name . '</h2>
<div class="box generalbox m-b-1 adminerror alert alert-info p-y-1">'
. get_string('eprotroringreportsdesc', 'quizaccess_proctoring') . '</div>
';

if (has_capability('quizaccess/proctoring:viewreport', $context, $USER->id) && $cmid != null && $courseid != null) {

    // Check if report if for some user.
    if ($studentid != null && $cmid != null && $courseid != null && $reportid != null) {
        // Report for this user.
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
         e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
         from  {quizaccess_proctoring_logs} e INNER JOIN {user} u WHERE u.id = e.userid
         AND e.courseid = '$courseid' AND e.quizid = '$cmid' AND u.id = '$studentid' && e.id = '$reportid'";
    }

    if ($studentid == null && $cmid != null && $courseid != null) {
        // Report for all users.
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
         e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
         from  {quizaccess_proctoring_logs} e INNER JOIN {user} u WHERE u.id = e.userid
         AND e.courseid = '$courseid' AND e.quizid = '$cmid' group by e.userid";
    }

    // Print report.
    $table = new flexible_table('proctoring-report-' . $COURSE->id . '-' . $cmid);

    $table->course = $COURSE;

    $table->define_columns(array('fullname', 'email', 'dateverified', 'webcampicture', 'actions'));
    $table->define_headers(
        array(
            get_string('user'),
            get_string('email'),
            get_string('dateverified', 'quizaccess_proctoring'),
            get_string('dateverified', 'quizaccess_proctoring'),
            'webcampicture',
            get_string('actions', 'quizaccess_proctoring')
        )
    );
    $table->define_baseurl($url);

    $table->set_attribute('cellpadding', '6');
    $table->set_attribute('class', 'generaltable generalbox reporttable');
    $table->setup();

    // Prepare data.

    $sqlexecuted = $DB->get_recordset_sql($sql);

    $data = array();
    foreach ($sqlexecuted as $info) {
        $data = array('<a href="'.$CFG->wwwroot.'/user/view.php?id='.$info->studentid.
        '&course='.$courseid.'" target="_blank">'.$info->firstname.' '.$info->lastname.'</a>',
        $info->email, date("Y/M/d H:m:s", $info->timemodified), '<a href="?courseid='.$courseid.
        '&quizid='.$cmid.'&cmid='.$cmid.'&studentid='.$info->studentid.'&reportid='.$info->reportid.'">'.
        get_string('picturesreport', 'quizaccess_proctoring').'</a>');

        if (!empty($info->webcampicture)) {
            array_push($data, '<img src="'.$info->webcampicture.'" alt="screenshot"/>');
        } else {
            array_push($data, '');
        }
        $table->add_data($data);
    }

    // Print table.
    $table->print_html();


    // Print image results.
    if ($studentid != null && $cmid != null && $courseid != null && $reportid != null) {

        $data = array();
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
        e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
        from {quizaccess_proctoring_logs} e INNER JOIN {user} u WHERE u.id = e.userid
        AND e.courseid = '$courseid' AND e.quizid = '$cmid' AND u.id = '$studentid'";

        $sqlexecuted = $DB->get_recordset_sql($sql);
        echo '<h3>' . get_string('picturesusedreport', 'quizaccess_proctoring') . '</h3>';

        $tablepictures = new flexible_table('proctoring-report-pictures' . $COURSE->id . '-' . $cmid);

        $tablepictures->course = $COURSE;

        $tablepictures->define_columns(array('name', 'webcampicture'));
        $tablepictures->define_headers(array('name', 'webcampicture'));
        $tablepictures->define_baseurl($url);

        $tablepictures->set_attribute('cellpadding', '2');
        $tablepictures->set_attribute('class', 'generaltable generalbox reporttable');

        foreach ($sqlexecuted as $info) {
            $tablepictures->setup();
            $datapictures = array(
                $info->firstname . ' ' . $info->lastname,
                '<img src="' . $info->webcampicture . '" alt="' . $info->firstname . ' ' . $info->lastname . '" />'
            );
            $tablepictures->add_data($datapictures);
        }
        $tablepictures->print_html();
    }

} else {
    // User has not permissions to view this page.
    echo '<div class="box generalbox m-b-1 adminerror alert alert-danger p-y-1">' .
    get_string('notpermissionreport', 'quizaccess_proctoring') . '</div>';
}
echo '</div>';
echo $OUTPUT->footer();
