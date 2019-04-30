<?php

/*********

This script will take POST data from a form and create an NCast formatted event
on a Google Calendar. Used to automate lecture capture signup at University of
Nebraska-Lincoln. Created by Casey Lewiston, ITS Learning Spaces, based on
Google's templates. Check those templates if you need to update for future API
versions.

Contact: caseylewiston@gmail.
https://github.com/shredinger137/

Required dependency comes from the Google PHP client, which is available at
https://github.com/googleapis/google-api-php-client/

A Google Service Account is also required, along with the downloaded API key.
The account must have Calendar scope, and the calendar you plan to edit has
to be shared with it. The calendar ID must be correctly placed in the values
block below. Credentials can be generated using the Google Developers Console.

Currently, a test version is running at rrderby.org/unl/signup.html
*********/

require_once __DIR__.'/vendor/autoload.php';
   session_start();

/*********
 Configuration settings for using Google Calendar API. You'll need an access
 token from a Google Service Account. The calendar to be added to must be
 shared with that account username, with full permissions.

Test Dev Calendar ID: as0e2hrtu22bkureqpk2ehpeas@group.calendar.google.com
Production Calendar ID: unl.academic.video@gmail.com

*********/

$client = new Google_Client();
$application_creds = __DIR__.'/creds.json';  //the Service Account generated key in JSON
$credentials_file = file_exists($application_creds) ? $application_creds : false;
define("APP_NAME","Automatic Lecture Capture");
$client->setAuthConfig($credentials_file);
$client->setApplicationName(APP_NAME);
$client->addScope(Google_Service_Calendar::CALENDAR);
$client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
$calendarId = 'as0e2hrtu22bkureqpk2ehpeas@group.calendar.google.com';

$service = new Google_Service_Calendar($client);

/* showing POST data for debug
foreach($_POST as $name => $value) {
print "$name : $value<br>";
}
*/

// Pull in data from form

$title = 'REC ' . $_POST['title'];
$email = $_POST['account'];
$location = $_POST['location'];
$start = $_POST['start'];
$end = $_POST['end'];
$firstclass = $_POST['firstClass'];
$lastclass = $_POST['lastClass'];
$err = "";

//Validation checks, combined. 'False' is bad.
//[<>%\$] preg_match("/[^A-Za-z]/", $FirstName)

function formValidate(){
  global $title, $email, $location, $start, $end, $firstclass, $lastclass, $err;

  //Is there data? All fields are required.
  foreach($_POST as $name => $value) {
  if(!$value || preg_match("/[<>%\$]/", $title)) {
    $err = "ERR_NO_VALUE";
    return false;
  }

  //Are there unsafe characters in strings?
  if(is_string($value)){
    if(preg_match("/[<>%\$]/", $value)){
    $err = "ERR_INVALID_ENTRY";
    return false;
  }
  }
}

  //Does email look like email?
  if(!preg_match("/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/", $email)) {
    $err = "ERR_INVALID_EMAIL";
    return false;
  }
 return true;
}

/**** End Validation Check ***/

// Create day pattern in $days for recurring events
$days = '';
foreach($_POST['days'] as $addDay){
  if($days != ''){
    $days = $days . "," . $addDay;
  } else {
    $days = $addDay;
  }
}

// create syntactic strings for start time and 'until' element
$starttimedate = $firstclass . "T" . $start . ":00-0600";
$endtimedate = $firstclass . "T" . $end . ":00-0600";
$strippedEndDate = preg_replace("/[^a-zA-Z\d\s]/", "", $lastclass);


/******
 Conflict check - see if overlapping event exists. True means no conflict.
 Currently only checks the first instance of submitted class. This relies on instructors selecting the actual
 first day of class, and will not check other day patterns. Probably good enough for how little it should be needed.
 If instructors enter a first day that has a conflict, even if day pattern is different, this will flag.
 ******/

$optParams = array(
  'singleEvents' => true,
  'timeMin' => $starttimedate,

);
$results = $service->events->listEvents($calendarId, $optParams);
$events = $results->getItems();


function checkConflict($results, $events){
  global $location, $starttimedate, $err;
  if (empty($events)) {
    return true;
  }
  foreach ($events as $listedEvents) {
   if($location == $listedEvents['location'] && strtotime($starttimedate) >= strtotime($listedEvents['start']['dateTime']) && strtotime($starttimedate) <= strtotime($listedEvents['end']['dateTime'])){
     $err = "ERR_CONFLICTING_EVENT";
     return false;
   }
  }
  return true;
}



//Checks if start time is before end time; if not, return false and error

function timeCheck(){
  global $starttimedate, $endtimedate, $err;
  if(strtotime($starttimedate) > strtotime($endtimedate) ){
    $err = "ERR_END_BEFORE_START";
    return false;
  }
  return true;
}

//End time check


/***
Day validation - check if first date selected matches day pattern. Returns true
if so, false if there's a problem.
***/

function checkDay(){
  global $starttimedate, $days, $err;
  $day_start = strtoupper(substr(date('D', (strtotime($starttimedate))), 0, 2));
  if (strpos($days, $day_start) !== false){
    return true;
  }
  $err = "ERR_DAY_MISMATCH";
  return false;
}


/****
Create event object
This uses the current syntax in description. C4 means Channel 4, and will either be set
manually if that's wrong or set as a switch based on room selection. R1 is
another command string for the NCast. RP, email sets VidGrid account. RT, title sets
video title.
****/

$event = new Google_Service_Calendar_Event(array(
  'summary' => $title,
  'location' => $location,
  'description' => 'C4; R1; RP,' . $email .'; RT, ' . $title,
  'start' => array(
    'dateTime' => $starttimedate,
    'timeZone' => 'America/Chicago',
  ),
  'end' => array(
    'dateTime' => $endtimedate,
    'timeZone' => 'America/Chicago',
  ),
  'recurrence' => array(
    'RRULE:FREQ=WEEKLY;BYDAY=' . $days . ";UNTIL=" . $strippedEndDate
  ),

));


 //Push to Google (or not)
if(checkConflict($results, $events) && checkDay() && formValidate() && timeCheck()){
  $event = $service->events->insert($calendarId, $event);
  echo '<p>Lecture capture scheduled successfully. Please contact <a href="mailto:collaborate@unl.edu">collaborate@unl.edu</a> to make changes or for additional help.</p>';
} else {
  echo '<p>Unable to schedule capture. Please double check your entries or email <a href="mailto:collaborate@unl.edu">collaborate@unl.edu</a> for help. Process exited with error code: ' . $err . '.</p>';
}


?>
