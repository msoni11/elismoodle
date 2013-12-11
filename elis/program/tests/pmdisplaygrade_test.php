<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis_program
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');

// Libs.
require_once(elispm::lib('lib.php'));

/**
 * Test pm_display_grade function.
 * @group elis_program
 */
class pmdisplaygrade_testcase extends basic_testcase {

    /**
     * Data provider for testPmDisplayGrade
     * format: array( input value, expectedreturn string )
     *
     * @return array The test data.
     */
    public function pmdisplaygrade_data() {
        return array(
            array('96', '96'),
            array(96, '96'),
            array((float)96, '96.00'),
            array(87.8355550000, '87.84'),
            array((int)1, '1'),
            array('1', '1'),
            array(1.0, '1.00'),
            array(1.005, '1.01')
        );
    }

    /**
     * Method to test pm_display_grade()
     *
     * @param string|float|int $inval Input value to be passed to pm_display_grade()
     * @param string $expected The expected output from pm_display_grade() for input value
     * @dataProvider pmdisplaygrade_data
     */
    public function testpmdisplaygrade($inval, $expected) {
        $actual = pm_display_grade($inval);
        $this->assertTrue($expected === (string)$actual);
    }
}