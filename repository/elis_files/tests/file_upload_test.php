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
 * @package    repository_elis_files
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(dirname(__FILE__).'/../../../elis/core/test_config.php');
require_once($CFG->dirroot.'/elis/core/lib/setup.php');
require_once($CFG->dirroot.'/repository/elis_files/ELIS_files_factory.class.php');
require_once($CFG->dirroot.'/repository/elis_files/lib/lib.php');
require_once($CFG->dirroot.'/repository/elis_files/lib.php');
require_once($CFG->dirroot.'/repository/elis_files/tests/constants.php');

/**
 * Class for testing the uploading of files
 * @group repository_elis_files
 */
class repository_elis_files_file_upload_testcase extends elis_database_test {
    /**
     * This function create a temp file used by other tests.
     * @uses $CFG
     * @param int $mbs The file size (in MB) to generate
     * @return string the file name
     */
    protected function generate_temp_file($mbs) {
        global $CFG;

        $fname = tempnam($CFG->dataroot.'/temp/', FOLDER_NAME_PREFIX);

        if (!$fh = fopen($fname, 'w+')) {
            error('Could not open temporary file');
        }

        $maxbytes = $mbs * ONE_MB_BYTES;
        $data     = '';
        $fsize    = 0;

        for ($i = 0; $i < $mbs; $i++) {
            while ((strlen($data) < ONE_MB_BYTES) && ((strlen($data) + $fsize) < $maxbytes)) {
                $data .= 'a';
            }

            fwrite($fh, $data);
            $fsize += strlen($data);
        }

        fclose($fh);

        return $fname;
    }

    /**
     * This function loads data into the PHPUnit tables for testing.
     */
    protected function setup_test_data_xml() {
        $this->loadDataSet($this->createXMLDataSet(__DIR__.'/fixtures/elis_files_config.xml'));
        $this->loadDataSet($this->createXMLDataSet(__DIR__.'/fixtures/elis_files_instance.xml'));
    }

    /** @var array An array of unique ids. */
    protected $createduuids = array();

    /**
     * This function initializes all of the setup steps required by each step.
     */
    protected function setUp() {
        parent::setUp();
        $this->setAdminUser();
    }

    /**
     * This function removes any initialized data.
     */
    protected function tearDown() {
        foreach ($this->createduuids as $uuid) {
            elis_files_delete($uuid);
        }
        if ($dir = elis_files_read_dir()) {
            foreach ($dir->files as $file) {
                if (strpos($file->title, FOLDER_NAME_PREFIX) === 0 ||
                    strpos($file->title, FILE_NAME_PREFIX) === 0 ) {
                    elis_files_delete($file->uuid);
                }
            }
        }
        parent::tearDown();
    }

    /**
     * This funciton uploads a file asserts some tests.
     * @param ELIS_files $repo an instance of ELIS_files
     * @param string $upload   The array index of the uploaded file.
     * @param string $path     The full path to the file on the local filesystem.
     * @param string $uuid     The UUID of the folder where the file is being uploaded to.
     * @return object Node values for the uploaded file.
     */
    protected function call_upload_file($repo, $upload, $path, $uuid) {
        $response = elis_files_upload_file($upload, $path, $uuid);
        if ($response && !empty($response->uuid)) {
            $this->createduuids[] = $response->uuid;
            $node = elis_files_get_parent($response->uuid);
            $this->assertTrue($node && !empty($node->uuid));
            $this->assertEquals($uuid, $node->uuid);
        }
        return $response;
    }

    /**
     * This function returns an array of an array of integers.
     */
    public function file_size_provider() {
        return array(
                array(8),
                array(16),
                array(32),
                array(64),
                array(128),
                array(256),
                array(512),
                array(1024),
                array(2048),
        );
    }

    /**
     * Test uploading of files with progressively larger sizes.
     * @param int $mb the size of the file
     * @dataProvider file_size_provider
     */
    public function test_upload_incremental_file_sizes($mb) {
        $this->resetAfterTest(true);
        $this->setup_test_data_xml();

        $this->markTestSkipped('Not necessary to test incremental file sizes at this time.');

        // Check if Alfresco is enabled, configured and running first
        if (!$repo = repository_factory::factory('elis_files')) {
            $this->markTestSkipped();
        }

        if (!$repo = repository_factory::factory('elis_files')) {
            $this->markTestSkipped('Repository not configured or enabled');
        }

        $filesize = $mb * ONE_MB_BYTES;
        $filename = $this->generate_temp_file($mb);

        $response = $this->call_upload_file($repo, '', $filename, $repo->root->uuid);

        unlink($filename);

        $this->assertNotEquals(false, $response);
        $this->assertObjectHasAttribute('uuid', $response);
    }

    /**
     * Test uploading a file to Alfresco explicitly using the web services method.
     */
    public function test_upload_file_via_ws() {
        $this->resetAfterTest(true);
        $this->setup_test_data_xml();

        // Check if Alfresco is enabled, configured and running first
        if (!$repo = repository_factory::factory('elis_files')) {
            $this->markTestSkipped();
        }

        // Explicitly set the file transfer method to Web Services
        set_config('file_transfer_method', ELIS_FILES_XFER_WS, 'elis_files');

        $filename = $this->generate_temp_file(1);

        $response = $this->call_upload_file($repo, '', $filename, $repo->root->uuid);

        unlink($filename);

        $this->assertNotEquals(false, $response);
        $this->assertObjectHasAttribute('uuid', $response);
    }

    /**
     * Test uploading a file to Alfresco explicitly using the web services method.
     */
    public function test_upload_file_via_ftp() {
        $this->resetAfterTest(true);
        $this->setup_test_data_xml();

        // Check if Alfresco is enabled, configured and running first
        if (!$repo = repository_factory::factory('elis_files')) {
            $this->markTestSkipped();
        }

        // Explicitly set the file transfer method to FTP
        set_config('file_transfer_method', ELIS_FILES_XFER_FTP, 'elis_files');

        $targets = array(
                $repo->root->uuid,
                $repo->muuid,
                $repo->suuid,
                $repo->cuuid,
                $repo->uuuid,
                $repo->ouuid
        );

        foreach ($targets as $uuid) {
            $filename = $this->generate_temp_file(1);
            $response = $this->call_upload_file($repo, '', $filename, $uuid);
            unlink($filename);
            $this->assertNotEquals(false, $response);
            $this->assertObjectHasAttribute('uuid', $response);
        }
    }

    /**
     * This function returns an array of arrays of strings.
     */
    public function file_extensions_provider() {
        return array(
                array('EMPTY'),
                array('c'),
                array('csv'),
                array('docx'),
                array('pdf'),
                array('png'),
                array('xml'),
        );
    }

    /**
     * Test uploading a file to Alfresco explicitly using the web services method.
     * @uses $CFG, $DB
     * @param string $extension file extension
     * @dataProvider file_extensions_provider
     */
    public function test_upload_file_types_via_ws($extension) {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $this->setup_test_data_xml();

        // Check for ELIS_files repository
        if (file_exists($CFG->dirroot.'/repository/elis_files/')) {
            // RL: ELIS files: Alfresco
            $data = null;
            $listing = null;
            $sql = 'SELECT i.name, i.typeid, r.type
                      FROM {repository} r, {repository_instances} i
                     WHERE r.type = ? AND i.typeid = r.id';

            $repository = $DB->get_record_sql($sql, array('elis_files'));

            if ($repository) {
                try {
                    $repo = new repository_elis_files('elis_files', get_context_instance(CONTEXT_SYSTEM),
                            array('ajax' => false,
                                  'name' => $repository->name,
                                  'type' => 'elis_files'
                            )
                    );
                } catch (Exception $e) {
                    $this->markTestSkipped();
                }
            } else {
                $this->markTestSkipped();
            }
        } else {
            $this->markTestSkipped();
        }

        // Explicitly set the file transfer method to Web Services
        set_config('file_transfer_method', ELIS_FILES_XFER_WS, 'elis_files');

        // Handle the no extension test case
        $extension = ($extension == 'EMPTY') ? '' : '.'.$extension;

        $filename = $CFG->dirroot.'/repository/elis_files/tests/'.FILE_NAME_PREFIX.$extension;
        $response = $this->call_upload_file($repo, '', $filename, $repo->elis_files->root->uuid);

        // Download the file and compare contents
        $thefile = $repo->get_file($response->uuid);

        // Assert that the downloaded file is the same as the uploaded file
        $this->assertFileEquals($filename, $thefile['path']);
    }
}
