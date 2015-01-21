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
 * Peer review online submission viewing page
 *
 * @package    mod
 * @subpackage peerreview
 * @copyright  2012 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/peerreview/locallib.php');

$cmid = required_param('id', PARAM_INT);
$peerreviewid = required_param('peerreviewid', PARAM_INT);
$cm = get_coursemodule_from_id('peerreview', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);
if (! $peerreview = $DB->get_record("peerreview", array('id' => $peerreviewid))) {
    print_error('invalidid', 'peerreview');
}

$submissionID = required_param('submissionid',PARAM_TEXT);
$submission = $DB->get_record('peerreview_submissions', array('id'=>$submissionID));
$user = $DB->get_record('user', array('id'=>$submission->userid));

// Check user is logged in and capable of viewing the submission
require_login($course->id, false, $cm);
if($USER->id != $user->id) {
    require_capability('mod/peerreview:grade', $context);
}

// Set up the page
$attributes = array('peerreviewid' => $peerreview->id, 'id' => $cm->id, 'submissionid'=>$submissionID);
$PAGE->set_url('/mod/peerreview/viewOnlneText.php', $attributes);
$PAGE->set_title(format_string($peerreview->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submission', 'peerreview').': '.fullname($user), 2, 'leftHeading');
echo $OUTPUT->box_start();
echo format_text(stripslashes($submission->onlinetext), PARAM_CLEAN);
echo $OUTPUT->box_end();
echo $OUTPUT->footer();