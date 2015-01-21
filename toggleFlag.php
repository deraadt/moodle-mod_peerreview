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
 * Peer review review flag toggle page
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

$r    = required_param('r', PARAM_INT);           // Review ID
if(! $review = $DB->get_record("peerreview_review", array('id'=>$r, 'peerreview'=>$peerreview->id))) {
    error("Review ID is incorrect");
}

$attributes = array('peerreviewid' => $peerreview->id, 'id' => $cm->id, 'r'=>$r);
$PAGE->set_url('/mod/peerreview/toggleFlag.php', $attributes);
$PAGE->set_context($context);

// Check user is logged in and capable of submitting
require_login($course->id, false, $cm);
require_capability('mod/peerreview:submit', $context);

/// Toggle the field
$DB->set_field('peerreview_review','flagged',($review->flagged==1?'0':'1'),array('id'=>$review->id));

$url = new moodle_url('/mod/peerreview/view.php', array('id' => $cmid));
redirect($url, get_string('review'.($review->flagged==1?'un':'').'flagged','peerreview'),1);
