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
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/question/type/multichoice/backup/moodle2/restore_qtype_multichoice_plugin.class.php");

/**
 * restore plugin class that provides the necessary information
 * needed to restore one mcsaon qtype plugin
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_mcsaon_plugin extends restore_qtype_multichoice_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // This qtype uses question_answers, add them
        $this->add_question_question_answers($paths);

        // Add own qtype stuff
        $elename = 'mcsaon';
        // we used get_recommended_name() so this works
        $elepath = $this->get_pathfor('/mcsaon');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths
    }

    /**
     * Process the qtype/mcsaon element
     */
    public function process_mcsaon($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = (bool) $this->get_mappingid('question_created', $oldquestionid);

        // If the question has been created by restore, we need to create its
        // question_mcsaon too
        if ($questioncreated) {
            // Adjust some columns
            $data->question = $newquestionid;
            // Map sequence of question_answer ids
            if ($data->answers) {
                $answersarr = explode(',', $data->answers);
            } else {
                $answersarr = array();
            }
            foreach ($answersarr as $key => $answer) {
                $answersarr[$key] = $this->get_mappingid('question_answer', $answer);
            }
            $data->answers = implode(',', $answersarr);
            // Insert record
            $newitemid = $DB->insert_record('question_mcsaon', $data);
            // Create mapping (needed for decoding links)
            $this->set_mapping('question_mcsaon', $oldid, $newitemid);
        }
    }

    /**
     * Return the contents of this qtype to be processed by the links decoder
     */
    public static function define_decode_contents() {

        $contents = array();

        $fields = array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback');
        $contents[] = new restore_decode_content('question_mcsaon',
                $fields, 'question_mcsaon');

        return $contents;
    }
}
