<?php
$relPath = "./../../pinc/";
include_once($relPath.'base.inc');
include_once($relPath.'metarefresh.inc');
include_once($relPath.'misc.inc');
include_once($relPath.'theme.inc');
include_once($relPath.'MARCRecord.inc');
include_once($relPath.'project_states.inc');
include_once($relPath.'project_trans.inc');
include_once($relPath.'DPage.inc');
include_once($relPath.'Project.inc');
include_once($relPath.'comment_inclusions.inc');
include_once('edit_common.inc');
include_once($relPath.'project_edit.inc');
include_once($relPath.'project_events.inc');
include_once($relPath.'wordcheck_engine.inc');
include_once($relPath.'js_newpophelp.inc');

require_login();

$theme_args['js_data'] = get_newHelpWin_javascript("$code_url/faq/pophelp/project_manager/");

$return = array_get($_REQUEST, "return", "$code_url/tools/project_manager/projectmgr.php");

if (!user_is_PM()) {
    die('permission denied');
}

$pih = new ProjectInfoHolder();

if (isset($_POST['saveAndQuit']) || isset($_POST['saveAndProject']) || isset($_POST['preview']) || isset($_POST['save'])) {
    $errors = $pih->set_from_post();
    $pih->normalize_spaces();
    if (empty($errors)) {
        if (!isset($_POST['preview'])) {
            $pih->save_to_db();
        }
        if (isset($_POST['saveAndQuit'])) {
            // TRANSLATORS: PM = project manager
            metarefresh(0, "projectmgr.php", _("Save and Go To PM Page"), "");
        } elseif (isset($_POST['saveAndProject'])) {
            metarefresh(0, "$code_url/project.php?id=$pih->projectid", _("Save and Go To Project"), "");
        }
    }

    if (isset($pih->projectid)) {
        $page_title = _("Edit a Project");
    } else {
        // we're creating a new project
        check_user_can_load_projects(true); // exit if they can't
        if (isset($pih->original_marc_array_encd)) {
            $page_title = _("Create a Project from a MARC Record");
        } else {
            $page_title = _("Create a Project");
        }
    }

    output_header($page_title, NO_STATSBAR, $theme_args);
    echo "<h1>$page_title</h1>\n";

    if ($errors != '') {
        echo "<p class='error'>$errors</p>";
    }

    $pih->show_form();

    if (isset($_POST['preview'])) {
        $pih->preview();
    }
} elseif (isset($_POST['quit'])) {
    // if return is empty for whatever reason take them to
    // the PM page
    if (empty($return)) {
        $return = "$code_url/tools/project_manager/projectmgr.php";
    }

    // do the redirect
    metarefresh(0, $return, _("Quit Without Saving"), "");
} else {
    $requested_action = get_enumerated_param($_REQUEST, 'action', null, ['createnew', 'clone', 'create_from_marc_record', 'edit']);

    if (in_array($requested_action, ['createnew', 'clone', 'create_from_marc_record'])) {
        check_user_can_load_projects(true); // exit if they can't
    }

    switch ($requested_action) {
        case 'createnew':
            $page_title = _("Create a Project");
            $fatal_error = $pih->set_from_nothing();
            break;

        case 'clone':
            $page_title = _("Clone a Project");
            $fatal_error = $pih->set_from_db(false);
            break;

        case 'create_from_marc_record':
            $page_title = _("Create a Project from a MARC Record");
            $fatal_error = $pih->set_from_marc_record();
            break;

        case 'edit':
            $page_title = _("Edit a Project");
            $fatal_error = $pih->set_from_db(true);
            break;

        default:
            $page_title = 'editproject.php';
            $fatal_error = sprintf(_("parameter '%s' is invalid"), 'action') . ": '$requested_action'";
    }

    output_header($page_title, NO_STATSBAR, $theme_args);
    echo "<h1>$page_title</h1>\n";

    if ($fatal_error != '') {
        $fatal_error = _('Error') . ': ' . $fatal_error;
        echo "<p class='error'>$fatal_error</p>";
        exit;
    }

    $pih->normalize_spaces();
    $pih->show_form();
}

function get_default_character_suites()
{
    global $default_project_char_suites;
    return $default_project_char_suites;
}

// XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX

class ProjectInfoHolder
{
    public function set_from_nothing()
    {
        global $pguser;

        $this->nameofwork = '';
        $this->authorsname = '';
        $this->projectmanager = $pguser;
        $this->checkedoutby = '';
        $this->language = '';
        $this->scannercredit = '';
        $this->comments = '';
        $this->comment_format = 'markdown';
        $this->clearance = '';
        $this->postednum = '';
        $this->charsuites = get_default_character_suites();
        $this->genre = '';
        $this->difficulty_level = ($pguser == "BEGIN" ? "beginner" : "average");
        $this->special_code = '';
        $this->image_source = '';
        $this->image_preparer = $pguser;
        $this->text_preparer = $pguser;
        $this->extra_credits = '';
        $this->deletion_reason = '';
        $this->custom_chars = '';
        // $this->year          = '';
        $this->state = '';
    }

    // -------------------------------------------------------------------------

    public function set_from_marc_record()
    {
        global $pguser;

        $encoded_marc_array = array_get($_POST, "rec", "");
        if (!$encoded_marc_array) {
            return sprintf(_("No record selected. If no results are suitable, select '%s' to create the project manually."), _("No Matches"));
        }

        $yaz_array = unserialize(base64_decode($encoded_marc_array));
        if (!$yaz_array) {
            return _("Unable to use selected record. Please contact a site administrator.");
        }

        $marc_record = new MARCRecord();
        $marc_record->load_yaz_array($yaz_array);

        $this->nameofwork = $marc_record->title;
        $this->authorsname = $marc_record->author;
        $this->projectmanager = $pguser;
        $this->language = $marc_record->language;
        $this->charsuites = get_default_character_suites();
        $this->genre = $marc_record->literary_form;

        $this->checkedoutby = '';
        $this->scannercredit = '';
        $this->comments = '';
        $this->comment_format = 'markdown';
        $this->clearance = '';
        $this->postednum = '';
        $this->difficulty_level = ($pguser == "BEGIN" ? "beginner" : "average");
        $this->special_code = '';
        $this->image_source = '';
        $this->image_preparer = $pguser;
        $this->text_preparer = $pguser;
        $this->extra_credits = '';
        $this->deletion_reason = '';
        $this->custom_chars = '';
        $this->state = '';

        $this->original_marc_array_encd = $encoded_marc_array;
    }

    // -------------------------------------------------------------------------
    // edit an existing project, or create a new project by
    // cloning an existing project
    public function set_from_db($edit_existing, $projectid = '')
    {
        if (!isset($_GET['project']) && $projectid == '') {
            return sprintf(_("parameter '%s' is unset"), 'project');
        }

        if ($projectid == '') {
            $projectid = $_GET['project'];
        }
        if ($projectid == '') {
            return sprintf(_("parameter '%s' is empty"), 'project');
        }
        validate_projectID($projectid);

        $ucep_result = user_can_edit_project($projectid);
        // we only let people clone projects that they can edit, so this
        // is valid whether they are cloning or editing
        if ($ucep_result == PROJECT_DOES_NOT_EXIST) {
            return _("parameter 'project' is invalid: no such project").": '$projectid'";
        } elseif ($ucep_result == USER_CANNOT_EDIT_PROJECT) {
            return _("You are not authorized to manage this project.").": '$projectid'";
        } elseif ($ucep_result == USER_CAN_EDIT_PROJECT) {
            // fine
        } else {
            return _("unexpected return value from user_can_edit_project") . ": '$ucep_result'";
        }

        try {
            $project = new Project($projectid);
        } catch (NonexistentProjectException $exception) {
            // should never get here because user_can_edit_project() catches PROJECT_DOES_NOT_EXIST
            return $exception->getMessage();
        }

        $this->nameofwork = $project->nameofwork;
        $this->projectmanager = $project->username;
        $this->authorsname = $project->authorsname;
        $this->checkedoutby = $project->checkedoutby;
        $this->language = $project->language;
        $this->scannercredit = $project->scannercredit;
        $this->comments = $project->comments;
        $this->comment_format = $project->comment_format;
        $this->clearance = $project->clearance;
        $this->genre = $project->genre;
        $this->difficulty_level = $project->difficulty;
        $this->special_code = $project->special_code;
        $this->image_source = $project->image_source;
        $this->image_preparer = $project->image_preparer;
        $this->text_preparer = $project->text_preparer;
        $this->extra_credits = $project->extra_credits;
        if ($edit_existing) {
            $this->projectid = $project->projectid;
            $this->deletion_reason = $project->deletion_reason;
            $this->posted = @$_GET['posted'];
            $this->postednum = $project->postednum;
            $this->state = $project->state;
        } else {
            // we're cloning, so leave projectid unset
            $this->postednum = '';
            $this->deletion_reason = '';
            $this->clone_projectid = $project->projectid;
            $this->state = '';
        }
        $project_charsuites = $project->get_charsuites(false);
        $this->charsuites = [];
        foreach ($project_charsuites as $project_charsuite) {
            array_push($this->charsuites, $project_charsuite->name);
        }
        $this->custom_chars = $project->custom_chars;
    }

    // -------------------------------------------------------------------------

    public function set_from_post()
    {
        $errors = '';

        if (isset($_POST['projectid'])) {
            $projectid = get_projectID_param($_POST, 'projectid');
            $this->projectid = $projectid;

            $ucep_result = user_can_edit_project($this->projectid);
            if ($ucep_result == PROJECT_DOES_NOT_EXIST) {
                return _("parameter 'projectid' is invalid: no such project").": '$this->projectid'";
            } elseif ($ucep_result == USER_CANNOT_EDIT_PROJECT) {
                return _("You are not authorized to manage this project.").": '$this->projectid'";
            } elseif ($ucep_result == USER_CAN_EDIT_PROJECT) {
                // fine
            } else {
                return _("unexpected return value from user_can_edit_project") . ": '$ucep_result'";
            }
        } elseif (isset($_POST['clone_projectid'])) {
            // we're creating a clone
            $clone_projectid = get_projectID_param($_POST, 'clone_projectid');
            $this->clone_projectid = $clone_projectid;
        }

        $this->nameofwork = @$_POST['nameofwork'];
        // we're using preg_match as this field will be space-normalised later
        if (preg_match('/^\s*$/', $this->nameofwork)) {
            $errors .= "Name of work is required.<br>";
        }

        $this->authorsname = @$_POST['authorsname'];
        if (preg_match('/^\s*$/', $this->authorsname)) {
            $errors .= "Author is required.<br>";
        }

        if (user_is_a_sitemanager()) {  // only SAs can change PM
            $this->projectmanager = @$_POST['username'];
            if ($this->projectmanager == '') {
                $errors .= _("Project manager is required.") . "<br>";
            } else {
                $errors .= check_user_exists($this->projectmanager, 'Project manager');
            }
            if (empty($errors) && !that_user_is_PM($this->projectmanager)) {
                // TRANSLATORS: PM = project manager
                $errors .= sprintf(_("%s is not a PM."), $this->projectmanager) . "<br>";
            }
        } else { // it'll be set when we save the info to the db
            $this->projectmanager = '';
        }

        $pri_language = @$_POST['pri_language'];
        if ($pri_language == '') {
            $errors .= _("Primary Language is required.")."<br>";
        }

        $sec_language = @$_POST['sec_language'];

        $this->language = (
            $sec_language != ''
            ? Project::encode_languages([$pri_language, $sec_language])
            : $pri_language);

        $this->charsuites = [];
        foreach ($_POST['charsuites'] ?? [] as $charsuite) {
            array_push($this->charsuites, $charsuite);
        }
        if (sizeof($this->charsuites) == 0) {
            $errors .= _("At least one Character Suite is required.")."<br>";
        }

        $this->genre = @$_POST['genre'];
        if ($this->genre == '') {
            $errors .= _("Genre is required.")."<br>";
        }

        // read post and set up

        $this->image_source = @$_POST['image_source'];
        if ($this->image_source == '') {
            $errors .= _("Image Source is required. If the one you want isn't in list, you can propose to add it.")."<br>";
            $this->image_source = '_internal';
        }

        /*
            else
            {
                if ($this->image_source == 'OTHER')
                {
                    if (empty($_POST['imso_other']))
                    {
                        $errors .= "When Image Source is OTHER, details must be supplied.<br>";
                    }
                    else
                    {
                        $imso_other = $_POST['imso_other'];
                        $this->image_source = "O:".$imso_other;
                    }
                }
            }

        */


        $this->special_code = @$_POST['special_code'];
        if ($this->special_code != '') {
            if (startswith($this->special_code, 'Birthday') ||
                 startswith($this->special_code, 'Otherday')
            ) {
                if (empty($_POST['bdayday']) or empty($_POST['bdaymonth'])) {
                    $errors .= _("Month and Day are required for Birthday or Otherday Specials.")."<br>";
                } else {
                    $bdaymonth = $_POST['bdaymonth'];
                    $bdayday = $_POST['bdayday'];
                    if (!checkdate($bdaymonth, $bdayday, 2000)) {
                        $errors .= _("Invalid date supplied for Birthday or Otherday Special.")."<br>";
                    } else {
                        if (strlen($this->special_code) == 8) {
                            $this->special_code .= " ".$bdaymonth.$bdayday;
                        }
                    }
                }
            }
        }

        $this->checkedoutby = @$_POST['checkedoutby'];
        // if it's an existing project, we want to know its state
        if (isset($this->projectid)) {
            // Somewhat kludgey to have to do this query here.
            $sql = sprintf("
                SELECT state, checkedoutby, username
                FROM projects
                WHERE projectid='%s'",
                DPDatabase::escape($this->projectid)
            );
            $res = DPDatabase::query($sql);
            [$state, $PPer, $PM] = mysqli_fetch_row($res);
            $this->state = $state;

            // don't allow an empty PPer/PPVer if the project is checked out
            if (($this->state == PROJ_POST_FIRST_CHECKED_OUT ||
                   $this->state == PROJ_POST_SECOND_CHECKED_OUT) &&
                 $this->checkedoutby == '') {
                $errors .= _("This project is checked out: you must specify a PPer/PPVer");
                $this->checkedoutby = $PPer;
            }
            if ($this->projectmanager == '') {
                $this->projectmanager = $PM;
            }
        } else {
            $this->state = '';
        }

        if ($this->checkedoutby != '') {
            // make sure the named PPer/PPVer actually exists
            $errors .= check_user_exists($this->checkedoutby, 'PPer/PPVer');
        }

        $this->image_preparer = @$_POST['image_preparer'];
        if ($this->image_preparer != '') {
            $errors .= check_user_exists($this->image_preparer, 'Image Preparer') ;
        }

        $this->text_preparer = @$_POST['text_preparer'];
        if ($this->text_preparer != '') {
            $errors .= check_user_exists($this->text_preparer, 'Text Preparer') ;
        }

        $this->postednum = @$_POST['postednum'];
        if ($this->postednum != '') {
            if (! preg_match('/^[1-9][0-9]*$/', $this->postednum)) {
                $errors .= sprintf(
                    _("Posted Number \"%s\" is not of the correct format."),
                    html_safe($this->postednum)) . "<br>";
                // Occasionally, there will be a PG ebook that is still
                // under U.S. copyright. This is indicated in their system
                // by appending a 'C' to the etext number. The link to
                // the etext, however, does not include the 'C', nor should
                // the DP link. If this changes, update the pattern here.
            }
        }

        $this->posted = @$_POST['posted'];
        if ($this->posted) {
            // We are in the process of marking this project as posted.
            if ($this->postednum == '') {
                $errors .= _("Posted Number is required.")."<br>";
            }
        }

        $this->custom_chars = utf8_normalize(@$_POST['custom_chars']);
        if ($this->custom_chars) {
            $codepoints = utf8_codepoints_combining($this->custom_chars);

            // all characters must be unique
            if ($codepoints != array_unique($codepoints)) {
                $errors .= _("The set of custom characters must be unique.")."<br>";
            }

            // only allow 32 characters
            if (count($codepoints) > 32) {
                $errors .= _("A maximum of 32 custom characters are allowed.")."<br>";
            }

            // prevent disallowed characters from being added
            $disallowed_codepoints = array_intersect(get_disallowed_codepoints(), $codepoints);
            if ($disallowed_codepoints != []) {
                $errors .= sprintf(
                    _("The following custom characters are not allowed: %s"),
                    implode(", ", array_map('voku\helper\UTF8::hex_to_chr', $disallowed_codepoints))
                )."<br>";
            }
        }

        $this->scannercredit = @$_POST['scannercredit'];
        $this->comments = @$_POST['comments'];
        $this->comment_format = @$_POST['comment_format'];
        $this->clearance = @$_POST['clearance'];
        $this->difficulty_level = @$_POST['difficulty_level'];
        $this->original_marc_array_encd = @$_POST['rec'];
        $this->extra_credits = @$_POST['extra_credits'];
        $this->deletion_reason = @$_POST['deletion_reason'];

        if ($this->difficulty_level == '') {
            global $pguser;
            $this->difficulty_level = ($pguser == "BEGIN" ? "beginner" : "average");
        }
        return $errors;
    }

    // -------------------------------------------------------------------------

    public function save_to_db()
    {
        global $projects_dir, $pguser;

        // enforce postednum being either NULL or a number
        $postednum_str = ($this->postednum == "") ? "NULL" : sprintf("%d", $this->postednum);

        // Call DPDatabase::escape(XX) to escape all strings.

        $common_project_settings = "
            t_last_edit    = UNIX_TIMESTAMP(),
            nameofwork     = '".DPDatabase::escape(utf8_normalize($this->nameofwork))."',
            authorsname    = '".DPDatabase::escape(utf8_normalize($this->authorsname))."',
            language       = '".DPDatabase::escape($this->language)."',
            genre          = '".DPDatabase::escape($this->genre)."',
            difficulty     = '".DPDatabase::escape($this->difficulty_level)."',
            special_code   = '".DPDatabase::escape($this->special_code)."',
            clearance      = '".DPDatabase::escape($this->clearance)."',
            comments       = '".DPDatabase::escape(utf8_normalize($this->comments))."',
            comment_format = '".DPDatabase::escape($this->comment_format)."',
            image_source   = '".DPDatabase::escape($this->image_source)."',
            scannercredit  = '".DPDatabase::escape($this->scannercredit)."',
            checkedoutby   = '".DPDatabase::escape($this->checkedoutby)."',
            postednum      = $postednum_str,
            image_preparer = '".DPDatabase::escape($this->image_preparer)."',
            text_preparer  = '".DPDatabase::escape($this->text_preparer)."',
            extra_credits  = '".DPDatabase::escape(utf8_normalize($this->extra_credits))."',
            deletion_reason= '".DPDatabase::escape($this->deletion_reason)."',
            custom_chars   = '".DPDatabase::escape(utf8_normalize($this->custom_chars))."'
        ";
        $pm_setter = '';
        if (user_is_a_sitemanager()) {
            // can change PM
            $pm_setter = sprintf("
                username = '%s',
            ", DPDatabase::escape($this->projectmanager));
        } elseif (isset($this->clone_projectid)) {
            // cloning a project. The PM should be the same as
            // that of the project being cloned, if the user
            // isn't an SA
            $sql = sprintf("
                SELECT username
                FROM projects
                WHERE projectid='%s'",
                DPDatabase::escape($this->clone_projectid)
            );
            $res = DPDatabase::query($sql);
            [$projectmanager] = mysqli_fetch_row($res);

            $pm_setter = sprintf("
                username = '%s',
            ", DPDatabase::escape($projectmanager));
        }

        if (isset($this->projectid)) {
            // We are updating an already-existing project.

            // needn't change $pm_setter, as there is no change if the user
            // isn't an SA

            // find out what we are changing from
            $old_pih = new ProjectInfoHolder();
            $fatal_error = $old_pih->set_from_db(true, $this->projectid);
            if ($fatal_error != '') {
                $fatal_error = _('Error') . ': ' . $fatal_error;
                echo "<p class='error'>$fatal_error</p>";
                exit;
            }
            $changed_fields = get_changed_fields($this, $old_pih);

            // We're particularly interested in knowing
            // when the project comments change.
            if (!in_array('comments', $changed_fields)) {
                // no change
                $tlcc_setter = '';
            } else {
                // changed!
                $tlcc_setter = 't_last_change_comments = UNIX_TIMESTAMP(),';
            }
            // We also want to know if the edit is resulting in the project
            // effectively being checked out to a new PPer
            if ($old_pih->state == PROJ_POST_FIRST_CHECKED_OUT &&
                 in_array('checkedoutby', $changed_fields)) {
                $md_setter = 'modifieddate = UNIX_TIMESTAMP(),';
                $PPer_checkout = true;
            } else {
                $md_setter = '';
                $PPer_checkout = false;
            }

            // Update the projects database with the updated info
            $where = sprintf(
                "WHERE projectid='%s'",
                DPDatabase::escape($this->projectid)
            );
            $sql = "
                UPDATE projects SET
                    $pm_setter
                    $tlcc_setter
                    $md_setter
                    $common_project_settings
                    $where
            ";
            DPDatabase::query($sql);

            $details1 = implode(' ', $changed_fields);

            if ($details1 == '') {
                // There are no changed fields.

                // Don't just save '' for the details1 column,
                // because then do_history() won't be able to distinguish
                // this case (no changed fields) from old cases
                // (edit occurred before we started recording changed fields).
                // Instead, use a special value.

                $details1 = 'NONE';
            }
            $e = log_project_event($this->projectid, $GLOBALS['pguser'], 'edit', $details1);
            if (!empty($e)) {
                die($e);
            }
            if ($PPer_checkout) {
                // we fake the project transition...
                $e = log_project_event($this->projectid,
                                        $GLOBALS['pguser'],
                                        'transition',
                                        PROJ_POST_FIRST_CHECKED_OUT,
                                        PROJ_POST_FIRST_CHECKED_OUT,
                                        $this->checkedoutby
                                        );
                if (!empty($e)) {
                    die($e);
                }
            }

            // Update the MARC record with any info we've received.
            $project = new Project($this->projectid);
            $marc_record = $project->load_marc_record();
            $this->update_marc_record_from_post($marc_record);
            $project->save_marc_record($marc_record);
        } else {
            // We are creating a new project
            $this->projectid = uniqid("projectID"); // The project ID

            if ('' == $pm_setter) {
                $pm_setter = "username = '$pguser',";
            }

            // Insert a new row into the projects table
            $pid_setter = sprintf(
                "projectid = '%s',",
                DPDatabase::escape($this->projectid)
            );
            $state_setter = sprintf(
                "state = '%s',",
                DPDatabase::escape(PROJ_NEW)
            );
            $sql = "
                INSERT INTO projects
                SET
                    $pid_setter
                    $pm_setter
                    $state_setter
                    modifieddate = UNIX_TIMESTAMP(),
                    t_last_change_comments = UNIX_TIMESTAMP(),
                    postcomments = '',
                    $common_project_settings
            ";
            DPDatabase::query($sql);

            $e = log_project_event($this->projectid, $GLOBALS['pguser'], 'creation');
            if (!empty($e)) {
                die($e);
            }

            $e = project_allow_pages($this->projectid);
            if (!empty($e)) {
                die($e);
            }

            // Make a directory in the projects_dir for this project
            mkdir("$projects_dir/$this->projectid", 0777) or die("System error: unable to mkdir '$projects_dir/$this->projectid'");
            chmod("$projects_dir/$this->projectid", 0777);


            // Do MARC record manipulations
            $project = new Project($this->projectid);
            $marc_record = new MARCRecord();

            // Save original MARC record, if provided
            $yaz_array = unserialize(base64_decode($this->original_marc_array_encd));
            if ($yaz_array !== false) {
                $marc_record->load_yaz_array($yaz_array);
                $project->init_marc_record($marc_record);

                // Update the MARC record with data from POST
                $this->update_marc_record_from_post($marc_record);
                $project->save_marc_record($marc_record);
            }

            // Create the project's 'good word list' and 'bad word list'.
            if (isset($this->clone_projectid)) {
                // We're creating a project via cloning.
                // Copy the original project's word-lists.

                $good_words = load_project_good_words($this->clone_projectid);
                if (is_string($good_words)) {
                    // It's an error message.
                    echo "$good_words<br>\n";
                    $good_words = [];
                }

                $bad_words = load_project_bad_words($this->clone_projectid);
                if (is_string($bad_words)) {
                    // It's an error message.
                    echo "$bad_words<br>\n";
                    $bad_words = [];
                }
            } else {
                // We're creating a project by means other than cloning
                // (from_nothing, from_marc_record).
                // Initialize its GWL and BWL to empty.

                $good_words = [];
                $bad_words = [];
            }

            save_project_good_words($this->projectid, $good_words);
            save_project_bad_words($this->projectid, $bad_words);
        }

        // Create/update the Dublin Core file for the project.
        // When we get here, the project's database entry has been fully
        // updated, so we can create a Project object and allow it
        // to pull the relevant fields from the database.
        $project = new Project($this->projectid);
        $project->create_dc_xml_oai($marc_record);

        $project->set_charsuites($this->charsuites);

        // If the project has been posted to PG, make the appropriate transition.
        if ($this->posted) {
            $err = project_transition($this->projectid, PROJ_SUBMIT_PG_POSTED, $pguser);
            if ($err != '') {
                echo "$err<br>\n";
                exit;
            }
        }
    }

    // =========================================================================

    public function show_form()
    {
        echo "<form method='post' enctype='multipart/form-data' action='#preview'>";

        $this->show_hidden_controls();

        echo "<table class='basic' style='width: 90%; margin: auto;'>";

        $this->show_visible_controls();

        echo "<tr>";
        echo   "<th colspan='2'>";
        // TRANSLATORS: PM = project manager
        echo     "<input type='submit' name='preview' value='".attr_safe(_("Preview"))."'>";
        echo     "<input type='submit' name='save' value='".attr_safe(_("Save"))."'>";
        echo     "<input type='submit' name='saveAndQuit' value='".attr_safe(_("Save and Go To PM Page"))."'>";
        echo     "<input type='submit' name='saveAndProject' value='".attr_safe(_("Save and Go To Project"))."'>";
        echo     "<input type='submit' name='quit' formnovalidate value='".attr_safe(_("Quit Without Saving"))."'>";
        echo   "</th>";
        echo "</tr>\n";

        echo "</table>";
        echo "</form>";
        echo "\n";
    }

    // -------------------------------------------------------------------------

    public function show_hidden_controls()
    {
        global $return;

        if (!empty($this->original_marc_array_encd)) {
            echo "<input type='hidden' name='rec' value='$this->original_marc_array_encd'>";
        }
        if (!empty($this->posted)) {
            echo "<input type='hidden' name='posted' value='1'>";
        }
        if (!empty($this->projectid)) {
            echo "<input type='hidden' name='projectid' value='$this->projectid'>";
        }
        if (!empty($this->clone_projectid)) {
            echo "<input type='hidden' name='clone_projectid' value='$this->clone_projectid'>";
        }
        echo "<input type='hidden' name='comment_format' value='$this->comment_format'>";
        echo "<input type='hidden' name='return' value='$return'>";
    }

    // -------------------------------------------------------------------------

    public function show_visible_controls()
    {
        global $site_abbreviation, $pguser;

        $can_set_difficulty_tofrom_beginner = ($pguser == "BEGIN") || user_is_a_sitemanager();

        $can_edit_PPer = true;
        $is_checked_out = false;
        if (!empty($this->projectid)) {
            $this->row(_("Project ID"), 'just_echo', $this->projectid);

            // do some things that depend on the project state
            if ($this->state == PROJ_DELETE) {
                $this->row(_("Reason for Deletion"), 'text_field', $this->deletion_reason, 'deletion_reason');
            } elseif ($this->state == PROJ_POST_FIRST_CHECKED_OUT) {
                // once the project is in PP, PPer can only be changed by an SA, PF,
                // or if it's checked out to the PM
                $is_checked_out = true;
                $can_edit_PPer = (($this->projectmanager == $this->checkedoutby) ||
                                   user_is_a_sitemanager() ||
                                   user_is_proj_facilitator());
            } elseif ($this->state == PROJ_POST_SECOND_CHECKED_OUT) {
                $is_checked_out = true;
                $can_edit_PPer = user_is_a_sitemanager();
            }
        }
        $this->row(_("Name of Work"), 'text_field', $this->nameofwork, 'nameofwork', '', ["maxlength" => 255, "required" => true]);
        $this->row(_("Author's Name"), 'text_field', $this->authorsname, 'authorsname', '', ["maxlength" => 255, "required" => true]);
        if (user_is_a_sitemanager()) {
            // SAs are the only ones who can change this
            $this->row(_("Project Manager"), 'DP_user_field', $this->projectmanager, 'username', sprintf(_("%s username only."), $site_abbreviation), ["required" => true]);
        }
        $this->row(_("Language"), 'language_list', $this->language);

        $project_charsuites = [];
        if (isset($this->projectid)) {
            $project = new Project($this->projectid);
            $project_charsuites = $project->get_charsuites(false);
        }
        $this->row(_("Character Suites"), 'charsuite_list', $this->charsuites, $project_charsuites);
        $this->row(_("Custom Characters"), 'text_field', $this->custom_chars, 'custom_chars');

        $this->row(_("Genre"), 'genre_list', $this->genre);

        if ($this->difficulty_level == "beginner" && !$can_set_difficulty_tofrom_beginner) {
            // allow PF to edit a BEGIN project, but without altering the difficulty
            $this->row(_("Difficulty Level"), 'just_echo', _("Beginner"));
            echo "<input type='hidden' name='difficulty_level' value='$this->difficulty_level'>";
        } else {
            $this->row(_("Difficulty Level"), 'difficulty_list', $this->difficulty_level);
        }
        $this->row(_("Special Day (optional)"), 'special_list', $this->special_code);
        if ($can_edit_PPer) {
            $this->row(_("PPer/PPVer"), 'DP_user_field', $this->checkedoutby, 'checkedoutby', sprintf(_("Optionally reserve for a PPer. %s username only."), $site_abbreviation));
        } else {
            $this->row(_("PPer/PPVer"), 'just_echo', $this->checkedoutby);
            echo "<input type='hidden' name='checkedoutby' value='$this->checkedoutby'>";
        }
        $this->row(_("Original Image Source"), 'image_source_list', $this->image_source);
        $this->row(_("Image Preparer"), 'DP_user_field', $this->image_preparer, 'image_preparer', sprintf(_("%s user who scanned or harvested the images."), $site_abbreviation));
        $this->row(_("Text Preparer"), 'DP_user_field', $this->text_preparer, 'text_preparer', sprintf(_("%s user who prepared the text files."), $site_abbreviation));
        $this->row(_("Extra Credits<br>(to be included in list of names--no URLs)"),
                                               'extra_credits_field', $this->extra_credits, null, '', '', true);
        if ($this->scannercredit != '') {
            $this->row(_("Scanner Credit (deprecated)"), 'text_field', $this->scannercredit, 'scannercredit');
        }
        $this->row(_("Clearance Information"), 'text_field', $this->clearance, 'clearance');
        $this->row(_("Posted Number"), 'text_field', $this->postednum, 'postednum', '', ["type" => "number"]);
        $this->row(_("Project Comments Format"), 'proj_comments_format', $this->comment_format);
        $this->row(_("Project Comments"), 'proj_comments_field', $this->comments);
        // don't show the word list line if we're in the process of cloning
        if (!empty($this->projectid)) {
            $this->row(_("Project Dictionary"), 'word_lists', null, null, '', $this->projectid);
        }
    }

    public function row($label, $display_function, $field_value, $field_name = null, $explain = '', $args = '', $html_label = false)
    {
        echo "<tr>";
        echo   "<th class='label'>";
        echo     $html_label ? $label : html_safe($label);
        echo   "</th>";
        echo   "<td>";
        $display_function($field_value, $field_name, $args);
        echo   "  ";
        echo   html_safe($explain);
        echo   "</td>";
        echo "</tr>";
        echo "\n";
    }

    // =========================================================================

    public function preview()
    {
        // insert e.g. templates and biographies
        $comments = parse_project_comments($this);

        $a = _("The Guidelines give detailed instructions for working in this round.");
        $b = _('The instructions below are particular to this project, and <b>take precedence over those guidelines</b>.');

        // TRANSLATORS: This is a strftime-formatted string for the date with year and time
        $now = strftime(_("%A, %B %e, %Y at %X"));

        echo "<h2 id='preview'>", _("Preview Project"), "</h2>";
        echo "<p>", _("This is a preview of your project and roughly how it will look to the proofreaders."), "</p>\n";
        echo "<table class='basic'>";
        echo "<tr><th>", _("Title"), "</th><td>", html_safe($this->nameofwork), "</td></tr>\n";
        echo "<tr><th>", _("Author"), "</th><td>", html_safe($this->authorsname), "</td></tr>\n";
        if (user_is_a_sitemanager()) {
            // SAs are the only ones who can change this.
            echo "<tr><th>", _("Project Manager"), "</th><td>", $this->projectmanager, "</td></tr>\n";
        }
        echo "<tr><th>", _("Last Proofread"), "</th><td>$now</td></tr>\n";
        echo "<tr><th>", _("Forum"), "</th><td>", _("Start a discussion about this project"), "</td></tr>\n";

        echo "<tr><th colspan='2'>";
        echo "<p class='large'>", _("Project Comments"), "</p>";
        echo "<br>$a<br>$b";
        echo "</th></tr>\n";
        echo "<tr><td colspan='2'>";
        echo $comments;
        echo "</td></tr>\n";

        echo "</table><br>";
    }

    // -------------------------------------------------------------------------

    public function normalize_spaces()
    // In the project's text fields, replace sequences of space characters
    // with a unique space, and trim beginning and end space
    {
        $this->nameofwork = preg_replace('/\s+/', ' ', trim($this->nameofwork));
        $this->authorsname = preg_replace('/\s+/', ' ', trim($this->authorsname));
        $this->clearance = preg_replace('/\s+/', ' ', trim($this->clearance));
        $this->extra_credits = preg_replace('/\s+/', ' ', trim($this->extra_credits));
    }

    // Updates the *passed in* MARCRecord from $_POST
    public function update_marc_record_from_post(&$marc_record)
    {
        //Update the Name of Work
        if (!empty($_POST['nameofwork'])) {
            $marc_record->title = $_POST['nameofwork'];
        }

        //Update the Authors Name
        if (!empty($_POST['authorsname'])) {
            $marc_record->author = $_POST['authorsname'];
        }

        //Update the Primary Language
        $curr_lang = langcode3_for_langname($_POST['pri_language']);
        $marc_record->language = $curr_lang;

        //Update the Genre
        $marc_record->literary_form = $_POST['genre'];
    }
}

function get_changed_fields($new_pih, $old_pih)
// Return an array whose values are the names of the properties
// whose values differ between the two objects $new_pih and $old_pih.
// [Note that this is completely generic code, so we could consider
// moving it to pinc/misc.inc.]
{
    $old_pih_as_array = (array)$old_pih;
    $new_pih_as_array = (array)$new_pih;
    // They should have the same set of keys, but just in case,
    // merge the two sets of keys:
    $all_keys = array_keys($old_pih_as_array + $new_pih_as_array);

    /*
    {
        if (count($old_pih_as_array) != count($all_keys))
        {
            echo "<p>all - old:";
            var_dump(array_diff($all_keys, array_keys($old_pih_as_array)));
            echo "</p>\n";
        }
        if (count($new_pih_as_array) != count($all_keys))
        {
            echo "<p>all - new:";
            var_dump(array_diff($all_keys, array_keys($new_pih_as_array)));
            echo "</p>\n";
        }
    }
    */

    $changed_fields = [];
    foreach ($all_keys as $key) {
        if (@$new_pih_as_array[$key] != @$old_pih_as_array[$key]) {
            // echo "<p>'$key' changed from '{$old_pih_as_array[$key]}' to '{$new_pih_as_array[$key]}'</p>\n";
            $changed_fields[] = $key;
        }
    }
    return $changed_fields;
}
