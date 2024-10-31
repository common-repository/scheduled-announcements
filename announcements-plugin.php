<?php
/*
Plugin Name: Scheduled Announcements
Plugin URI: https://jgbuilt.com/scheduled-announcements/
Description: Display timed announcements in a widget or shortcode. Great for sales and event announcements, holiday and birthday greetings, or daily specials.
Author: JGbuilt
Version: 1.6.8
Author URI: https://jgbuilt.com
Copyright 2022 Scheduled Announcements (email : jerry@jgbuilt.com)
*/
//Script version
$jgbltsa_saversion = '1.6.8'; 


//Paths
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
//Include pluggable path for user permissions and nounce
include_once(ABSPATH . 'wp-includes/pluggable.php');


//Activation 
global $wpdb;
$wpdb->show_errors();
register_activation_hook( __FILE__, 'jgbltsa_announcementPlugin_activate' );
register_uninstall_hook( __FILE__, 'jgbltsa_pluginUninstall' );
global $jgbltsa_table;
$jgbltsa_table = $wpdb->prefix."jgblt_scheduledannouncements";


//Create Table if it does not exist
function jgbltsa_announcementPlugin_activate(){
	global $wpdb;
	global $jgbltsa_table;
	$sql = "CREATE TABLE $jgbltsa_table (`ID` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, `title` TEXT NOT NULL, `body` TEXT NOT NULL, `startdate` DATE NOT NULL, `enddate` DATE NOT NULL, `annual` BOOLEAN NOT NULL, `weekdaystart` TEXT NOT NULL, `weekdayduration` TEXT NOT NULL, `monthlyslot` TEXT NOT NULL, `monthlyday` TEXT NOT NULL, `monthlyduration` TEXT NOT NULL, `priority` INT NOT NULL, `draft` BOOLEAN NOT NULL) ENGINE = MyISAM;";
	jgbltsa_createAATable($jgbltsa_table, $sql); 
}


//See if table exists
function jgbltsa_createAATable($theTable, $sql){
    global $wpdb;
    if($wpdb->get_var("show tables like '". $theTable . "'") != $theTable) { 
	$wpdb->query($sql); 
    }
}


//Uninstall db and delete options upon delete
function jgbltsa_pluginUninstall(){
	global $wpdb;
	global $jgbltsa_table;
	$wpdb->query("DROP TABLE IF EXISTS $jgbltsa_table");
	delete_option('jgbltsa_plugin_class');
	delete_option('jgbltsa_plugin_tagopen');
	delete_option('jgbltsa_plugin_tagclose');
}


//Local Time
global $wp_locale;
$jgbltsa_getdate = get_option('date_format');
$jgbltsa_gettime = get_option('time_format');
$jgbltsa_dateformat = date_i18n($jgbltsa_getdate,strtotime("11/15-1976"));
$jgbltsa_timeformat = date_i18n($jgbltsa_gettime,strtotime("11/15-1976"));
$jgbltsa_thedate = date('Y-m-d', strtotime($jgbltsa_dateformat));


// Disable captions
add_filter('disable_captions', '__return_true');


//Create Admin Menu
add_action('admin_menu', 'jgbltsa_announcementPluginAdminMenu');


function jgbltsa_announcementPluginAdminMenu(){
	$appName = 'Scheduled Announcements';
	$appID = 'scheduled_announcements_plugin';
	add_menu_page($appName, $appName, 'administrator', $appID, 'jgbltsa_announcementPluginAdminScreenSeeRecords');
	add_submenu_page($appID, $appName,'Create', 'administrator', $appID . '-create', 'jgbltsa_announcementPluginAdminScreen');
	add_submenu_page($appID, $appName,'Shortcode', 'administrator', $appID . '-shortcode', 'jgbltsa_announcementPluginAdminScreenShortcode');
	add_submenu_page($appID, $appName,'Configure', 'administrator', $appID . '-configure', 'jgbltsa_announcementPluginAdminScreenConfig');
}


function jgbltsa_announcementPluginHeader(){
	global $jgbltsa_saversion;
?>
	<p class="saAuthor">Version <?php echo esc_html($jgbltsa_saversion); ?> by <a href="https://jgbuilt.com" target="_blank">jgbuilt.com</a> | <a href="https://jgbuilt.com/scheduled-announcements/?utm_source=plugin&utm_medium=instructions&utm_campaign=sa-plugin" target="_blank" />Instructions</a></p>

<?php	
}


/*----------------RECORDS FUNCTION------------------*/ 


//Page you see in admin section
function jgbltsa_announcementPluginAdminScreenSeeRecords(){


//Register scripts
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('jquery-style', plugins_url('_inc/jquery-ui.css', __FILE__));
	wp_enqueue_script('announcements-js',plugins_url('_inc/announcements-js.js', __FILE__));
	wp_enqueue_style('announcements-css',plugins_url('_inc/announcements-css.css', __FILE__));

	
//Set time		
	global $jgbltsa_thedate;
	global $jgbltsa_timeformat;

	
//Script version		
	global $jgbltsa_saversion;	
	
?>
	<div class='wrap'>
		<h2 class='saPluginTitle'>Scheduled Announcements</h2>
		<h3 class="saSubTitle">Display timed announcements in a widget or shortcode.</h3>
		
		<?php jgbltsa_announcementPluginHeader(); ?>

		<div id="message" class="updated" style="<?php if(!isset($_REQUEST['feedback']) || isset($_REQUEST['submitSchAnnEdit']) || isset($_REQUEST['submitSchAnnCopy'])){echo esc_attr('display:none');} ?>"><p>Action completed</p></div>

		<hr class="line" />	
	
<?php

// confirm delete announcement
		if(isset($_REQUEST['submitSchAnnPreDelete'])){

			$deleteid = sanitize_text_field($_REQUEST['deleteid']);
			$deletetitle = sanitize_text_field($_REQUEST['deletetitle']);
			
			?>
			<div class="confirmDelete">	
						
					<h2 class="red">CONFIRM DELETE</h2>
					<p><strong><?php echo esc_html($deletetitle); ?></strong></p>
					<form method="post">
					<input type="hidden" name="id" value="<?php echo esc_html($deleteid); ?>">
					<input type="hidden" name="feedback" value="">	
					<?php echo wp_nonce_field( 'sa_delete_action', 'sa_nonce_delete_field' ); ?>
					<input class="button-primary" type="submit" name="submitSchAnnDelete" value="Delete" />
					<a href="?page=scheduled_announcements_plugin" class="cancelEntry button-secondary">Cancel</a>
					</form>
			</div>

			<?php
		
		}		

//Show existing announcements

	global $wpdb;
	global $jgbltsa_table;
	global $jgbltsa_thedate;


	$thecount = $wpdb->get_var("SELECT count(*) FROM $jgbltsa_table");

	if($wpdb->get_var("SELECT count(*) FROM $jgbltsa_table") <= 0){
		$thecount = '0';	
	}

?>

	<h2>ANNOUNCEMENTS</h2>
	<p>You have <?php echo esc_html($thecount); ?> saved announcements</p>


<?php
	if($thecount == '0'){

?>	
		<a href="?page=scheduled_announcements_plugin-create" class="button-primary" value="Edit" />Create your first scheduled announcement</a>
<?php

	}else{	

		$clmn = 'title';

// order by
		if(isset($_REQUEST['o'])){
			
			$clmn = sanitize_text_field($_REQUEST['o']);

			switch ($clmn) {
				case "t":
					$clmn = 'title';
					break;
				case "b":
					$clmn = 'body';
					break;
				case "a": //currently not used
					$clmn = 'annual DESC';
					break;
				case "o": //currently not used
					$clmn = 'priority';
					break;				  
				case "d": //currently not used
					$clmn = 'draft DESC';
					break;
				default:
					$clmn = 'title';
			}
		}
// end order by 

		$announcements=$wpdb->get_results("SELECT * FROM $jgbltsa_table ORDER BY $clmn");
	
?>

		<table class="hovertable"><tr><th>Status</th><th><a href="?page=scheduled_announcements_plugin&o=t">Title</a></th><th><a href="?page=scheduled_announcements_plugin&o=b">Body</a></th><th class="mobileHide">Date</th><th class="mobileHide">Weekly</th><th class="mobileHide">Monthly</th><th class="mobileHide"><a href="?page=scheduled_announcements_plugin&o=o">Order</a></th><th>Actions</th></tr>

<?php
	
		foreach($announcements as $announcement){


		//Comparisons. Date. Is not Annual.


			if($announcement->draft == false && $announcement->startdate != '0000-00-00' && $announcement->enddate != '0000-00-00' && $announcement->annual == false){		
				if($announcement->enddate < $jgbltsa_thedate) {
					$datemessage = 'expired';
					$dateclass = 'dateExpired';
				}elseif($announcement->startdate <= $jgbltsa_thedate && $announcement->enddate >= $jgbltsa_thedate){
					$datemessage = 'live';
					$dateclass = 'dateLive';
				}elseif($announcement->startdate == $jgbltsa_thedate && $announcement->enddate == $jgbltsa_thedate){
					$datemessage = 'live';
					$dateclass = 'dateLive';
				}else{	
					$datemessage = 'pending';
					$dateclass = 'datePending';
				}
			}

			
		//Comparisons. Date. Is Annual.


			if($announcement->draft == false && $announcement->annual == true){
				//strip years
				$newthedate = date('m-d', strtotime($jgbltsa_thedate));
				$newstartdate = date('m-d', strtotime($announcement->startdate));
				$newenddate = date('m-d', strtotime($announcement->enddate));

				if($newenddate < $newthedate) {
					$datemessage = 'annual pending';
					$dateclass = 'datePending';
				}elseif($newstartdate <= $newthedate && $newenddate >= $newthedate){
					$datemessage = 'annual live';
					$dateclass = 'dateLive';
				}else{
					$datemessage = 'annual pending';
					$dateclass = 'datePending';
				}
			}		

			
		//Comparisons. Weekly.


			if($announcement->draft == false && $announcement->weekdaystart != '' && $announcement->weekdayduration != ''){
			
				$weekdaystartlast = date('Y-m-d', strtotime($announcement->weekdaystart.' last week'));
				$weekdaystartthis = date('Y-m-d', strtotime($announcement->weekdaystart.' this week'));
				$weekdaydurationlast = date('Y-m-d', strtotime($weekdaystartlast.'+'.$announcement->weekdayduration.'days'));
				$weekdaydurationthis = date('Y-m-d', strtotime($weekdaystartthis.'+'.$announcement->weekdayduration.'days'));

				if(($jgbltsa_thedate >= $weekdaystartlast && $jgbltsa_thedate <= $weekdaydurationlast) || ($jgbltsa_thedate >= $weekdaystartthis && $jgbltsa_thedate <= $weekdaydurationthis)){
					$datemessage = 'live';
					$dateclass = 'dateLive';			
				}else{
					$datemessage = 'pending';
					$dateclass = 'datePending';			
				}
			}


		//Comparisons. Monthly.

		
			if($announcement->draft == false && $announcement->monthlyslot != '' && $announcement->monthlyday != '' && $announcement->monthlyduration != ''){

				
		//Is it the X day of X month?
				$monthyear = date('F Y', strtotime($jgbltsa_thedate));
				$daycheck = date('Y-m-d', strtotime($announcement->monthlyslot.' '.$announcement->monthlyday.' of '.$monthyear));

				
		//Add the desired number of days 
				$addedduration = date('Y-m-d', strtotime($daycheck.'+'.$announcement->monthlyduration.'days'));

				
		//how about last month?
				$monthyearlastmonth = date('F Y', strtotime($jgbltsa_thedate.'- 1 month'));
				$daychecklastmonth = date('Y-m-d', strtotime($announcement->monthlyslot.' '.$announcement->monthlyday.' of '.$monthyearlastmonth));

				
		//Add the desired number of days 
				$addeddurationlastmonth = date('Y-m-d', strtotime($daychecklastmonth.'+'.$announcement->monthlyduration.'days'));

				
				if(($jgbltsa_thedate >= $daycheck && $jgbltsa_thedate <= $addedduration) || ($jgbltsa_thedate >= $daychecklastmonth && $jgbltsa_thedate <= $addeddurationlastmonth)){
					$datemessage = 'live';
					$dateclass = 'dateLive';			
				}else{
					$datemessage = 'pending';
					$dateclass = 'datePending';			
				}
			
			}		


		//Comparisons. Draft
			if( $announcement->draft == true){		
				$datemessage = 'draft';
				$dateclass = 'dateDraft';
			}				


		//Draft		
			if($announcement->startdate == '0000-00-00'){
				$correctedstartdate = ''; //draft announcement
			}else{
				$preText = 'Display from ';
				$correctedstartdate = date('l, m/d/Y', strtotime($announcement->startdate));
			}


			if($announcement->enddate == '0000-00-00'){
				$correctedenddate = ''; //draft announcement
			}else{
				$midText = 'to ';
				$correctedenddate = date('l, m/d/Y', strtotime($announcement->enddate));
				$postText = '.';
			}


		//Annual notice
			if($announcement->draft == false && $announcement->annual == true){
				$annual = true;
				$preText = '<span class="annual">ANNUAL</span><br />Display from ';
				$correctedstartdate = date('F jS', strtotime($announcement->startdate));
				$midText = 'to ';
				$correctedenddate = date('F jS', strtotime($announcement->enddate));
				$postText = '.';
			}else{
				$annual = '';
			}
		
		?>

			<tr>
				<td class="<?php echo esc_html($dateclass); ?>"><?php echo esc_html($datemessage); ?></td>
			<td class="titleClr"><strong><?php echo esc_html(stripslashes($announcement->title)); ?></strong></td>
			<td class="bodyClr">
			<?php echo wp_kses_post(substr(stripslashes($announcement->body), 0, 300)); ?>
			...</td>

				
		<?php
			if($correctedstartdate != '' && $correctedenddate != ''){
		?>	

				<td class="hasentry mobileHide dateClr">
				<?php echo wp_kses_post($preText); ?>	
				<?php echo esc_html($correctedstartdate); ?> 
				<?php echo esc_html($midText); ?>	
				<?php echo esc_html($correctedenddate); ?><?php echo esc_html($postText); ?>	
				</td>
		<?php
			}else{
		?>
				<td class="mobileHide"></td>
		<?php
			}

				
			if($announcement->weekdaystart != '' && $announcement->weekdayduration != ''){
				$weekdayduration = $announcement->weekdayduration+1;
		?>
				<td class="hasentry mobileHide dateClr">Display every 
				<?php echo esc_html($announcement->weekdaystart); ?> 
				for 
				<?php echo esc_html($weekdayduration); ?> 
				day(s).</td>
		
		<?php	
			}else{
		?>

				<td class="mobileHide"></td>

		<?php
			}

				
			if($announcement->monthlyslot != '' && $announcement->monthlyday != '' && $announcement->monthlyduration != ''){
				$monthlyduration = $announcement->monthlyduration + 1;
		?>		
				<td class="hasentry mobileHide dateClr">Display on the 
				<?php echo esc_html(ucfirst($announcement->monthlyslot)); ?> 
				<?php echo esc_html(ucfirst($announcement->monthlyday)); ?> 
				of every month for 
				<?php echo esc_html($monthlyduration); ?> 
				day(s).</td>
		
		<?php
			}else{
		?>
				<td class="mobileHide"></td>
		<?php
			}		

				
			if($announcement->priority != ''){	
				$priority = $announcement->priority + 1; 
		?>		
				<td class="mobileHide orderClr">
				<?php echo esc_html($priority); ?>
				</td>		
			
		<?php
			}		
		?>

			<td>
				<form action="?page=scheduled_announcements_plugin-create"  method="post">
					<input type="hidden" name="id" value="<?php echo esc_html($announcement->ID); ?>">
					<input type="hidden" name="feedback" value="">	
					<input class="button-secondary btnSpace" type="submit" name="submitSchAnnEdit" value="Edit" />
					<input class="button-secondary btnSpace" type="submit" name="submitSchAnnCopy" value="Copy" />
				</form>
			
				<form method="post">
					<input type="hidden" name="deleteid" value="<?php echo esc_html($announcement->ID); ?>">
					<input type="hidden" name="deletetitle" value="<?php echo esc_html($announcement->title); ?>">
					<input class="button-secondary" type="submit" name="submitSchAnnPreDelete" value="Delete" />
				</form>
			</td>

	<?php	
		} // end of foreach
	?>


		</tr></table>

		</div><!-- end div class wrap -->

	<?php

	} //end of else
}

/*----------------END RECORDS FUNCTION------------------*/ 

/*----------------SHORT CODE FUNCTION------------------*/ 


//Page you see in admin section
function jgbltsa_announcementPluginAdminScreenShortcode() {


//Register scripts
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('jquery-style', plugins_url('_inc/jquery-ui.css', __FILE__));
	wp_enqueue_script('announcements-js',plugins_url('_inc/announcements-js.js', __FILE__));
	wp_enqueue_style('announcements-css',plugins_url('_inc/announcements-css.css', __FILE__));


//Script version		
	global $jgbltsa_saversion;	


	?>
	<div class='wrap'>
		<h2 class='saPluginTitle'>Scheduled Announcements</h2>
		<h3 class="saSubTitle">Display timed announcements in a widget or shortcode.</h3>
			
		<?php jgbltsa_announcementPluginHeader(); ?>

		<hr class="line" />

		<h2>SHORTCODE</h2>			
				
		<input type="text" value="[scheduled_announcements]" id="saShortcode" size="23" readonly="readonly">
		<span class="copyShortcode button-primary" onclick="jgbltsa_copyShortcode()">Copy Shortcode</span><br />
		<div id="scCopied"></div>
	
		<br /><br />	
	</div>

<?php
}


/*----------------END SHORT CODE FUNCTION------------------*/

/*----------------CONFIG FUNCTION------------------*/ 


//Page you see in admin section
function jgbltsa_announcementPluginAdminScreenConfig() {

	//Register scripts
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style('jquery-style', plugins_url('_inc/jquery-ui.css', __FILE__));
		wp_enqueue_script('announcements-js',plugins_url('_inc/announcements-js.js', __FILE__));
		wp_enqueue_style('announcements-css',plugins_url('_inc/announcements-css.css', __FILE__));

		
	//Set time		
		global $jgbltsa_thedate;
		global $jgbltsa_timeformat;

		
	//Script version		
		global $jgbltsa_saversion;

		
?>
		<div class='wrap'>
			<h2 class='saPluginTitle'>Scheduled Announcements</h2>
			<h3 class="saSubTitle">Display timed announcements in a widget or shortcode.</h3>
				
			<?php jgbltsa_announcementPluginHeader(); ?>

			<div id="message" class="updated" style="<?php if(!isset($_REQUEST['feedback']) || isset($_REQUEST['submitSchAnnEdit']) || isset($_REQUEST['submitSchAnnCopy'])){echo esc_attr('display:none');} ?>"><p>Action completed</p></div>				

			<hr class="line" />

			<div class="configure">

				<h3>TIMEZONE</h3>
					
				<p>It is approximately <span class="crnt"><?php echo esc_html($jgbltsa_timeformat); ?></span> on <span class="crnt"><?php echo esc_html(date('l, F d, Y', strtotime($jgbltsa_thedate))); ?></span>. Is this correct? If not, go to <a href="options-general.php">settings</a></strong> and make adjustments to your Timezone. </p> <p><strong>Note</strong>: This time stamp was created the moment you got here and does not self-update. This is normal. <span class="reloadWindow" onclick="window.location.reload();">Reload</span> window to see an updated time stamp.</br></br></p>
					
				<hr class="line" />

				<h3>TITLE CSS CLASS</h3>

	<?php


	//Class for Title or set default
					$class = get_option('jgbltsa_plugin_class');
					if($class == ''){
						$class = 'widget-div';
					}else{
						$class = get_option('jgbltsa_plugin_class');
					}
	?>
					<p>Current CSS div class: <span class="crnt">
	<?php
					echo esc_html($class);
	?>
					</span></p>
								
					<form method="post">
					<p>Enter a custom CSS div class: <i>(class name only, no period. example: my-widget-div)</i></p><input type="text" name="class" required="">
						<input type="hidden" name="feedback" value="">
						<?php wp_nonce_field( 'sa_class_action', 'sa_nonce_class_field' ); ?>
						<input class="button-primary" type="submit" name="submitSchAnnClass" value="Save" />
					</form></br>

					<hr class="line" />

					<h3>TITLE HTML TAG</h3>

	<?php


	//Tags for Title or set default
					//get tag
					$tagOpen = get_option('jgbltsa_plugin_tagopen');
					if($tagOpen == ''){
						$tagOpen = 'h3';
						$tagClose = 'h3';
					}else{
						$tagOpen = get_option('jgbltsa_plugin_tagopen');
						$tagClose = get_option('jgbltsa_plugin_tagclose');
					}
	?>	
					<p>Current HTML title tag: <span class="crnt">
	<?php
					echo esc_html($tagOpen);
	?>	
					</span></p>
					
					<form method="post">
					<p>Choose a different custom HTML tag:</p>
					<select name="tag" required="">
						<option value="h1">h1</option>
						<option value="h2">h2</option>
						<option value="h3">h3</option>
						<option value="h4">h4</option>
						<option value="h5">h5</option>
						<option value="h6">h6</option>
					</select>
					<input type="hidden" name="feedback" value="">
					<?php wp_nonce_field( 'sa_tag_action', 'sa_nonce_tag_field' ); ?>
					<input class="button-primary" type="submit" name="submitSchAnnTag" value="Save" />
					</form>
					<br /><br />
				</div>
		</div>			


<?php
}
// end Configuration function

/*----------------END CONFIG FUNCTION------------------*/ 

/*----------------CREATE FUNCTION------------------*/ 


//Page you see in admin section
function jgbltsa_announcementPluginAdminScreen() {


//Register scripts
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('jquery-style', plugins_url('_inc/jquery-ui.css', __FILE__));
	wp_enqueue_script('announcements-js',plugins_url('_inc/announcements-js.js', __FILE__));
	wp_enqueue_style('announcements-css',plugins_url('_inc/announcements-css.css', __FILE__));


//Set time		
	global $jgbltsa_thedate;
	global $jgbltsa_timeformat;


//Script version		
	global $jgbltsa_saversion;


?>


<div class='wrap'>
	<h2 class='saPluginTitle'>Scheduled Announcements</h2>
	<h3 class="saSubTitle">Display timed announcements in a widget or shortcode.</h3>	

	<?php jgbltsa_announcementPluginHeader(); ?>

	<div id="message" class="updated" style="<?php if(!isset($_REQUEST['feedback']) || isset($_REQUEST['submitSchAnnEdit'])){echo esc_attr('display:none');} ?>"><p>Action completed</p></div>	

	<hr class="line" />

<?php


//Get data to edit existing announcement


			if(isset($_REQUEST['submitSchAnnEdit']) || isset($_REQUEST['submitSchAnnCopy']) ){

				$id = sanitize_text_field($_REQUEST['id']);
			
				global $wpdb;
				global $jgbltsa_table;
				$announcements=$wpdb->get_results("SELECT * FROM $jgbltsa_table WHERE ID = '$id'");

					foreach ($announcements as $announcement){

						$updateID = $announcement->ID;
						$title = stripslashes($announcement->title);
						$body = stripslashes($announcement->body);
						$startdate = $announcement->startdate;
						$enddate = $announcement->enddate;
						$annual = $announcement->annual;
						$weekdaystart = $announcement->weekdaystart;
						$weekdayduration = $announcement->weekdayduration;
						$monthlyslot = $announcement->monthlyslot;
						$monthlyday = $announcement->monthlyday;
						$monthlyduration = $announcement->monthlyduration;								
						$priority = $announcement->priority;
						
						if($startdate != '0000-00-00'){
							$editstartdate = date('m/d/Y', strtotime($announcement->startdate));
						}
						if($enddate != '0000-00-00'){
							$editenddate = date('m/d/Y', strtotime($announcement->enddate));
						}
						
					}

			}


//Section----------------------------------------------------- :)				


// Create announcement		
?>

			<form action="?page=scheduled_announcements_plugin" method="post">
				<h2>CREATE ANNOUNCEMENT</h2>
				
				Title (required):<br />
			<?php
				if(!isset($title)){
					$title='';
				}
			?>
				<input type="text" name="title" required="" value="<?php echo esc_html($title); ?>">
				<br /><br />
				
				Body:<br />

			<?php


//WYSIWYG editor
				
				if(!isset($body)){
					$body = '';
				}


				$settings = array(
					'textarea_rows' => 8,
				);

				wp_editor($body, 'body', $settings); 
				
			?>
				<br /><br />
				<strong>Choose from one of the following display options:</strong>
				<br /><br />
				<div class="button-secondary clearEntries">Reset Options</div>
				<br /><br />
				&#10151; Display from <input name="startdate" type="text" class="displaydate datepicker" size="10" value="<?php if(isset($editstartdate) && $editstartdate != ''){echo esc_html($editstartdate);} ?>">
				to <input name="enddate" type="text" class="displaydate datepicker" size="10" value="<?php if(isset($editenddate) && $editenddate != ''){echo esc_html($editenddate);} ?>">. Display Annually:
				<input class="displaydateCB" type="checkbox" name="annual" value="Yes" <?php if(isset($annual) && $annual == true){echo esc_attr('checked="checked"');}?>>
				<br /><br />
				&#10151; Display weekly on 
				<select name="weekdaystart" class="displayweekly">
			<?php
				if(isset($weekdaystart) && $weekdaystart != ''){
			?>	
					<option value="
					<?php echo esc_html($weekdaystart); ?>
					" selected>
					<?php echo esc_html($weekdaystart); ?>
					</option>';
					<option value=""></option>
			
			<?php		
				}else{
			?>

					<option value="" selected></option>
			<?php	
				}
			?>
					<option value="Monday">Monday</option>
					<option value="Tuesday">Tuesday</option>
					<option value="Wednesday">Wednesday</option>
					<option value="Thursday">Thursday</option>
					<option value="Friday">Friday</option>
					<option value="Saturday">Saturday</option>
					<option value="Sunday">Sunday</option>
				</select>
				for 
				<select name="weekdayduration" class="displayweekly">
			
			<?php
				if(isset($weekdayduration) && $weekdayduration != ''){
					$weekdaydurationplus = $weekdayduration + 1;
			?>	
				
					<option value="
					<?php echo esc_html($weekdayduration); ?>
					" selected>
					<?php echo esc_html($weekdaydurationplus); ?>
					</option>
					<option value=""></option>
				
			<?php
				}else{
			?>	
					<option value="" selected></option>
			<?php
				}
			?>		
					<option value="0">1</option>
					<option value="1">2</option>
					<option value="2">3</option>
					<option value="3">4</option>
					<option value="4">5</option>
					<option value="5">6</option>
					<option value="6">7</option>
				</select>
				day(s).<br /><br />
				&#10151; Display on the 
				<select name="monthlyslot" class="displaymonthly">
			
			<?php
				if(isset($monthlyslot) && $monthlyslot != ''){
			?>

					<option value="
					<?php echo esc_html($monthlyslot); ?>
					" selected>
					<?php echo esc_html($monthlyslot); ?>
					</option>
					<option value=""></option>';
				
			<?php
				}else{
			?>
					<option value="" selected></option>
			<?php
				}
			?>			
					<option value="First">First</option>
					<option value="Second">Second</option>
					<option value="Third">Third</option>
					<option value="Fourth">Fourth</option>
					<option value="Fifth">Fifth</option>
				</select>
				<select name="monthlyday" class="displaymonthly">
			
			<?php
				if(isset($monthlyday) && $monthlyday != ''){
			?>

					<option value="
					<?php echo esc_html($monthlyday); ?>
					" selected>
					<?php echo esc_html($monthlyday); ?>
					</option>
					<option value=""></option>
			
			<?php
				}else{
			?>
					<option value="" selected></option>
			<?php
				}
			?>
					<option value="Monday">Monday</option>
					<option value="Tuesday">Tuesday</option>
					<option value="Wednesday">Wednesday</option>
					<option value="Thursday">Thursday</option>
					<option value="Friday">Friday</option>
					<option value="Saturday">Saturday</option>
					<option value="Sunday">Sunday</option>
				</select>
				of each month for 
				<select name="monthlyduration" class="displaymonthly">
			<?php
				if(isset($monthlyduration) && $monthlyduration != ''){
					$monthlydurationplus = $monthlyduration + 1;
			?>		
					<option value="
					<?php echo esc_html($monthlyduration); ?>
					" selected>
					<?php echo esc_html($monthlydurationplus); ?>
					</option>
					<option value=""></option>
			<?php
				}else{
			?>
					<option value="" selected></option>
			<?php
				}
			?>
					<option value="0">1</option>
					<option value="1">2</option>
					<option value="2">3</option>
					<option value="3">4</option>
					<option value="4">5</option>
					<option value="5">6</option>
					<option value="6">7</option>
				</select>				
				day(s).
				<br /><br />	
				Announcement order: 	
				<select name="priority" class="priority">
			
			<?php		
				if(isset($priority) && $priority != ''){
					$priority = $priority + 1;
					$priorityValue = $priority - 1;
			?>	
					<option value="
					<?php echo esc_html($priorityValue) ?>
					" selected>
					<?php echo esc_html($priority) ?>
					</option>
					<option value=""></option>			
			<?php
				}else{		
			?>	
						<option value="" selected></option>		
			<?php
				}		
			?>		
						<option value="0">1 Top</option>		
						<option value="1">2</option>		
						<option value="2">3</option>		
						<option value="3">4</option>		
						<option value="4">5</option>		
						<option value="5">6</option>		
						<option value="6">7</option>				
						<option value="7">8</option>				
						<option value="8">9</option>				
						<option value="9">10 Bottom</option>		
					</select>	
					<br /><br /><br />
					<strong>Save your announcement</strong>	
					<br /><br />	
				<input type="hidden" name="feedback" value="">
				<input type="hidden" name="updateid" value="<?php if(!isset($_REQUEST['submitSchAnnCopy']) && isset($updateID)){ echo esc_html($updateID);} ?>">
				<?php wp_nonce_field( 'sa_announcement_action', 'sa_nonce_announcement_field' ); ?>
				<input class="button-primary" type="submit" name="submitSchAnnSave" value="Save Announcement" />
				<input class="button-secondary" type="submit" name="submitSchAnnDraft" value="Save As Draft" />
				<a href="?page=scheduled_announcements_plugin" class="cancelEntry button-secondary">Cancel</a>
			</form>
			<br /><br />

</div><!-- end div class wrap -->


<?php

}


/*----------------END CREATE FUNCTION------------------*/ 

/*----------------DATA HANDLING------------------*/ 


//Admin Form Option Submit CSS Class Function

	if(isset($_REQUEST['submitSchAnnClass'])) {
		jgbltsa_update_class();
	}
	function jgbltsa_update_class() {
// check nonce
		if (!isset( $_POST['sa_nonce_class_field'] ) || !wp_verify_nonce( $_POST['sa_nonce_class_field'], 'sa_class_action' )
		) {
			die( __('Security check', 'textdomain' ));
		}else{ 
			update_option('jgbltsa_plugin_class',sanitize_text_field($_REQUEST['class']));
		}
	}


//Admin Form Option Submit CSS Tag Function


	if(isset($_REQUEST['submitSchAnnTag'])) {
		jgbltsa_update_tags();
	}

	function jgbltsa_update_tags() {
// check nonce
		if (!isset( $_POST['sa_nonce_tag_field'] ) || !wp_verify_nonce( $_POST['sa_nonce_tag_field'], 'sa_tag_action' )
		) {
			die( __('Security check', 'textdomain' ));
		}else{		
			update_option('jgbltsa_plugin_tagopen',sanitize_text_field($_REQUEST['tag']));
			update_option('jgbltsa_plugin_tagclose',sanitize_text_field($_REQUEST['tag']));
		}
	}


//Section----------------------------------------------------- :)


//Admin Announcements Form Submit Announcement Function. Update existing announcement or insert new announcement


	if(isset($_REQUEST['submitSchAnnSave'])) {
		jgbltsa_update_announcement();
	}
	
	function jgbltsa_update_announcement(){

//check nounce
		if (!isset( $_POST['sa_nonce_announcement_field'] ) || !wp_verify_nonce( $_POST['sa_nonce_announcement_field'], 'sa_announcement_action' )
		) {
		   die( __('Security check', 'textdomain' ));
		}


//check permissions
		$capability = 'edit_others_posts';

		if (current_user_can($capability)) {

//UPDATE EXISTING ANNOUNCEMENT

			if(isset($_REQUEST['updateid']) && $_REQUEST['updateid'] != '' && $_REQUEST['updateid'] != null){		

				$updateID = sanitize_text_field($_REQUEST['updateid']);
				$where = array('ID' => $updateID);
				global $post, $wpdb; //wordpress post and wpdb global object
				global $jgbltsa_table;
				
				if(trim($_REQUEST['startdate']) == ''){
					$modifiedstartdate = '0000-00-00';
				}else{
					$modifiedstartdate = date('Y-m-d', strtotime(trim($_REQUEST['startdate'])));
				}
				if(trim($_REQUEST['enddate']) == ''){
					$modifiedenddate = '0000-00-00';
				}else{
					$modifiedenddate = date('Y-m-d', strtotime(trim($_REQUEST['enddate'])));
				}


//Annual Checkbox: avoid undefined error
				if (isset($_REQUEST['annual']) && $_REQUEST['annual'] == true){
					$annual = true;
				}else{
					$annual = '';
				}


					$currentpage["title"] = sanitize_text_field($_REQUEST['title']); 
					$currentpage["body"] = wp_kses_post($_REQUEST['body']);
					$currentpage["startdate"] = $modifiedstartdate;
					$currentpage["enddate"] = $modifiedenddate;
					$currentpage["annual"] = sanitize_text_field($annual);
					$currentpage["weekdaystart"] = sanitize_text_field($_REQUEST['weekdaystart']);
					$currentpage["weekdayduration"] = sanitize_text_field($_REQUEST['weekdayduration']);
					$currentpage["monthlyslot"] = sanitize_text_field($_REQUEST['monthlyslot']);
					$currentpage["monthlyday"] = sanitize_text_field($_REQUEST['monthlyday']);
					$currentpage["monthlyduration"] = sanitize_text_field($_REQUEST['monthlyduration']);
					$currentpage["priority"] = sanitize_text_field($_REQUEST['priority']); 

					
//Specific values are empty create defaults and set to draft. If someone clicks Save and not Save draft					
				if( $_REQUEST['startdate'] == '' && $_REQUEST['weekdaystart'] == '' && $_REQUEST['monthlyslot'] == '' ) {						
					$currentpage["draft"] = true;
				}else{
					$currentpage["draft"] = false;
				}

				
//if no title do nothing					
				if($_REQUEST['title'] == ''){

				}else{
					$wpdb->update($jgbltsa_table, $currentpage, $where); //update the captured values
				}
					
			}else{

//INSERT NEW ANNOUNCEMENT

				global $post, $wpdb; //wordpress post and wpdb global object
				global $jgbltsa_table;

				if(trim($_REQUEST['startdate']) == ''){
					$modifiedstartdate = '0000-00-00';
				}else{
					$modifiedstartdate = date('Y-m-d', strtotime(trim($_REQUEST['startdate'])));
				}
				if(trim($_REQUEST['enddate']) == ''){
					$modifiedenddate = '0000-00-00';
				}else{
					$modifiedenddate = date('Y-m-d', strtotime(trim($_REQUEST['enddate'])));
				}
				
//Annual Checkbox: avoid undefined error
				if (isset($_REQUEST['annual']) && sanitize_text_field($_REQUEST['annual']) == true){
					$annual = true;
				}else{
					$annual = '';
				}			
				
				$currentpage["title"] = sanitize_text_field($_REQUEST['title']); 
				$currentpage["body"] = wp_kses_post($_REQUEST['body']);
				$currentpage["startdate"] = $modifiedstartdate;
				$currentpage["enddate"] = $modifiedenddate;
				$currentpage["annual"] = $annual;
				$currentpage["weekdaystart"] = sanitize_text_field($_REQUEST['weekdaystart']);
				$currentpage["weekdayduration"] = sanitize_text_field($_REQUEST['weekdayduration']);
				$currentpage["monthlyslot"] = sanitize_text_field($_REQUEST['monthlyslot']);
				$currentpage["monthlyday"] = sanitize_text_field($_REQUEST['monthlyday']);
				$currentpage["monthlyduration"] = sanitize_text_field($_REQUEST['monthlyduration']);		
				$currentpage["priority"] = sanitize_text_field($_REQUEST['priority']); 
				
//Specific values are empty create defaults and set to draft. If someone clicks Save and not Save draft						
				if( $_REQUEST['startdate'] == '' && $_REQUEST['weekdaystart'] == '' && $_REQUEST['monthlyslot'] == '' ) {
					$currentpage["draft"] = true;	
				}else{
					$currentpage["draft"] = false;
				}
				
//if no title do nothing					
				if($_REQUEST['title'] == ''){
				}else{			
					$wpdb->insert($jgbltsa_table, $currentpage); //insert the captured values 
				}
					
			}
		}
	}

//Convert announcement to draft


if(isset($_REQUEST['submitSchAnnDraft'])) {
	jgbltsa_draft_announcement();
}


function jgbltsa_draft_announcement(){

//check nounce
	if (!isset( $_POST['sa_nonce_announcement_field'] ) || !wp_verify_nonce( $_POST['sa_nonce_announcement_field'], 'sa_announcement_action' )
	) {
	   die( __('Security check', 'textdomain' ));
	}


//check permissions
	$capability = 'edit_others_posts';
	if (current_user_can($capability)) {

		//update announcement
		if(isset($_REQUEST['updateid']) && $_REQUEST['updateid'] != '' && $_REQUEST['updateid'] != null){		

			$updateID = sanitize_text_field($_REQUEST['updateid']);
			$where = array('ID' => $updateID);
			global $post, $wpdb; //wordpress post and wpdb global object
			global $jgbltsa_table;
			
			if(trim($_REQUEST['startdate']) == ''){
				$modifiedstartdate = '0000-00-00';
			}else{
				$modifiedstartdate = date('Y-m-d', strtotime(trim($_REQUEST['startdate'])));
			}
			if(trim($_REQUEST['enddate']) == ''){
				$modifiedenddate = '0000-00-00';
			}else{
				$modifiedenddate = date('Y-m-d', strtotime(trim($_REQUEST['enddate'])));
			}
			//Annual Checkbox: avoid undefined error
			if (isset($_REQUEST['annual']) && $_REQUEST['annual'] == true){
				$annual = true;
			}else{
				$annual = '';
			}

			$currentpage["title"] = sanitize_text_field($_REQUEST['title']); 
			$currentpage["body"] = wp_kses_post($_REQUEST['body']);
			$currentpage["startdate"] = $modifiedstartdate;
			$currentpage["enddate"] = $modifiedenddate;
			$currentpage["annual"] = sanitize_text_field($annual);
			$currentpage["weekdaystart"] = sanitize_text_field($_REQUEST['weekdaystart']);
			$currentpage["weekdayduration"] = sanitize_text_field($_REQUEST['weekdayduration']);
			$currentpage["monthlyslot"] = sanitize_text_field($_REQUEST['monthlyslot']);
			$currentpage["monthlyday"] = sanitize_text_field($_REQUEST['monthlyday']);
			$currentpage["monthlyduration"] = sanitize_text_field($_REQUEST['monthlyduration']);						
			$currentpage["priority"] = sanitize_text_field($_REQUEST['priority']); 

//Set to draft
			$currentpage["draft"] = true;
			
			
			if($_REQUEST['title'] == ''){
//if no title do nothing	
			}else{
				$wpdb->update($jgbltsa_table, $currentpage, $where); //update the captured values
			}
				
		}else{


//INSERT NEW ANNOUNCEMENT


			global $post, $wpdb; //wordpress post and wpdb global object
			global $jgbltsa_table;

			if(trim($_REQUEST['startdate']) == ''){
				$modifiedstartdate = '0000-00-00';
			}else{
				$modifiedstartdate = date('Y-m-d', strtotime(trim($_REQUEST['startdate'])));
			}
			if(trim($_REQUEST['enddate']) == ''){
				$modifiedenddate = '0000-00-00';
			}else{
				$modifiedenddate = date('Y-m-d', strtotime(trim($_REQUEST['enddate'])));
			}
				
//Annual Checkbox: avoid undefined error
			if (isset($_REQUEST['annual']) && sanitize_text_field($_REQUEST['annual']) == true){
				$annual = true;
			}else{
				$annual = '';
			}			
			
			$currentpage["title"] = sanitize_text_field($_REQUEST['title']); 
			$currentpage["body"] = wp_kses_post($_REQUEST['body']);
			$currentpage["startdate"] = $modifiedstartdate;
			$currentpage["enddate"] = $modifiedenddate;
			$currentpage["annual"] = $annual;
			$currentpage["weekdaystart"] = sanitize_text_field($_REQUEST['weekdaystart']);
			$currentpage["weekdayduration"] = sanitize_text_field($_REQUEST['weekdayduration']);
			$currentpage["monthlyslot"] = sanitize_text_field($_REQUEST['monthlyslot']);
			$currentpage["monthlyday"] = sanitize_text_field($_REQUEST['monthlyday']);
			$currentpage["monthlyduration"] = sanitize_text_field($_REQUEST['monthlyduration']);		
			$currentpage["priority"] = sanitize_text_field($_REQUEST['priority']); 

			
//Set to draft. 					
			$currentpage["draft"] = true;	

			
//if no title do nothing					
			if($_REQUEST['title'] == ''){
			}else{			
				$wpdb->insert($jgbltsa_table, $currentpage); //insert the captured values 
			}
				
		}
	}
}



//Section----------------------------------------------------- :)


//Admin Announcement Form Delete Announcement Function

	if(isset($_REQUEST['submitSchAnnDelete'])) {
		jgbltsa_delete_announcement();
	}

	function jgbltsa_delete_announcement() {
// check nonce
		if (!isset( $_POST['sa_nonce_delete_field'] ) || !wp_verify_nonce( $_POST['sa_nonce_delete_field'], 'sa_delete_action' )
		) {
			die( __('Security check', 'textdomain' ));
		}else{ 
			$id = sanitize_text_field($_REQUEST['id']);
			global $wpdb;
			global $jgbltsa_table;
			$wpdb->query("DELETE FROM $jgbltsa_table WHERE ID = '$id'");
		}
	}

//Section----------------------------------------------------- :)


/*----------------FRONT END------------------*/ 


//Widget Section


//Get data
	function jgbltsa_widget_announcementPlugin($args) {

		extract($args, EXTR_SKIP);


//Get valid entries
		global $jgbltsa_thedate;
		global $wpdb;
		global $jgbltsa_table;
		
		$announcements=$wpdb->get_results("SELECT * FROM $jgbltsa_table WHERE `draft` = false");
		//var_dump($announcements);

		
//Show widget only if at least one record exists
		if(!empty($announcements)){
			echo wp_kses_post($before_widget);
			jgbltsa_announcementPlugin();
			echo wp_kses_post($after_widget);
		}
	}


//Register widget
	function jgbltsa_widget_announcementPlugin_init() {
		
		wp_register_sidebar_widget(
			'Scheduled_Announcements', 
			__('Scheduled Announcements'),
			'jgbltsa_widget_announcementPlugin',
			array(              // options
			'description' => 'Display timed announcements in a widget or shortcode')
		);
		
	}

	add_action("plugins_loaded", "jgbltsa_widget_announcementPlugin_init");


//Widget


	function jgbltsa_announcementPlugin() {
		
		global $jgbltsa_thedate;
		global $wpdb;
		global $jgbltsa_table;

		
//create empty variable
		$schedAnnWidget = '';		

		
//get class from options
		$class = get_option('jgbltsa_plugin_class');
		if($class == ''){
			$class = 'widget-div';
		}else{
			$class = get_option('jgbltsa_plugin_class');
		}


//get tag from options
		$tagOpen = get_option('jgbltsa_plugin_tagopen');
		if($tagOpen == ''){
			$tagOpen = 'h3';
			$tagClose = 'h3';
		}else{
			$tagOpen = get_option('jgbltsa_plugin_tagopen');
			$tagClose = get_option('jgbltsa_plugin_tagclose');
		}

		
//Get announcements
		
		$announcementsDate=$wpdb->get_results("SELECT * FROM $jgbltsa_table WHERE `draft` = false ORDER BY `priority`");
		//var_dump($announcementsDate);

		foreach($announcementsDate as $announcementDate){


//date related. not annual.
		
			if($announcementDate->startdate != '' && $announcementDate->enddate != '' && $announcementDate->annual == false){
				if($announcementDate->startdate <= $jgbltsa_thedate && $announcementDate->enddate >= $jgbltsa_thedate){

				$schedAnnWidget .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
				$schedAnnWidget .= '<p>'.stripslashes($announcementDate->body).'</p><br /></div>';
				}
			}


//date related. annual.

		
			if($announcementDate->startdate != '' && $announcementDate->enddate != '' && $announcementDate->annual == true){
				//strip years
				$newthedate = date('m-d', strtotime($jgbltsa_thedate));
				$newstartdate = date('m-d', strtotime($announcementDate->startdate));
				$newenddate = date('m-d', strtotime($announcementDate->enddate));						

				if($newstartdate <= $newthedate && $newenddate >= $newthedate){
					$schedAnnWidget .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
					$schedAnnWidget .= '<p>'.stripslashes($announcementDate->body).'</p><br /></div>';
				}
			}	


//week related


			if($announcementDate->weekdaystart != '' && $announcementDate->weekdayduration != ''){
				$weekdaystartlast = date('Y-m-d', strtotime($announcementDate->weekdaystart.' last week'));
				$weekdaystartthis = date('Y-m-d', strtotime($announcementDate->weekdaystart.' this week'));
				$weekdaydurationlast = date('Y-m-d', strtotime($weekdaystartlast.'+'.$announcementDate->weekdayduration.'days'));
				$weekdaydurationthis = date('Y-m-d', strtotime($weekdaystartthis.'+'.$announcementDate->weekdayduration.'days'));

				if(($jgbltsa_thedate >= $weekdaystartlast && $jgbltsa_thedate <= $weekdaydurationlast) || ($jgbltsa_thedate >= $weekdaystartthis && $jgbltsa_thedate <= $weekdaydurationthis)){
					$schedAnnWidget .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
					$schedAnnWidget .= '<p>'.stripslashes($announcementDate->body).'</p><br /></div>';
				}
			}	


//month related		


			if($announcementDate->monthlyslot != '' && $announcementDate->monthlyduration != ''){

//Is it the X day of X month?
				$monthyear = date('F Y', strtotime($jgbltsa_thedate));
				$daycheck = date('Y-m-d', strtotime($announcementDate->monthlyslot.' '.$announcementDate->monthlyday.' of '.$monthyear));

//Add the desired number of days 
				$addedduration = date('Y-m-d', strtotime($daycheck.'+'.$announcementDate->monthlyduration.'days'));
				
//how about last month?
				$monthyearlastmonth = date('F Y', strtotime($jgbltsa_thedate.'- 1 month'));
				$daychecklastmonth = date('Y-m-d', strtotime($announcementDate->monthlyslot.' '.$announcementDate->monthlyday.' of '.$monthyearlastmonth));
			
//Add the desired number of days 
				$addeddurationlastmonth = date('Y-m-d', strtotime($daychecklastmonth.'+'.$announcementDate->monthlyduration.'days'));				
				
				if(($jgbltsa_thedate >= $daycheck && $jgbltsa_thedate <= $addedduration) || ($jgbltsa_thedate >= $daychecklastmonth && $jgbltsa_thedate <= $addeddurationlastmonth)){
					$schedAnnWidget .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
					$schedAnnWidget .= '<p>'.stripslashes($announcementDate->body).'</p><br /></div>';
				}
			}
		}
		
	echo wp_kses_post($schedAnnWidget);
		
	}
	

//Section----------------------------------------------------- :)	


//Shortcode

	add_shortcode("scheduled_announcements", "jgbltsa_announcementPluginSC");

	function jgbltsa_announcementPluginSC() {
		
		global $jgbltsa_thedate;
		global $wpdb;
		global $jgbltsa_table;

		
//create empty variable
		$schedAnnShortcode = '';

		
//get class from options
		$class = get_option('jgbltsa_plugin_class');
		if($class == ''){
			$class = 'widget-div';
		}else{
			$class = get_option('jgbltsa_plugin_class');
		}


//get tag from options
		$tagOpen = get_option('jgbltsa_plugin_tagopen');
		if($tagOpen == ''){
			$tagOpen = 'h3';
			$tagClose = 'h3';
		}else{
			$tagOpen = get_option('jgbltsa_plugin_tagopen');
			$tagClose = get_option('jgbltsa_plugin_tagclose');
		}


//Get announcements
		
		$announcementsDate=$wpdb->get_results("SELECT * FROM $jgbltsa_table WHERE `draft` = false ORDER BY `priority`");
		//var_dump($announcementsDate);

		foreach($announcementsDate as $announcementDate){


//date related. not annual.
		
			if($announcementDate->startdate != '' && $announcementDate->enddate != '' && $announcementDate->annual == false){
				if($announcementDate->startdate <= $jgbltsa_thedate && $announcementDate->enddate >= $jgbltsa_thedate){
					$schedAnnShortcode .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
					$schedAnnShortcode .= '<p>'.stripslashes($announcementDate->body).'</p></div>';
				}
			}


//date related. annual.


			if($announcementDate->startdate != '' && $announcementDate->enddate != '' && $announcementDate->annual == true){
//strip years
				$newthedate = date('m-d', strtotime($jgbltsa_thedate));
				$newstartdate = date('m-d', strtotime($announcementDate->startdate));
				$newenddate = date('m-d', strtotime($announcementDate->enddate));						

				if($newstartdate <= $newthedate && $newenddate >= $newthedate){
					$schedAnnShortcode .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
					$schedAnnShortcode .= '<p>'.stripslashes($announcementDate->body).'</p></div>';
				}
			}	


//week related
		
			if($announcementDate->weekdaystart != '' && $announcementDate->weekdayduration != ''){
				$weekdaystartlast = date('Y-m-d', strtotime($announcementDate->weekdaystart.' last week'));
				$weekdaystartthis = date('Y-m-d', strtotime($announcementDate->weekdaystart.' this week'));
				$weekdaydurationlast = date('Y-m-d', strtotime($weekdaystartlast.'+'.$announcementDate->weekdayduration.'days'));
				$weekdaydurationthis = date('Y-m-d', strtotime($weekdaystartthis.'+'.$announcementDate->weekdayduration.'days'));

				if(($jgbltsa_thedate >= $weekdaystartlast && $jgbltsa_thedate <= $weekdaydurationlast) || ($jgbltsa_thedate >= $weekdaystartthis && $jgbltsa_thedate <= $weekdaydurationthis)){
					$schedAnnShortcode .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
					$schedAnnShortcode .= '<p>'.stripslashes($announcementDate->body).'</p></div>';
				}
			}

			
//month related		

			if($announcementDate->monthlyslot != '' && $announcementDate->monthlyduration != ''){

//Is it the X day of X month?
				$monthyear = date('F Y', strtotime($jgbltsa_thedate));
				$daycheck = date('Y-m-d', strtotime($announcementDate->monthlyslot.' '.$announcementDate->monthlyday.' of '.$monthyear));


//Add the desired number of days 
				$addedduration = date('Y-m-d', strtotime($daycheck.'+'.$announcementDate->monthlyduration.'days'));

				
//how about last month?
				$monthyearlastmonth = date('F Y', strtotime($jgbltsa_thedate.'- 1 month'));
				$daychecklastmonth = date('Y-m-d', strtotime($announcementDate->monthlyslot.' '.$announcementDate->monthlyday.' of '.$monthyearlastmonth));

				
//Add the desired number of days 
				$addeddurationlastmonth = date('Y-m-d', strtotime($daychecklastmonth.'+'.$announcementDate->monthlyduration.'days'));				
				
				if(($jgbltsa_thedate >= $daycheck && $jgbltsa_thedate <= $addedduration) || ($jgbltsa_thedate >= $daychecklastmonth && $jgbltsa_thedate <= $addeddurationlastmonth)){
					$schedAnnShortcode .= '<div class="'.$class.'"><'.$tagOpen.'>'.stripslashes($announcementDate->title).'</'.$tagClose.'>';
					$schedAnnShortcode .= '<p>'.stripslashes($announcementDate->body).'</p></div>';
				}
			}
		}
		
	return $schedAnnShortcode;
	 
	}


?>
