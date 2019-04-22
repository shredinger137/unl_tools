<?php

/*********

This script will take POST data from a form and create an NCast formatted event
on a Google Calendar. Used to automate lecture capture signup at University of
Nebraska-Lincoln. Created by Casey Lewiston, ITS Learning Spaces, based on
Google's templates. Check those templates if you need to update for future API
versions.

Required dependency comes from the Google PHP client, which is available at
https://github.com/googleapis/google-api-php-client/

A Google Service Account is also required, along with the downloaded API key.
The account must have Calendar scope, and the calendar you plan to edit has
to be shared with it. The calendar ID must be correctly placed in the values
block below. Credentials can be generated using the Google Developers Console.


*********/

require_once __DIR__.'/vendor/autoload.php';
   session_start();

/*********
 Configuration settings for using Google Calendar API. You'll need an access
 token from a Google Service Account.
*********/

$client = new Google_Client();
$application_creds = __DIR__.'/creds.json';  //the Service Account generated key in JSON
$credentials_file = file_exists($application_creds) ? $application_creds : false;
define("APP_NAME","Automatic Lecture Capture");
$client->setAuthConfig($credentials_file);
$client->setApplicationName(APP_NAME);
$client->addScope(Google_Service_Calendar::CALENDAR);
$client->addScope(Google_Service_Calendar::CALENDAR_READONLY);
$calendarId = 'fc4tlf3ujvu6kcg78i48mebaho@group.calendar.google.com';

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
 Conflict check - see if overlapping event exists. False means no conflict.
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
  global $location, $starttimedate;
  if (empty($events)) {
    return false;
  }
  foreach ($events as $listedEvents) {
   if($location == $listedEvents['location'] && strtotime($starttimedate) >= strtotime($listedEvents['start']['dateTime']) && strtotime($starttimedate) <= strtotime($listedEvents['end']['dateTime'])){
     return true;
   }
  }
  return false;
}

/***
Day validation - check if first date selected matches day pattern. Returns true if so, false if there's a problem.
***/

function checkDay(){
  global $starttimedate, $days;
  $day_start = strtoupper(substr(date('D', (strtotime($starttimedate))), 0, 2));
  if (strpos($days, $day_start) !== false){
    return true;
  }
  return false;
}


/****
Create event object
This uses the current syntax in description. C4 means Channel 4, and will either be set
manually if that's wrong or set as a switch based on room selection. To be decided. R1 is
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
if(!checkConflict($results, $events) && checkDay()){
  $event = $service->events->insert($calendarId, $event);
  printf('<br />Event created: %s\n', $event->htmlLink);

} else {
  echo "Unable to create event, conflict detected or date mismatch.";

}


?>
