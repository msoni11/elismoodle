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
 * @copyright  (C) 2013 Remote Learner.net Inc http://www.remote-learner.net
 * @author     James McQuillan <james.mcquillan@remote-learner.net>
 *
 */

require_once(dirname(__FILE__).'/../../core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(dirname(__FILE__).'/other/deepsight_testlib.php');

require_once(elispm::lib('deepsightpage.class.php'));
require_once(elispm::lib('selectionpage.class.php'));

require_once(elispm::lib('data/clustertrack.class.php'));
require_once(elispm::lib('data/curriculum.class.php'));
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elispm::lib('data/usermoodle.class.php'));

/**
 * Mock trackuserset_assigned datatable class to expose protected methods and properties.
 */
class deepsight_datatable_trackuserset_assigned_mock extends deepsight_datatable_trackuserset_assigned {

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __get($name) {
        return (isset($this->$name)) ? $this->$name : false;
    }

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __isset($name) {
        return (isset($this->$name)) ? true : false;
    }

    /**
     * Expose protected methods.
     * @param string $name The name of the called method.
     * @param array $args Array of arguments.
     */
    public function __call($name, $args) {
        if (method_exists($this, $name)) {
            return call_user_func_array(array($this, $name), $args);
        }
    }

    /**
     * Expose protected properties.
     * @param string $name The name of the property.
     * @param mixed $val The name to set.
     */
    public function __set($name, $val) {
        $this->$name = $val;
    }
}

/**
 * Mock trackuserset_available datatable class to expose protected methods and properties.
 */
class deepsight_datatable_trackuserset_available_mock extends deepsight_datatable_trackuserset_available {

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __get($name) {
        return (isset($this->$name)) ? $this->$name : false;
    }

    /**
     * Magic function to expose protected properties.
     * @param string $name The name of the property
     * @return string|int|bool The value of the property
     */
    public function __isset($name) {
        return (isset($this->$name)) ? true : false;
    }

    /**
     * Expose protected methods.
     * @param string $name The name of the called method.
     * @param array $args Array of arguments.
     */
    public function __call($name, $args) {
        if (method_exists($this, $name)) {
            return call_user_func_array(array($this, $name), $args);
        }
    }

    /**
     * Expose protected properties.
     * @param string $name The name of the property.
     * @param mixed $val The name to set.
     */
    public function __set($name, $val) {
        $this->$name = $val;
    }
}

/**
 * Tests trackuserset datatable functions.
 * @group elis_program
 * @group deepsight
 */
class deepsight_datatable_trackuserset_testcase extends deepsight_datatable_searchresults_test {
    /**
     * @var string The CSV to use for asserting results.
     */
    public $resultscsv = 'deepsight_userset.csv';

    /**
     * Do any setup before tests that rely on data in the database - i.e. create users/courses/classes/etc or import csvs.
     */
    protected function set_up_tables() {
        $dataset = $this->createCsvDataSet(array(
            curriculum::TABLE => elispm::file('tests/fixtures/deepsight_program.csv'),
            track::TABLE => elispm::file('tests/fixtures/deepsight_track.csv'),
            userset::TABLE => elispm::file('tests/fixtures/deepsight_userset.csv'),
            user::TABLE => elispm::file('tests/fixtures/deepsight_user.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Transform an element from a csv into a search result array.
     * @param array $element An array of raw data from the CSV.
     * @return array A single search result array.
     */
    protected function create_search_result_from_csvelement($element) {
        return array(
            'element_id' => $element['id'],
            'element_name' => $element['name'],
            'id' => $element['id'],
            'meta' => array(
                'label' => $element['name']
            ),
            'autoenroldefault' => 0,
        );
    }

    /**
     * Dataprovider for test_assigned_shows_assigned_usersets.
     * @return array Array of test parameters.
     */
    public function dataprovider_assigned_shows_assigned_usersets() {
        return array(
                // 0: Test table shows nothing when no associations present.
                array(
                        array(),
                        2,
                        array(),
                        0,
                ),
                // 1: Test table shows nothing when no associations present for current track.
                array(
                        array(
                                array('trackid' => 100, 'clusterid' => 2),
                        ),
                        101,
                        array(),
                        0,
                ),
                // 2: Test table shows existing associations for the current track.
                array(
                        array(
                                array('trackid' => 100, 'clusterid' => 2),
                        ),
                        100,
                        array(
                                $this->get_search_result_row($this->resultscsv, 2),
                        ),
                        1,
                ),
                // 3: Test table shows multiple existing associations for the current track.
                array(
                        array(
                                array('trackid' => 100, 'clusterid' => 1),
                                array('trackid' => 100, 'clusterid' => 2),
                        ),
                        100,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                        ),
                        2,
                ),
                // 4: Test table shows multiple existing associations for the current track and associations for other tracks
                // don't appear.
                array(
                        array(
                                array('trackid' => 100, 'clusterid' => 1),
                                array('trackid' => 100, 'clusterid' => 2),
                                array('trackid' => 101, 'clusterid' => 3),
                                array('trackid' => 101, 'clusterid' => 4),
                        ),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test assigned table shows assigned usersets.
     *
     * @dataProvider dataprovider_assigned_shows_assigned_usersets
     * @param array $associations An array of arrays of parameters to construct clustertrack associations.
     * @param int $tabletrackid The ID of the track we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_assigned_shows_assigned_usersets($associations, $tabletrackid, $expectedresults, $expectedtotal) {
        global $DB;

        foreach ($associations as $association) {
            $clustertrack = new clustertrack($association);
            $clustertrack->save();
        }

        $table = new deepsight_datatable_trackuserset_assigned_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid($tabletrackid);

        $actualresults = $table->get_search_results(array(), array(), 0, 20);
        foreach ($expectedresults as &$result) {
            unset($result['autoenroldefault']);
        }
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);
    }

    /**
     * Test available table can show all usersets.
     */
    public function test_available_can_show_all_usersets() {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));

        // Construct test table.
        $table = new deepsight_datatable_trackuserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid(100);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $expectedresults = array(
                $this->get_search_result_row($this->resultscsv, 1),
                $this->get_search_result_row($this->resultscsv, 2),
                $this->get_search_result_row($this->resultscsv, 3),
                $this->get_search_result_row($this->resultscsv, 4),
                $this->get_search_result_row($this->resultscsv, 5),
        );
        $expectedtotal = 5;
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_doesnt_show_assigned_usersets.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_doesnt_show_assigned_usersets() {
        return array(
                // Test the table shows all tracks when nothing is assigned.
                array(
                        array(),
                        100,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        5
                ),
                // Test the table doesn't show assigned tracks.
                array(
                        array(
                                array('trackid' => 102, 'clusterid' => 2),
                        ),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        4
                ),
                // Test multiple assignments.
                array(
                        array(
                                array('trackid' => 102, 'clusterid' => 3),
                                array('trackid' => 102, 'clusterid' => 4),
                        ),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        3
                ),
                // Test only assignments for the current userset affect results.
                array(
                        array(
                                array('trackid' => 101, 'clusterid' => 1),
                                array('trackid' => 102, 'clusterid' => 2),
                                array('trackid' => 102, 'clusterid' => 3),
                        ),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        3
                ),
        );
    }

    /**
     * Test available table doesn't show assigned usersets.
     * @dataProvider dataprovider_available_doesnt_show_assigned_usersets
     * @param array $associations An array of arrays of parameters to construct clustertrack associations.
     * @param int $tabletrackid The ID of the track we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_doesnt_show_assigned_usersets($associations, $tabletrackid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();
        $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));

        foreach ($associations as $association) {
            $clustertrack = new clustertrack($association);
            $clustertrack->save();
        }

        // Construct test table.
        $table = new deepsight_datatable_trackuserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid($tabletrackid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }

    /**
     * Dataprovider for test_available_permissions_associate.
     * @return array Array of test parameters.
     */
    public function dataprovider_available_permissions_associate() {
        return array(
                // 0: Test no permissons results in no results.
                array(
                        array(),
                        1,
                        array(),
                        0,
                ),
                // 1: Test permissions on the system level results in all results.
                array(
                        array('system' => true),
                        101,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 2),
                                $this->get_search_result_row($this->resultscsv, 3),
                                $this->get_search_result_row($this->resultscsv, 4),
                                $this->get_search_result_row($this->resultscsv, 5),
                        ),
                        5,
                ),
                // 2: Test permissions on one track returns that track.
                array(
                        array('userset' => array(1)),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                        ),
                        1,
                ),
                // 3: Test permissions on multiple tracks returns those tracks.
                array(
                        array('userset' => array(1, 3)),
                        102,
                        array(
                                $this->get_search_result_row($this->resultscsv, 1),
                                $this->get_search_result_row($this->resultscsv, 3),
                        ),
                        2,
                ),
        );
    }

    /**
     * Test available table only shows usersets that the assigner has the elis/program::associate permission on.
     * @dataProvider dataprovider_available_permissions_associate
     * @param array $contextstoassign An array of information specifying the contexts to assign the associate permission on.
     *                                This is formatted like array('system' => true, 'track' => array(1, 2, 3))
     * @param int $tabletrackid The ID of the track we're going to manage.
     * @param array $expectedresults The expected page of results.
     * @param int $expectedtotal The expected number of total results.
     */
    public function test_available_permissions_associate($contextstoassign, $tabletrackid, $expectedresults, $expectedtotal) {
        global $USER, $DB, $CFG;
        $userbackup = $USER;

        // Set up permissions.
        $USER = $this->setup_permissions_test();

        // Set up capabilities.
        foreach ($contextstoassign as $contexttype => $ids) {
            if ($contexttype === 'system') {
                $this->give_permission_for_context($USER->id, 'elis/program:associate', get_context_instance(CONTEXT_SYSTEM));
            } else {
                foreach ($ids as $contextinstanceid) {
                    switch($contexttype) {
                        case 'userset':
                            $context = context_elis_userset::instance($contextinstanceid);
                            break;
                    }
                    $this->give_permission_for_context($USER->id, 'elis/program:associate', $context);
                }
            }
        }

        accesslib_clear_all_caches(true);

        // Construct test table.
        $table = new deepsight_datatable_trackuserset_available_mock($DB, 'test', 'http://localhost', 'testuniqid');
        $table->set_trackid($tabletrackid);

        // Perform test.
        $actualresults = $table->get_search_results(array(), array(), 0, 20);

        // Verify result.
        $this->assert_search_results($expectedresults, $expectedtotal, $actualresults);

        // Restore user.
        $USER = $userbackup;
    }
}