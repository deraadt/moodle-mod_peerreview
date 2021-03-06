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
 * Internal library of functions for module peerreview
 *
 * @package    mod
 * @subpackage peerreview
 * @copyright  2013 Michael de Raadt (michaeld@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Submission formats
define('SUBMIT_DOCUMENT',  0);
define('ONLINE_TEXT',  1);
define('DEFAULT_FORMAT', 'docx');
define('FILE_PREFIX', 'toReview');

// Review constants
$REVIEW_COLOURS = array('#DCB39D','#AEA97E','#C1D692','#E1E1AE');
$REVIEW_COMMENT_COLOURS = array('#FCD3BD','#CEC99E','#E1F6B2','#F1F1CE');
$NUMBER_OF_COLOURS = count($REVIEW_COLOURS);
define('REVIEW_FEEDBACK_MIN',  10); // reviews
define('MINIMAL_REVIEW_TIME',  30); // seconds
define('MINIMAL_REVIEW_COMMENT_LENGTH',  12); // characters
define('ACCURACY_REQUIRED',  0.7);
define('ACCEPTABLE_REVIEW_RATE',  0.9);
define('ACCEPTABLE_REVIEW_ATTENTION_RATE',  0.9);
define('ACCEPTABLE_MODERATION_RATE',  0.6);
define('ACCEPTABLE_REVIEW_TIME',  60); // seconds
define('ACCEPTABLE_COMMENT_LENGTH',  50); // characters
define('ACCEPTABLE_FLAG_RATE',  0.1);
define('ACCEPTABLE_CHECKED_RATE',  0.5);

// Status values
define('FLAGGED',  0); // Moderation required
define('CONFLICTING',  1); // Moderation required
define('FLAGGEDANDCONFLICTING',  2); // Moderation required
define('LESSTHANTWOREVIEWS',  3); // Moderation required
define('CONCENSUS',  4); // Good
define('OVERRIDDEN',  5); // Good

/**
 * Print tabs at top of page
 *
 * @param int $coursemoduleid id of the course module
 * @param int $peerreviewid id of the peerreview activity instance
 * @param string $current
 * @return string
 */
function peerreview_tabs($coursemoduleid, $peerreviewid, $current='description') {
    global $CFG;

    $params = array('id' => $coursemoduleid, 'peerreviewid' => $peerreviewid);
    $tabs = array();
    $row  = array();

    $link = new moodle_url($CFG->wwwroot.'/mod/peerreview/view.php', $params);
    $row[] = new tabobject('description', $link, get_string('description'));

    $link = new moodle_url($CFG->wwwroot.'/mod/peerreview/criteria.php', $params);
    $row[] = new tabobject('criteria', $link, get_string('criteria', 'peerreview'));

    $link = new moodle_url($CFG->wwwroot.'/mod/peerreview/submissions.php', $params);
    $row[] = new tabobject('submissions', $link, get_string('submissions', 'peerreview'));

    // $link = new moodle_url($CFG->wwwroot.'/mod/peerreview/analysis.php', $params);
    // $row[] = new tabobject('analysis', $link, get_string('analysis', 'peerreview'));

    $tabs[] = $row;
    return print_tabs($tabs, $current, null, null, true);
}


/**
 * Print status box at top of page
 *
 * @param string $class   The CSS class of the box
 * @param int    $number  The number in the box
 * @param string $title   The title at the top of the box
 * @param string $message The message at the bottom the box
 * @return string
 */
function print_progress_box($class='redProgressBox', $number='1', $title='', $message='') {
    global $CFG;

    if($class=='end') {
        echo '<div class="progressBoxEnd"></div>';
    }
    else {
        echo '<div class="progressBox '.$class.'">';
        echo '<div class="progressNumber">'.$number.'</div>';
        echo '<div class="progressTitle">'.get_string($title,'peerreview').'</div>';
        echo '<div class="progressMessage">'.get_string($message,'peerreview').'</div>';
        echo '</div>';
    }
}


/**
 * Print a table with criteria
 *
 * @param array $criteria the criteria values (with before and after text)
 * @param bool  $beforesubmission controls which set of criteria values are used
 * @param bool  $disablecheckboxes controls whether the table contains active form elements
 * @return string
 */
function criteria_table($criteria, $beforesubmission=true, $disablecheckboxes=true) {
    $table = new html_table();
    $table->attributes = array('class'=>'criteriaTable');
    $checkboxcell = new html_table_cell(HTML_WRITER::empty_tag('input', array('type'=>'checkbox', 'checked'=>'true', 'disabled'=>'true')));
    $checkboxcell->attributes = array('class' => 'criteriaCheckboxColumn');

    foreach($criteria as $i=>$criterion) {
        if($beforesubmission || $criterion->textshownatreview=='') {
            $criteriacell = new html_table_cell(s($criterion->textshownwithinstructions));
        }
        else {
            $criteriacell = new html_table_cell(s($criterion->textshownatreview));
        }
        $criteriacell->attributes = array('class'=>'criteriaTextColumn');
        $tablerow = new html_table_row();
        $table->data[] = array($checkboxcell, $criteriacell);
    }
    return HTML_WRITER::table($table);
}

/**
 * Instantiates a new submission object for a given user
 *
 * Sets the peerreview, userid and times, everything else is set to default values.
 *
 * @param int $peerreviewid The id of the current peerreview activity instance
 * @param int $userid The userid for which we want a submission object
 * @return object The submission
 */
function prepare_new_submission($peerreviewid, $userid, $teachermodified=false) {
    $submission = new stdClass();
    $submission->peerreview   = $peerreviewid;
    $submission->userid       = $userid;
    $submission->timecreated  = time();
    $submission->timemodified = $submission->timecreated;
    $submission->status       = 0;
    $submission->onlinetext   = '';
    $submission->grade        = -1;
    $submission->teacher      = 0;
    $submission->timemarked   = 0;
    $submission->mailed       = 0;
    return $submission;
}

function prepare_new_review($peerreviewid, $reviewer, $reviewee) {
    $review = new stdClass;
    $review->peerreview                 = $peerreviewid;
    $review->reviewer                   = $reviewer;
    $review->reviewee                   = $reviewee;
    $review->timeallocated              = time();
    $review->timemodified               = time();
    $review->downloaded                 = 0;
    $review->timedownloaded             = 0;
    $review->completed                  = 0;
    $review->timecompleted              = 0;
    $review->teacherreview              = 0;
    $review->flagged                    = 0;
    $review->reviewcomment              = '';
    $review->timefirstviewedbyreviewee  = 0;
    $review->timelastviewedbyreviewee   = 0;
    $review->timesviewedbyreviewee      = 0;
    return $review;
}


/**
 * Load the submission object for a particular user
 *
 * @param int $peerreviewid The id of the current peerreview activity instance
 * @param int $userid int The id of the user whose submission we want or 0 in which case USER->id is used
 * @param bool $createnew boolean optional Defaults to false. If set to true a new submission object will be created in the database
 * @return object|bool The submission or false (if $createnew is false and there is no existing submission).
 */
function get_submission($peerreviewid, $userid=0, $createnew=false) {
    global $USER, $DB;
    $submission = false;

    // If user is not provided, use current user.
    if (empty($userid)) {
        $userid = $USER->id;
    }

    // Attempt to retrieve user submission
    $submission = $DB->get_record('peerreview_submissions', array('peerreview'=>$peerreviewid, 'userid'=>$userid));
    // If there is no submission and a submission is required, create a new one
    if (!$submission && $createnew) {
        $newsubmission = prepare_new_submission($peerreviewid, $userid);
        $DB->insert_record("peerreview_submissions", $newsubmission);
        $submission = $DB->get_record('peerreview_submissions', array('peerreview'=>$peerreviewid, 'userid'=>$userid));
    }

    return $submission;
}

function get_next_review($peerreview) {
    global $DB, $USER;

    $select = 'peerreview=:peerreview and reviewer=:userid';
    $params = array('peerreview' => $peerreview->id, 'userid' => $USER->id, 'completed' => 0);
    $reviews = $DB->get_records_select('peerreview_review', $select, $params, 'id ASC');
    return array_shift($reviews);
}

/**
 * Returns true if the student is allowed to submit
 *
 * Checks that the assignment has started and, if the option to prevent late
 * submissions is set, also checks that the assignment has not yet closed.
 *
 * @param object $peerreview A peerreview activity instance object
 * @return boolean
 */
function isopen($peerreview) {
    $time = time();

    return (
        ($peerreview->allowsubmissionsfromdate==0 || $time >= $peerreview->allowsubmissionsfromdate) &&
        ($peerreview->cutoffdate==0               || $time <  $peerreview->cutoffdate)
    );
}

/**
 * Outputs the submission form
 *
 * @param object $peerreview A peerreview activity instance object
 * @return none
 */
function view_upload_form($peerreview, $cmid) {
    global $CFG, $PAGE, $COURSE;

    require_once($CFG->dirroot.'/mod/peerreview/upload_form.php');

    if($peerreview->submissionformat==ONLINE_TEXT) {
        $mform = new mod_peerreview_edit_form($CFG->wwwroot.'/mod/peerreview/upload.php',array('peerreviewid'=>$peerreview->id, 'id'=>$cmid));
        $mform->display();
    }
    else {
        $options = get_file_options($peerreview);
        $mform = new mod_peerrview_upload_form($CFG->wwwroot.'/mod/peerreview/upload.php', array('peerreviewid'=>$peerreview->id, 'id'=>$cmid, 'options'=>$options));
        $mform->display();
    }
    $PAGE->requires->event_handler('#id_submitbutton', 'click', 'M.util.show_confirm_dialog', array('message' => get_string('singleuploadquestion','peerreview')));
}

function get_reviews_of_student($peerreview, $reviewee) {
    global $CFG, $DB;

    // Query for finding the reviews for this student (the query of many joins)
    $query = 'SELECT r.id as review, r.timemodified as timemodified, r.timecompleted as timecompleted, r.timedownloaded as timedownloaded, r.teacherreview as teacherreview, r.reviewcomment as reviewcomment, r.flagged as flagged, r.timefirstviewedbyreviewee as timefirstviewedbyreviewee, r.timelastviewedbyreviewee as timelastviewedbyreviewee, r.timesviewedbyreviewee as timesviewedbyreviewee, u.firstname as firstname, u.lastname as lastname';
    $query .= ' FROM {peerreview_review} r, {user} u';
    $query .= ' WHERE r.peerreview=\''.$peerreview.'\' AND r.reviewee=\''.$reviewee.'\' AND r.completed=1';
    $query .= ' AND r.reviewer=u.id ';
    $query .= ' ORDER BY r.id ASC';
    $reviews = $DB->get_records_sql($query);
    if($reviews) {
        $reviews = array_values($reviews);
        $numberOfReviews = count($reviews);
        for($i=0; $i<$numberOfReviews; $i++) {
            $criteriaList = $DB->get_records('peerreview_review_criterion',array('review'=>$reviews[$i]->review));
            foreach($criteriaList as $id=>$criterion) {
                $reviews[$i]->{'checked'.$criterion->criterion} = $criterion->checked;
            }
        }
        return $reviews;
    }
    return NULL;
}

function get_reviews_completed_by_student($peerreview, $reviewer) {
    global $DB;

    $query = 'SELECT r.id as review, r.timemodified as timemodified, r.timecompleted as timecompleted, r.timedownloaded as timedownloaded, r.teacherreview as teacherreview, r.reviewcomment as reviewcomment, r.flagged as flagged, r.timefirstviewedbyreviewee as timefirstviewedbyreviewee, r.timelastviewedbyreviewee as timelastviewedbyreviewee, r.timesviewedbyreviewee as timesviewedbyreviewee, u.firstname as firstname, u.lastname as lastname';
    $query .= ' FROM {peerreview_review} r, {user} u';
    $query .= ' WHERE r.peerreview=:peerreview AND r.reviewer=:reviewer AND r.complete=1';
    $query .= ' AND r.reviewer=u.id ';
    $query .= ' ORDER BY r.id ASC';
    $params = array('peerreview'=>$peerreview, 'reviewer'=>$reviewer);
    $reviews = $DB->get_records_sql($query, $params);

    if($reviews) {
        $reviews = array_values($reviews);
        $numberOfReviews = count($reviews);
        for($i=0; $i<$numberOfReviews; $i++) {
            $criteriaList = $DB->get_records('peerreview_review_criterion',array('review'=>$reviews[$i]->review));
            foreach($criteriaList as $id=>$criterion) {
                $reviews[$i]->{'checked'.$criterion->criterion} = $criterion->checked;
            }
        }
        return $reviews;
    }
    return NULL;
}

function get_reviews_allocated_to_student($peerreviewid, $reviewerid) {
    global $DB;

    $select  = 'peerreview=:peerreview AND reviewer=:reviewer';
    $sort    = 'id ASC';
    $params  = array('peerreview' => $peerreviewid, 'reviewer' => $reviewerid);
    $reviews = $DB->get_records_select('peerreview_review', $select, $params, $sort);
    if(is_array($reviews)) {
        $reviews = array_values($reviews);
    }
    return $reviews;
}

function email_from_teacher($studentID, $subject, $messageText, $messageHTML, $eventtype) {
    global $DB;

    if($student = $DB->get_record('user',array('id'=>$studentID))) {
        $eventdata = new stdClass();
        $eventdata->modulename       = 'peerreview';
        $eventdata->userfrom         = get_admin();
        $eventdata->userto           = $student;
        $eventdata->subject          = $subject;
        $eventdata->fullmessage      = $messageText;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml  = $messageHTML;
        $eventdata->smallmessage     = $subject;
        $eventdata->name             = $eventtype;
        $eventdata->component        = 'mod_peerreview';
        $eventdata->notification     = 1;
        return message_send($eventdata);
    }
    else {
        return false;
    }
}

function view_intro($peerreview, $cmid) {
    global $OUTPUT;

    echo $OUTPUT->box_start('generalbox boxaligncenter', 'mod_peerreview_intro');
    echo format_module_intro('peerreview', $peerreview, $cmid);
    echo $OUTPUT->box_end();
}

function showHiddenDescription($peerreview, $cmid) {
    global $OUTPUT, $PAGE;

    echo HTML_WRITER::start_tag('p', array('id'=>'showDescription', 'style'=>'display:none;'));
    echo HTML_WRITER::tag('a', get_string('showdescription','peerreview'), array('href'=>'#null', 'onclick'=>'M.peerreview.showDescription();'));
    echo HTML_WRITER::end_tag('p');
    echo HTML_WRITER::start_tag('div', array('id'=>'hiddenDescription'));
    echo HTML_WRITER::start_tag('p', array('id'=>'hideDescription', 'style'=>'display:none;'));
    echo HTML_WRITER::tag('a', get_string('hidedescription','peerreview'), array('href'=>'#null', 'onclick'=>'M.peerreview.hideDescription();'));
    echo $OUTPUT->heading(get_string('description','peerreview'),2,'leftHeading','assignmentDescription');
    view_intro($peerreview, $cmid);
    echo HTML_WRITER::end_tag('div');
    $jsmodule = array(
        'name' => 'peerreview',
        'fullpath' => '/mod/peerreview/module.js',
        'requires' => array(),
        'strings' => array(),
    );
    $PAGE->requires->js_init_call('M.peerreview.initHiddenDescription', null, false, $jsmodule);
}

function print_status($status,$return=false) {
    $progressMessage = '';
    switch($status) {
        case FLAGGED:               $progressMessage = '<span class="errorStatus">'.get_string('flagged','peerreview').'</span>'; break;
        case CONFLICTING:           $progressMessage = '<span class="errorStatus">'.get_string('conflicting','peerreview').'</span>'; break;
        case FLAGGEDANDCONFLICTING: $progressMessage = '<span class="errorStatus">'.get_string('conflicting','peerreview').', '.get_string('flagged','peerreview').'</span>'; break;
        case LESSTHANTWOREVIEWS:    $progressMessage = '<span class="errorStatus">'.get_string('lessthantworeviews','peerreview').'</span>'; break;
        case CONCENSUS:             $progressMessage = '<span class="goodStatus">'. get_string('concensus','peerreview').'</span>'; break;
        case OVERRIDDEN:            $progressMessage = '<span class="goodStatus">'. get_string('overridden','peerreview').'</span>'; break;

    }
    if($return) {
        return $progressMessage;
    }
    else {
        echo $progressMessage;
    }
}

//------------------------------------------------------------------------------
// Finds the status of the submission and if moderation is needed
function get_status($reviews, $numberOfCriteria) {
    $numberOfReviewsOfThisStudent = is_array($reviews)?count($reviews):0;
    $flagged = false;
    $conflicting = false;
    $overridden = false;
    
    for($i=0; $i<$numberOfReviewsOfThisStudent && !$overridden; $i++) {
        $overridden = $reviews[$i]->teacherreview==1;
        $flagged = $flagged || $reviews[$i]->flagged==1;
    }
    
    if($overridden) {
        return OVERRIDDEN;
    }
    if($numberOfReviewsOfThisStudent<2) {
        return LESSTHANTWOREVIEWS;
    }
    
    for($i=0; $i<$numberOfCriteria && !$conflicting; $i++) {
        for($j=0; $j<$numberOfReviewsOfThisStudent-1 && !$conflicting; $j++) {
            $conflicting = $reviews[$j]->{'checked'.$i} != $reviews[$j+1]->{'checked'.$i};
        }
    }
    
    if($flagged && $conflicting) {
        return FLAGGEDANDCONFLICTING;
    }
    if($flagged) {
        return FLAGGED;
    }
    if($conflicting) {
        return CONFLICTING;
    }
    return CONCENSUS;
}

function submission_link ($peerreview, $submissionID, $contextid, $linkOnly=false) {
    global $CFG, $OUTPUT;

    if(isset($peerreview->submissionformat) && $peerreview->submissionformat==ONLINE_TEXT) {
        $attributes = array('id'=>$contextid, 'peerreviewid'=>$peerreview->id, 'submissionid'=>$submissionID);
        $url = new moodle_url($CFG->wwwroot.'/mod/peerreview/viewOnlineText.php', $attributes);
        $linkText = $OUTPUT->pix_icon(file_mimetype_icon(mimeinfo('type','xxx.html')),'');
        if(!$linkOnly) {
            $linkText .= '&nbsp;'.get_string('showsubmission','peerreview');
        }
        // $attributes = array('target'=>'_blank', 'title'=>get_string('clicktoview','peerreview'));
    }
    else {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_peerreview', 'submission', $submissionID, "timemodified", false);
        $file = array_pop($files);
        $filename = $file->get_filename();
        $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/'.$contextid.'/mod_peerreview/submission/'.$file->get_itemid().$file->get_filepath().$filename, true);
        $linkText  = $OUTPUT->pix_icon(file_mimetype_icon(mimeinfo('type',$filename)),'');
        if(!$linkOnly) {
            $linkText .= '&nbsp;'.$filename;
        }
        $attributes = null;
    }
    return html_writer::link($url, $linkText);
}

function review_file_link ($peerreview, $submissionID, $contextid, $reviewNumber, $onclick='') {
    global $CFG, $OUTPUT;
    
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_peerreview', 'submission', $submissionID, "timemodified", false);
    $file = array_pop($files);
    $filename = FILE_PREFIX.$reviewNumber.'.'.$peerreview->fileextension;
    $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/'.$contextid.'/mod_peerreview/submission/'.$file->get_itemid(). $file->get_filepath().$file->get_id().'/'.$filename, true);
    $linkText  = $OUTPUT->pix_icon(file_mimetype_icon(mimeinfo('type',$filename)),'');
    $linkText .= '&nbsp;'.$filename;
    $attributes = null;
    // $parameters = array('a' => $this->assignment->id, 'contextid' => $this->context->id, 'forcedownload' => 1);
    // $text = $OUTPUT->pix_icon(file_mimetype_icon(mimeinfo('type','xxx.'.$this->assignment->fileextension)),'').'&nbsp'.$linkText;
    return HTML_WRITER::link($url, $linkText, (empty($onclick)?null:array('onclick'=>$onclick)));
}


function get_review_statistics($peerreview) {
    global $DB;

    $stats = new Object;
    $stats->numberOfSubmissions = 0;
    $stats->numberOfReviews = 0;
    $stats->reviewRate = 0;
    $stats->numberOfModerations = 0;
    $stats->moderationRate = 0;
    $stats->totalReviewTime = 0; // seconds
    $stats->averageReviewTime = 0; // seconds
    $stats->normalisedAverageReviewTime = 0; // seconds
    $stats->stdDevReviewTime = 0; // seconds
    $stats->minReviewTime = PHP_INT_MAX; // seconds
    $stats->maxReviewTime = 0; // seconds
    $stats->totalCommentLength = 0; // characters
    $stats->averageCommentLength = 0; // characters
    $stats->normalisedAverageCommentLength = 0; // characters
    $stats->stdDevCommentLength = 0; // characters
    $stats->reviewTimeOutlierLowerBoundary = 0; // seconds
    $stats->reviewTimeOutlierUpperBoundary = 0; // seconds
    $stats->commentLengthOutlierLowerBoundary = 0;
    $stats->commentLengthOutlierUpperBoundary = 0;
    $stats->minCommentLength = PHP_INT_MAX; // characters
    $stats->maxCommentLength = 0; // characters        
    $stats->flags = 0;
    $stats->flagRate = 0;
    $stats->numberOfReviewsViewed = 0;
    $stats->reviewAttentionRate = 0;
    $stats->numberOfReviewViews = 0;
    $stats->averageViewRate = 0;
    $stats->totalPeriodBetweenReviewAndView = 0; // seconds
    $stats->averagePeriodBewtweenReviewAndView = 0; // seconds
    $stats->medianPeriodBewtweenReviewAndView = 0; // seconds
    $commentLengths = array();
    $reviewTimes = array();
    $waitTimes = array();

    // Get submission and moderation stats
    $stats->numberOfSubmissions = $DB->count_records('peerreview_submissions',array('peerreview'=>$peerreview->id));
    $stats->numberOfModerations = $DB->count_records_select('peerreview_review', 'peerreview=\''.$peerreview->id.'\' AND teacherreview=\'1\' AND completed=\'1\'');
    $stats->moderationRate = $stats->numberOfSubmissions==0?0:$stats->numberOfModerations/$stats->numberOfSubmissions;

    // Calculate review stats
    $reviews = $DB->get_records_select('peerreview_review', 'peerreview=\''.$peerreview->id.'\' AND teacherreview=\'0\' AND completed=\'1\'',null,'id');
    $stats->numberOfReviews = count($reviews);
    if(is_array($reviews) && $stats->numberOfReviews >= REVIEW_FEEDBACK_MIN) {
        $stats->reviewRate = $stats->numberOfSubmissions==0?0:$stats->numberOfReviews/2/$stats->numberOfSubmissions;

        // Collect review times and lengths for normalisation
        foreach($reviews as $id=>$review) {

            // Review times
            $reviewTime = $review->timecompleted - $review->timedownloaded;
            $reviewTimes[] = $reviewTime;
            $stats->totalReviewTime += $reviewTime;
            if($reviewTime > $stats->maxReviewTime) {
                $stats->maxReviewTime = $reviewTime;
            }
            if($reviewTime < $stats->minReviewTime) {
                $stats->minReviewTime = $reviewTime;
            }

            // Comment lengths
            $commentLength = strlen($review->reviewcomment);
            $commentLengths[] = $commentLength;
            $stats->totalCommentLength += $commentLength;
            if($commentLength > $stats->maxCommentLength) {
                $stats->maxCommentLength = $commentLength;
            }
            if($commentLength < $stats->minCommentLength) {
                $stats->minCommentLength = $commentLength;
            }

            // Count flags
            if($review->flagged == 1) {
                $stats->flags++;
            }

            // Feedback attention stats
            if($review->timefirstviewedbyreviewee > 0) {
                $stats->numberOfReviewsViewed++;
                $stats->numberOfReviewViews += $review->timesviewedbyreviewee;
                $waitTime = $review->timefirstviewedbyreviewee-$review->timecompleted;
                $waitTimes[] = $waitTime;
                $stats->totalPeriodBetweenReviewAndView += $waitTime;
            }
        }

        // Normalise the stats before calculating averages
        sort($reviewTimes);
        sort($commentLengths);
        $reviewTimeLowerQuartileBound = $reviewTimes[floor($stats->numberOfReviews*0.25)];
        $reviewTimeThirdQuartileBound = $reviewTimes[floor($stats->numberOfReviews*0.75)];
        $commentLengthLowerQuartileBound = $commentLengths[(int)$stats->numberOfReviews*0.25];
        $commentLengthThirdQuartileBound = $commentLengths[(int)$stats->numberOfReviews*0.75];
        $interQuartileDistanceTime = $reviewTimeThirdQuartileBound - $reviewTimeLowerQuartileBound;
        $interQuartileDistanceLength = $commentLengthThirdQuartileBound - $commentLengthLowerQuartileBound;
        $stats->reviewTimeOutlierLowerBoundary = $reviewTimeLowerQuartileBound - 1.5*$interQuartileDistanceTime;
        $stats->reviewTimeOutlierUpperBoundary = $reviewTimeThirdQuartileBound + 1.5*$interQuartileDistanceTime;
        $stats->commentLengthOutlierLowerBoundary = $commentLengthLowerQuartileBound - 1.5*$interQuartileDistanceTime;
        $stats->commentLengthOutlierUpperBoundary = $commentLengthThirdQuartileBound + 1.5*$interQuartileDistanceTime;

        // Remove outliers from the ends of the arrays
        $numberOfNormalisedReviewTimes = $stats->numberOfReviews;
        while($reviewTimes[$numberOfNormalisedReviewTimes-1] > $stats->reviewTimeOutlierUpperBoundary) {
            $numberOfNormalisedReviewTimes--;
            array_pop($reviewTimes);
        }
        while($reviewTimes[0] < $stats->reviewTimeOutlierLowerBoundary) {
            $numberOfNormalisedReviewTimes--;
            array_shift($reviewTimes);
        }
        $numberOfNormalisedCommentLengths = $stats->numberOfReviews;
        while($commentLengths[$numberOfNormalisedCommentLengths-1] > $stats->commentLengthOutlierUpperBoundary) {
            $numberOfNormalisedCommentLengths--;
            array_pop($commentLengths);
        }
        while($commentLengths[0] > $stats->commentLengthOutlierUpperBoundary) {
            $numberOfNormalisedCommentLengths--;
            array_shift($commentLengths);
        }

        // Normalised average summing
        $normalisedReviewTimeSum = 0;
        foreach($reviewTimes as $reviewTime) {
            $normalisedReviewTimeSum += $reviewTime;
        }
        $normalisedCommentLengthSum = 0;
        foreach($commentLengths as $commentLength) {
            $normalisedCommentLengthSum += $commentLength;
        }

        // Average calculations
        $stats->averageReviewTime = $stats->totalReviewTime/$stats->numberOfReviews;
        $stats->normalisedAverageReviewTime = $normalisedReviewTimeSum / $numberOfNormalisedReviewTimes;
        $stats->averageCommentLength = $stats->totalCommentLength/$stats->numberOfReviews;
        $stats->normalisedAverageCommentLength = $normalisedCommentLengthSum / $numberOfNormalisedCommentLengths;
        $stats->flagRate = $stats->flags/$stats->numberOfReviews;
        $stats->reviewAttentionRate = $stats->numberOfReviewsViewed/$stats->numberOfReviews;
        $stats->averageViewRate = $stats->numberOfReviewViews/$stats->numberOfReviews;
        if($stats->numberOfReviewsViewed>0) {
            $stats->averagePeriodBewtweenReviewAndView = $stats->totalPeriodBetweenReviewAndView/$stats->numberOfReviews;
            sort($waitTimes);
            $stats->medianPeriodBewtweenReviewAndView = $waitTimes[(int)($stats->numberOfReviewsViewed/2)];
        }

        // Standard deviation calculations
        $sumOfTimeDifferences = 0;
        $sumOfCommentDifferences = 0;
        foreach($reviews as $id=>$review) {
            $sumOfTimeDifferences += pow(($review->timecompleted - $review->timedownloaded) - $stats->averageReviewTime, 2);
            $sumOfCommentDifferences += pow(strlen($review->reviewcomment) - $stats->averageCommentLength, 2);
        }
        $stats->stdDevReviewTime = sqrt($sumOfTimeDifferences/$stats->numberOfReviews);
        $stats->stdDevCommentLength = sqrt($sumOfCommentDifferences/$stats->numberOfReviews);
    }
    else {
        $stats->minReviewTime = 0;
        $stats->minCommentLength = 0;
    }
    
    return $stats;
}

//------------------------------------------------------------------------------
// Calculates statistics relating to criteria
function get_criteria_statistics($peerreview) {
    global $DB;
    $stats = array();
    
    // Get the criteria
    $criteria = $DB->get_records_list('assignment_criteria','peerreview',array($peerreview->id),'ordernumber');
    $numberOfCriteria = 0;
    if(is_array($criteria)) {
        $criteria = array_values($criteria);
        $numberOfCriteria = count($criteria);
    }
    for($i=0; $i<$numberOfCriteria; $i++) {
        $stats[$i] = new Object;
        $stats[$i]->count = 0;
        $stats[$i]->rate = 0;
        $stats[$i]->textshownatreview = $criteria[$i]->textshownatreview;
        $stats[$i]->textshownwithinstructions = $criteria[$i]->textshownwithinstructions;
    }
    
    // Review checked stats
    $numberOfReviews = 0;
    if($reviews = $DB->get_records_select('assignment_review', 'peerreview=\''.$peerreview->id.'\' AND teacherreview=\'0\' AND complete=\'1\'',null,'id')) {
        $numberOfReviews = count($reviews);
        foreach($reviews as $id=>$review) {
            $reviewCriteria = $DB->get_records('assignment_review_criterion',array('review'=>$id));
            foreach($reviewCriteria as $criterionID=>$reviewCriterion) {
                if($reviewCriterion->checked) {
                    $stats[$reviewCriterion->criterion]->count++;
                }
            }
        }
    }

    // Review checked rate calculations
    for($i=0; $i<$numberOfCriteria; $i++) {
        $stats[$i]->rate = $numberOfReviews==0 ? 0 : ($stats[$i]->count / $numberOfReviews);
    }
    
    return $stats;
}

//------------------------------------------------------------------------------
// Calculates statistics relating to submissions
function get_submission_statistics($peerreview, $cm) {
    global $CFG, $DB;

    $stats = new Object;
    $stats->numberOfStudents = 0;
    $stats->numberOfSubmissions = 0;
    $stats->submissionRate = 0;
    $stats->totalWaitForFeedback = 0; // seconds
    $stats->averageWaitForFeedback = 0; // seconds
    $stats->medianWaitForFeedback = 0; // seconds
    
    // Get all ppl that are allowed to submit assignments
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    if ($users = get_users_by_capability($context, 'mod/assignment:submit', 'u.id')) {
        $users = array_keys($users);
        if ($teachers = get_users_by_capability($context, 'mod/assignment:grade', 'u.id')) {
            $users = array_diff($users, array_keys($teachers));
            $stats->numberOfStudents = count($users);
        }
    }
    
    // Get the number of submissions and rate
    $stats->numberOfSubmissions = $DB->count_records('assignment_submissions', array('peerreview'=>$peerreview->id));
    $stats->submissionRate = $stats->numberOfStudents==0?0:$stats->numberOfSubmissions/$stats->numberOfStudents;

    $sql = 'SELECT r.id, userid, timecreated, timecompleted '.
           'FROM {assignment_submissions} s, {assignment_review} r '.
           'WHERE s.peerreview=\''.$peerreview->id.'\' '.
           'AND s.peerreview=r.peerreview '.
           'AND s.userid=r.reviewee '.
           'AND r.teacherreview=\'0\' '.
           'AND r.complete=\'1\' '.
           'ORDER BY userid ASC, timecompleted DESC';
    $waitStats = $DB->get_records_sql($sql);
    $waitTimes = array();
    if(isset($waitStats) && is_array($waitStats) && count($waitStats)>0) {
        $stats->numberOfSubmissionsWithFeedback = count($waitStats);
        foreach($waitStats as $userid=>$stat) {
            $waitTime = $stat->timecompleted - $stat->timecreated;
            $waitTimes[] = $waitTime;
            $stats->totalWaitForFeedback += $waitTime;
        }
        if($stats->numberOfSubmissionsWithFeedback>0) {
            $stats->averageWaitForFeedback = $stats->totalWaitForFeedback/$stats->numberOfSubmissionsWithFeedback;
            sort($waitTimes);
            $stats->medianWaitForFeedback = $waitTimes[(int)($stats->numberOfSubmissionsWithFeedback/2)];
        }
    }
    return $stats;
}

function get_file_options($peerreview) {
    global $CFG, $COURSE;
    require_once($CFG->dirroot.'/repository/lib.php');

    $fileoptions = array(
        'subdirs'=>0,
        'maxbytes'=>get_max_upload_file_size($CFG->maxbytes, $COURSE->maxbytes, $peerreview->maxbytes),
        'maxfiles'=>1,
        'accepted_types'=>$peerreview->fileextension,
        'return_types'=>FILE_INTERNAL
    );
    return $fileoptions;
}

function get_marks($reviews,$criteriaList,$numberOfReviewsByStudent,$reviewReward) {
    $numberOfReviewsOfThisStudent = is_array($reviews)?count($reviews):0;
    $numberOfCriteria = is_array($criteriaList)?count($criteriaList):0;
    $teacherReviewIndex = $numberOfReviewsOfThisStudent-1;
    $marks = 0;
    $statusCode = get_status($reviews,$numberOfCriteria);
    
    if($statusCode<=3) {
        return '???';
    }

    if($statusCode == OVERRIDDEN) {

        while($reviews[$teacherReviewIndex]->teacherreview!=1) {
            $teacherReviewIndex--;
        }
        for($i=0; $i<$numberOfCriteria; $i++) {
            if($reviews[$teacherReviewIndex]->{'checked'.$i} == 1) {
                $marks += $criteriaList[$i]->value;
            }
        }
    }
    
    if($statusCode == CONCENSUS) {

        for($i=0; $i<$numberOfCriteria; $i++) {
            if($reviews[0]->{'checked'.$i} == 1) {
                $marks += $criteriaList[$i]->value;
            }
        }
    }
    return $marks + $numberOfReviewsByStudent*$reviewReward;
}

function display_lateness($timesubmitted, $timedue) {
    if (!$timedue) {
        return '';
    }
    $time = $timedue - $timesubmitted;
    if ($time < 0) {
        $timetext = get_string('late', 'peerreview', format_time($time));
        return ' (<span class="late">'.$timetext.'</span>)';
    } else {
        $timetext = get_string('early', 'peerreview', format_time($time));
        return ' (<span class="early">'.$timetext.'</span>)';
    }
}
