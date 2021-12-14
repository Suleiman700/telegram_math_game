<?php
include 'Telegram-Core.php';
include_once 'inc/db.php';
include_once 'func/gen_tbl_id.php';


$bot_token = 'bot:token';
$telegram = new Telegram($bot_token);
$text = $telegram->Text(); //user sent messga
$chat_id = $telegram->ChatID();

$username = $telegram->Username();
$userID = $telegram->UserID();
$fName = $telegram->FirstName();
$lName = $telegram->LastName();




function getFirstUser(){
    return custom_query("SELECT id FROM users LIMIT 1");
}

// Time now with milliseconds
function timeNow(){
    $now = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
    $local = $now->setTimeZone(new DateTimeZone('Asia/Jerusalem'));
    return $local->format("H:i:s.u");
}

// Generate questions
function genQuestion($difficulty){
    $ops = array('-', '+');
    $answer = -1;
    while($answer < 0 || $answer > 99) {

        if ($difficulty==1) {
            $num1 = rand(0, 10);
            $num2 = rand(0, 10);
        } else if ($difficulty==2) {
            $num1 = rand(0, 30);
            $num2 = rand(0, 30);
        } else if ($difficulty==3) {
            array_push($ops, "*", "*", "*");
            $num1 = rand(10, 30);
            $num2 = rand(10, 30);
        }
        shuffle($ops);
        $op = $ops[0];

        $answer = eval("return $num1 $op $num2;");
    }

    $new_id = get_new_id('id', 'questions');
    custom_query("INSERT INTO `questions`(`id`, `question`, `used`, `diff`) VALUES ('$new_id', '$num1 $op $num2', '0', '$difficulty')"); // Insert question to table
}



// Try to get answers from users
if (is_numeric($text)) {
    // Increase player answers counter
    $player_answers = custom_query("SELECT answers FROM users WHERE userID='$userID'"); // Get user answers counter

    // Check if already answered
    $already_answered = custom_query("SELECT val FROM data WHERE col='answered'");
    if (!$already_answered && $player_answers<3) {
        // Get current question
        $current_question = custom_query("SELECT val FROM data WHERE col='current_quest'"); // Get current question id
        $answer = eval('return '.$current_question.';'); // Question answer

        // Correct answer
        if ($text == $answer) {
            $game_diff = custom_query("SELECT val FROM data WHERE col='diff'"); // Get game difficulty
            $user_points = custom_query("SELECT points FROM users WHERE userID='$userID'"); // Get user points
            $points = $user_points + $game_diff;
            custom_query("UPDATE users SET points='$points' WHERE userID='$userID'"); // Update user points
            custom_query("UPDATE data SET val='1' WHERE col='answered'"); // Set as answered


            // Check streak
            $current_streak_data = custom_query("SELECT val FROM data WHERE col='streak'"); // Get current streak
            // If no streak yet
            if (empty($current_streak_data)) {
                custom_query("UPDATE data SET val='$userID:1' WHERE col='streak'"); // Set streak
            }
            // Update streak
            else {
                $current_streak_userID = explode(":", $current_streak_data);
                // Player is still in streak
                if ($current_streak_userID[0]==$userID) {
                    // +1 streak
                    $streak_point = $current_streak_userID[1] + 1;
                    custom_query("UPDATE data SET val='$userID:$streak_point' WHERE col='streak'"); // Set streak

                    if ($current_streak_userID[1]>=3) {
                        $content = array('chat_id' => $chat_id, 'text' => "* $fName is on a STREAK! [$streak_point] *");
                        $telegram->sendMessage($content);
                    }
                }
                // Player lost streak
                else {
                    custom_query("UPDATE data SET val='$userID:1' WHERE col='streak'"); // Set streak

                    $player_fname = custom_query("SELECT fName FROM users WHERE userID='$current_streak_userID[0]'");
                    $content = array('chat_id' => $chat_id, 'text' => " * $player_fname has lost streak of $current_streak_userID[1] *");
                    $telegram->sendMessage($content);
                }

            }

            $question_time = custom_query("SELECT val FROM data WHERE col='timer'"); // Get time
            $time1 = date_create_from_format('H:i:s.u', $question_time); // Timer time
            $time2 = date_create_from_format('H:i:s.u', timeNow()); // Now time
            $diff = $time2->diff($time1);
            $taken_time = substr($diff->format('%I:%s:%f'), 0, -3);

            if (empty($fName)) $response = "Congrats [$username] (+$game_diff Point!),\nYou are the first to answer [$answer] in $taken_time.\n* /math_next - next question.\n * /math_showpoints - show points";
            else if (!empty($fName)) $response = "Congrats [$fName] (+$game_diff Point!),\nYou are the first to answer [$answer] in $taken_time.\n* /math_next - next question.\n * /math_showpoints - show points";
        }

        $increase_answers = $player_answers + 1;
        custom_query("UPDATE users SET answers='$increase_answers' WHERE userID='$userID'"); // Increase player answers counter

        $content = array('chat_id' => $chat_id, 'text' => $response);
        $telegram->sendMessage($content);
    }

    else if (!$already_answered && $player_answers==3) {
        custom_query("UPDATE users SET answers='4' WHERE userID='$userID'"); // Disable player answers for this round
        $content = array('chat_id' => $chat_id, 'text' => "[$fName] has reached the maximum number of the answers (3/3)");
        $telegram->sendMessage($content);
    }

}


if ($text == "/math_joingame" || $text == '/math_joingame@clean_math_game_bot'){
    // Check if userID exists in table
    $userID_exists = record_info_in_tbl("userID", "userID", "$userID", "users");

    // User already in game
    if ($userID_exists) {
        if (empty($fName)) $response = "Sorry $username, But you are already in game";
        else if (!empty($fName)) $response = "Sorry $fName, But you are already in game";
    }

    // User join game
    else if (!$userID_exists) {
        $new_id = get_new_id("id", "users");
        $sql = "INSERT INTO `users` (`id`, `username`, `userID`, `fName`, `lName`) VALUES (:id,:username,:userID,:fName,:lName);";
        $stmt = $conn->prepare($sql);
        try{
            $stmt->execute(array(':id' => $new_id, ':username' => $username, ':userID' => $userID, ':fName' => $fName, ':lName' => $lName));
            if (empty($fName)) $response = "$username has joined the game";
            else if (!empty($fName)) $response = "$fName has joined the game";
        }
        catch(PDOException $e) {
            echo json_encode(array("statusCode" => $e->getMessage()));
            return 0;
        }
    }

    $content = array('chat_id' => $chat_id, 'text' => $response);
    $telegram->sendMessage($content);
}

else if ($text == "/math_leavegame" || $text == '/math_leavegame@clean_math_game_bot') {
    // Check if userID exists in table
    $userID_exists = record_info_in_tbl("userID", "userID", "$userID", "users");

    // User in game
    if ($userID_exists) {
        $sql = "DELETE FROM users WHERE userID='$userID'";
        if ($conn->query($sql) == TRUE) {
            if (empty($fName)) $response = "$username has left the game!";
            else if (!empty($fName)) $response = "$fName has left the game!";
        }

    }

    // User not in game
    else if (!$userID_exists) {
        if (empty($fName)) $response = "Sorry $username, You are not in game!";
        else if (!empty($fName)) $response = "Sorry $fName, You are not in game";

    }

    $content = array('chat_id' => $chat_id, 'text' => $response);
    $telegram->sendMessage($content);
}

else if ($text == "/math_startgame" || $text == '/math_startgame@clean_math_game_bot'){

    custom_query("UPDATE users SET answers=0"); // Reset all answers counter

    // Check if user is in game
    $userID_exists = custom_query("SELECT COUNT(*) FROM users WHERE userID=$userID");

    // User is in game
    if ($userID_exists) {
        // Check if game is already started
        $game_started = custom_query("SELECT val FROM data WHERE col='game_started'");

        // Game has not been started
        if (!$game_started) {
            $count_users = custom_query("SELECT COUNT(*) FROM users");
            // No players
            if ($count_users<1) $response = "Sorry, There are no players!";

            // Only one player
            else if ($count_users==1) $response = "Sorry, Cant start game with 1 player only!";

            // More than two players
            else if ($count_users>1) {

                // Set turn
                $sql = "UPDATE data SET val=:val WHERE col=:col";
                $stmt = $conn->prepare($sql);
                $stmt->execute(array(':val' => getFirstUser(), ':col' => 'current_turn'));
                $affected_rows = $stmt->rowCount();

                $sql = "UPDATE data SET val=:val WHERE col=:col";
                $stmt = $conn->prepare($sql);
                $stmt->execute(array(':val' => '1', ':col' => 'game_started'));
                $affected_rows = $stmt->rowCount();

                custom_query("UPDATE data SET val='' WHERE col='timer'"); // Empty timer
                custom_query("UPDATE data SET val='' WHERE col='current_quest'"); // Empty current question
                custom_query("UPDATE data SET val='' WHERE col='streak'"); // Empty streak

                $game_diff = custom_query("SELECT val FROM data WHERE col='diff'"); // Get game difficulty

                if ($affected_rows == 1) $response = "Game has been started - Difficulty $game_diff\n* /math_next - start playing!\n\n* /math_resetquest reset used questions!\n* /math_resetpoints reset all points";
                else $response = "Error while starting the game!";
            }

            $content = array('chat_id' => $chat_id, 'text' => $response);
            $telegram->sendMessage($content);
        }
        else if ($game_started) {
            $game_diff = custom_query("SELECT val FROM data WHERE col='diff'"); // Get game difficulty

            $response = "Game has been already started - Difficulty $game_diff\n* /math_next - start playing!\n";

            // If any player has points, remind them to reset points
            $player_has_points = custom_query("SELECT COUNT(*) FROM users WHERE points>0");
            if ($player_has_points) $response .= "\n* /math_resetpoints reset all points";

            // If any difficulty question is marked as used
            $player_has_points = custom_query("SELECT COUNT(*) FROM questions WHERE used='1' AND diff='$game_diff'");
            if ($player_has_points) $response .= "\n* /math_resetquest reset used questions!";

            $content = array('chat_id' => $chat_id, 'text' => $response);
            $telegram->sendMessage($content);
        }
        // User is not in game
        else if (!$userID_exists) {
            $content = array('chat_id' => $chat_id, 'text' => "Sorry, But you are not in game!");
            $telegram->sendMessage($content);
        }


    }

    // Game has been already started
    else if ($game_started) {
        $content = array('chat_id' => $chat_id, 'text' => "Sorry, Game has been already started!\nUse /math_stopgame to stop the game.");
        $telegram->sendMessage($content);
    }

}

else if ($text == "/math_stopgame" || $text == '/math_stopgame@clean_math_game_bot') {
    // Check if game is already started
    $game_started = custom_query("SELECT val FROM data WHERE col='game_started'");

    // Game has already been started
    if ($game_started) {
        // Stop the game
        $sql = "UPDATE data SET val=:val WHERE col=:col";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array(':val' => '0', ':col' => 'game_started'));
        $affected_rows = $stmt->rowCount();
        if ($affected_rows == 1) $response = "Game has been stopped!";
        else $response = "Error while stopping the game!";

        custom_query("UPDATE data SET val='' WHERE col='timer'"); // Empty timer
        custom_query("UPDATE data SET val='' WHERE col='current_quest'"); // Empty current question
        custom_query("UPDATE data SET val='' WHERE col='streak'"); // Empty streak

        $content = array('chat_id' => $chat_id, 'text' => $response);
        $telegram->sendMessage($content);
    }

    // Game has not been started
    else if (!$game_started) {
        $content = array('chat_id' => $chat_id, 'text' => "Sorry, Game has not been started!\nUse /math_startgame to start the game.");
        $telegram->sendMessage($content);
    }
}

else if ($text == "/math_resetquest" || $text == '/math_resetquest@clean_math_game_bot') {
    custom_query("DELETE FROM questions WHERE 1"); // Mark all questions as not used
    custom_query("UPDATE data SET val='' WHERE col='timer'"); // Empty timer
    custom_query("UPDATE data SET val='' WHERE col='current_quest'"); // Empty current question
    custom_query("UPDATE data SET val='' WHERE col='streak'"); // Empty streak
    custom_query("UPDATE data SET val='0' WHERE col='answered'"); // Empty answered


    $response = 'Done resetting questions.';

    // If any player has points, remind them to reset points
    $player_has_points = custom_query("SELECT COUNT(*) FROM users WHERE points>0");
    if ($player_has_points) $response .= "\nSome players have points, Use /math_resetpoints to reset all points";

    $content = array('chat_id' => $chat_id, 'text' => $response);
    $telegram->sendMessage($content);
}

// Count unused questions
else if ($text == "/math_countfree" || $text == '/math_countfree@clean_math_game_bot') {
    $count_diff1 = custom_query("SELECT COUNT(*) FROM questions WHERE used='0' AND diff='1'"); // Count unused difficulty 1
    $count_diff2 = custom_query("SELECT COUNT(*) FROM questions WHERE used='0' AND diff='2'"); // Count unused difficulty 2
    $count_diff3 = custom_query("SELECT COUNT(*) FROM questions WHERE used='0' AND diff='3'"); // Count unused difficulty 3

    $content = array('chat_id' => $chat_id, 'text' => "Counting All Unused Questions\nDifficulty 1: $count_diff1\nDifficulty 2: $count_diff2\nDifficulty 3: $count_diff3");
    $telegram->sendMessage($content);
}


// Reset users points
else if ($text == "/math_resetpoints" || $text == '/math_resetpoints@clean_math_game_bot') {
    custom_query("UPDATE users SET points=0"); // Reset all points
    custom_query("UPDATE users SET answers=0"); // Reset all answers counter

    $content = array('chat_id' => $chat_id, 'text' => "Done resetting points.");
    $telegram->sendMessage($content);
}

// Show users points
else if ($text == "/math_showpoints" || $text=="/math_showpoints@clean_math_game_bot") {
    $user_ids_arr_dirty = custom_query("SELECT GROUP_CONCAT(id SEPARATOR ',') FROM users"); // Get all users IDs
    $user_ids_arr_clean = explode (",", $user_ids_arr_dirty);

    $response = '';
    foreach ($user_ids_arr_clean AS $player_id) {
        $player_fname = custom_query("SELECT fName FROM users WHERE id='$player_id'"); // Get player fName
        $player_points = custom_query("SELECT points FROM users WHERE id='$player_id'"); // Get player points
        $response .= "Player [$player_fname]: $player_points points\n";
    }


    $content = array('chat_id' => $chat_id, 'text' => $response);
    $telegram->sendMessage($content);
}


else if ($text == "/math_setdiff") {

    $content = array('chat_id' => $chat_id, 'text' => "Bad syntax, Use these commands:\n /math_setdiff1\n/math_setdiff2\n/math_setdiff3");
    $telegram->sendMessage($content);
}

// Set difficulty
else if (strpos($text, '/math_setdiff') !== false) {
    $wanted_diff = str_replace('/math_setdiff', '', $text); // Extract difficulty from text
    $wanted_diff = str_replace(' ', '', $wanted_diff); // Extract difficulty from text

    // Bad syntax
    if (!is_numeric($wanted_diff) || ($wanted_diff!=1 && $wanted_diff!=2 && $wanted_diff!=3)) {
        $content = array('chat_id' => $chat_id, 'text' => "Error while setting game difficulty, Please use the following syntax: /math_setdiff<difficulty>\nAllowed difficulty: 1, 2, 3.");
        $telegram->sendMessage($content);
    }
    // Good syntax
    else {
        custom_query("UPDATE data SET val='$wanted_diff' WHERE col='diff'"); // Update difficulty
        $content = array('chat_id' => $chat_id, 'text' => "Game difficulty has been set to $wanted_diff");
        $telegram->sendMessage($content);
    }

}

else if ($text == "/math_next" || $text == "/math_next@clean_math_game_bot" || $text == "131."){

    $game_started = custom_query("SELECT val FROM data WHERE col='game_started'"); // Check if game started
    if (!$game_started) {
        $content = array('chat_id' => $chat_id, 'text' => "Game has not been started!\nUser /math_startgame to start the game.");
        $telegram->sendMessage($content);
        return 0;
    }

    custom_query("UPDATE data SET val='0' WHERE col='answered'"); // Set answered as false
    custom_query("UPDATE users SET answers='0'"); // Reset players answers counter

    // Check if difficulty is set
    $difficulty = custom_query("SELECT val FROM data WHERE col='diff'"); // Get stored difficulty
    if (!$difficulty) {
        $content = array('chat_id' => $chat_id, 'text' => "Please set game difficulty first before playing.\nUse /math_setdiff<difficulty> to set difficulty");
        $telegram->sendMessage($content);
        return 0;
    }


    // Get random question
    $rand_unused_id = custom_query("SELECT id FROM questions WHERE used='0' AND diff='$difficulty' ORDER BY RAND() LIMIT 1"); // Get random question id

    if (!$rand_unused_id) {
        genQuestion($difficulty); // Generate random question
        $rand_unused_id = custom_query("SELECT id FROM questions WHERE used='0' AND diff='$difficulty' ORDER BY RAND() LIMIT 1"); // Get random question id
    }

    // Show countdown to players
    $content = array('chat_id' => $chat_id, 'text' => "Get ready!");
    $telegram->sendMessage($content);
    $i=5;
    while ($i>=1) {
        $content = array('chat_id' => $chat_id, 'text' => $i);
        $telegram->sendMessage($content);
        sleep(1);
        $i--;
    }

    $rand_unused_quest = custom_query("SELECT question FROM questions WHERE id='$rand_unused_id'"); // Get question
    custom_query("UPDATE data SET val='$rand_unused_quest' WHERE col='current_quest'"); // Set current question
    custom_query("UPDATE questions SET used='1' WHERE id='$rand_unused_id'"); // Mark question as used

    // Timing
    $timeNow = timeNow(); // Get time
    custom_query("UPDATE data SET val='$timeNow' WHERE col='timer'"); // Set timer

    $content = array('chat_id' => $chat_id, 'text' => $rand_unused_quest);
    $telegram->sendMessage($content);
}

else if ($text == "/math_help") {
    $content = array('chat_id' => $chat_id, 'text' => "Math Game Bot Help\n
    /math_joingame -> Join Game.
    /math_leavegame ->Leave Game.
    /math_startgame -> Start Game.
    /math_stopgame -> Stop Game.
    /math_next -> Next Question.
    /math_countfree -> Count All Unused questions.
    /math_setdiff -> Set difficulty.
    /math_resetquest -> Reset All Used Questions.
    /math_resetpoints -> Reset All Points.
    /math_showpoints -> Show users points.\n
    Made With ♥ By Suleiman");
    $telegram->sendMessage($content);
}
