<?php

    /*
    
      What you need to do: 
      1. Set up a webhook in Intercom to this script. 
      2. Get necessary access tokens from Dialogflow and Intercom
        a) Access tokens for API from both platforms
        b) The ID of the admin account under which you want the bot to reply from (can find that from the profile URL for example)
      
      If you have any improvements, feel free to do them! 
      
      You also need to add your tokens inside the script. This is very hacky still ;) 
    
    */

    //Get Header JSON, returns all header json as object
    $json = file_get_contents('php://input');
    $obj = json_decode($json);

    //Get user message & conversation id
    $usermessage = strip_tags($obj->data->item->conversation_parts->conversation_parts['0']->body);
    $conversationid = strip_tags($obj->data->item->id);
    $deliveryattempts = $obj->delivery_attempts;

    if ($deliveryattempts > 1 ) {
        //Hacky way to stop the bot from replying multiple times
        die();
    }

    if ($obj->topic == 'conversation.user.replied') {
        echo buildReplyToUser($usermessage, $conversationid);
    }

    if ($obj->topic == 'conversation.user.created') {
        //User message is different for user.created 
        $usermessage = strip_tags($obj->data->item->conversation_message->body);
        echo buildReplyToUser($usermessage, $conversationid);
    }

    function getReplyFromAPIai($usermessage, $conversationid) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,'https://api.api.ai/v1/query');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer *********************',
            'Accept: application/json',
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            json_encode(array('v' => '20170712', 'lang' => 'en', 'sessionId' => $conversationid, 'query' => $usermessage)));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec ($ch);
        $server_output_json = json_decode($server_output);
        $reply = $server_output_json->result->speech;

        curl_close ($ch);

        return $reply;
    }

    //This breaks a longer response into smaller messages by looking for \n\n inside the message
    function buildReplyToUser($usermessage, $conversationid) {
        $reply = getReplyFromAPIai($usermessage, $conversationid);

        $replyMessage = explode('\n\n', $reply);

        foreach ($replyMessage as $message) {
            replyToUser($conversationid, $message);
        }

        return 'OK';

    }

    function replyToUser($conversationid, $reply) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,'https://api.intercom.io/conversations/' . $conversationid . '/reply');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer ******************',
            'Accept: application/json',
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
                  json_encode(array('type' => 'admin', 'message_type' => 'comment', 'admin_id' => '***********', 'body' => $reply)));

        // receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec ($ch);

        curl_close ($ch);

        // further processing ....
        if ($server_output == "OK") {
            return 'OK';
        } else {
            return 'Issue with Intercom: ' . $server_output;
        }

    }

?>
