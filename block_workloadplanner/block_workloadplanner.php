<?php
defined('MOODLE_INTERNAL') || die();
class block_workloadplanner extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_workloadplanner');
    }
    public function applicable_formats() {
        return ['all' => false, 'my' => true];
    }
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';
        if (isloggedin() && !isguestuser()) {
            $this->content->text = html_writer::tag('p',
                get_string('intro', 'block_workloadplanner'));
            $this->content->text .= html_writer::link(
                new moodle_url('/blocks/workloadplanner/view.php'),
                get_string('openplanner', 'block_workloadplanner'), ['class' => 'btn btn-primary']);
        }
        return $this->content;
    }
}
