<?php
$relPath="./../../pinc/";
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

$return = array_get($_REQUEST,"return","$code_url/tools/project_manager/projectmgr.php");

if ( !user_is_PM() )
{
    die('permission denied');
}

$pih = new ProjectInfoHolder;

if (isset($_POST['saveAndQuit']) || isset($_POST['saveAndProject']) || isset($_POST['saveAndPreview']) )
{
    $errors = $pih->set_from_post();
    $pih->normalize_spaces();
    if (empty($errors))
    {
        $pih->save_to_db();
        if (isset($_POST['saveAndQuit']))
        {
            metarefresh(0, "projectmgr.php", _("Save and Go To PM Page"), "");
        }
        elseif (isset($_POST['saveAndProject']))
        {
            metarefresh(0, "$code_url/project.php?id=$pih->projectid", _("Save and Go To Project"), "");
        }
    }

    if ( isset($pih->projectid) )
    {
        $page_title = _("Edit a Project");
    }
    else
    {
        // we're creating a new project
        check_user_can_load_projects(true); // exit if they can't
        if ( isset($pih->up_projectid) && $pih->up_projectid )
        {
            $page_title = _("Create a Project from an Uber Project");
        }
        elseif ( isset($pih->original_marc_array_encd) )
        {
            $page_title = _("Create a Project from a MARC Record");
        }
        else
        {
            $page_title = _("Create a Project");
        }
    }

    output_header($page_title, NO_STATSBAR, $theme_args);
    echo "<br><h2 align='center'>$page_title</h2>\n";

    if ($errors != '')
    {
        echo "<br><center><font size='+1' color='#ff0000'><b>$errors</b></font></center>";
    }

    $pih->show_form();

    if ( isset($_POST['saveAndPreview']))
    {
        $pih->preview();
    }
}
elseif (isset($_POST['quit']))
{
    // if return is empty for whatever reason take them to
    // the PM page
    if(empty($return))
        $return="$code_url/tools/project_manager/projectmgr.php";

    // do the redirect
    metarefresh(0, $return, _("Quit Without Saving"), "");
}
else
{
    $requested_action = get_enumerated_param($_REQUEST, 'action', null, array('createnew', 'clone', 'createnewfromuber', 'create_from_marc_record', 'edit'));

    if (in_array($requested_action, array('createnew', 'clone', 'createnewfromuber', 'create_from_marc_record')))
    {
        check_user_can_load_projects(true); // exit if they can't
    }

    switch ($requested_action)
    {
        case 'createnew':
            $page_title = _("Create a Project");
            $fatal_error = $pih->set_from_nothing();
            break;
        
        case 'clone':
            $page_title = _("Clone a Project");
            $fatal_error = $pih->set_from_db(FALSE);
            break;

        case 'createnewfromuber':
            $page_title = _("Create a Project from an Uber Project");
            $fatal_error = $pih->set_from_uberproject();
            break;
        
        case 'create_from_marc_record':
            $page_title = _("Create a Project from a MARC Record");
            $fatal_error = $pih->set_from_marc_record();
            break;

        case 'edit':
            $page_title = _("Edit a Project");
            $fatal_error = $pih->set_from_db(TRUE);
            break;
    
        default:
            $page_title = 'editproject.php';
            $fatal_error = sprintf(_("parameter '%s' is invalid"), 'action') . ": '$requested_action'";
    }

    output_header($page_title, NO_STATSBAR, $theme_args);
    echo "<br><h2 align='center'>$page_title</h2>\n";

    if ($fatal_error != '')
    {
        $fatal_error = _('site error') . ': ' . $fatal_error;
        echo "<br><center><font size='+1' color='#ff0000'><b>$fatal_error</b></font></center>";
        exit;
    }

    $pih->normalize_spaces();
    $pih->show_form();
}

// XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX

class ProjectInfoHolder
{
    function set_from_nothing()
    {
        global $pguser;

        $this->nameofwork       = '';
        $this->authorsname      = '';
        $this->projectmanager   = $pguser;
        $this->checkedoutby     = '';
        $this->language         = '';
        $this->scannercredit    = '';
        $this->comments         = '';
        $this->clearance        = '';
        $this->postednum        = '';
        $this->genre            = '';
        $this->difficulty_level = ( $pguser == "BEGIN" ? "beginner" : "average" );
        $this->special_code     = '';
        $this->image_source     = '_internal';
        $this->image_preparer   = $pguser;
        $this->text_preparer    = $pguser;
        $this->extra_credits    = '';
        $this->deletion_reason  = '';
        // $this->year          = '';
        $this->state            = '';    
    }

    // -------------------------------------------------------------------------

    function set_from_marc_record()
    {
        global $pguser;

        if (!isset($_POST['rec']))
        {
            return sprintf(_("parameter '%s' is unset"), 'rec');
        }

        $r1 = $_POST['rec'];
        if ( $r1 == '' )
        {
            return sprintf(_("parameter '%s' is empty"), 'rec');
        }

        $r2 = base64_decode($r1);
        if ( $r2 === FALSE )
        {
            return sprintf(_("parameter '%s' cannot be decoded"), 'rec');
        }

        $r3 = unserialize($r2);
        if ( $r3 === FALSE )
        {
            return sprintf(_("parameter '%s' cannot be unserialized"), 'rec');
        }

        $marc_record = new MARCRecord();
        $marc_record->load_yaz_array($r3);

        $this->nameofwork  = $marc_record->title;
        $this->authorsname = $marc_record->author;
        $this->projectmanager = $pguser;
        $this->language    = $marc_record->language;
        $this->genre       = $marc_record->literary_form;

        $this->checkedoutby     = '';
        $this->scannercredit    = '';
        $this->comments         = '';
        $this->clearance        = '';
        $this->postednum        = '';
        $this->difficulty_level = ( $pguser == "BEGIN" ? "beginner" : "average" );
        $this->special_code     = '';
        $this->image_source     = '_internal';
        $this->image_preparer   = $pguser;
        $this->text_preparer    = $pguser;
        $this->extra_credits    = '';
        $this->deletion_reason  = '';
        $this->state            = '';

        $this->original_marc_array_encd = $r1;
    }

    // -------------------------------------------------------------------------

    function set_from_uberproject()
    {
        global $pguser;
        if (!isset($_GET['up_projectid']))
        {
            return sprintf(_("parameter '%s' is unset"), 'up_projectid');
        }

        $up_projectid = intval($_GET['up_projectid']);
        if ( $up_projectid == '' )
        {
            return sprintf(_("parameter '%s' is empty"), 'up_projectid');
        }

        $result = mysql_query("SELECT * FROM uber_projects WHERE up_projectid = $up_projectid");
        if (mysql_num_rows($result) == 0)
        {
            return sprintf(_("parameter '%s' is invalid"), 'up_projectid') . ": '$up_projectid'";
        }

        // TODO: check that user has permission to create a project from this UP

        $up_info = mysql_fetch_assoc($result);

        $this->nameofwork       = $up_info['d_nameofwork'];
        $this->authorsname      = $up_info['d_authorsname'];
        $this->projectmanager   = $pguser;
        $this->checkedoutby     = $up_info['d_checkedoutby'];
        $this->language         = $up_info['d_language'];
        $this->scannercredit    = $up_info['d_scannercredit'];
        $this->comments         = $up_info['d_comments'];
        $this->clearance        = $up_info['d_clearance'];
        $this->postednum        = $up_info['d_postednum'];
        $this->genre            = $up_info['d_genre'];
        $this->difficulty_level = $up_info['d_difficulty'];
        $this->special_code     = $up_info['d_special'];
        $this->image_source     = $up_info['d_image_source'];
        $this->image_preparer   = $up_info['d_image_preparer'];
        $this->text_preparer    = $up_info['d_text_preparer'];
        $this->extra_credits    = $up_info['d_extra_credits'];
        $this->deletion_reason  = '';
        $this->state            = '';

        // $this->year          = $up_info['d_year'];

        $this->up_projectid     = $up_projectid;
    }

    // -------------------------------------------------------------------------
    // edit an existing project, or create a new project by
    // cloning an existing project
    function set_from_db($edit_existing, $projectid='')
    {
        if (!isset($_GET['project']) && $projectid == '')
        {
            return sprintf(_("parameter '%s' is unset"), 'project');
        }

        if ( $projectid == '' )
        {
            $projectid = $_GET['project'];
        }
        if ( $projectid == '' )
        {
            return sprintf(_("parameter '%s' is empty"), 'project');
        }
        $projectid = validate_projectID('project', $projectid);

        $ucep_result = user_can_edit_project($projectid);
        // we only let people clone projects that they can edit, so this
        // is valid whether they are cloning or editing
        if ( $ucep_result == PROJECT_DOES_NOT_EXIST )
        {
            return _("parameter 'project' is invalid: no such project").": '$projectid'";
        }
        else if ( $ucep_result == USER_CANNOT_EDIT_PROJECT )
        {
            return _("You are not authorized to manage this project.").": '$projectid'";
        }
        else if ( $ucep_result == USER_CAN_EDIT_PROJECT )
        {
            // fine
        }
        else
        {
            return _("unexpected return value from user_can_edit_project") . ": '$ucep_result'";
        }

        try
        {
            $project = new Project($projectid);
        }
        catch(NonexistentProjectException $exception)
        {
            return sprintf(_("parameter '%s' is invalid"), 'project') . ": '$projectid'";
        }

        $this->nameofwork       = $project->nameofwork;
        $this->projectmanager   = $project->username;
        $this->authorsname      = $project->authorsname;
        $this->checkedoutby     = $project->checkedoutby;
        $this->language         = $project->language;
        $this->scannercredit    = $project->scannercredit;
        $this->comments         = $project->comments;
        $this->clearance        = $project->clearance;
        $this->genre            = $project->genre;
        $this->difficulty_level = $project->difficulty;
        $this->special_code     = $project->special_code;
        $this->image_source     = $project->image_source;
        $this->image_preparer   = $project->image_preparer;
        $this->text_preparer    = $project->text_preparer;
        $this->extra_credits    = $project->extra_credits;
        if ($edit_existing) 
        {
            $this->projectid        = $project->projectid;
            $this->deletion_reason  = $project->deletion_reason;
            $this->posted           = @$_GET['posted'];        
            $this->postednum        = $project->postednum;
            $this->state            = $project->state;
        }
        else
        {
            // we're cloning, so leave projectid unset
            $this->postednum        = '';
            $this->deletion_reason  = '';
            $this->clone_projectid = $project->projectid;
            $this->state            = '';
        }
        $this->up_projectid     = $project->up_projectid;
    }

    // -------------------------------------------------------------------------

    function set_from_post()
    {
        $errors = '';

        if ( isset($_POST['projectid']) )
        {
            $projectid = validate_projectID('projectid', @$_POST['projectid']);
            $this->projectid = $projectid;

            $ucep_result = user_can_edit_project($this->projectid);
            if ( $ucep_result == PROJECT_DOES_NOT_EXIST )
            {
                return _("parameter 'projectid' is invalid: no such project").": '$this->projectid'";
            }
            else if ( $ucep_result == USER_CANNOT_EDIT_PROJECT )
            {
                return _("You are not authorized to manage this project.").": '$this->projectid'";
            }
            else if ( $ucep_result == USER_CAN_EDIT_PROJECT )
            {
                // fine
            }
            else
            {
                return _("unexpected return value from user_can_edit_project") . ": '$ucep_result'";
            }
        }
        else if ( isset($_POST['clone_projectid']) )
        {
            // we're creating a clone
            $clone_projectid = validate_projectID('clone_projectid', @$_POST['clone_projectid']);
            $this->clone_projectid = $clone_projectid;
        }

        $this->nameofwork = @$_POST['nameofwork'];
        // we're using preg_match as this field will be space-normalised later
        if ( preg_match('/^\s*$/', $this->nameofwork) ) 
        { 
            $errors .= "Name of work is required.<br>"; 
        }

        $this->authorsname = @$_POST['authorsname'];
        if ( preg_match('/^\s*$/', $this->authorsname) ) 
        { 
            $errors .= "Author is required.<br>"; 
        }

        if ( user_is_a_sitemanager() )  // only SAs can change PM
        {
            $this->projectmanager = @$_POST['username'];
            if ( $this->projectmanager == '' )
            {
                $errors .= _("Project manager is required.") . "<br>";
            }
            else
            {
                $errors .= check_user_exists($this->projectmanager, 'Project manager');
            }
            if ( empty($errors) && !that_user_is_PM($this->projectmanager) )
            {
                $errors .= sprintf(_("%s is not a PM."), $this->projectmanager) . "<br>";
            }
        }
        else // it'll be set when we save the info to the db
        {
            $this->projectmanager = '';
        }

        $pri_language = @$_POST['pri_language'];
        if ( $pri_language == '' ) { $errors .= _("Primary Language is required.")."<br>"; }

        $sec_language = @$_POST['sec_language'];

        $this->language = (
            $sec_language != ''
            ? "$pri_language with $sec_language"
            : $pri_language );

        $this->genre = @$_POST['genre'];
        if ( $this->genre == '' ) { $errors .= _("Genre is required.")."<br>"; }

        $this->image_source = @$_POST['image_source'];
        if ($this->image_source == '')
        {
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
        if ($this->special_code != '')
        {
            if ( startswith($this->special_code, 'Birthday') ||
                 startswith($this->special_code, 'Otherday')
            )
            {
                if (empty($_POST['bdayday']) or empty($_POST['bdaymonth']))
                {
                    $errors .= _("Month and Day are required for Birthday or Otherday Specials.")."<br>";
                }
                else
                {
                    $bdaymonth = $_POST['bdaymonth'];
                    $bdayday = $_POST['bdayday'];
                    if (!checkdate ( $bdaymonth, $bdayday, 2000))
                    {
                        $errors .= _("Invalid date supplied for Birthday or Otherday Special.")."<br>";
                    }
                    else
                    {
                        if (strlen($this->special_code) == 8) { $this->special_code .= " ".$bdaymonth.$bdayday; }
                    }
                }
            }
        }

        $this->checkedoutby = @$_POST['checkedoutby'];
        // if it's an existing project, we want to know its state
        if ( isset($this->projectid) )
        {
             // Somewhat kludgey to have to do this query here.
            $res = mysql_query("
                SELECT state, checkedoutby, username
                FROM projects
                WHERE projectid='{$this->projectid}'
            ") or die(mysql_error());
            list($state, $PPer, $PM) = mysql_fetch_row($res);
            $this->state = $state;

            // don't allow an empty PPer/PPVer if the project is checked out
            if ( ( $this->state == PROJ_POST_FIRST_CHECKED_OUT ||
                   $this->state == PROJ_POST_SECOND_CHECKED_OUT ) &&
                 $this->checkedoutby == '')
            {
                $errors .= _("This project is checked out: you must specify a PPer/PPVer");
                $this->checkedoutby = $PPer;
            }
            if ( $this->projectmanager == '' )
            {
                $this->projectmanager = $PM;
            }
        }
        else
        {
            $this->state = '';
        }

        if ($this->checkedoutby != '')
        {
            // make sure the named PPer/PPVer actually exists
            $errors .= check_user_exists($this->checkedoutby, 'PPer/PPVer');
        }

        $this->image_preparer = @$_POST['image_preparer'];
        if ($this->image_preparer != '')
        {
            $errors .= check_user_exists($this->image_preparer,'Image Preparer') ;
        }

        $this->text_preparer = @$_POST['text_preparer'];
        if ($this->text_preparer != '')
        {
            $errors .= check_user_exists($this->text_preparer,'Text Preparer') ;
        }

        $this->posted    = @$_POST['posted'];
        $this->postednum = @$_POST['postednum'];
        if ( $this->posted )
        {
            // We are in the process of marking this project as posted.
            if ( $this->postednum == '' )
            {
                $errors .= _("Posted Number is required.")."<br>";
            }
            else if ( ! preg_match('/^[1-9][0-9]*$/', $this->postednum ) )
            {
                $errors .= sprintf(
                    _("Posted Number \"%s\" is not of the correct format."),
                    $this->postednum) . "<br>";
                // You'll sometimes see PG etext numbers with a 'C' appended.
                // The 'C' is not part of the etext number
                // (e.g., it does not appear in PG's RDF catalog),
                // rather it's a bit of information about the identified text,
                // namely that it's still under (US) copyright.
                // Anyhow, the 'C' should not be included here.
            }
        }

        $this->scannercredit    = @$_POST['scannercredit'];
        $this->comments         = @$_POST['comments'];
        $this->clearance        = @$_POST['clearance'];
        $this->difficulty_level = @$_POST['difficulty_level'];
        $this->up_projectid     = intval(@$_POST['up_projectid']);
        $this->original_marc_array_encd = @$_POST['rec'];
        $this->extra_credits    = @$_POST['extra_credits'];
        $this->deletion_reason  = @$_POST['deletion_reason'];

        if ($this->difficulty_level == '')
        {
            global $pguser;
            $this->difficulty_level = ( $pguser == "BEGIN" ? "beginner" : "average" );
        }
        return $errors;
    }

    // -------------------------------------------------------------------------

    function save_to_db()
    {
        global $projects_dir, $pguser;

        $postednum_str = ($this->postednum == "") ? "NULL" : "'$this->postednum'";

        // Call mysql_real_escape_string() on any members of $this that might
        // contain single-quotes/apostrophes (because they are unescaped, and
        // would otherwise break the query).

        $common_project_settings = "
            t_last_edit    = UNIX_TIMESTAMP(),
            up_projectid   = '{$this->up_projectid}',
            nameofwork     = '".mysql_real_escape_string($this->nameofwork)."',
            authorsname    = '".mysql_real_escape_string($this->authorsname)."',
            language       = '".mysql_real_escape_string($this->language)."',
            genre          = '".mysql_real_escape_string($this->genre)."',
            difficulty     = '".mysql_real_escape_string($this->difficulty_level)."',
            special_code   = '".mysql_real_escape_string($this->special_code)."',
            clearance      = '".mysql_real_escape_string($this->clearance)."',
            comments       = '".mysql_real_escape_string($this->comments)."',
            image_source   = '".mysql_real_escape_string($this->image_source)."',
            scannercredit  = '".mysql_real_escape_string($this->scannercredit)."',
            checkedoutby   = '".mysql_real_escape_string($this->checkedoutby)."',
            postednum      = $postednum_str,
            image_preparer = '".mysql_real_escape_string($this->image_preparer)."',
            text_preparer  = '".mysql_real_escape_string($this->text_preparer)."',
            extra_credits  = '".mysql_real_escape_string($this->extra_credits)."',
            deletion_reason= '".mysql_real_escape_string($this->deletion_reason)."'
        ";
        $pm_setter = '';
        if ( user_is_a_sitemanager() )
        {
            // can change PM
            $pm_setter = sprintf("
                username = '%s',
            ", mysql_real_escape_string($this->projectmanager));
        }
        else if ( isset($this->clone_projectid) )
        {
            // cloning a project. The PM should be the same as 
            // that of the project being cloned, if the user
            // isn't an SA
            $res = mysql_query("
                SELECT username
                FROM projects
                WHERE projectid='{$this->clone_projectid}'
            ") or die(mysql_error());
            list($projectmanager) = mysql_fetch_row($res);

            $pm_setter = sprintf("
                username = '%s',
            ", mysql_real_escape_string($projectmanager));
        }

        if (isset($this->projectid))
        {
            // We are updating an already-existing project.

            // needn't change $pm_setter, as there is no change if the user
            // isn't an SA

            // find out what we are changing from
            $old_pih = new ProjectInfoHolder;
            $fatal_error = $old_pih->set_from_db(TRUE, $this->projectid);
            if ($fatal_error != '')
            {
                $fatal_error = _('site error') . ': ' . $fatal_error;
                echo "<br><center><font size='+1' color='#ff0000'><b>$fatal_error</b></font></center>";
                exit;
            }
            $changed_fields = get_changed_fields($this, $old_pih);

            // We're particularly interested in knowing
            // when the project comments change.
            if ( !in_array('comments', $changed_fields) )
            {
                // no change
                $tlcc_setter = '';
            }
            else
            {
                // changed!
                $tlcc_setter = 't_last_change_comments = UNIX_TIMESTAMP(),';
            }
            // We also want to know if the edit is resulting in the project
            // effectively being checked out to a new PPer
            if ( $old_pih->state == PROJ_POST_FIRST_CHECKED_OUT &&
                 strpos($changed_fields, 'PPer/PPVer') != FALSE )
            {
                $md_setter = 'modifieddate = UNIX_TIMESTAMP(),';
                $PPer_checkout = TRUE;
            }
            else
            {
                $md_setter = '';
                $PPer_checkout = FALSE;
            }

            // Update the projects database with the updated info
            mysql_query("
                UPDATE projects SET
                    $pm_setter
                    $tlcc_setter
                    $md_setter
                    $common_project_settings
                WHERE projectid='{$this->projectid}'
            ") or die(mysql_error());

            $details1 = implode(' ', $changed_fields);

            if ( $details1 == '' )
            {
                // There are no changed fields.

                // Don't just save '' for the details1 column,
                // because then do_history() won't be able to distinguish
                // this case (no changed fields) from old cases
                // (edit occurred before we started recording changed fields).
                // Instead, use a special value.

                $details1 = 'NONE';
            }
            $e = log_project_event( $this->projectid, $GLOBALS['pguser'], 'edit', $details1 );
            if ( !empty($e) ) die($e);
            if ($PPer_checkout)
            {
                // we fake the project transition...
                $e = log_project_event( $this->projectid, 
                                        $GLOBALS['pguser'], 
                                        'transition', 
                                        PROJ_POST_FIRST_CHECKED_OUT,
                                        PROJ_POST_FIRST_CHECKED_OUT,
                                        $this->checkedoutby
                                        );
                if ( !empty($e) ) die($e);
            }

            // Update the MARC record with any info we've received.
            $project = new Project($this->projectid);
            $marc_record = $project->load_marc_record();
            $this->update_marc_record_from_post($marc_record);
            $project->save_marc_record($marc_record);
        }
        else
        {
            // We are creating a new project
            $this->projectid = uniqid("projectID"); // The project ID

            if ( '' == $pm_setter) {
                $pm_setter = "username = '$pguser',";
            }

            // Insert a new row into the projects table
            mysql_query("
                INSERT INTO projects
                SET
                    projectid    = '{$this->projectid}',
                    $pm_setter
                    state        = '".PROJ_NEW."',
                    modifieddate = UNIX_TIMESTAMP(),
                    t_last_change_comments = UNIX_TIMESTAMP(),
                    $common_project_settings
            ") or die(mysql_error());

            $e = log_project_event( $this->projectid, $GLOBALS['pguser'], 'creation' );
            if ( !empty($e) ) die($e);

            $e = project_allow_pages( $this->projectid );
            if ( !empty($e) ) die($e);

            // Make a directory in the projects_dir for this project
            mkdir("$projects_dir/$this->projectid", 0777) or die("System error: unable to mkdir '$projects_dir/$this->projectid'");
            chmod("$projects_dir/$this->projectid", 0777);


            // Do MARC record manipulations
            $project = new Project($this->projectid);

            // Save original MARC record
            $marc_record = new MARCRecord();
            $marc_record->load_yaz_array(
                unserialize(base64_decode($this->original_marc_array_encd)));
            $project->init_marc_record($marc_record);

            // Update the MARC record with data from POST
            $this->update_marc_record_from_post($marc_record);
            $project->save_marc_record($marc_record);

            // Create the project's 'good word list' and 'bad word list'.

            if ( isset($this->clone_projectid) )
            {
                // We're creating a project via cloning.
                // Copy the original project's word-lists.

                $good_words = load_project_good_words($this->clone_projectid);
                if ( is_string($good_words) )
                {
                    // It's an error message.
                    echo "$good_words<br>\n";
                    $good_words = array();
                }

                $bad_words = load_project_bad_words($this->clone_projectid);
                if ( is_string($bad_words) )
                {
                    // It's an error message.
                    echo "$bad_words<br>\n";
                    $bad_words = array();
                }
            }
            else
            {
                // We're creating a project by means other than cloning
                // (from_nothing, from_marc_record, from_uberproject).
                // Initialize its GWL and BWL to empty.

                $good_words = array();
                $bad_words = array();
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

        // If the project has been posted to PG, make the appropriate transition.
        if ($this->posted)
        {
            $err = project_transition( $this->projectid, PROJ_SUBMIT_PG_POSTED, $pguser );
            if ( $err != '' )
            {
                echo "$err<br>\n";
                exit;
            }
        }
    }

    // =========================================================================

    function show_form()
    {
        echo "<form method='post' enctype='multipart/form-data' action='". attr_safe($_SERVER['PHP_SELF'])."'>";

        $this->show_hidden_controls();

        echo "<br>";
        echo "<center>";
        echo "<table cellspacing='0' cellpadding='5' border='1' width='90%' style='border: 1px solid #000; border-collapse:collapse'>";

        $this->show_visible_controls();

        echo "<tr>";
        echo   "<td bgcolor='#CCCCCC' colspan='2' align='center'>";
        echo     "<input type='submit' name='saveAndQuit' value='".attr_safe(_("Save and Go To PM Page"))."'>";
        echo     "<input type='submit' name='saveAndProject' value='".attr_safe(_("Save and Go To Project"))."'>";
        echo     "<input type='submit' name='saveAndPreview' value='".attr_safe(_("Save and Preview"))."'>";
        echo     "<input type='submit' name='quit' value='".attr_safe(_("Quit Without Saving"))."'>";
        echo   "</td>";
        echo "</tr>\n";

        echo "</table>";
        echo "</center>";
        echo "</form>";
        echo "\n";
    }

    // -------------------------------------------------------------------------

    function show_hidden_controls()
    {
        global $return;

        if (!empty($this->original_marc_array_encd))
        {
            echo "<input type='hidden' name='rec' value='$this->original_marc_array_encd'>";
        }
        if (!empty($this->posted))
        {
            echo "<input type='hidden' name='posted' value='1'>";
        }
        if (!empty($this->projectid))
        {
            echo "<input type='hidden' name='projectid' value='$this->projectid'>";
        }
        if (!empty($this->up_projectid))
        {
            echo "<input type='hidden' name='up_projectid' value='$this->up_projectid'>";
        }
        if (!empty($this->clone_projectid))
        {
            echo "<input type='hidden' name='clone_projectid' value='$this->clone_projectid'>";
        }
        echo "<input type='hidden' name='return' value='$return'>";
    }

    // -------------------------------------------------------------------------

    function show_visible_controls()
    {
        global $site_abbreviation, $pguser;

        $can_set_difficulty_tofrom_beginner = ($pguser == "BEGIN") || user_is_a_sitemanager();

        $can_edit_PPer = TRUE;
        $is_checked_out = FALSE;
        if (!empty($this->projectid))
        {
            $this->row( _("Project ID"), 'just_echo', $this->projectid );

            // do some things that depend on the project state
            if ($this->state == PROJ_DELETE)
            {
                $this->row( _("Reason for Deletion"),         'text_field',          $this->deletion_reason, 'deletion_reason' );
            }
            else if ( $this->state == PROJ_POST_FIRST_CHECKED_OUT )
            {
                // once the project is in PP, PPer can only be changed by an SA, PF, 
                // or if it's checked out to the PM
                $is_checked_out = TRUE;
                $can_edit_PPer = ( ($this->projectmanager == $this->checkedoutby) || 
                                   user_is_a_sitemanager() ||
                                   user_is_proj_facilitator());
            }
            else if ( $this->state == PROJ_POST_SECOND_CHECKED_OUT )
            {
                $is_checked_out = TRUE;
                $can_edit_PPer = user_is_a_sitemanager();
            }
        }
        if (!empty($this->up_projectid))
        {
            $res2 = mysql_query("
                SELECT up_nameofwork
                FROM uber_projects
                WHERE up_projectid = '$this->up_projectid'
            ");
            $up_nameofwork = mysql_result($res2, 0, "up_nameofwork");

            $this->row( _("Related Uber Project"), 'just_echo', $up_nameofwork );
        }
        $this->row( _("Name of Work"),                'text_field',          $this->nameofwork,      'nameofwork' );
        $this->row( _("Author's Name"),               'text_field',          $this->authorsname,     'authorsname' );
        if ( user_is_a_sitemanager() )
        {
            // SAs are the only ones who can change this
            $this->row( _("Project Manager"),         'DP_user_field',       $this->projectmanager,  'username', sprintf(_("%s username only."),$site_abbreviation));
        }
        $this->row( _("Language"),                    'language_list',       $this->language         );
        $this->row( _("Genre"),                       'genre_list',          $this->genre            );
        if ($this->difficulty_level == "beginner" && !$can_set_difficulty_tofrom_beginner )
        {
            // allow PF to edit a BEGIN project, but without altering the difficulty
            $this->row( _("Difficulty Level"), 'just_echo', _("Beginner") );
            echo "<input type='hidden' name='difficulty_level' value='$this->difficulty_level'>";
        } 
        else 
        {
            $this->row( _("Difficulty Level"),        'difficulty_list',     $this->difficulty_level );
        }
        $this->row( _("Special Day (optional)"),      'special_list',        $this->special_code     );
        if ( $can_edit_PPer )
        {
            $this->row( _("PPer/PPVer"),                  'DP_user_field',       $this->checkedoutby,    'checkedoutby' , sprintf(_("Optionally reserve for a PPer. %s username only."),$site_abbreviation));
        }
        else
        {
            $this->row( _("PPer/PPVer"),                  'just_echo',       $this->checkedoutby);
            echo "<input type='hidden' name='checkedoutby' value='$this->checkedoutby'>";
        }
        $this->row( _("Original Image Source"),       'image_source_list',   $this->image_source     );
        $this->row( _("Image Preparer"),              'DP_user_field',       $this->image_preparer,  'image_preparer', sprintf(_("%s user who scanned or harvested the images."),$site_abbreviation));
        $this->row( _("Text Preparer"),               'DP_user_field',       $this->text_preparer,   'text_preparer', sprintf(_("%s user who prepared the text files."),$site_abbreviation) );
        $this->row( _("Extra Credits<br>(to be included in list of names)"),   
                                               'extra_credits_field', $this->extra_credits);
        if ($this->scannercredit != '') {
            $this->row( _("Scanner Credit (deprecated)"), 'text_field',      $this->scannercredit,   'scannercredit' );
        }
        $this->row( _("Clearance Information"),       'text_field',          $this->clearance,       'clearance' );
        $this->row( _("Posted Number"),               'text_field',          $this->postednum,       'postednum' );
        $this->row( _("Project Comments"),            'proj_comments_field', $this->comments         );
        // don't show the word list line if we're in the process of cloning
        if(!empty($this->projectid)) {
            $this->row( _("Project Dictionary"),  'word_lists',  null,  null,  '', $this->projectid);
        }
    }

    function row( $label, $display_function, $field_value, $field_name=NULL, $explan='', $args='' )
    {
        echo "<tr>";
        echo   "<td bgcolor='#CCCCCC'>";
        echo     "<b>$label</b>";
        echo   "</td>";
        echo   "<td>";
        $display_function( $field_value, $field_name, $args );
        echo   "  ";
        echo   $explan;
        echo   "</td>";
        echo "</tr>";
        echo "\n";
    }

    // =========================================================================

    function preview()
    {
        // insert e.g. templates and biographies
        $comments = parse_project_comments($this->comments);

        $a = _("The Guidelines give detailed instructions for working in this round.");
        $b = _('The instructions below are particular to this project, and <b>take precedence over those guidelines</b>.');

        $now = strftime(_("%A, %B %e, %Y at %X"));

        echo "<br><table width='90%' border=1>";
        echo "<tr><td align='middle' bgcolor='#cccccc'><h3>", _("Preview<br>Project"), "</h3></td>";
        echo "<td bgcolor='#cccccc'><b>", _("This is a preview of your project and roughly how it will look to the proofreaders."), "</b></td></tr>\n";
        echo "<tr><td align='middle' bgcolor='#cccccc'><b>", _("Title"), "</b></td><td>$this->nameofwork</td></tr>\n";
        echo "<tr><td align='middle' bgcolor='#cccccc'><b>", _("Author"), "</b></td><td>$this->authorsname</td></tr>\n";
        if (user_is_a_sitemanager())
        {
            // SAs are the only ones who can change this.
            echo "<tr><td align='middle' bgcolor='#cccccc'><b>", _("Project Manager"), "</b></td><td>$this->projectmanager</td></tr>\n";
        }
        echo "<tr><td align='middle' bgcolor='#cccccc'><b>", _("Last Proofread"), "</b></td><td>$now</td></tr>\n";
        echo "<tr><td align='middle' bgcolor='#cccccc'><b>", _("Forum"), "</b></td><td>", _("Start a discussion about this project"), "</td></tr>\n";

        echo "<tr><td colspan='2' bgcolor='#cccccc' align='center'>";
        echo "<font size='+1'><b>", _("Project Comments"), "</b></font>";
        echo "<br>$a<br>$b";
        echo "</td></tr>\n";
        echo "<tr><td colspan='2'>";
        echo $comments;
        echo "</td></tr>\n";

        echo "</table><br><br>";
    }
    
    // -------------------------------------------------------------------------

    function normalize_spaces()
    // In the project's text fields, replace sequences of space characters 
    // with a unique space, and trim beginning and end space
    {
        $this->nameofwork = preg_replace('/\s+/', ' ', trim($this->nameofwork));
        $this->authorsname = preg_replace('/\s+/', ' ', trim($this->authorsname));
        $this->clearance = preg_replace('/\s+/', ' ', trim($this->clearance));
        $this->extra_credits = preg_replace('/\s+/', ' ', trim($this->extra_credits));
    }

    // Updates the *passed in* MARCRecord from $_POST
    function update_marc_record_from_post(&$marc_record) {
        //Update the Name of Work
        if (!empty($_POST['nameofwork'])) {
            $marc_record->title = $_POST['nameofwork'];
        }

        //Update the Authors Name
        if (!empty($_POST['authorsname'])) {
            $marc_record->author = $_POST['authorsname'];
        }

        //Update the Primary Language
        $curr_lang = langcode3_for_langname( $_POST['pri_language'] );
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

    $changed_fields = array();
    foreach ( $all_keys as $key )
    {
        if (@$new_pih_as_array[$key] != @$old_pih_as_array[$key])
        { 
            // echo "<p>'$key' changed from '{$old_pih_as_array[$key]}' to '{$new_pih_as_array[$key]}'</p>\n";
            $changed_fields[] = $key;
        }
    }
    return $changed_fields;
}

// vim: sw=4 ts=4 expandtab
?>
