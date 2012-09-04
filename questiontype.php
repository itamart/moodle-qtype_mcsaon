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
 * The questiontype class for the multiple choice question type.
 *
 * @package    qtype
 * @subpackage mcsaon
 * @copyright  2012 Itamar Tzadok
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once("$CFG->dirroot/question/type/multichoice/questiontype.php");
require_once("$CFG->dirroot/question/type/multichoice/question.php");


/**
 * The multiple choice question type.
 *
 * @copyright  2012 Itamar Tzadok
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mcsaon extends qtype_multichoice {
    public function get_question_options($question) {
        global $DB, $OUTPUT;
        $question->options = $DB->get_record('question_mcsaon',
                array('question' => $question->id), '*', MUST_EXIST);
        question_type::get_question_options($question);
    }

    public function save_question_options($question) {
        global $DB;
        $context = $question->context;
        $result = new stdClass();

        $oldanswers = $DB->get_records('question_answers',
                array('question' => $question->id), 'id ASC');

        // following hack to check at least two answers exist
        $answercount = 0;
        foreach ($question->answer as $key => $answer) {
            if ($answer != '') {
                $answercount++;
            }
        }
        if ($answercount < 2) { // check there are at lest 2 answers for multiple choice
            $result->notice = get_string('notenoughanswers', 'qtype_multichoice', '2');
            return $result;
        }

        // Insert all the new answers
        $totalfraction = 0;
        $maxfraction = -1;
        $answers = array();
        foreach ($question->answer as $key => $answerdata) {
            if (trim($answerdata['text']) == '') {
                continue;
            }

            // Update an existing answer if possible.
            $answer = array_shift($oldanswers);
            if (!$answer) {
                $answer = new stdClass();
                $answer->question = $question->id;
                $answer->answer = '';
                $answer->feedback = '';
                $answer->id = $DB->insert_record('question_answers', $answer);
            }

            // Doing an import
            $answer->answer = $this->import_or_save_files($answerdata,
                    $context, 'question', 'answer', $answer->id);
            $answer->answerformat = $answerdata['format'];
            $answer->fraction = $question->fraction[$key];
            $answer->feedback = $this->import_or_save_files($question->feedback[$key],
                    $context, 'question', 'answerfeedback', $answer->id);
            $answer->feedbackformat = $question->feedback[$key]['format'];

            $DB->update_record('question_answers', $answer);
            $answers[] = $answer->id;

            if ($question->fraction[$key] > 0) {
                $totalfraction += $question->fraction[$key];
            }
            if ($question->fraction[$key] > $maxfraction) {
                $maxfraction = $question->fraction[$key];
            }
        }

        // Delete any left over old answer records.
        $fs = get_file_storage();
        foreach ($oldanswers as $oldanswer) {
            $fs->delete_area_files($context->id, 'question', 'answerfeedback', $oldanswer->id);
            $DB->delete_records('question_answers', array('id' => $oldanswer->id));
        }

        $options = $DB->get_record('question_mcsaon', array('question' => $question->id));
        if (!$options) {
            $options = new stdClass();
            $options->question = $question->id;
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->id = $DB->insert_record('question_mcsaon', $options);
        }

        $options->answers = implode(',', $answers);
        $options->single = $question->single;
        if (isset($question->layout)) {
            $options->layout = $question->layout;
        }
        $options->answernumbering = $question->answernumbering;
        $options->shuffleanswers = $question->shuffleanswers;
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $DB->update_record('question_mcsaon', $options);

        $this->save_hints($question, true);

        // Perform sanity checks on fractional grades
        if ($options->single == 1) {
            if ($maxfraction != 1) {
                $result->notice = get_string('fractionsnomax', 'qtype_multichoice',
                        $maxfraction * 100);
                return $result;
            }
        } else if ($options->single == 2) { // All or nothing
            $totalfraction = round($totalfraction, 2);
            if ($totalfraction <= 0) {
                $result->notice = get_string('fractionsaddwrong', 'qtype_multichoice',
                        $totalfraction * 100);
                return $result;
            }
        } else {
            $totalfraction = round($totalfraction, 2);
            if ($totalfraction != 1) {
                $result->notice = get_string('fractionsaddwrong', 'qtype_multichoice',
                        $totalfraction * 100);
                return $result;
            }
        }
    }

    protected function make_question_instance($questiondata) {
        question_bank::load_question_definition_classes($this->name());
        if ($questiondata->options->single == 1) {
            $class = 'qtype_multichoice_single_question';
            return new $class();
        } else {
            $class = 'qtype_mcsaon_multi_question';
            return new $class($questiondata->options->single);
        }
    }

    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('question_mcsaon', array('question' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    public function get_random_guess_score($questiondata) {
        if ($questiondata->options->single != 1) {
            // Pretty much impossible to compute for _multi questions. Don't try.
            return null;
        }

        // Single choice questions - average choice fraction.
        $totalfraction = 0;
        foreach ($questiondata->options->answers as $answer) {
            $totalfraction += $answer->fraction;
        }
        return $totalfraction / count($questiondata->options->answers);
    }

    public function get_possible_responses($questiondata) {
        if ($questiondata->options->single == 1) {
            $responses = array();

            foreach ($questiondata->options->answers as $aid => $answer) {
                $responses[$aid] = new question_possible_response(html_to_text(format_text(
                        $answer->answer, $answer->answerformat, array('noclean' => true)),
                        0, false), $answer->fraction);
            }

            $responses[null] = question_possible_response::no_response();
            return array($questiondata->id => $responses);
        } else {
            $parts = array();

            foreach ($questiondata->options->answers as $aid => $answer) {
                $parts[$aid] = array($aid =>
                        new question_possible_response(html_to_text(format_text(
                        $answer->answer, $answer->answerformat, array('noclean' => true)),
                        0, false), $answer->fraction));
            }

            return $parts;
        }
    }

    /**
     *
     */
    public function export_to_xml($question, qformat_xml $format, $extra=null) {       

        if ($question->options->single == 2) {
            $single = 'aon';
        } else {
            $single = $this->get_single($question->options->single);
        }        

        $expout = '';
        $expout .= "    <single>" . $single. "</single>\n";
        $expout .= "    <shuffleanswers>". $format->get_single($question->options->shuffleanswers). "</shuffleanswers>\n";
        $expout .= "    <answernumbering>". $question->options->answernumbering. "</answernumbering>\n";
        $expout .= $format->write_combined_feedback($question->options, $question->id, $question->contextid);
        $expout .= $format->write_answers($question->options->answers);

        return $expout;
    }

    /**
     *
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        // get common parts
        $qo = $format->import_headers($data);

        // 'header' parts particular to multichoice
        $qo->qtype = 'mcsaon';
        $single = $format->getpath($data, array('#', 'single', 0, '#'), 'true');
        $qo->single = ($single == 'aon' ? 2 : $format->trans_single($single));
        $shuffleanswers = $format->getpath($data, array('#', 'shuffleanswers', 0, '#'), 'false');
        $qo->answernumbering = $format->getpath($data, array('#', 'answernumbering', 0, '#'), 'abc');
        $qo->shuffleanswers = $format->trans_single($shuffleanswers);

        // There was a time on the 1.8 branch when it could output an empty
        // answernumbering tag, so fix up any found.
        if (empty($qo->answernumbering)) {
            $qo->answernumbering = 'abc';
        }

        // Run through the answers
        $answers = $data['#']['answer'];
        $acount = 0;
        foreach ($answers as $answer) {
            $ans = $format->import_answer($answer, true, $format->get_format($qo->questiontextformat));
            $qo->answer[$acount] = $ans->answer;
            $qo->fraction[$acount] = $ans->fraction;
            $qo->feedback[$acount] = $ans->feedback;
            ++$acount;
        }

        $format->import_combined_feedback($qo, $data, true);
        $format->import_hints($qo, $data, true, false, $format->get_format($qo->questiontextformat));

        return $qo;
    }


}
