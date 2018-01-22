<?php
require 'collateBot-API.php';

require '../../collateBot-connect.php';

unset($dbservername, $dbusername, $dbpassword, $dbname);

function processMessage($message, $conn) {
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  $chat_type = $message['chat']['type'];
  
  if (!isset($message['from']['username'])) {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Your telegram username is not set. Please set your telegram username.", "parse_mode" => "Markdown"));
    exit;
  }
  $user_username = $message['from']['username'];
  
  if (isset($message['text'])) {
    $text = htmlspecialchars(stripslashes(trim($message['text'])));

    if (strpos($text, "/start") === 0) {
      start($chat_id, $message_id);
      
    } else if (strpos($text, "/stop") === 0) {
      //
      
    } else if (strpos($text, "/help") === 0) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "/start - Start using the bot\n\nHow to use:\n1. Tap <i>New list</i>\n2. Tap <i>Allow list</i> in chosen group\n3. You're good to go!", "parse_mode" => "HTML"));
      
  /*} else if (strpos($text, "/newcollate") === 0) {
      newCollate($text, $user_username, $chat_id, $conn);
      
    } else if (strpos($text, "/endcollate") === 0) {
      endCollate($text, $user_username, $chat_id, $conn);
      
    } else if (strpos($text, "/indicate") === 0) {
      indicate($text, $user_username, $chat_id, $chat_type, $conn);
      
    } else if (strpos($text, "/drop") === 0) {
      drop($text, $user_username, $chat_id, $chat_type, $conn);
      
    } else if (strpos($text, "/show") === 0) {
      show($text, $chat_id, $chat_type, $conn);
      
    } else if (strpos($text, "/allow") === 0) {
      allow($text, $user_username, $chat_id, $chat_type, $conn);
      
    } else if (strpos($text, "/display") === 0) {
      display($text, $user_username, $chat_id, $chat_type, $conn);
    */
    } else {
      if (isset($message['reply_to_message']['text'])) {
        $rtm_message_id = $message['reply_to_message']['message_id'];
        $rtm_text = trim($message['reply_to_message']['text']);
        if (strpos($rtm_text, "Enter list name.") !== false) {
          newCollate($text, $user_username, $chat_id, $conn);
          apiRequest("deleteMessage", array("chat_id" => $chat_id, "message_id" => $rtm_message_id));
        } else if (strpos($rtm_text, "Please type:\n") !== false) {
          indicate($text, $user_username, $chat_id, $chat_type, $conn);
          apiRequest("deleteMessage", array("chat_id" => $chat_id, "message_id" => $rtm_message_id));
        }
      }
    }
  } else if (isset($message['new_chat_members']) || isset($message['new_chat_member'])) {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "/start - Start using the bot\n\nHow to use:\n1. Tap <i>New list</i>\n2. Tap <i>Allow list</i> in chosen group\n3. You're good to go!", "parse_mode" => "HTML"));
  }
}

function start($chat_id, $message_id) {
  apiRequest("sendMessage",
  array(
  "chat_id" => $chat_id,
  "text" => "Tap on the buttons below.",
  "parse_mode" => "HTML",
  "reply_markup" =>
  array("inline_keyboard" =>
  array(
  array(
  array("text" => "New list", "callback_data" => "/newcollate,"),
  array("text" => "Allow list", "callback_data" => "/allow,"),
  array("text" => "End list", "callback_data" => "/endcollate,")
  ),
  array(
  array("text" => "Indicate entry", "callback_data" => "/indicate,"),
  array("text" => "Drop entry", "callback_data" => "/drop,")
  ),
  array(
  array("text" => "Show entries", "callback_data" => "/show,"),
  array("text" => "Display lists", "callback_data" => "/display,")
  ),
  array(
  array("text" => "<< Exit", "callback_data" => "/close,")
  )))));
  
}

function newCollate($text, $user_username, $chat_id, $conn) {
  if (!ctype_alnum(str_replace(" ", "", $text))) {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "List name can only be alphanumeric [a-z][0-9] and space", "parse_mode" => "HTML"));
  } else {
    $listName = trim(ucwords($text));
    if (empty($listName)) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "List name cannot be empty.", "parse_mode" => "HTML"));
    } else {
      $sql = "INSERT INTO collateLists (adminUsername, listName) VALUES ('$user_username', '$listName')";
      if ($conn->query($sql) === TRUE) {
        apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "<b>@$user_username</b>'s <b>$listName</b> is <i>created</i>.", "parse_mode" => "HTML"));
      } else {
        apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "<b>@$user_username</b>'s <b>$listName</b> is not <i>created</i>."/*.$conn->error*/, "parse_mode" => "HTML"));
      }
    }
  }
}

function indicate($text, $user_username, $chat_id, $chat_type, $conn) {
  $textArray = explode(",", $text);
  $adminUsername = trim($textArray[0]);
  $listName = ucwords(trim($textArray[1]));
  $sql = "SELECT listID FROM collateLists WHERE listName = '$listName' AND adminUsername = '$adminUsername'";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $listID = $row['listID'];
    if (empty($textArray[2])) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "1st field cannot be empty.", "parse_mode" => "HTML"));
      exit;
    }
    $entryFirstColumn = trim($textArray[2]);
    if (!ctype_alnum(str_replace(" ", "", $entryFirstColumn))) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "1st field can only be alphanumeric [a-z][0-9] and space", "parse_mode" => "HTML"));
      exit;
    }
    $entrySecondColumn = "";
    if (!empty($textArray[3])) {
      $entrySecondColumn = trim($textArray[3]);
      if (!ctype_alnum(str_replace(" ", "", $entrySecondColumn))) {
        apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "2nd field can only be alphanumeric [a-z][0-9] and space", "parse_mode" => "HTML"));
        exit;
      }
    }
    $sql = "INSERT INTO collateEntries (listID, entryUsername, entryFirstColumn, entrySecondColumn) VALUES ($listID, '$user_username', '$entryFirstColumn', '$entrySecondColumn')";
    if ($conn->query($sql) === TRUE) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Entry is added into <b>@$user_username</b>'s <b>$listName</b>.", "parse_mode" => "HTML"));
    } else {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Entry is not added into <b>@$user_username</b>'s <b>$listName</b>."/*.$conn->error*/, "parse_mode" => "HTML"));
    }
  } else {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "<b>@$user_username</b>'s <b>$listName</b> does not exist.", "parse_mode" => "HTML"));
  }
}
/*
function endCollate($text, $user_username, $chat_id, $conn) {
  $listName = trim(ucwords(substr($text, 12)));
  if (empty($listName)) {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "List name cannot be empty.", "parse_mode" => "Markdown"));
    exit;
  }
  $sql = "SELECT listID FROM collateLists WHERE adminUsername = '$user_username' AND listName = '$listName'";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    $sql = "DELETE FROM collateLists WHERE adminUsername = '$user_username' AND listName ='$listName'";
    if ($conn->query($sql) === TRUE) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* ended.", "parse_mode" => "Markdown"));
    } else {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* is not ended.".$conn->error, "parse_mode" => "Markdown"));
    }
  } else {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* does not exist.", "parse_mode" => "Markdown"));
  }
}

function drop($text, $user_username, $chat_id, $chat_type, $conn) {
  $listName = trim(ucwords(substr($text, 6)));
  if (empty($listName)) {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "List name cannot be empty.", "parse_mode" => "Markdown"));
    exit;
  }
  $sql = "SELECT listID FROM collateLists WHERE listName = '$listName'";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $listID = $row['listID'];
    if ($chat_type === "group") {
      $sql = "SELECT groupID FROM collateGroups WHERE listID = '$listID'";
      $result = $conn->query($sql);
      if ($result->num_rows <= 0) {
        apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* is not available to this group.", "parse_mode" => "Markdown"));
        exit;
      }
    }
    $sql = "DELETE FROM collateEntries WHERE listID = $listID AND entryUsername ='$user_username'";
    if ($conn->query($sql) === TRUE) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Entry deleted from *$listName*.", "parse_mode" => "Markdown"));
    } else {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Entry not deleted from *$listName*.".$conn->error, "parse_mode" => "Markdown"));
    }
  } else {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* does not exist.", "parse_mode" => "Markdown"));
  }
}

function show($text, $chat_id, $chat_type, $conn) {
  $listName = trim(ucwords(substr($text, 6)));
  if (empty($listName)) {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "List name cannot be empty.", "parse_mode" => "Markdown"));
    exit;
  }
  $sql = "SELECT listID, adminUsername FROM collateLists WHERE listName = '$listName'";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $listID = $row['listID'];
    if ($chat_type === "group") {
      $sql = "SELECT groupID FROM collateGroups WHERE listID = '$listID'";
      $result = $conn->query($sql);
      if ($result->num_rows <= 0) {
        apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* is not available to this group.", "parse_mode" => "Markdown"));
        exit;
      }
    }
    $adminUsername = $row['adminUsername'];
    $sql = "SELECT entryFirstColumn, entrySecondColumn FROM collateEntries WHERE listID = $listID";
    $result = $conn->query($sql);
    $paragraph = "```\n$listName by @$adminUsername";
    if ($result->num_rows > 0) {
      $indexNum = 1;
      while ($row = $result->fetch_assoc()) {
        if (empty($row['entrySecondColumn'])) {
          $paragraph .= "\n$indexNum. {$row['entryFirstColumn']}";
        } else {
          $paragraph .= "\n$indexNum. {$row['entryFirstColumn']}, {$row['entrySecondColumn']}";
        }
        $indexNum++;
      }
    } else {
      $paragraph .= "\nempty";
    }
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "$paragraph\n```", "parse_mode" => "Markdown"));
  } else {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* does not exist.", "parse_mode" => "Markdown"));
  }
}

function allow($text, $user_username, $chat_id, $chat_type, $conn) {
  if ($chat_type === "group") {
    $listName = trim(ucwords(substr($text, 7)));
    if (empty($listName)) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "List name cannot be empty.", "parse_mode" => "Markdown"));
      exit;
    }
    $sql = "SELECT listID FROM collateLists WHERE listName = '$listName' AND adminUsername = '$user_username'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      $listID = $row['listID'];
      $sql = "INSERT INTO collateGroups (groupID, listID) VALUES ($chat_id, $listID)";
      if ($conn->query($sql) === TRUE) {
        apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "*$listName* is added to this group.", "parse_mode" => "Markdown"));
      } else {
        apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "*$listName* is not added to this group.".$conn->error, "parse_mode" => "Markdown"));
      }
    } else {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Collate list *$listName* does not exist / Collate list can only be allowed by admin/creator.", "parse_mode" => "Markdown"));
    }
  } else {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "You are not in a group.", "parse_mode" => "Markdown"));
  }
}

function display($text, $user_username, $chat_id, $chat_type, $conn) {
  $sql = "";
  $paragraph = "";
  if ($chat_type === "private") {
    $sql = "SELECT listID FROM collateLists WHERE adminUsername = '$user_username'";
    $paragraph = "```\nYour lists:";
  } else if ($chat_type === "group") {
    $sql = "SELECT listID FROM collateGroups WHERE groupID = $chat_id";
    $paragraph = "```\nAvailable lists:";
  }
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    $listID_array = array();
    while ($row = $result->fetch_assoc()) {
      $listID_array[] = $row['listID'];
    }
    $indexNum = 1;
    foreach ($listID_array as $listID) {
      $sql = "SELECT adminUsername, listName FROM collateLists WHERE listID = $listID";
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $paragraph .= "\n$indexNum. @{$row['adminUsername']}: {$row['listName']}";
      } else {
        //
      }
      $indexNum++;
    }
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "$paragraph\n```", "parse_mode" => "Markdown"));
  } else {
    if ($chat_type === "private") {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "You don't have any collate lists.", "parse_mode" => "Markdown"));
    } else if ($chat_type === "group") {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "There is no available collate lists in this group.", "parse_mode" => "Markdown"));
    }
  }
}
*/
function processCallbackQuery($callback_query, $conn) {
  $message_id = $callback_query['message']['message_id'];
  $chat_id = $callback_query['message']['chat']['id'];
  $chat_type = $callback_query['message']['chat']['type'];
  
  $callback_query_data_array = explode(",", $callback_query['data']);
  $command = trim($callback_query_data_array[0]);
  $listID_received = trim($callback_query_data_array[1]);
  
  if (!isset($callback_query['from']['username'])) {
    apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Your telegram username is not set. Please set your telegram username.", "parse_mode" => "Markdown"));
    exit;
  }
  $user_username = $callback_query['from']['username'];
  
  if (empty($listID_received)) {
    
    if ($command === "/newcollate") {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "@$user_username Enter list name.", "parse_mode" => "HTML", "reply_markup" => array("force_reply" => true, "selective" => true)));
      apiRequest("deleteMessage", array("chat_id" => $chat_id, "message_id" => $message_id));
      
    } else if ($command === "/indicate") {
      if ($chat_type === "private") {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Command unavailable in private chat.", "parse_mode" => "HTML"));
      } else if ($chat_type === "group") {
        $sql = "SELECT collateLists.listID, collateLists.adminUsername, collateLists.listName FROM collateGroups INNER JOIN collateLists ON collateGroups.listID = collateLists.listID WHERE collateGroups.groupID = $chat_id";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
          $inline_keyboard = array();
          while ($row = $result->fetch_assoc()) {
            $listID = $row['listID'];
            $adminUsername = $row['adminUsername'];
            $listName = $row['listName'];
            $inline_keyboard[] = array(array("text" => "@$adminUsername's $listName", "callback_data" => "$command, $adminUsername:$listName:$listID"));
          }
          $inline_keyboard[] = array(array("text" => "<< Exit", "callback_data" => "/close,"));
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Available lists to <i>indicate</i>:", "parse_mode" => "HTML", "reply_markup" => array("inline_keyboard" => $inline_keyboard)));
        } else {
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "No available lists to <i>indicate</i>.", "parse_mode" => "HTML"));
        }
      }
      
    } else if ($command === "/endcollate") {
      $sql = "SELECT listID, listName FROM collateLists WHERE adminUsername = '$user_username'";
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
        $inline_keyboard = array();
        while ($row = $result->fetch_assoc()) {
          $listID = $row['listID'];
          $listName = $row['listName'];
          $inline_keyboard[] = array(array("text" => "@$user_username's $listName", "callback_data" => "$command, $user_username:$listName:$listID"));
        }
        $inline_keyboard[] = array(array("text" => "<< Exit", "callback_data" => "/close,"));
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Available lists for <b>@$user_username</b> to <i>end</i>:", "parse_mode" => "HTML", "reply_markup" => array("inline_keyboard" => $inline_keyboard)));
      } else {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "No available lists for <b>@$user_username</b> to <i>end</i>.", "parse_mode" => "HTML"));
      }
      
    } else if ($command === "/allow") {
      if ($chat_type === "private") {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Command unavailable in private chat.", "parse_mode" => "HTML"));
      } else if ($chat_type === "group") {
        $sql = "SELECT listID, listName FROM collateLists WHERE adminUsername = '$user_username'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
          $inline_keyboard = array();
          while ($row = $result->fetch_assoc()) {
            $listID = $row['listID'];
            $listName = $row['listName'];
            $inline_keyboard[] = array(array("text" => "@$user_username's $listName", "callback_data" => "$command, $user_username:$listName:$listID"));
          }
          $inline_keyboard[] = array(array("text" => "<< Exit", "callback_data" => "/close,"));
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Available lists for <b>@$user_username</b> to <i>allow</i>:", "parse_mode" => "HTML", "reply_markup" => array("inline_keyboard" => $inline_keyboard)));
        } else {
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "No available lists for <b>@$user_username</b> to <i>allow</i>.", "parse_mode" => "HTML"));
        }
      }
      
    } else if ($command === "/drop") {
      if ($chat_type === "private") {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Command unavailable in private chat.", "parse_mode" => "Markdown"));
      } else if ($chat_type === "group") {
        $sql = "SELECT collateLists.listID, collateLists.adminUsername, collateLists.listName FROM collateGroups INNER JOIN collateLists ON collateGroups.listID = collateLists.listID WHERE collateGroups.groupID = $chat_id";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
          $inline_keyboard = array();
          while ($row = $result->fetch_assoc()) {
            $listID = $row['listID'];
            $adminUsername = $row['adminUsername'];
            $listName = $row['listName'];
            $inline_keyboard[] = array(array("text" => "@$adminUsername's $listName", "callback_data" => "$command, $adminUsername:$listName:$listID"));
          }
          $inline_keyboard[] = array(array("text" => "<< Exit", "callback_data" => "/close,"));
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Available lists to <i>drop</i>:", "parse_mode" => "HTML", "reply_markup" => array("inline_keyboard" => $inline_keyboard)));
        } else {
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "No available lists to <i>drop</i>.", "parse_mode" => "HTML"));
        }
      }
      
    } else if ($command === "/show") {
      $sql = "";
      if ($chat_type === "private") {
        $sql = "SELECT listID, adminUsername, listName FROM collateLists WHERE adminUsername = '$user_username'";
      } else if ($chat_type === "group") {
        $sql = "SELECT collateLists.listID, collateLists.adminUsername, collateLists.listName FROM collateGroups INNER JOIN collateLists ON collateGroups.listID = collateLists.listID WHERE collateGroups.groupID = $chat_id";
      }
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
        $inline_keyboard = array();
        while ($row = $result->fetch_assoc()) {
          $listID = $row['listID'];
          $adminUsername = $row['adminUsername'];
          $listName = $row['listName'];
          $inline_keyboard[] = array(array("text" => "@$adminUsername's $listName", "callback_data" => "$command, $adminUsername:$listName:$listID"));
        }
        $inline_keyboard[] = array(array("text" => "<< Exit", "callback_data" => "/close,"));
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Available lists to <i>show</i>:", "parse_mode" => "HTML", "reply_markup" => array("inline_keyboard" => $inline_keyboard)));
      } else {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "No available lists to <i>show</i>.", "parse_mode" => "HTML"));
      }
      
    } else if ($command === "/display") {
      $sql = "";
      $paragraph = "";
      if ($chat_type === "private") {
        $sql = "SELECT listID FROM collateLists WHERE adminUsername = '$user_username'";
        $paragraph = "<pre>Your lists:";
      } else if ($chat_type === "group") {
        $sql = "SELECT listID FROM collateGroups WHERE groupID = $chat_id";
        $paragraph = "<pre>Available lists:";
      }
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
        $listID_array = array();
        while ($row = $result->fetch_assoc()) {
          $listID_array[] = $row['listID'];
        }
        $indexNum = 1;
        foreach ($listID_array as $listID) {
          $sql = "SELECT adminUsername, listName FROM collateLists WHERE listID = $listID";
          $result = $conn->query($sql);
          if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $paragraph .= "\n$indexNum. @{$row['adminUsername']}: {$row['listName']}";
          } else {
            //
          }
          $indexNum++;
        }
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "$paragraph</pre>", "parse_mode" => "HTML"));
      } else {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "No available lists to <i>display</i>.", "parse_mode" => "HTML"));
      }
      
    } else if ($command === "/close") {
      apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Thank you!", "parse_mode" => "Markdown"));
      apiRequest("deleteMessage", array("chat_id" => $chat_id, "message_id" => $message_id));
      
    }
    
  } else {
    
    if ($command === "/indicate") {
      $listID_received_array = explode(":", $listID_received);
      $adminUsername = trim($listID_received_array[0]);
      $listName = trim($listID_received_array[1]);
      $listID_received = trim($listID_received_array[2]);
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "@$user_username Please type:\n<b>$adminUsername, $listName, 1st field, (optional) 2nd field</b>", "parse_mode" => "HTML", "reply_markup" => array("force_reply" => true, "selective" => true)));
      apiRequest("deleteMessage", array("chat_id" => $chat_id, "message_id" => $message_id));
      
    } else if ($command === "/endcollate") {
      $listID_received_array = explode(":", $listID_received);
      $adminUsername = trim($listID_received_array[0]);
      $listName = trim($listID_received_array[1]);
      $listID_received = trim($listID_received_array[2]);
      if ($user_username === $adminUsername) {
        $sql = "DELETE FROM collateLists WHERE listID = $listID_received";
        if ($conn->query($sql) === TRUE) {
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "<b>@$adminUsername</b>'s <b>$listName</b> is <i>ended</i>.", "parse_mode" => "HTML"));
        } else {
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "<b>@$adminUsername</b>'s <b>$listName</b> is not <i>ended</i>."/*.$conn->error*/, "parse_mode" => "HTML"));
        }
      } else {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Only <b>@$adminUsername</b> can <i>end</i> <b>$listName</b>.", "parse_mode" => "HTML"));
      }
      
    } else if ($command === "/allow") {
      $listID_received_array = explode(":", $listID_received);
      $adminUsername = trim($listID_received_array[0]);
      $listName = trim($listID_received_array[1]);
      $listID_received = trim($listID_received_array[2]);
      if ($user_username === $adminUsername) {
        $sql = "INSERT INTO collateGroups (groupID, listID) VALUES ($chat_id, $listID_received)";
        if ($conn->query($sql) === TRUE) {
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "<b>@$adminUsername</b>'s <b>$listName</b> is added to this group.", "parse_mode" => "HTML"));
        } else {
          apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "<b>@$adminUsername</b>'s <b>$listName</b> is not added to this group."/*.$conn->error*/, "parse_mode" => "HTML"));
        }
      } else {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Only <b>@$adminUsername</b> can <i>allow</i> <b>$listName</b> into the group.", "parse_mode" => "HTML"));
      }
      
    } else if ($command === "/drop") {
      $listID_received_array = explode(":", $listID_received);
      $adminUsername = trim($listID_received_array[0]);
      $listName = trim($listID_received_array[1]);
      $listID_received = trim($listID_received_array[2]);
      $sql = "DELETE FROM collateEntries WHERE listID = $listID_received AND entryUsername ='$user_username'";
      if ($conn->query($sql) === TRUE) {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Entry is <i>deleted</i> from <b>@$adminUsername</b>'s <b>$listName</b>.", "parse_mode" => "HTML"));
      } else {
        apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "Entry is not <i>deleted</i> from <b>@$adminUsername</b>'s <b>$listName</b>."/*.$conn->error*/, "parse_mode" => "HTML"));
      }
      
    } else if ($command === "/show") {
      $listID_received_array = explode(":", $listID_received);
      $adminUsername = trim($listID_received_array[0]);
      $listName = trim($listID_received_array[1]);
      $listID_received = trim($listID_received_array[2]);
      $sql = "SELECT collateEntries.entryFirstColumn, collateEntries.entrySecondColumn, collateLists.adminUsername, collateLists.listName FROM collateEntries INNER JOIN collateLists ON collateEntries.listID = collateLists.listID WHERE collateEntries.listID = $listID_received";
      $result = $conn->query($sql);
      $paragraph = "<pre>@$adminUsername's $listName:";
      if ($result->num_rows > 0) {
        $indexNum = 1;
        while ($row = $result->fetch_assoc()) {
          if (empty($row['entrySecondColumn'])) {
            $paragraph .= "\n$indexNum. {$row['entryFirstColumn']}";
          } else {
            $paragraph .= "\n$indexNum. {$row['entryFirstColumn']}, {$row['entrySecondColumn']}";
          }
          $indexNum++;
        }
      } else {
        $paragraph .= "\nempty";
      }
      apiRequest("editMessageText", array("chat_id" => $chat_id, "message_id" => $message_id, "text" => "$paragraph</pre>", "parse_mode" => "HTML"));
    }
  }
}

require 'collateBot-webhook.php';

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
  // receive wrong update, must not happen
  exit;
}

if (isset($update['message'])) {
  processMessage($update['message'], $conn);
} else if (isset($update['callback_query'])) {
  processCallbackQuery($update['callback_query'], $conn);
}

$conn->close();
?>
