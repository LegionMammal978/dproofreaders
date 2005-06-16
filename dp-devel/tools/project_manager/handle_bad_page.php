<?
$relPath="./../../pinc/";
include_once($relPath.'v_site.inc');
include_once($relPath.'dp_main.inc');
include_once($relPath.'project_states.inc');
include_once($relPath.'theme.inc');
include_once($relPath.'page_ops.inc');
include_once($relPath.'stages.inc');

if (!isset($_POST['resolution'])) {
    //Get variables to use for form
    $reason_list = array('','Image Missing','Missing Text','Image/Text Mismatch','Corrupted Image','Other');
    $projectid = $_GET['projectid'];
    $fileid = $_GET['fileid'];
    if (!isset($projectid)) {
        $projectid = $_POST['projectid'];
        $fileid = $_POST['fileid'];
    }

    //Find out information about the bad page report
    $result = mysql_query("SELECT * FROM $projectid WHERE fileid='$fileid'");
    $image = mysql_result($result,0,"image");
    $state = mysql_result($result,0,"state");
    $b_User = mysql_result($result,0,"b_user");
    $b_Code = mysql_result($result,0,"b_code");
    
    $round = get_Round_for_page_state($state);

    //Display form
    $header = _("Bad Page Report");
    theme($header, "header");

    echo "<form action='handle_bad_page.php' method='post'>";
    echo "<input type='hidden' name='projectid' value='$projectid'>";
    echo "<input type='hidden' name='fileid' value='$fileid'>";
    echo "<input type='hidden' name='image' value='$image'>";
    echo "<input type='hidden' name='state' value='$state'>";
    echo "<br><div align='center'><table bgcolor='".$theme['color_mainbody_bg']."' border='1' bordercolor='#111111' cellspacing='0' cellpadding='0' style='border-collapse: collapse'>";
    echo "<tr><td bgcolor='".$theme['color_headerbar_bg']."' colspan='2' align='center'>";
    echo "<B><font color='".$theme['color_headerbar_font']."'>Bad Page Report</font></B></td></tr>";
    
    if (!empty($b_User)) {
        //Get the user id of the reporting user to be used for private messaging
        $result = mysql_query("SELECT * FROM phpbb_users WHERE username='$b_User'");
        $b_UserID = mysql_result($result,0,"user_id");

        echo "<tr><td bgcolor='$theme[color_logobar_bg]' align='left'>";
        echo "<strong>Username:</strong></td>";
        echo "<td bgcolor='#ffffff' align='center'>";
        echo "$b_User (<a href='$forums_url/privmsg.php?mode=post&u=$b_UserID'>Private Message</a>)</td></tr>";
    }
    
    if (!empty($b_Code)) {
        echo "<tr><td bgcolor='$theme[color_logobar_bg]' align='left'>";
        echo "<strong>Reason:</strong></td>";
        echo "<td bgcolor='#ffffff' align='center'>";
        echo $reason_list[$b_Code]."</td></tr>";
    }
    
    $prev_round_num = $round->round_number - 1;
    echo "<tr><td bgcolor='$theme[color_logobar_bg]' align='left'>";
    echo "<strong>Originals:</strong></td>";
    echo "<td bgcolor='#ffffff' align='center'>";
    echo "<a href='downloadproofed.php?project=$projectid&fileid=$fileid&round_num=$prev_round_num' target='_new'>View Text</a>";
    echo " | ";
    echo "<a href='displayimage.php?project=$projectid&imagefile=$image' target='_new'>View Image</a>";
    echo "</td></tr>";
    echo "<tr><td bgcolor='$theme[color_logobar_bg]' align='left'>";

    echo "<strong>Modify:</strong></td>";
    echo "<td bgcolor='#ffffff' align='center'>";
    echo "<a href='handle_bad_page.php?projectid=$projectid&fileid=$fileid&modify=text'>Text from Previous Round</a>";
    echo " | ";
    echo "<a href='handle_bad_page.php?projectid=$projectid&fileid=$fileid&modify=image'>Original Image</a>";
    echo "</td></tr>";
    echo "<tr><td bgcolor='$theme[color_logobar_bg]' align='left'>";
    
    if (!empty($b_User) && !empty($b_Code)) {
        echo "<strong>What to do:&nbsp;&nbsp;</strong></td>";
        echo "<td bgcolor='#ffffff' align='center'>";
        echo "<input name='resolution' value='fixed' type='radio'>Fixed&nbsp;";
        echo "<input name='resolution' value='invalid' type='radio'>Invalid Report&nbsp;";
        echo "<input name='resolution' value='unfixed' checked type='radio'>Not Fixed&nbsp;";
        echo "</td></tr>";
    }
    else
    {
        echo "<input name='resolution' value='something' type='hidden'>";
        // Doesn't really matter what the value is.
    }
    
    echo "<tr><td bgcolor='$theme[color_headerbar_bg]' colspan='2' align='center'>";
    echo "<input type='submit' VALUE='Continue'>";
    echo "</td></tr></table></form></div><br><br>";

    //Determine if modify is set & if so display the form to either modify the image or text
    if (isset($_GET['modify']) && $_GET['modify'] == "text") {
        $prevtext_column = $round->prevtext_column_name;
        $result = mysql_query("SELECT $prevtext_column FROM $projectid where fileid='$fileid'");
        $prev_text = mysql_result($result, 0, $prevtext_column);

        echo "<form action='handle_bad_page.php' method='post'>";
        echo "<input type='hidden' name='modify' value='text'>";
        echo "<input type='hidden' name='projectid' value='$projectid'>";
        echo "<input type='hidden' name='fileid' value='$fileid'>";
        echo "<input type='hidden' name='prevtext_column' value='$prevtext_column'>";

        echo "<textarea name='prev_text' cols=70 rows=10>";
        // SENDING PAGE-TEXT TO USER
        echo htmlspecialchars($prev_text,ENT_NOQUOTES);
        echo "</textarea><br><br>";
        echo "<input type='submit' value='Update Text From Previous Round'></form>";

    } elseif (isset($_POST['modify']) && $_POST['modify'] == "text") {
        $prev_text = $_POST['prev_text'];
        $prevtext_column = $_POST['prevtext_column'];
        Page_modifyText( $projectid, $image, $prev_text, $prevtext_column, $pguser );
        echo "<b>Update of Text from Previous Round Complete!</b>";

    } elseif (isset($_GET['modify']) && $_GET['modify'] == "image") {
        echo "<form enctype='multipart/form-data' action='handle_bad_page.php' method='post'>";
        echo "<input type='hidden' name='modify' value='image'>";
        echo "<input type='hidden' name='projectid' value='$projectid'>";
        echo "<input type='hidden' name='fileid' value='$fileid'>";
        echo "<input type='hidden' name='image' value='$image'>";
        echo "<input type='file' name='image_upload' size=30><br><br>";
        echo "<input type='submit' value='Update Original Image'></form>";
    } elseif (isset($_POST['modify']) && $_POST['modify'] == "image") {
        if (substr($_FILES['image_upload']['name'], -4) == ".png") {
            copy($_FILES['image_upload']['tmp_name'],"$projects_dir/$projectid/$image") or die("Could not upload new image!");
            echo "<b>Update of Original Image Complete!</b>";
        } else {
            echo "<b>The uploaded file must be a PNG file! Click <a href='handle_bad_page.php?projectid=$projectid&fileid=$fileid&modify=image'>here</a> to return.</b>";
        }
    }

    echo "</center>";
    theme("", "footer");
} else {

    //Get variables passed from form
    $projectid = $_POST['projectid'];
    $fileid = $_POST['fileid'];
    $image = $_POST['image'];
    $state = $_POST['state'];

    //If the PM fixed the problem or stated the report was invalid update the database to reflect
    if (($resolution == "fixed") || ($resolution == "invalid")) {
        $round = get_Round_for_page_state($state);
        Page_eraseBadMark( $projectid, $image, $round, $pguser );
    }

    //Redirect the user back to the project detail page.
    header("Location: $code_url/project.php?id=$projectid&detail_level=4");
}

// vim: sw=4 ts=4 expandtab
?>
