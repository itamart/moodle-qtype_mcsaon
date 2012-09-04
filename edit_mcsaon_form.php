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
 * Defines the editing form for the multiple choice question type.
 *
 * @package    qtype
 * @subpackage mcsaon
 * @copyright  2012 Itamar Tzadok
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/question/type/multichoice/edit_multichoice_form.php");

/**
 * Multiple choice editing form definition.
 *
 * @copyright  2012 Itamar Tzadok
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mcsaon_edit_form extends qtype_multichoice_edit_form {
    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) {
        parent::definition_inner($mform);

        // add 'single sort of' option
        $groups = &$mform->getElement('single');
        $groups->addOption(get_string('answersinglesortof', 'qtype_mcsaon'), 2);        
    }

    public function qtype() {
        return 'mcsaon';
    }
}
