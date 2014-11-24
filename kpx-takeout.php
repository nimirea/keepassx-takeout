<?php

  date_default_timezone_set('UTC');

  /********* 0. ARGUMENT PREPROCESSING *********/

  $flags = array(
    'o' => false,
    'v' => false,
  );
  $arguments = array();
  foreach ($argv as $key => $arg) {
    if (substr($arg,0,1) == "-" && $key > 1) {
      for ($i = 1; $i < strlen($arg); $i++) {
        $flags[substr($arg,$i,1)] = true;
      }
    }
    elseif ($key == 2)
      $outform = $arg;
    else (array_push($arguments,$arg));
  }
  $argv = $arguments;

  /********* 1. CHECK INPUT *********/

  $errors = array();

  // output formats specified here
  $supportedforms = array(
    'lastpass' => array(
      'name' => 'LastPass',
      'extension' => '.csv',
      'delimiter' => ',',
      'mapping' => array(   // Same order as in output file. Keys = column headers, values = tag names in input file
        'url' => 'url',
        'username' => 'username',
        'password' => 'password',
        'extra' => 'comment',
        'name' => 'title',
        'grouping' => 'group',
        'fav' => 0
      )
    ),
    'passwordsafe' => array(
      'name' => 'PasswordSafe',
      'extension' => '.txt',
      'delimiter' => "\t",
      'enclosure' => FALSE,
      'dateformat' => function($utime) {
          return date("Y/m/d H:i:s",$utime);
        },
      'mapping' => array(
        'Group/Title' => array(
          'register' => function($group,$title) {
            // strip periods
            $title = str_replace(".",chr(175),$title);
            $group = str_replace(".",chr(45),$group);

            if ($group != "") $group.=".";
            return $group.$title;
          },
          'variables' => array('group','title')
        ),
        'Username' => 'username',
        'Password' => 'password',
        'Created Time' => 'creation',
        'Password Modified Time' => 'lastmod',
        'Record Modified Time' => 'lastmod',
        'Password Policy' => '',
        'Password Policy Name' => '',
        'History e-mail' => '',
        'Symbols' => '',
        'Notes' => 'comment'
      )
    )
  );

  if (!array_key_exists($outform,$supportedforms)) {
    $badform = "Please specify an output format as the last argument. This utility currently supports: ";
    foreach ($supportedforms as $flag => $format) {
      $badform .= "\n\t\t".$format['name'].' `'.$flag.'` ';
    }
    array_push($errors,$badform);
  }

  // path checks
  if (count($argv) <= 2) {
    array_push($errors,"No input file specified.");
  }
  else {
    $currentdir = getcwd();

    $inputpath = $argv[2];
    if (substr($inputpath,0,1) != "/") // parse relative path
      $inputpath = $currentdir."/".$inputpath;

    if (!file_exists($inputpath)) // check existence
      array_push($errors,"Input does not exist.");
    elseif (substr($inputpath, -4) != '.xml') // check filetype
      array_push($errors,"Input is not an XML file.");

    else {
      $input = file_get_contents($inputpath);
      if (substr($input,0,28) != '<!DOCTYPE KEEPASSX_DATABASE>')
        array_push($errors,"Input is not in KeePassX format.");
    }

    // check output, if it exists
    if (count($argv) > 3) {
      $outputpath = $argv[3];
      print $outputpath;
      if (substr($outputpath,0,1) != "/") // parse relative path
        $outputpath = $currentdir."/".$outputpath;
      if (substr($outputpath,-1) == "/")
        $outputpath = substr($outputpath,0,-1);
      $filegiven = (substr($outputpath,-strlen($supportedforms[$outform]['extension'])) == $supportedforms[$outform]['extension']);

      if (!is_dir($outputpath) && !$filegiven) { // check existence of directory
        if (substr($outputpath,-4,1) == "." || substr($outputpath,-5,1) == ".") // if attempting to specify file
          array_push($errors,"Incorrect output file extension. Please use ".$supportedforms[$outform]['extension']." for ".$supportedforms[$outform]['name']." files.");
        else
          array_push($errors,"Output directory does not exist.");
      }

      if ($filegiven && file_exists($outputpath) && $flags['o'] == false)
        array_push($errors,"A file exists at the specified output location. Please add the '-o' tag if you would like it to overwrite it.");

    }
    else {
      $outputpath = $currentdir;
    }

  }

  if (count($errors) > 0) { // display errors
    $errorout = "Some errors found:\n";
    foreach ($errors as $error) {
      $errorout .= "\t- ".$error."\n";
    }
    $errorout .= "\nPlease recheck the input and try again.\n";
    exit($errorout);
  }


  /********* 2. PROCESSING *********/

  // parse data into associative array
  $assocdata = array();
  $rawdata = new SimpleXMLElement($input);

  foreach($rawdata->group as $group) {
    foreach($group->entry as $entry) {
      $entrydata = array(
        'url' => $entry->url,
        'password' => $entry->password,
        'username' => $entry->username,
        'comment' => $entry->comment,
        'title' => $entry->title,
        'creation' => mktime(
            substr($entry->creation,11,2),
            substr($entry->creation,14,2),
            substr($entry->creation,17,2),
            substr($entry->creation,5,2),
            substr($entry->creation,8,2),
            substr($entry->creation,0,4)
          ),
        'lastaccess' => mktime(
          substr($entry->lastaccess,11,2),
          substr($entry->lastaccess,14,2),
          substr($entry->lastaccess,17,2),
          substr($entry->lastaccess,5,2),
          substr($entry->lastaccess,8,2),
          substr($entry->lastaccess,0,4)
        ),
        'lastmod' => mktime(
          substr($entry->lastmod,11,2),
          substr($entry->lastmod,14,2),
          substr($entry->lastmod,17,2),
          substr($entry->lastmod,5,2),
          substr($entry->lastmod,8,2),
          substr($entry->lastmod,0,4)
        ),
        'exipire' => mktime(
          substr($entry->exipire,11,2),
          substr($entry->exipire,14,2),
          substr($entry->exipire,17,2),
          substr($entry->exipire,5,2),
          substr($entry->exipire,8,2),
          substr($entry->exipire,0,4)
        ),
        'icon' => $entry->icon,
        'group' => $group->title
      );
      array_push($assocdata,$entrydata);
    }
  }

  // format-specific processing
  $output = array('headers' => array_keys($supportedforms[$outform]['mapping']));
  foreach($assocdata as $entry) {
    $output_entry = array();
    foreach ($supportedforms[$outform]['mapping'] as $field) {
      $pushthis = "";

      if (is_array($field) && is_callable($field['register'])) { // using registration function
        $regvars = array();
        foreach ($field['variables'] as $var) {
          array_push($regvars,$entry[$var]);
        }
        $pushthis = call_user_func_array($field['register'],$regvars);
      }

      else {
        if (array_key_exists($field,$entry)) { // mappings to existing data
          // dealing with dates
          if ($field == 'creation' || $field == 'lastmod' || $field == 'lastaccess' || $field == 'exipire') {
            if (array_key_exists('dateformat',$supportedforms[$outform]))
              $pushthis = call_user_func($supportedforms[$outform]['dateformat'],$entry[$field]);
            else
              $pushthis = date("c",$field); // ISO 8601 date, also used in input
          }
          // other stored values
          else
            $pushthis = $entry[$field];
        }
        else // filler values necessary for output
          $pushthis = $field;
      }
      array_push($output_entry,$pushthis);

    }
    array_push($output, $output_entry);
  }


  /********* 3. SAVE *********/

  // set output file if no specific one given
  if (!$filegiven) {
    $localpath = "/".$outform."-import";
    $suffix = "";
    if (file_exists($outputpath.$localpath.$supportedforms[$outform]['extension']) && $flags['o'] == false)
      $suffix = "_".date("Y-m-d-His");
    $outputpath = $outputpath.$localpath.$suffix.$supportedforms[$outform]['extension'];

  }

  // write to file
  if ($outhandle = fopen($outputpath,'a')) {

    file_put_contents($outputpath,"");    // overwrite existing contents

    foreach ($output as $entry) {
      if ($supportedforms[$outform]['extension'] == '.csv')
        fputcsv($outhandle,$entry,$supportedforms[$outform]['delimiter']);
      else
        fwrite($outhandle,implode($supportedforms[$outform]['delimiter'],$entry)."\n");
    }

    fclose($outhandle);

    // give info in verbose mode
    if ($flags['v'] === true) {
      $info = array(
        (count($output)-1)." entries",
        filesize($outputpath)." bytes"
      );
      print "Output was successful! The script wrote\n";
      foreach ($info as $infoentry) {
        print "\t$infoentry\n";
      }
      print "to ".$outputpath."\n";
    }
  }
  else {
    print "Output unsuccessful. This may be because of insufficient write permissions to the directory or file.\n";
  }

  end;

?>
