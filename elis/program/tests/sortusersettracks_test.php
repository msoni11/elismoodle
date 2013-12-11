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
require_once(elispm::lib('data/track.class.php'));
require_once(elispm::lib('data/clustertrack.class.php'));

/**
 * Test clustertrack::get_tracks sorting.
 * @group elis_program
 */
class sortusersettracks_testcase extends elis_database_test {

    /**
     * Load initial data from CSVs
     */
    protected function load_csv_data() {
        $dataset = $this->createCsvDataSet(array(
            curriculum::TABLE => elis::component_file('program', 'tests/fixtures/curriculum.csv'),
            track::TABLE => elis::component_file('program', 'tests/fixtures/track.csv'),
            clustertrack::TABLE => elis::component_file('program', 'tests/fixtures/cluster_track.csv'),
        ));
        $this->loadDataSet($dataset);
    }

    /**
     * Provide data to dataproviders
     */
    public static function usersettracksdata() {
        $rec1 = new stdClass;
        $rec1->id = 1;
        $rec1->trackid = 1;
        $rec1->idnumber = 'TRK1';
        $rec1->name = 'TRK1';
        $rec1->description = 'TRK-1 Description';
        $rec1->startdate = 0;
        $rec1->enddate = 0;
        $rec1->autoenrol = 0;

        $rec2 = new stdClass;
        $rec2->id = 2;
        $rec2->trackid = 2;
        $rec2->idnumber = 'TRK-ID2';
        $rec2->name = 'TRK2';
        $rec2->description = 'TRK-2 Description';
        $rec2->startdate = 0;
        $rec2->enddate = 0;
        $rec2->autoenrol = 1;

        $rec3 = new stdClass;
        $rec3->id = 3;
        $rec3->trackid = 3;
        $rec3->idnumber = 'TRK-3';
        $rec3->name = 'TRK-3';
        $rec3->description = 'TRK-3 Description';
        $rec3->startdate = 0;
        $rec3->enddate = 0;
        $rec3->autoenrol = 1;

        $dataset = array();
        $dataset[$rec1->id] =  $rec1;
        $dataset[$rec2->id] =  $rec2;
        $dataset[$rec3->id] =  $rec3;

        return $dataset;
    }

    public static function sortidnumberascprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[3], $ustdata[2], $ustdata[1]));
        return $dataset;
    }

    public static function sortidnumberdescprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[1], $ustdata[2], $ustdata[3]));
        return $dataset;
    }

    public static function sortnameascprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[3], $ustdata[1], $ustdata[2]));
        return $dataset;
    }

    public static function sortnamedescprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[2], $ustdata[1], $ustdata[3]));
        return $dataset;
    }

    public static function sortdescriptionascprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[1], $ustdata[2], $ustdata[3]));
        return $dataset;
    }

    public static function sortdescriptiondescprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[3], $ustdata[2], $ustdata[1]));
        return $dataset;
    }

    public static function sortautoenrolascprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[1], $ustdata[2], $ustdata[3]));
        return $dataset;
    }

    public static function sortautoenroldescprovider() {
        $ustdata = self::usersettracksdata();
        $dataset = array();
        $dataset[] = array(array($ustdata[2], $ustdata[3], $ustdata[1]));
        return $dataset;
    }

    /**
     * @dataProvider sortidnumberdescprovider
     */
    public function testsortidnumberdesc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'idnumber', 'DESC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }

    /**
     * @dataProvider sortidnumberascprovider
     */
    public function testsortidnumberasc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'idnumber', 'ASC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }

    /**
     * @dataProvider sortnameascprovider
     */
    public function testsortnameasc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'name', 'ASC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }

    /**
     * @dataProvider sortnamedescprovider
     */
    public function testsortnamedesc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'name', 'DESC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }

    /**
     * @dataProvider sortdescriptionascprovider
     */
    public function testsortdescriptionasc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'description', 'ASC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }

    /**
     * @dataProvider sortdescriptiondescprovider
     */
    public function testsortdescriptiondesc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'description', 'DESC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }

    /**
     * @dataProvider sortautoenrolascprovider
     */
    public function testsortautoenrolasc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'autoenrol', 'ASC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }

    /**
     * @dataProvider sortautoenroldescprovider
     */
    public function testsortautoenroldesc($data) {
        $this->load_csv_data();
        $dataset = clustertrack::get_tracks(1, 'autoenrol', 'DESC');
        $this->assertEquals(array_values($data), array_values($dataset));
    }
}