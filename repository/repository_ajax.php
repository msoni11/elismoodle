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
 * The Web service script that is called from the filepicker front end
 *
 * @since 2.0
 * @package    repository
 * @copyright  2009 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(__FILE__)).'/config.php');
require_once(dirname(dirname(__FILE__)).'/lib/filelib.php');
require_once(dirname(__FILE__).'/lib.php');

$err = new stdClass();

// Parameters
$action    = optional_param('action', '', PARAM_ALPHA);
$repo_id   = optional_param('repo_id', 0, PARAM_INT);           // Repository ID
$contextid = optional_param('ctx_id', SYSCONTEXTID, PARAM_INT); // Context ID
$env       = optional_param('env', 'filepicker', PARAM_ALPHA);  // Opened in editor or moodleform
$license   = optional_param('license', $CFG->sitedefaultlicense, PARAM_TEXT);
$author    = optional_param('author', '', PARAM_TEXT);          // File author
$source    = optional_param('source', '', PARAM_RAW);           // File to download
$itemid    = optional_param('itemid', 0, PARAM_INT);            // Itemid
$page      = optional_param('page', '', PARAM_RAW);             // Page
$maxbytes  = optional_param('maxbytes', 0, PARAM_INT);          // Maxbytes
$req_path  = optional_param('p', '', PARAM_RAW);                // Path
$accepted_types  = optional_param_array('accepted_types', '*', PARAM_RAW);
$saveas_filename = optional_param('title', '', PARAM_FILE);     // save as file name
$areamaxbytes  = optional_param('areamaxbytes', FILE_AREA_MAX_BYTES_UNLIMITED, PARAM_INT); // Area max bytes.
$saveas_path   = optional_param('savepath', '/', PARAM_PATH);   // save as file path
$search_text   = optional_param('s', '', PARAM_CLEANHTML);
$linkexternal  = optional_param('linkexternal', '', PARAM_ALPHA);
$usefilereference  = optional_param('usefilereference', false, PARAM_BOOL);
//RL EDIT: BJB130215
$overwriteexisting = optional_param('overwrite', false, PARAM_BOOL);
$categories        = optional_param_array('categories', NULL, PARAM_RAW); // this parameter specifies an array of categories to filter on
// End RL EDIT
list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm, false, true);
$PAGE->set_context($context);

echo $OUTPUT->header(); // send headers

// If uploaded file is larger than post_max_size (php.ini) setting, $_POST content will be empty.
if (empty($_POST) && !empty($action)) {
    $err->error = get_string('errorpostmaxsize', 'repository');
    die(json_encode($err));
}

if (!confirm_sesskey()) {
    $err->error = get_string('invalidsesskey', 'error');
    die(json_encode($err));
}

// Get repository instance information
$repooptions = array(
    'ajax' => true,
    'mimetypes' => $accepted_types
);
$repo = repository::get_repository_by_id($repo_id, $contextid, $repooptions);

// Check permissions
$repo->check_capability();

$coursemaxbytes = 0;
if (!empty($course)) {
    $coursemaxbytes = $course->maxbytes;
}
// Make sure maxbytes passed is within site filesize limits.
$maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $coursemaxbytes, $maxbytes);

// Wait as long as it takes for this script to finish
set_time_limit(0);

// These actions all occur on the currently active repository instance
switch ($action) {
    case 'sign':
    case 'signin':
    case 'list':
        if ($repo->check_login()) {
            $listing = repository::prepare_listing($repo->get_listing($req_path, $page));
            // RL EDIT: BJB130215
            if (get_class($repo) == 'repository_elis_files' && !empty($listing['path']) && !empty($listing['path'][0]['name']) &&
                $listing['path'][0]['name'] == $repo->elis_files->get_root()->title) {
                $listing['path'][0]['name'] = get_string('repository', 'repository_elis_files');
            }
            // End RL EDIT
            $listing['repo_id'] = $repo_id;
            echo json_encode($listing);
            break;
        } else {
            $action = 'login';
        }
    case 'login':
        $listing = $repo->print_login();
        $listing['repo_id'] = $repo_id;
        echo json_encode($listing);
        break;
    case 'logout':
        $logout = $repo->logout();
        $logout['repo_id'] = $repo_id;
        echo json_encode($logout);
        break;
    case 'searchform':
        $search_form['repo_id'] = $repo_id;
        $search_form['form'] = $repo->print_search();
        $search_form['allowcaching'] = true;
        // RL EDIT: BJB130301 ELIS-8326 (Kaltura)
        $search_form['tree'] = method_exists($repo, 'category_tree') ? $repo->category_tree() : array();
        // End RL EDIT
        echo json_encode($search_form);
        break;
    case 'search':
        // RL EDIT: BJB130215
        // Perform the search, filtering on categories and search text
        $search_result = repository::prepare_listing($repo->search($search_text, (int)$page, $categories));
        $search_result['advancedsearch'] = true;
        $search_result['executesearch'] = true;
        // End RL EDIT
        $search_result['repo_id'] = $repo_id;
        $search_result['issearchresult'] = true;
        echo json_encode($search_result);
        break;
    case 'download':
        // RL EDIT: BJB130215
        $toelisfiles = (strpos($saveas_path, '/') === 0) // TBD
                ? false : file_exists($CFG->dirroot.'/repository/elis_files/');
        // End RL EDIT
        // validate mimetype
        $mimetypes = array();
        if ((is_array($accepted_types) and in_array('*', $accepted_types)) or $accepted_types == '*') {
            $mimetypes = '*';
        } else {
            foreach ($accepted_types as $type) {
                $mimetypes[] = mimeinfo('type', $type);
            }
            if (!in_array(mimeinfo('type', $saveas_filename), $mimetypes)) {
                throw new moodle_exception('invalidfiletype', 'repository', '', get_mimetype_description(array('filename' => $saveas_filename)));
            }
        }

        // We have two special repository type need to deal with
        // local and recent plugins don't added new files to moodle, just add new records to database
        // so we don't check user quota and maxbytes here
        $allowexternallink = (int)get_config(null, 'repositoryallowexternallinks');
        if (!empty($allowexternallink)) {
            $allowexternallink = true;
        } else {
            $allowexternallink = false;
        }
        // allow external links in url element all the time
        $allowexternallink = !$toelisfiles && ($allowexternallink || ($env == 'url')); // RL EDIT

        $reference = $repo->get_file_reference($source);

        // Use link of the files
        if ($allowexternallink and $linkexternal === 'yes' and ($repo->supported_returntypes() & FILE_EXTERNAL)) {
            // use external link
            $link = $repo->get_link($reference);
            $info = array();
            $info['file'] = $saveas_filename;
            $info['type'] = 'link';
            $info['url'] = $link;
            echo json_encode($info);
            die;
        } else {
            $fs = get_file_storage();

            // Prepare file record.
            $record = new stdClass();
            $record->filepath = $saveas_path;
            $record->filename = $saveas_filename;
            $record->component = 'user';
            $record->filearea = 'draft';
            $record->itemid = $itemid;
            $record->license = $license;
            $record->author = $author;

            if ($record->filepath !== '/') {
                $record->filepath = trim($record->filepath, '/');
                $record->filepath = '/'.$record->filepath.'/';
            }
            $usercontext = context_user::instance($USER->id);
            $now = time();
            $record->contextid = $usercontext->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $record->userid = $USER->id;
            $record->sortorder = 0;

            // Check that user has permission to access this file
            if (!$repo->file_is_accessible($source)) {
                throw new file_exception('storedfilecannotread');
            }

            // {@link repository::build_source_field()}
            $sourcefield = $repo->get_file_source_info($source);
            $record->source = $repo::build_source_field($sourcefield);

            // If file is already a reference, set $source = file source, $repo = file repository
            // note that in this case user may not have permission to access the source file directly
            // so no file_browser/file_info can be used below
            if ($repo->has_moodle_files()) {
                $file = repository::get_moodle_file($source);
                if ($file && $file->is_external_file()) {
                    $sourcefield = $file->get_source(); // remember the original source
                    $record->source = $repo::build_source_field($sourcefield);
                    $record->contenthash = $file->get_contenthash();
                    $record->filesize = $file->get_filesize();
                    $reference = $file->get_reference();
                    $repo_id = $file->get_repository_id();
                    $repo = repository::get_repository_by_id($repo_id, $contextid, $repooptions);
                }
            }

            if ($usefilereference) {
                if ($repo->has_moodle_files()) {
                    $sourcefile = repository::get_moodle_file($reference);
                    $record->contenthash = $sourcefile->get_contenthash();
                    $record->filesize = $sourcefile->get_filesize();
                }
                // Check if file exists.
                if (repository::draftfile_exists($itemid, $saveas_path, $saveas_filename)) {
                    // File name being used, rename it.
                    $unused_filename = repository::get_unused_filename($itemid, $saveas_path, $saveas_filename);
                    $record->filename = $unused_filename;
                    // Create a file copy using unused filename.
                    $storedfile = $fs->create_file_from_reference($record, $repo_id, $reference);

                    $event = array();
                    $event['event'] = 'fileexists';
                    $event['newfile'] = new stdClass;
                    $event['newfile']->filepath = $saveas_path;
                    $event['newfile']->filename = $unused_filename;
                    $event['newfile']->url = moodle_url::make_draftfile_url($itemid, $saveas_path, $unused_filename)->out();

                    $event['existingfile'] = new stdClass;
                    $event['existingfile']->filepath = $saveas_path;
                    $event['existingfile']->filename = $saveas_filename;
                    $event['existingfile']->url      = moodle_url::make_draftfile_url($itemid, $saveas_path, $saveas_filename)->out();
                } else {

                    $storedfile = $fs->create_file_from_reference($record, $repo_id, $reference);
                    $event = array(
                        'url'=>moodle_url::make_draftfile_url($storedfile->get_itemid(), $storedfile->get_filepath(), $storedfile->get_filename())->out(),
                        'id'=>$storedfile->get_itemid(),
                        'file'=>$storedfile->get_filename(),
                        'icon' => $OUTPUT->pix_url(file_file_icon($storedfile, 32))->out(),
                    );
                }
                // Repository plugin callback
                // You can cache reository file in this callback
                // or complete other tasks.
                $repo->cache_file_by_reference($reference, $storedfile);
                echo json_encode($event);
                die;
            } else if ($repo->has_moodle_files()) {
                // Some repository plugins (local, user, coursefiles, recent) are hosting moodle
                // internal files, we cannot use get_file method, so we use copy_to_area method

                // If the moodle file is an alias we copy this alias, otherwise we copy the file
                // {@link repository::copy_to_area()}.
                $fileinfo = $repo->copy_to_area($reference, $record, $maxbytes, $areamaxbytes);

                // RL EDIT: BJB130215 - ELIS Files (alfresco)
                $decodedsrc = unserialize(base64_decode($source));
                // error_log("repository_ajax.php::download (IV): saveas_path = {$saveas_path}, toelisfiles = {$toelisfiles}");
                if ($toelisfiles) {
                    // Copying to ELIS Files (Alfresco repo)
                    $fpsrc = null;
                    $tempfname = false;
                    if (is_array($decodedsrc) && // TBD: isset(...) ?
                        ($userfile = $fs->get_file($decodedsrc['contextid'],
                                                   $decodedsrc['component'],
                                                   $decodedsrc['filearea'],
                                                   $decodedsrc['itemid'],
                                                   $decodedsrc['filepath'],
                                                   $decodedsrc['filename'])) &&
                        ($fpsrc = $userfile->get_content_file_handle()) &&
                        ($tempfname = tempnam(sys_get_temp_dir(), 'rl_ef')) !== false &&
                        ($fpdest = fopen($tempfname, 'w+b'))) {
                        require_once($CFG->dirroot .'/repository/elis_files/lib/lib.php');
                        while (!feof($fpsrc)) {
                            fwrite($fpdest, fread($fpsrc, 8192), 8192); // TBD
                        }
                        fclose($fpsrc);
                        fclose($fpdest);
                        $savename = dirname($tempfname) .'/'. $saveas_filename;
                        @rename($tempfname, $savename);
                        $decodedpath = unserialize(base64_decode($saveas_path));
                        $fileinfo = (array)elis_files_upload_file('', $savename,
                                               !empty($decodedpath['path'])
                                               ? $decodedpath['path'] : '');
                        @unlink($savename);
                    } else { // TBD
                        if ($fpsrc) {
                            fclose($fpsrc);
                        }
                        if ($tempfname !== false) {
                            @unlink($tempfname);
                        }
                        $err->error = get_string('cannotdownload', 'repository');
                        die(json_encode($err));
                    }
                } else { // TBD ??? is the following even required ???
                    $fileinfo = $repo->copy_to_area($source, $record, $maxbytes);
                }
                // End RL EDIT

                echo json_encode($fileinfo);
                die;
            } else {
                // Download file to moodle.
                $downloadedfile = $repo->get_file($reference, $saveas_filename);
                // error_log("repository_ajax.php::download (V): saveas_path = {$saveas_path}, toelisfiles = {$toelisfiles}");
                if (empty($downloadedfile['path'])) {
                    $err->error = get_string('cannotdownload', 'repository');
                    die(json_encode($err));
                }

                // Check if exceed maxbytes.
                if ($maxbytes != -1 && filesize($downloadedfile['path']) > $maxbytes) {
                    @unlink($downloadedfile['path']); // RL EDIT: BJB130215
                    // TBD: or return error object???
                    throw new file_exception('maxbytes');
                }

                // Check if we exceed the max bytes of the area.
                // RL EDIT: BJB130215 - TBD
                if (!$toelisfiles && file_is_draft_area_limit_reached($itemid, $areamaxbytes, filesize($downloadedfile['path']))) {
                    // TBD: or return error object???
                    throw new file_exception('maxareabytes');
                }

                // RL EDIT: BJB130215
                if ($toelisfiles) {
                    $decodedpath = unserialize(base64_decode($saveas_path));
                    $info = null;
                    if (!empty($decodedpath['path'])) {
                        // Handle duplicates
                        if (!$overwriteexisting) {
                            $listing = $repo->elis_files->read_dir($decodedpath['path']);
                            if ($duplicateuuid = elis_files_file_exists($saveas_filename, $listing)) {
                                $existingfilename = optional_param(
                                                        'existingfilename',
                                                        $saveas_filename,
                                                        PARAM_FILE); // TBD
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $mime_type = ''; // TBD: default?
                                if ($finfo) {
                                    $mime_type = finfo_file($finfo, $downloadedfile['path']);
                                    @finfo_close($finfo);
                                }
                                die(json_encode(elis_files_duplicate_event(
                                                    $action, $listing,
                                                    true /* TBD: duplicateelisfiles ??? */,
                                                    $existingfilename,
                                                    $saveas_path,
                                                    $downloadedfile['path'],
                                                    dirname($downloadedfile['path']) .'/',
                                                    filesize($downloadedfile['path']),
                                                    $mime_type,
                                                    $duplicateuuid, $repo_id)));
                            }
                        }
                        $info = (array)elis_files_upload_file('', $downloadedfile['path'], $decodedpath['path'], true, $saveas_filename);
                    } else {
                        @unlink($downloadedfile['path']);
                        $err->error = get_string('cannotdownload', 'repository');
                        die(json_encode($err));
                    }
                } else {
                $info = repository::move_to_filepool($downloadedfile['path'], $record);
                }
                @unlink($downloadedfile['path']);
                // End RL EDIT
                if (empty($info)) {
                    $info['e'] = get_string('error', 'moodle');
                }
            }
            echo json_encode($info);
            die;
        }
        break;

    case 'upload':
        // RL EDIT: BJB130215
        $elis_files_exists = file_exists($CFG->dirroot.'/repository/elis_files/lib/lib.php');
        if (method_exists($repo, 'upload')) {
        $result = $repo->upload($saveas_filename, $maxbytes);
        } else if ($elis_files_exists) {
            require_once($CFG->dirroot.'/repository/elis_files/lib/lib.php');
            $decodedpath = unserialize(base64_decode($saveas_path));
            // Track the success of this operation
            if (empty($decodedpath['path']) ||
                !($result = $repo->can_edit_decoded_path($decodedpath))) {
                // Handle any specific error signaled during the transfer
                error_log("repository_ajax::upload: cannot edit decoded path: {$saveas_path}!");
                $logger = elis_files_logger::instance();
                $error_message = $logger->signal_error(ELIS_FILES_ERROR_UPLOAD_PERMS);
                if (empty($error_message)) { // then use a generic error message
                    $error_message = get_string('errorupload', 'repository_elis_files');
                }
                $err = new stdClass;
                $err->error = $error_message;
                die(json_encode($err));
            }
            // Obtain the parent node's UUID for duplicate filename checking purposes
            $uuid = $decodedpath['path'];

            // Permissions ok, so attempt the upload
            $elname = 'repo_upload_file';

            // fetch the optional params and see if we are handling a rename, in which case $_FILES is gone
            $duplicateelisfiles = optional_param('duplicateelisfiles', false, PARAM_BOOL);
            $duplicatefilemetaname = optional_param('duplicatefilemetaname', false, PARAM_FILE);
            $duplicatefilemetapath = optional_param('duplicatefilemetapath', false, PARAM_PATH);
            $duplicatefilemetasize = optional_param('duplicatefilemetasize', false, PARAM_INT);
            $duplicatefilemetatype = optional_param('duplicatefilemetatype', false, PARAM_RAW);

            if (!$duplicateelisfiles && !isset($_FILES[$elname])) {
                error_log("/repository/repository_ajax.php: upload: duplicateelisfiles && _FILES[{$elname}] not set!");
                $err = new stdClass;
                $err->error = get_string('errorupload', 'repository_elis_files');
                die(json_encode($err));
            }

            $filesize = ($duplicateelisfiles) ? $duplicatefilemetasize : $_FILES[$elname]['size'];
            if (!elis_files_quota_check($filesize)) {
                $err = new stdClass;
                $err->error = get_string('erroruploadquota', 'repository_elis_files');
                die(json_encode($err));
            }

            // if we don't have a filename or this is a drag 'n drop rename
            if (isset($_FILES[$elname])) {
                $form_upload_name = clean_param($_FILES[$elname]['name'], PARAM_FILE);
            } else {
                $form_upload_name = '';
            }

            if (empty($saveas_filename)) {
                // TBD  ^^^ WAS: || $saveas_filename != $form_upload_name
                $newfilename = optional_param('newfilename', '', PARAM_FILE);
                $realfilename = !empty($newfilename)
                                ? $newfilename : $form_upload_name;
            } else {
                $realfilename = $saveas_filename;
            }

            $listing = $repo->elis_files->read_dir($uuid);
            if ($duplicateuuid = elis_files_file_exists($realfilename, $listing)) {
                if ($overwriteexisting) {
                    // drag 'n drop overwrite
                    //error_log("/repository/repository_ajax.php: upload: overwriteexistsing = TRUE!");
                    $result = elis_files_upload_file($elname, '', $uuid);
                } else {
                    $existingfilename = optional_param('existingfilename',
                                            $realfilename, PARAM_FILE); // TBD
                    die(json_encode(elis_files_duplicate_event($action,
                                        $listing, $duplicateelisfiles,
                                        $existingfilename, $saveas_path,
                                        $duplicateelisfiles
                                        ? '' : $_FILES[$elname]['tmp_name'],
                                        $duplicatefilemetapath,
                                        $duplicateelisfiles
                                        ? $duplicatefilemetasize
                                        : $_FILES[$elname]['size'],
                                        $duplicateelisfiles
                                        ? $duplicatefilemetatype
                                        : $_FILES[$elname]['type'],
                                        $duplicateuuid, $repo_id)));
                }
            } else if ($duplicateelisfiles) {
                // error_log("/repository/repository_ajax.php::upload: INFO: duplicateelisfiles flagged!");
                // See if we need to handle a rename here...
                // get the params required to handle duplicate files
                $newfilename = optional_param('newfilename', '', PARAM_FILE);
                $filemeta = new stdClass;
                $filemeta->name = !empty($newfilename) ? $newfilename : $duplicatefilemetaname;
                $filemeta->filepath = $duplicatefilemetapath;
                $filemeta->type = $duplicatefilemetatype;
                $filemeta->size = $duplicatefilemetasize;

                if (!empty($newfilename) &&
                    !@rename($duplicatefilemetapath.$duplicatefilemetaname,
                             $duplicatefilemetapath.$newfilename)) {
                    error_log("/repository/repository_ajax.php: upload: Failed copying file: {$duplicatefilemetapath}{$duplicatefilemetaname} to {$duplicatefilemetapath}{$newfilename}");
                    // delete files
                    @unlink($duplicatefilemetapath.$duplicatefilemetaname);
                    @unlink($duplicatefilemetapath.$newfilename);
                    $err = new stdClass;
                    $err->error = get_string('errorupload',
                                             'repository_elis_files');
                    die(json_encode($err));
                }
                $result = elis_files_upload_file($elname, '', $uuid, true, $newfilename, $duplicateuuid, $filemeta);

                // cleanup old file
                @unlink($duplicatefilemetapath.$newfilename);
            } else {
                // regular upload
                // error_log("/repository/repository_ajax.php::upload: INFO: Regular upload of {$realfilename}");
                $result = elis_files_upload_file($elname, '', $uuid, true, $realfilename);
            }
        }
        if (!$result && $elis_files_exists) {
            require_once(dirname(__FILE__).'/elis_files/lib/elis_files_logger.class.php');
            // Handle any specific error signaled during the transfer
            $logger = elis_files_logger::instance();
            $error_message = $logger->get_error_message();
            if (empty($error_message)) { // then use a generic error message
                $error_message = get_string('errorupload',
                                            'repository_elis_files');
            }
            error_log("/repository/repository_ajax.php::upload: Error: {$error_message}");
            // Pass back an encoded error
            $err = new stdClass;
            $err->error = $error_message;
            die(json_encode($err));
        }
        // End RL EDIT
        echo json_encode($result);
        break;

    case 'overwrite':
        // existing file
        $filepath    = required_param('existingfilepath', PARAM_PATH);
        $filename    = required_param('existingfilename', PARAM_FILE);
        // user added file which needs to replace the existing file
        $newfilepath = required_param('newfilepath', PARAM_PATH);
        $newfilename = required_param('newfilename', PARAM_FILE);

        // RL EDIT: BJB130215 - find out if this is an elis files duplicate
        $duplicateelisfiles = optional_param('duplicateelisfiles', false, PARAM_BOOL);
        $duplicateuuid = optional_param('duplicateuuid', false, PARAM_RAW);
        $duplicatefilemetaname = optional_param('duplicatefilemetaname', false, PARAM_FILE);
        $duplicatefilemetapath = optional_param('duplicatefilemetapath', false, PARAM_PATH);
        $duplicatefilemetasize = optional_param('duplicatefilemetasize', false, PARAM_INT);
        $duplicatefilemetatype = optional_param('duplicatefilemetatype', false, PARAM_RAW);

        // process elis files overwrite
        if ($duplicateelisfiles) {
            $elis_files_exists = file_exists($CFG->dirroot.'/repository/elis_files/lib/lib.php');
            require_once($CFG->dirroot.'/repository/elis_files/lib/lib.php');

            // get parent folder
            $decodedpath = unserialize(base64_decode($saveas_path));

            // Track the success of this operation
            $result = $repo->can_edit_decoded_path($decodedpath);
            if ($result) {
                // Obtain the parent node's UUID
                $uuid = $decodedpath['path'];

                $filemeta = new stdClass;
                $filemeta->name = $duplicatefilemetaname;
                $filemeta->filepath = $duplicatefilemetapath;
                $filemeta->type = $duplicatefilemetatype;
                $filemeta->size = $duplicatefilemetasize;

                $info = elis_files_upload_file('repo_upload_file', $filename, $uuid, true, '', $duplicateuuid, $filemeta);
            } else {
                // Handle any specific error signaled during the transfer
                $logger = elis_files_logger::instance();
                $error_message = $logger->signal_error(ELIS_FILES_ERROR_UPLOAD_PERMS);
                if (empty($error_message)) { // then use a generic error message
                    $error_message = get_string('errorupload',
                                                'repository_elis_files');
                }
                error_log("/repository/repository_ajax.php::overwrite: Error: {$error_message}");
                $err = new stdClass;
                $err->error = $error_message;
                die(json_encode($err));
            }
        } else {
            // moving the file within the Moodle file area
        $info = repository::overwrite_existing_draftfile($itemid, $filepath, $filename, $newfilepath, $newfilename);
        }
        // End RL EDIT
        echo json_encode($info);
        break;

    case 'deletetmpfile':
        // delete tmp file
        $newfilepath = required_param('newfilepath', PARAM_PATH);
        $newfilename = required_param('newfilename', PARAM_FILE);
        echo json_encode(repository::delete_tempfile_from_draft($itemid, $newfilepath, $newfilename));

        break;
}

// RL EDIT: BJB130215 - ELIS-7010,ELIS-7002
/**
 * Function to create 'fileexists' event to return back to filemanager or filepicker
 *
 * @param string $action              the current action setting
 * @param objext $listing             repo listing for destination
 * @param bool   $duplicateelisfiles  flag - true if not first pass or download
 * @param string $existingfilename
 * @param string $saveas_path
 * @param string $filepath            file source path on local filesystem
 * @param int    $size                file size
 * @param string $type                file MIME type
 * @param string $duplicateuuid       uuid of existing file
 * @param int    $repo_id             id of destination repo
 * @uses  $CFG
 */
function elis_files_duplicate_event($action, $listing, $duplicateelisfiles, $existingfilename, $saveas_path, $src, $filepath, $size, $type, $duplicateuuid, $repo_id) {
    global $CFG;
    $unused_filename = elis_files_generate_unique_filename($existingfilename, $listing);

    // error_log("/repository/repository_ajax.php: elis_files_duplicate_event: action = {$action}, DUPLICATE: Q rename/overwrite? existingfilename = {$existingfilename}, unused_filename = {$unused_filename}");

    if (!$duplicateelisfiles) {
        $tmpuploaddir = "{$CFG->dataroot}/temp/upload/duplicate/";
        if (!file_exists($tmpuploaddir) && !@mkdir($tmpuploaddir, 0777, true)) {
            error_log("/repository/repository_ajax.php: {$action}: Failed creating directory: {$tmpuploaddir}");
            $err = new stdClass;
            $err->error = get_string('errorcouldnotcreatedirectory',
                                     'repository_elis_files', $tmpuploaddir);
            die(json_encode($err));
        }
        // save tmp file when in filepicker - first pass
        $tmpuploadfile = $tmpuploaddir.$existingfilename;
        @unlink($tmpuploadfile); // TBD
        if (!copy($src, $tmpuploadfile)) {
            error_log("/repository/repository_ajax.php: {$action}: Failed copying file: {$tmpuploadfile}");
            $err = new stdClass;
            $err->error = get_string('errorupload', 'repository_elis_files');
            die(json_encode($err));
        }
    }

    $resp = array();
    $resp['event'] = 'fileexists';
    $resp['newfile'] = new stdClass;
    $resp['newfile']->filepath = $saveas_path;
    $resp['newfile']->filename = $unused_filename;

    $resp['existingfile'] = new stdClass;
    $resp['existingfile']->filepath = $saveas_path;
    $resp['existingfile']->filename = $existingfilename;

    // elis files info
    $resp['duplicateelisfiles'] = true;
    $resp['duplicateuuid'] = $duplicateuuid;

    // file info
    $resp['duplicatefilemeta'] = new stdClass;
    $resp['duplicatefilemeta']->name = $existingfilename;
    $resp['duplicatefilemeta']->filepath = $duplicateelisfiles ? $filepath
                                                               : $tmpuploaddir;
    $resp['duplicatefilemeta']->type = $type;
    $resp['duplicatefilemeta']->size = $size;

    // need to pass the active repo id too...
    $resp['duplicaterepo_id'] = $repo_id;
    return $resp;
}
// End RL EDIT
