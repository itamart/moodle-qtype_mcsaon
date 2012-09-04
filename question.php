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
 * @package    qtype
 * @subpackage mcsaon
 * @copyright  2012 Itamar Tzadok
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Represents a multiple choice question where multiple choices can be selected
 * and marked either with partial mark or all or nothing
 *
 * @copyright  2012 Itamar Tzadok
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mcsaon_multi_question extends qtype_multichoice_multi_question {
    
    protected $aon;

    /**
     * 
     */
    public function __construct($aon) {
        parent::__construct();
        $this->aon = $aon;
    }

    /**
     * 
     */
    public function grade_response(array $response) {
        if (!$this->aon) {
            // partial mark grading
            return $this->grade_response_partial_mark($response);
        } else {
            // all or nothing grading
            return $this->grade_response_no_partial_mark($response);        
        }
    }
    
    protected function grade_response_no_partial_mark(array $response) {
        $fraction = 1;
        foreach ($this->order as $key => $ansid) {
            $iscorrect = ($this->answers[$ansid]->fraction > 0);
            // correct choice should appear in response
            if ($iscorrect and empty($response[$this->field($key)])) {
                $fraction = 0;
                break;

            // incorrect shouldn't appear in response
            } else if (!$iscorrect and !empty($response[$this->field($key)])) {
                $fraction = 0;
                break;
            }
        }
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    protected function grade_response_partial_mark(array $response) {
        $fraction = 0;
        foreach ($this->order as $key => $ansid) {
            if (!empty($response[$this->field($key)])) {
                $fraction += $this->answers[$ansid]->fraction;
            }
        }
        $fraction = min(max(0, $fraction), 1.0);
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }
}
